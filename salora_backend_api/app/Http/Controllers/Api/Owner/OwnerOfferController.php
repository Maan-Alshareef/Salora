<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Venue;
use App\Models\VenueOffer;
use App\Services\VenueOfferAnnouncementService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OwnerOfferController extends BaseApiController
{
    public function index(Request $request)
    {
        $offers = VenueOffer::query()
            ->with('venue:id,owner_id,name_ar,name_en')
            ->whereHas('venue', fn ($query) => $query->where('owner_id', $request->user()->id))
            ->latest()
            ->get()
            ->map(fn (VenueOffer $offer) => $this->present($offer));

        return $this->ok($offers);
    }

    public function store(Request $request, VenueOfferAnnouncementService $announcements)
    {
        $data = $this->validated($request);
        $venue = $this->ownedVenue($request, (int) $data['venue_id']);

        $offer = VenueOffer::create([
            'venue_id' => $venue->id,
            'title' => $data['title'],
            'offer_type' => 'percentage',
            'percentage' => $data['percentage'],
            'starts_on' => $data['starts_on'],
            'ends_on' => $data['ends_on'],
            'is_active' => true,
            'published_at' => now(),
        ]);

        $offer->load('venue');
        $announcements->announce($offer);

        return $this->ok($this->present($offer), 'تم نشر العرض مباشرة في التطبيق وإرسال إشعار للعملاء.', 201);
    }

    public function update(Request $request, int $offer, VenueOfferAnnouncementService $announcements)
    {
        $row = VenueOffer::findOrFail($offer);
        $this->ownedVenue($request, (int) $row->venue_id);
        $data = $this->validated($request, (int) $row->venue_id);
        $this->ownedVenue($request, (int) $data['venue_id']);

        $row->update([
            'venue_id' => $data['venue_id'],
            'title' => $data['title'],
            'offer_type' => 'percentage',
            'scheduled_discount_type' => null,
            'percentage' => $data['percentage'],
            'fixed_amount_syp' => null,
            'starts_on' => $data['starts_on'],
            'ends_on' => $data['ends_on'],
            'days_of_week' => null,
            'start_time' => null,
            'end_time' => null,
            'is_active' => true,
            'published_at' => now(),
        ]);

        $fresh = $row->fresh('venue');
        if (\Illuminate\Support\Facades\Schema::hasColumn('venue_offers', 'announcement_sent_at')) {
            $fresh->forceFill(['announcement_sent_at' => null])->save();
        }
        $announcements->announce($fresh, true);

        return $this->ok($this->present($fresh), 'تم تحديث العرض ونشره مباشرة وإرسال إشعار للعملاء.');
    }

    public function destroy(Request $request, int $offer)
    {
        $row = VenueOffer::findOrFail($offer);
        $this->ownedVenue($request, (int) $row->venue_id);
        $row->delete();

        return $this->ok(null, 'تم حذف العرض.');
    }

    private function validated(Request $request, ?int $defaultVenueId = null): array
    {
        $request->merge([
            'venue_id' => $request->input('venue_id', $defaultVenueId),
            'title' => $request->input('title', $request->input('title_ar', $request->input('title_en'))),
            'percentage' => $request->input('percentage', $request->input('discount_value')),
            'starts_on' => $request->input('starts_on', $request->input('start_date')),
            'ends_on' => $request->input('ends_on', $request->input('end_date')),
        ]);

        return $request->validate([
            'venue_id' => ['required', 'integer', 'exists:venues,id'],
            'title' => ['required', 'string', 'max:160'],
            'percentage' => ['required', 'numeric', 'min:1', 'max:50'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
        ], [
            'percentage.max' => 'ممنوع أن تتجاوز نسبة الخصم 50%.',
            'ends_on.after_or_equal' => 'تاريخ انتهاء العرض لا يمكن أن يكون قبل تاريخ البداية.',
        ]);
    }

    private function ownedVenue(Request $request, int $venueId): Venue
    {
        $venue = Venue::whereKey($venueId)
            ->where('owner_id', $request->user()->id)
            ->first();

        if (!$venue) {
            throw ValidationException::withMessages(['venue_id' => ['الصالة المحددة لا تتبع لهذا المالك.']]);
        }

        return $venue;
    }

    private function present(VenueOffer $offer): array
    {
        return [
            'id' => $offer->id,
            'venue_id' => $offer->venue_id,
            'owner_id' => $offer->venue?->owner_id,
            'scope' => 'specific_venue',
            'title' => $offer->title,
            'title_ar' => $offer->title,
            'title_en' => $offer->title,
            'discount_type' => 'percentage',
            'discount_value' => (float) $offer->percentage,
            'percentage' => (float) $offer->percentage,
            'start_date' => optional($offer->starts_on)->toDateString(),
            'end_date' => optional($offer->ends_on)->toDateString(),
            'starts_on' => optional($offer->starts_on)->toDateString(),
            'ends_on' => optional($offer->ends_on)->toDateString(),
            'status' => $offer->is_active ? 'active' : 'inactive',
            'is_active' => (bool) $offer->is_active,
            'published_at' => $offer->published_at,
            'venue' => $offer->venue,
        ];
    }
}
