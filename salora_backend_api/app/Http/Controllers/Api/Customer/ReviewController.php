<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Booking;
use App\Models\Review;
use App\Models\Venue;
use App\Support\SaloraStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReviewController extends BaseApiController
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'booking_id' => 'required|exists:bookings,id',
            'venue_id' => 'nullable|required_without:service_id|exists:venues,id',
            'service_id' => 'nullable|required_without:venue_id|exists:services,id',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);
        if (!empty($data['venue_id']) && !empty($data['service_id'])) {
            return $this->fail('Review either a venue or a service, not both.', 422);
        }

        $review = DB::transaction(function () use ($request, $data) {
            $booking = Booking::with(['services', 'providerRequests'])
                ->whereKey($data['booking_id'])
                ->lockForUpdate()
                ->firstOrFail();
            abort_unless((int)$booking->customer_id === (int)$request->user()->id, 403);
            if ($booking->booking_status !== SaloraStatus::BOOKING_COMPLETED) {
                abort(422, 'A review can be submitted only after the booking is completed.');
            }
            if (!empty($data['venue_id']) && (int)$booking->venue_id !== (int)$data['venue_id']) {
                abort(422, 'The selected venue is not part of this booking.');
            }
            if (!empty($data['service_id'])) {
                $serviceId = (int)$data['service_id'];
                $isBooked = $booking->services->contains('service_id', $serviceId)
                    || $booking->providerRequests->where('status', 'accepted')->contains('service_id', $serviceId);
                if (!$isBooked) abort(422, 'The selected service is not part of this booking.');
            }

            $duplicate = Review::where('customer_id', $request->user()->id)
                ->where('booking_id', $booking->id)
                ->where('venue_id', $data['venue_id'] ?? null)
                ->where('service_id', $data['service_id'] ?? null)
                ->where('status', '!=', 'deleted')
                ->exists();
            if ($duplicate) abort(422, 'A review for this booking item already exists.');

            $review = Review::create([
                ...$data,
                'customer_id' => $request->user()->id,
                'status' => 'visible',
            ]);
            if (!empty($data['venue_id'])) $this->recalculateVenue((int)$data['venue_id']);
            return $review;
        });

        return $this->ok($review->load(['customer:id,name', 'venue:id,name_ar,name_en', 'service:id,name_ar,name_en']), 'Review added.', 201);
    }

    private function recalculateVenue(int $venueId): void
    {
        $venue = Venue::find($venueId);
        if (!$venue) return;
        $query = $venue->reviews()->where('status', 'visible');
        $venue->update([
            'rating_avg' => round((float)($query->avg('rating') ?: 0), 2),
            'reviews_count' => $query->count(),
        ]);
    }
}
