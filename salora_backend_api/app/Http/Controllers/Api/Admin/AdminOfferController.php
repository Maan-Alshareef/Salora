<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\VenueOffer;
use Illuminate\Http\Request;

class AdminOfferController extends BaseApiController
{
    public function index()
    {
        $offers = VenueOffer::query()
            ->with(['venue:id,owner_id,name_ar,name_en', 'venue.owner:id,name'])
            ->latest()
            ->get()
            ->map(fn (VenueOffer $offer) => [
                'id' => $offer->id,
                'venue_id' => $offer->venue_id,
                'owner_id' => $offer->venue?->owner_id,
                'scope' => 'specific_venue',
                'title_ar' => $offer->title,
                'title_en' => $offer->title,
                'discount_type' => 'percentage',
                'discount_value' => (float) $offer->percentage,
                'start_date' => optional($offer->starts_on)->toDateString(),
                'end_date' => optional($offer->ends_on)->toDateString(),
                'status' => $offer->is_active ? 'active' : 'inactive',
                'venue' => $offer->venue,
                'owner' => $offer->venue?->owner,
                'published_at' => $offer->published_at,
            ]);

        return $this->ok($offers);
    }

    public function store(Request $request)
    {
        return $this->fail('العروض ينشئها مالك الصالة وتُنشر مباشرة. صفحة الأدمن للمراقبة فقط.', 403);
    }

    public function approve(int $offer)
    {
        return $this->fail('لا تحتاج عروض الصالات إلى موافقة الأدمن.', 403);
    }

    public function reject(Request $request, int $offer)
    {
        return $this->fail('لا تحتاج عروض الصالات إلى موافقة أو رفض الأدمن.', 403);
    }

    public function destroy(int $offer)
    {
        return $this->fail('صفحة الأدمن للمراقبة فقط. إدارة العرض من حساب مالك الصالة.', 403);
    }
}
