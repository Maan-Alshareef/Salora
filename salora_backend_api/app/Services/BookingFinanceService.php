<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Setting;

class BookingFinanceService
{
    public function __construct(private readonly ExchangeRateService $exchangeRates) {}

    public const DEFAULT_COMMISSION_PERCENT = 10.0;

    public function commissionPercent(): float
    {
        $value = Setting::where('key', 'platform_commission_percent')->value('value');
        return min(100, max(0, (float) ($value ?? self::DEFAULT_COMMISSION_PERCENT)));
    }

    public function amounts(Booking $booking): array
    {
        $commissionRate = $this->commissionPercent();
        $exchangeRate = $this->exchangeRates->resolveSnapshotRate(
            $booking->exchange_rate_syp_per_usd ?? null,
            $booking->total_syp,
            $booking->total_usd,
        );
        $totalSyp = (float) $booking->total_syp;
        $totalUsd = $this->exchangeRates->toUsd($totalSyp, $exchangeRate);
        $commissionSyp = round($totalSyp * $commissionRate / 100, 2);
        $commissionUsd = $this->exchangeRates->toUsd($commissionSyp, $exchangeRate);
        $ownerNetSyp = max(0, round($totalSyp - $commissionSyp, 2));

        return [
            'subtotal_usd' => $this->exchangeRates->toUsd($booking->subtotal_syp, $exchangeRate),
            'discount_usd' => $this->exchangeRates->toUsd($booking->discount_syp, $exchangeRate),
            'total_usd' => $totalUsd,
            'exchange_rate_syp_per_usd' => $exchangeRate,
            'platform_commission_rate' => $commissionRate,
            'platform_commission_syp' => $commissionSyp,
            'platform_commission_usd' => $commissionUsd,
            'owner_net_syp' => $ownerNetSyp,
            'owner_net_usd' => $this->exchangeRates->toUsd($ownerNetSyp, $exchangeRate),
        ];
    }

    public function initialise(Booking $booking): Booking
    {
        $booking->update([
            ...$this->amounts($booking),
            'commission_status' => 'not_due',
            'commission_collected_at' => null,
        ]);

        return $booking->fresh();
    }

    public function markDue(Booking $booking): Booking
    {
        $booking->update([
            ...$this->amounts($booking),
            'commission_status' => 'due',
            'commission_collected_at' => null,
        ]);

        return $booking->fresh();
    }

    public function reverse(Booking $booking, ?string $notes = null): Booking
    {
        $booking->update([
            'commission_status' => 'reversed',
            'commission_collected_at' => null,
            'commission_notes' => $notes,
        ]);

        return $booking->fresh();
    }
}
