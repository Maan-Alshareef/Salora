<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\Venue;
use App\Models\VenueOffer;

class BookingPricingService
{
    public function calculate(
        Venue $venue,
        int $guests,
        array $serviceIds = [],
        ?string $eventDate = null,
        string $currency = 'SYP'
    ): array {
        $usdToSyp = (float) (Setting::where('key', 'exchange_rate_usd_to_syp')->value('value') ?: env('USD_TO_SYP', 14000));

        $hallSyp = (float) ($venue->hourly_price_syp ?: $venue->price_syp);
        $hallUsd = (float) $venue->price_usd;
        if ($hallSyp <= 0 && $hallUsd > 0) $hallSyp = round($hallUsd * $usdToSyp, 2);
        if ($hallUsd <= 0 && $hallSyp > 0 && $usdToSyp > 0) $hallUsd = round($hallSyp / $usdToSyp, 2);

        $services = $venue->services()
            ->whereIn('services.id', $serviceIds)
            ->where('services.is_active', true)
            ->where('services.approval_status', 'approved')
            ->whereIn('services.type', ['included', 'hall_upgrade'])
            ->wherePivot('is_available', true)
            ->get();

        $items = [];
        $servicesUsd = 0.0;
        $servicesSyp = 0.0;
        foreach ($services as $service) {
            if ($service->type === 'included') {
                $usd = 0.0;
                $syp = 0.0;
            } else {
                $usd = $service->pivot->custom_price_usd !== null
                    ? (float) $service->pivot->custom_price_usd
                    : (float) $service->price_usd;
                $syp = $service->pivot->custom_price_syp !== null
                    ? (float) $service->pivot->custom_price_syp
                    : (float) $service->price_syp;
                if ($syp <= 0 && $usd > 0) $syp = round($usd * $usdToSyp, 2);
                if ($usd <= 0 && $syp > 0 && $usdToSyp > 0) $usd = round($syp / $usdToSyp, 2);
            }
            $servicesUsd += $usd;
            $servicesSyp += $syp;
            $items[] = [
                'service_id' => $service->id,
                'service_name' => $service->name_ar ?: $service->name_en,
                'service_type' => $service->type,
                'quantity' => 1,
                'unit_price_usd' => $usd,
                'unit_price_syp' => $syp,
                'total_usd' => $usd,
                'total_syp' => $syp,
            ];
        }

        $subtotalUsd = round($hallUsd + $servicesUsd, 2);
        $subtotalSyp = round($hallSyp + $servicesSyp, 2);
        $offer = $this->activeOfferFor($venue, $eventDate);
        $rate = $offer ? min(50, max(0, (float) $offer->percentage)) / 100 : 0;
        $discountUsd = round($subtotalUsd * $rate, 2);
        $discountSyp = round($subtotalSyp * $rate, 2);

        return [
            'hall_usd' => $hallUsd,
            'hall_syp' => $hallSyp,
            'service_items' => $items,
            'subtotal_usd' => $subtotalUsd,
            'subtotal_syp' => $subtotalSyp,
            'discount_usd' => $discountUsd,
            'discount_syp' => $discountSyp,
            'total_usd' => max(0, round($subtotalUsd - $discountUsd, 2)),
            'total_syp' => max(0, round($subtotalSyp - $discountSyp, 2)),
            'offer_id' => $offer?->id,
        ];
    }

    private function activeOfferFor(Venue $venue, ?string $eventDate = null): ?VenueOffer
    {
        $date = $eventDate ?: now()->toDateString();
        return VenueOffer::query()
            ->where('venue_id', $venue->id)
            ->where('is_active', true)
            ->whereDate('starts_on', '<=', $date)
            ->whereDate('ends_on', '>=', $date)
            ->orderByDesc('percentage')
            ->first();
    }
}
