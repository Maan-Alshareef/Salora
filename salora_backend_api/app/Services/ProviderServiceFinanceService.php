<?php

namespace App\Services;

use App\Models\ProviderServiceRequest;
use App\Models\Setting;

class ProviderServiceFinanceService
{
    public function __construct(private readonly ExchangeRateService $exchangeRates) {}

    public const DEFAULT_COMMISSION_PERCENT = 10.0;

    public function commissionPercent(): float
    {
        $value = Setting::where('key', 'provider_commission_percent')->value('value');
        return min(100, max(0, (float) ($value ?? self::DEFAULT_COMMISSION_PERCENT)));
    }

    public function amounts(ProviderServiceRequest $request): array
    {
        $commissionRate = $this->commissionPercent();
        $exchangeRate = $this->exchangeRates->resolveSnapshotRate(
            $request->exchange_rate_syp_per_usd ?? null,
            $request->price_syp,
            $request->price_usd,
        );
        $priceSyp = (float) $request->price_syp;
        $priceUsd = $this->exchangeRates->toUsd($priceSyp, $exchangeRate);
        $commissionSyp = round($priceSyp * $commissionRate / 100, 2);
        $commissionUsd = $this->exchangeRates->toUsd($commissionSyp, $exchangeRate);
        $netSyp = max(0, round($priceSyp - $commissionSyp, 2));

        return [
            'price_usd' => $priceUsd,
            'exchange_rate_syp_per_usd' => $exchangeRate,
            'provider_commission_rate' => $commissionRate,
            'provider_commission_syp' => $commissionSyp,
            'provider_commission_usd' => $commissionUsd,
            'provider_net_syp' => $netSyp,
            'provider_net_usd' => $this->exchangeRates->toUsd($netSyp, $exchangeRate),
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
