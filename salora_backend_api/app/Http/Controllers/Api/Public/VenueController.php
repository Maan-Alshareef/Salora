<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\VenueOffer;
use App\Models\Venue;
use App\Services\VenueAvailabilityService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class VenueController extends BaseApiController
{
    public function index(Request $request)
    {
        $query = Venue::with([
            'owner:id,name,avatar,status', 'images', 'videos', 'eventTypes',
            'services' => fn ($q) => $q->where('services.is_active', true)
                ->where('services.approval_status', 'approved')
                ->with('images'),
        ])->where('status', 'approved')
            ->whereHas('owner', fn ($q) => $q->where('status', 'active'));

        if ($request->filled('city')) {
            $city = trim((string) $request->query('city'));
            $query->where('city', 'like', "%{$city}%");
        }
        if ($request->filled('search') || $request->filled('q')) {
            $term = trim((string) $request->query('search', $request->query('q')));
            $query->where(fn ($q) => $q->where('name_en', 'like', "%{$term}%")
                ->orWhere('name_ar', 'like', "%{$term}%")
                ->orWhere('description_en', 'like', "%{$term}%")
                ->orWhere('description_ar', 'like', "%{$term}%")
                ->orWhere('city', 'like', "%{$term}%")
                ->orWhere('address', 'like', "%{$term}%"));
        }
        if ($request->filled('min_capacity')) $query->where('capacity', '>=', (int) $request->query('min_capacity'));
        if ($request->filled('max_capacity')) $query->where('capacity', '<=', (int) $request->query('max_capacity'));
        $hourlyPriceSql = 'COALESCE(NULLIF(hourly_price_syp, 0), price_syp, 0)';
        if ($request->filled('min_price_syp')) {
            $query->whereRaw("{$hourlyPriceSql} >= ?", [(float) $request->query('min_price_syp')]);
        }
        if ($request->filled('max_price_usd')) {
            $query->where('price_usd', '<=', (float) $request->query('max_price_usd'));
        }
        if ($request->filled('max_price_syp')) {
            $query->whereRaw("{$hourlyPriceSql} <= ?", [(float) $request->query('max_price_syp')]);
        }
        if ($request->filled('min_rating')) $query->where('rating_avg', '>=', (float) $request->query('min_rating'));
        if ($request->filled('event_type') || $request->filled('event_type_id')) {
            $eventType = $request->query('event_type_id', $request->query('event_type'));
            $query->whereHas('eventTypes', fn ($q) => $q
                ->where('event_types.id', $eventType)
                ->orWhere('name_en', $eventType)
                ->orWhere('name_ar', $eventType));
        }
        if ($request->boolean('featured')) $query->where('is_featured', true);
        if ($request->boolean('has_offer')) {
            $today = now()->toDateString();
            $query->whereExists(function ($offerQuery) use ($today) {
                $offerQuery->selectRaw('1')->from('venue_offers')
                    ->whereColumn('venue_offers.venue_id', 'venues.id')
                    ->where('venue_offers.is_active', true)
                    ->whereDate('venue_offers.starts_on', '<=', $today)
                    ->whereDate('venue_offers.ends_on', '>=', $today);
            });
        }

        $sort = $request->query('sort', 'latest');
        match ($sort) {
            'price_asc' => $query->orderByRaw("{$hourlyPriceSql} ASC"),
            'price_desc' => $query->orderByRaw("{$hourlyPriceSql} DESC"),
            'rating' => $query->orderByDesc('rating_avg')->orderByDesc('reviews_count'),
            'capacity' => $query->orderByDesc('capacity'),
            'name' => $query->orderBy('name_ar'),
            default => $query->orderByDesc('is_featured')->latest(),
        };

        $paginator = $query->paginate(min(max($request->integer('per_page', 20), 1), 100));
        $paginator->setCollection($paginator->getCollection()->map(fn (Venue $venue) => $this->decorateVenue($venue)));
        return $this->ok($paginator);
    }

    public function show(Venue $venue)
    {
        abort_unless($venue->status === 'approved' && $venue->owner?->isAvailableForNewBusiness(), 404);
        $venue->load([
            'owner:id,name,avatar,status', 'images', 'videos', 'eventTypes',
            'services' => fn ($q) => $q->where('services.is_active', true)
                ->where('services.approval_status', 'approved')
                ->with('images'),
            'reviews' => fn ($q) => $q->where('status', 'visible')->latest()->with('customer:id,name,avatar'),
        ]);
        return $this->ok($this->decorateVenue($venue));
    }

    public function reviews(Venue $venue)
    {
        abort_unless($venue->status === 'approved' && $venue->owner?->isAvailableForNewBusiness(), 404);
        return $this->ok($venue->reviews()->with('customer:id,name,avatar')->where('status', 'visible')->latest()->get());
    }

    public function services(Venue $venue)
    {
        abort_unless($venue->status === 'approved' && $venue->owner?->isAvailableForNewBusiness(), 404);
        return $this->ok($venue->services()
            ->where('services.is_active', true)
            ->where('services.approval_status', 'approved')
            ->whereIn('services.type', ['included', 'hall_upgrade'])
            ->wherePivot('is_available', true)
            ->with('images')
            ->get()
            ->map(fn ($service) => $this->decorateService($service)));
    }

    public function availability(Request $request, Venue $venue, VenueAvailabilityService $availability)
    {
        abort_unless($venue->status === 'approved' && $venue->owner?->isAvailableForNewBusiness(), 404);
        $data = $request->validate(['date' => 'required|date|after_or_equal:today']);
        $date = Carbon::parse($data['date']);
        $dayKey = strtolower($date->format('l'));
        $dayHours = $venue->opening_hours[$dayKey] ?? null;

        return $this->ok([
            'venue_id' => $venue->id,
            'date' => $date->toDateString(),
            'day' => $dayKey,
            'opening_hours' => $dayHours,
            'is_closed' => is_array($dayHours) && !($dayHours['enabled'] ?? false),
            'unavailable_intervals' => $availability->unavailableIntervals($venue->id, $date->toDateString()),
        ]);
    }

    private function decorateVenue(Venue $venue): array
    {
        $offer = $this->activeOfferFor($venue);
        $priceUsd = (float) $venue->price_usd;
        $hourlyPriceSyp = (float) ($venue->hourly_price_syp ?: $venue->price_syp);
        [$discountUsd, $discountSyp] = $this->discountAmounts($offer, $priceUsd, $hourlyPriceSyp);
        $finalHourlyPriceSyp = max(0, $hourlyPriceSyp - $discountSyp);

        return [
            ...$venue->toArray(),
            'price_usd' => $priceUsd,
            'price_syp' => $hourlyPriceSyp,
            'hourly_price_syp' => $hourlyPriceSyp,
            'final_price_usd' => max(0, $priceUsd - $discountUsd),
            'final_price_syp' => $finalHourlyPriceSyp,
            'final_hourly_price_syp' => $finalHourlyPriceSyp,
            'discount_usd' => $discountUsd,
            'discount_syp' => $discountSyp,
            'has_offer' => (bool) $offer,
            'discount_percentage' => $offer ? (int) $offer->percentage : null,
            'active_offer' => $offer,
            'badge' => $venue->is_featured
                ? 'مميزة'
                : ((float) $venue->rating_avg >= 4.5 && (int) $venue->reviews_count >= 3 ? 'الأعلى تقييماً' : null),
            'cover_image_url' => $venue->images->firstWhere('is_main', true)?->image_url ?: $venue->images->first()?->image_url,
            'services' => $venue->services
                ->filter(fn ($service) => in_array($service->type, ['included', 'hall_upgrade'], true) && (($service->pivot?->is_available) ?? true))
                ->map(fn ($service) => $this->decorateService($service))->values(),
            'external_services' => $venue->services
                ->filter(fn ($service) => $service->type === 'external_vendor' && (($service->pivot?->is_available) ?? true))
                ->map(fn ($service) => $this->decorateService($service))->values(),
        ];
    }

    private function decorateService($service): array
    {
        $pivot = $service->pivot ?? null;
        return [
            ...$service->toArray(),
            'price_usd' => (float) ($pivot?->custom_price_usd ?? $service->price_usd),
            'price_syp' => (float) ($pivot?->custom_price_syp ?? $service->price_syp),
            'is_available' => $pivot?->is_available ?? $service->is_active,
        ];
    }

    private function activeOfferFor(Venue $venue): ?VenueOffer
    {
        $today = now()->toDateString();
        return VenueOffer::query()
            ->where('venue_id', $venue->id)
            ->where('is_active', true)
            ->whereDate('starts_on', '<=', $today)
            ->whereDate('ends_on', '>=', $today)
            ->orderByDesc('percentage')
            ->first();
    }

    private function discountAmounts(?VenueOffer $offer, float $priceUsd, float $priceSyp): array
    {
        if (!$offer) return [0.0, 0.0];
        $rate = min(50, max(0, (float) $offer->percentage)) / 100;
        return [round($priceUsd * $rate, 2), round($priceSyp * $rate, 2)];
    }

}
