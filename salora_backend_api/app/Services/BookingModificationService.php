<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class BookingModificationService
{
    public function __construct(private readonly SaloraBookingV2Service $bookings)
    {
    }

    public function createHold(int $bookingId, int $changeRequestId, int $venueId, string $startAt, string $endAt): void
    {
        if (!Schema::hasTable('salora_booking_change_holds')) {
            return;
        }

        DB::table('salora_booking_change_holds')->updateOrInsert(
            ['change_request_id' => $changeRequestId],
            [
                'booking_id' => $bookingId,
                'venue_id' => $venueId,
                'start_at' => Carbon::parse($startAt),
                'end_at' => Carbon::parse($endAt),
                'status' => 'active',
                'released_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function releaseHold(int $changeRequestId): void
    {
        if (!Schema::hasTable('salora_booking_change_holds')) {
            return;
        }
        DB::table('salora_booking_change_holds')
            ->where('change_request_id', $changeRequestId)
            ->where('status', 'active')
            ->update(['status' => 'released', 'released_at' => now(), 'updated_at' => now()]);
    }

    public function finalizePaidAdjustment(int $adjustmentId, int $reviewerId): array
    {
        return DB::transaction(function () use ($adjustmentId, $reviewerId): array {
            $adjustment = DB::table('salora_booking_payment_adjustments')
                ->where('id', $adjustmentId)
                ->lockForUpdate()
                ->first();
            if (!$adjustment || $adjustment->type !== 'additional_payment') {
                throw ValidationException::withMessages(['adjustment' => ['فرق الدفع غير موجود.']]);
            }
            if (!in_array((string) $adjustment->status, ['proof_uploaded', 'pending_payment'], true)) {
                throw ValidationException::withMessages(['adjustment' => ['تمت معالجة فرق الدفع مسبقاً.']]);
            }
            $changeId = (int) ($adjustment->change_request_id ?? 0);
            $change = DB::table('booking_change_requests')->where('id', $changeId)->lockForUpdate()->first();
            if (!$change) {
                throw ValidationException::withMessages(['change_request' => ['طلب التعديل المرتبط غير موجود.']]);
            }

            $requested = $this->decode($change->requested_data ?? $change->requested_changes ?? null);
            $frozenQuote = $this->decode($change->quote_snapshot ?? null);
            $bookingBefore = $this->bookings->booking((int) $adjustment->booking_id);
            DB::table('venues')
                ->where('id', $this->bookings->bookingVenueId($bookingBefore))
                ->lockForUpdate()
                ->first();
            $quote = $this->bookings->applyApprovedChange(
                (int) $adjustment->booking_id,
                $requested,
                $reviewerId,
                $frozenQuote,
                true,
            );

            $changeUpdates = ['status' => 'approved', 'updated_at' => now()];
            foreach (['decided_by_user_id' => $reviewerId, 'reviewed_by' => $reviewerId, 'decided_at' => now(), 'finalized_at' => now()] as $column => $value) {
                if (Schema::hasColumn('booking_change_requests', $column)) {
                    $changeUpdates[$column] = $value;
                }
            }
            DB::table('booking_change_requests')->where('id', $changeId)->update($changeUpdates);

            DB::table('salora_booking_payment_adjustments')->where('id', $adjustmentId)->update([
                'status' => 'paid',
                'paid_syp' => $adjustment->amount_syp,
                'resolved_by_user_id' => $reviewerId,
                'resolved_at' => now(),
                'updated_at' => now(),
            ]);

            $this->releaseHold($changeId);
            $this->clearEditLock((int) $adjustment->booking_id);

            $booking = $this->bookings->booking((int) $adjustment->booking_id);
            NotificationService::send(
                (int) $this->bookings->bookingUserId($booking),
                'تم اعتماد تعديل الحجز',
                'تم قبول دفع فرق السعر واعتماد الموعد والسعر الجديد للحجز رقم '.$adjustment->booking_id.'.',
                'booking_change_finalized',
                [
                    'event' => 'booking_change_finalized',
                    'booking_id' => (string) $adjustment->booking_id,
                    'change_request_id' => (string) $changeId,
                    'payment_adjustment_id' => (string) $adjustmentId,
                    'target_route' => 'booking_details',
                ],
            );

            return ['quote' => $quote, 'adjustment_id' => $adjustmentId, 'change_request_id' => $changeId];
        });
    }

    public function confirmRefund(User $owner, int $bookingId, int $adjustmentId): array
    {
        return DB::transaction(function () use ($owner, $bookingId, $adjustmentId): array {
            $booking = $this->bookings->booking($bookingId);
            $this->bookings->assertVenueOwner($this->bookings->bookingVenueId($booking), $owner);
            $adjustment = DB::table('salora_booking_payment_adjustments')
                ->where('id', $adjustmentId)
                ->where('booking_id', $bookingId)
                ->where('type', 'refund_due')
                ->lockForUpdate()
                ->first();
            if (!$adjustment) {
                throw ValidationException::withMessages(['adjustment' => ['مبلغ الاسترجاع غير موجود.']]);
            }
            if (!in_array((string) $adjustment->status, ['pending_refund', 'pending'], true)) {
                throw ValidationException::withMessages(['adjustment' => ['تمت معالجة مبلغ الاسترجاع مسبقاً.']]);
            }

            DB::table('salora_booking_payment_adjustments')->where('id', $adjustmentId)->update([
                'status' => 'refunded',
                'resolved_by_user_id' => $owner->id,
                'resolved_at' => now(),
                'updated_at' => now(),
            ]);
            $this->clearEditLock($bookingId);

            NotificationService::send(
                (int) $this->bookings->bookingUserId($booking),
                'تم رد فرق مبلغ الحجز',
                'أكد مالك الصالة إعادة مبلغ '.number_format((float) $adjustment->amount_syp, 0, '.', ',').' ل.س الناتج عن تعديل الحجز.',
                'booking_change_refund_completed',
                [
                    'event' => 'booking_change_refund_completed',
                    'booking_id' => (string) $bookingId,
                    'payment_adjustment_id' => (string) $adjustmentId,
                    'target_route' => 'booking_details',
                ],
            );

            return (array) DB::table('salora_booking_payment_adjustments')->where('id', $adjustmentId)->first();
        });
    }

    public function clearEditLock(int $bookingId): void
    {
        $table = $this->bookings->bookingTable();
        $updates = [];
        if (Schema::hasColumn($table, 'edit_locked_at')) {
            $updates['edit_locked_at'] = null;
        }
        if (Schema::hasColumn($table, 'financial_status')) {
            $updates['financial_status'] = 'settled_after_booking_change';
        }
        if ($updates !== []) {
            DB::table($table)->where('id', $bookingId)->update($updates);
        }
    }

    private function decode(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value) && trim($value) !== '') {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }
}
