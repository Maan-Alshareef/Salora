<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\PlatformCommission;
use App\Models\ProviderServiceRequest;

class PlatformCommissionService
{
    public const RATE = 10.0;

    private const ACTIVE_BOOKING_STATUSES = [
        'confirmed', 'completed', 'modification_requested', 'cancellation_pending_refund',
    ];

    private const PRE_CONFIRMATION_BOOKING_STATUSES = [
        'pending_owner_review', 'pending_payment', 'payment_under_review',
    ];

    private const ACTIVE_PROVIDER_STATUSES = [
        'accepted', 'payment_pending', 'payment_under_review', 'paid',
        'payment_approved', 'completed',
    ];

    private const CANCELLED_BOOKING_STATUSES = ['owner_rejected', 'cancelled'];
    private const CANCELLED_PROVIDER_STATUSES = ['rejected', 'cancelled'];

    public function syncBooking(Booking $booking): ?PlatformCommission
    {
        $existing = PlatformCommission::query()
            ->where('source_type', 'booking')
            ->where('source_id', $booking->id)
            ->first();

        if (in_array((string) $booking->booking_status, self::CANCELLED_BOOKING_STATUSES, true)) {
            // A cancelled booking can still legitimately leave revenue with the owner
            // (for example a 50% or 0% customer refund). Preserve/recalculate the
            // commission on the retained amount instead of blindly cancelling it.
            $retained = (float) ($booking->owner_retained_syp ?? 0);
            $commission = (float) ($booking->commission_syp ?? 0);
            if ($retained <= 0 || $commission <= 0) {
                $this->cancelIfUncollected($existing);
                return $existing?->fresh();
            }
            // Continue into the normal upsert path below using the stored cancellation
            // financial snapshot.
        }

        if (in_array((string) $booking->booking_status, self::PRE_CONFIRMATION_BOOKING_STATUSES, true)) {
            $this->cancelIfUncollected($existing);
            return $existing?->fresh();
        }

        if (! in_array((string) $booking->booking_status, self::ACTIVE_BOOKING_STATUSES, true)) {
            return $existing;
        }

        $isCancellationFinancial = in_array((string) $booking->booking_status, ['cancellation_pending_refund', 'cancelled'], true);
        $grossSyp = $isCancellationFinancial && $booking->owner_retained_syp !== null
            ? (float) $booking->owner_retained_syp
            : (float) $booking->total_syp;
        $grossUsd = (float) $booking->total_usd;
        if ($isCancellationFinancial && (float) $booking->total_syp > 0) {
            $grossUsd = round((float) $booking->total_usd * ($grossSyp / (float) $booking->total_syp), 2);
        }

        $rate = (float) ($booking->platform_commission_rate ?? $booking->commission_rate ?? self::RATE);
        if ($rate <= 0) $rate = self::RATE;
        $commissionSyp = isset($booking->platform_commission_syp) && (float) $booking->platform_commission_syp > 0
            ? (float) $booking->platform_commission_syp
            : (isset($booking->commission_syp) && (float) $booking->commission_syp > 0
                ? (float) $booking->commission_syp
                : round($grossSyp * $rate / 100, 2));
        $commissionUsd = isset($booking->platform_commission_usd) && (float) $booking->platform_commission_usd > 0
            ? (float) $booking->platform_commission_usd
            : round($grossUsd * $rate / 100, 2);

        return $this->upsert(
            'booking',
            $booking->id,
            [
                'source_reference' => $booking->booking_number ?: 'BOOK-'.$booking->id,
                'source_title' => $booking->event_name ?: 'حجز صالة',
                'business_user_id' => $booking->owner_id,
                'business_role' => 'owner',
                'customer_id' => $booking->customer_id,
                'booking_id' => $booking->id,
                'approved_at' => $booking->owner_decision_at ?: $booking->admin_payment_decision_at ?: now(),
            ],
            $grossSyp,
            $grossUsd,
            $existing,
            $rate,
            $commissionSyp,
            $commissionUsd
        );
    }

    /**
     * Repair stale/missing booking commission rows without creating duplicates.
     * This is intentionally lightweight and is used by the admin finance screen
     * so bookings modified before this fix are reconciled on the next refresh.
     */
    public function reconcileBookings(int $limit = 1000): int
    {
        $bookings = Booking::query()
            ->whereIn('booking_status', [...self::ACTIVE_BOOKING_STATUSES, 'cancelled'])
            ->latest('updated_at')
            ->limit(max(1, min($limit, 5000)))
            ->get();

        if ($bookings->isEmpty()) {
            return 0;
        }

        $existing = PlatformCommission::query()
            ->where('source_type', 'booking')
            ->whereIn('source_id', $bookings->pluck('id'))
            ->get()
            ->keyBy(fn (PlatformCommission $row) => (int) $row->source_id);

        $repaired = 0;
        foreach ($bookings as $booking) {
            $row = $existing->get((int) $booking->id);
            $isCancellationFinancial = in_array((string) $booking->booking_status, ['cancellation_pending_refund', 'cancelled'], true);
            $grossSyp = $isCancellationFinancial && $booking->owner_retained_syp !== null
                ? (float) $booking->owner_retained_syp
                : (float) $booking->total_syp;
            $grossUsd = (float) $booking->total_usd;
            if ($isCancellationFinancial && (float) $booking->total_syp > 0) {
                $grossUsd = round((float) $booking->total_usd * ($grossSyp / (float) $booking->total_syp), 2);
            }
            $rate = (float) ($booking->platform_commission_rate ?? $booking->commission_rate ?? self::RATE);
            if ($rate <= 0) $rate = self::RATE;
            $commissionSyp = isset($booking->platform_commission_syp) && (float) $booking->platform_commission_syp > 0
                ? (float) $booking->platform_commission_syp
                : (isset($booking->commission_syp) && (float) $booking->commission_syp > 0
                    ? (float) $booking->commission_syp
                    : round($grossSyp * $rate / 100, 2));
            $commissionUsd = isset($booking->platform_commission_usd) && (float) $booking->platform_commission_usd > 0
                ? (float) $booking->platform_commission_usd
                : round($grossUsd * $rate / 100, 2);

            $stale = ! $row
                || abs((float) $row->gross_syp - $grossSyp) > 0.01
                || abs((float) $row->gross_usd - $grossUsd) > 0.01
                || abs((float) $row->commission_rate - $rate) > 0.0001
                || abs((float) $row->commission_syp - $commissionSyp) > 0.01
                || abs((float) $row->commission_usd - $commissionUsd) > 0.01;

            if ($stale) {
                $this->syncBooking($booking);
                $repaired++;
            }
        }

        return $repaired;
    }

    public function syncProviderRequest(ProviderServiceRequest $request): ?PlatformCommission
    {
        $existing = PlatformCommission::query()
            ->where('source_type', 'provider_service_request')
            ->where('source_id', $request->id)
            ->first();

        if (in_array((string) $request->status, self::CANCELLED_PROVIDER_STATUSES, true)) {
            $this->cancelIfUncollected($existing);
            return $existing?->fresh();
        }

        if (! in_array((string) $request->status, self::ACTIVE_PROVIDER_STATUSES, true)) {
            return $existing;
        }

        $grossSyp = (float) $request->price_syp;
        $grossUsd = (float) $request->price_usd;

        return $this->upsert(
            'provider_service_request',
            $request->id,
            [
                'source_reference' => 'SRV-'.$request->id,
                'source_title' => $request->service_name ?: 'طلب خدمة',
                'business_user_id' => $request->provider_id,
                'business_role' => 'provider',
                'customer_id' => $request->customer_id,
                'booking_id' => $request->booking_id,
                'approved_at' => $request->provider_decision_at ?: now(),
            ],
            $grossSyp,
            $grossUsd,
            $existing
        );
    }

    private function upsert(
        string $sourceType,
        int $sourceId,
        array $identity,
        float $grossSyp,
        float $grossUsd,
        ?PlatformCommission $existing,
        ?float $rate = null,
        ?float $commissionSyp = null,
        ?float $commissionUsd = null
    ): PlatformCommission {
        $rate = $rate !== null && $rate > 0 ? $rate : self::RATE;
        $commissionSyp = $commissionSyp ?? round($grossSyp * $rate / 100, 2);
        $commissionUsd = $commissionUsd ?? round($grossUsd * $rate / 100, 2);

        $status = $existing?->status ?: PlatformCommission::STATUS_UNCOLLECTED;
        if ($status === PlatformCommission::STATUS_CANCELLED) {
            $status = PlatformCommission::STATUS_UNCOLLECTED;
        }

        // If a commission was collected before a booking modification, keep the
        // collected amount as historical truth. When the recalculated commission
        // differs, expose it as partial instead of silently pretending it is fully
        // collected. The V2 financial ledger carries the exact settlement amount.
        if ($existing && !in_array($status, [PlatformCommission::STATUS_WAIVED, PlatformCommission::STATUS_DISPUTED], true)) {
            $collectedSyp = (float) ($existing->collected_syp ?? 0);
            $collectedUsd = (float) ($existing->collected_usd ?? 0);
            if ($collectedSyp > 0 || $collectedUsd > 0) {
                $sypSettled = abs($collectedSyp - $commissionSyp) <= 0.01;
                $usdSettled = abs($collectedUsd - $commissionUsd) <= 0.01;
                $status = ($sypSettled && $usdSettled)
                    ? PlatformCommission::STATUS_COLLECTED
                    : PlatformCommission::STATUS_PARTIAL;
            }
        }

        return PlatformCommission::query()->updateOrCreate(
            ['source_type' => $sourceType, 'source_id' => $sourceId],
            [
                ...$identity,
                'gross_syp' => $grossSyp,
                'gross_usd' => $grossUsd,
                'commission_rate' => $rate,
                'commission_syp' => $commissionSyp,
                'commission_usd' => $commissionUsd,
                'net_syp' => round($grossSyp - $commissionSyp, 2),
                'net_usd' => round($grossUsd - $commissionUsd, 2),
                'status' => $status,
            ]
        );
    }

    private function cancelIfUncollected(?PlatformCommission $commission): void
    {
        if (! $commission || $commission->status === PlatformCommission::STATUS_COLLECTED) {
            return;
        }

        $commission->update([
            'status' => PlatformCommission::STATUS_CANCELLED,
            'collected_syp' => 0,
            'collected_usd' => 0,
            'collected_at' => null,
            'collected_by' => null,
        ]);
    }
}