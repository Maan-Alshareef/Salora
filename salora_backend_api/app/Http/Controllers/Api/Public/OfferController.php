<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\VenueOffer;

class OfferController extends BaseApiController
{
    public function index()
    {
        $today = now()->toDateString();
        $offers = VenueOffer::query()
            ->with('venue:id,name_ar,name_en,owner_id')
            ->where('is_active', true)
            ->whereDate('ends_on', '>=', $today)
            ->latest('published_at')
            ->get()
            ->map(fn (VenueOffer $offer) => [
                'id' => $offer->id,
                'venue_id' => $offer->venue_id,
                'title' => $offer->title,
                'title_ar' => $offer->title,
                'title_en' => $offer->title,
                'discount_type' => 'percentage',
                'discount_value' => (float) $offer->percentage,
                'percentage' => (float) $offer->percentage,
                'start_date' => optional($offer->starts_on)->toDateString(),
                'end_date' => optional($offer->ends_on)->toDateString(),
                'status' => 'active',
                'is_current' => optional($offer->starts_on)->lte(now()) && optional($offer->ends_on)->gte(now()),
                'is_upcoming' => optional($offer->starts_on)->gt(now()),
                'venue' => $offer->venue,
            ]);

        return $this->ok($offers);
    }
}
