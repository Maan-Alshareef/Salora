<?php

namespace App\Services;

use App\Models\ProviderServiceRequest;
use App\Models\Setting;

class ProviderServiceFinanceService
{
    public const DEFAULT_COMMISSION_PERCENT = 10.0;

    public function commissionPercent(): float
    {
        $value = Setting::where('key', 'provider_commission_percent')->value('value');
        return min(100, max(0, (float) ($value ?? self::DEFAULT_COMMISSION_PERCENT)));
    }

    public function amounts(ProviderServiceRequest $request): array
    {
        $rate = $this->commissionPercent();
        $commissionSyp = round((float) $request->price_syp * $rate / 100, 2);
        $commissionUsd = round((float) $request->price_usd * $rate / 100, 2);

        return [
            'provider_commission_rate' => $rate,
            'provider_commission_syp' => $commissionSyp,
            'provider_commission_usd' => $commissionUsd,
            'provider_net_syp' => max(0, round((float) $request->price_syp - $commissionSyp, 2)),
            'provider_net_usd' => max(0, round((float) $request->price_usd - $commissionUsd, 2)),
        ];
    }

    public function initialise(ProviderServiceRequest $request): ProviderServiceRequest
    {
        $request->update([
            ...$this->amounts($request),
            'commission_status' => 'not_due',
            'commission_collected_at' => null,
        ]);
        return $request->fresh();
    }

    public function markDue(ProviderServiceRequest $request): ProviderServiceRequest
    {
        $request->update([
            ...$this->amounts($request),
            'commission_status' => 'due',
            'commission_collected_at' => null,
        ]);
        return $request->fresh();
    }
}
