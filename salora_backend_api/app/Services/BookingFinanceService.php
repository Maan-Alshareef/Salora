<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Setting;

class BookingFinanceService
{
    public const DEFAULT_COMMISSION_PERCENT = 10.0;

    public function commissionPercent(): float
    {
        $value = Setting::where('key', 'platform_commission_percent')->value('value');
        return min(100, max(0, (float) ($value ?? self::DEFAULT_COMMISSION_PERCENT)));
    }

    public function amounts(Booking $booking): array
    {
        $rate = $this->commissionPercent();
        $commissionSyp = round((float) $booking->total_syp * $rate / 100, 2);
        $commissionUsd = round((float) $booking->total_usd * $rate / 100, 2);

        return [
            'platform_commission_rate' => $rate,
            'platform_commission_syp' => $commissionSyp,
            'platform_commission_usd' => $commissionUsd,
            'owner_net_syp' => max(0, round((float) $booking->total_syp - $commissionSyp, 2)),
            'owner_net_usd' => max(0, round((float) $booking->total_usd - $commissionUsd, 2)),
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
