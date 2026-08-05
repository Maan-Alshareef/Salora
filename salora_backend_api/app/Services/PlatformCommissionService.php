<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\PlatformCommission;
use App\Models\ProviderServiceRequest;

class PlatformCommissionService
{
    public const RATE = 10.0;

    private const ACTIVE_BOOKING_STATUSES = [
        'confirmed', 'completed', 'modification_requested', 'cancellation_requested',
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
            $this->cancelIfUncollected($existing);
            return $existing?->fresh();
        }

        if (in_array((string) $booking->booking_status, self::PRE_CONFIRMATION_BOOKING_STATUSES, true)) {
            $this->cancelIfUncollected($existing);
            return $existing?->fresh();
        }

        if (! in_array((string) $booking->booking_status, self::ACTIVE_BOOKING_STATUSES, true)) {
            return $existing;
        }

        $grossSyp = (float) $booking->total_syp;
        $grossUsd = (float) $booking->total_usd;

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
            $existing
        );
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
        ?PlatformCommission $existing
    ): PlatformCommission {
        $status = $existing?->status ?: PlatformCommission::STATUS_UNCOLLECTED;
        if ($status === PlatformCommission::STATUS_CANCELLED) {
            $status = PlatformCommission::STATUS_UNCOLLECTED;
        }

        return PlatformCommission::query()->updateOrCreate(
            ['source_type' => $sourceType, 'source_id' => $sourceId],
            [
                ...$identity,
                'gross_syp' => $grossSyp,
                'gross_usd' => $grossUsd,
                'commission_rate' => self::RATE,
                'commission_syp' => round($grossSyp * self::RATE / 100, 2),
                'commission_usd' => round($grossUsd * self::RATE / 100, 2),
                'net_syp' => round($grossSyp * (100 - self::RATE) / 100, 2),
                'net_usd' => round($grossUsd * (100 - self::RATE) / 100, 2),
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