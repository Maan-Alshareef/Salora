<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Review;
use App\Models\Venue;

class AdminReviewController extends BaseApiController
{
    public function index()
    {
        return $this->ok(Review::with(['customer:id,name', 'venue', 'service', 'booking:id,booking_number'])->latest()->get());
    }

    public function hide(Review $review)
    {
        $review->update(['status' => 'hidden']);
        $this->refreshVenue($review);
        return $this->ok($review, 'Review hidden.');
    }

    public function restore(Review $review)
    {
        $review->update(['status' => 'visible']);
        $this->refreshVenue($review);
        return $this->ok($review, 'Review restored.');
    }

    public function destroy(Review $review)
    {
        $review->update(['status' => 'deleted']);
        $this->refreshVenue($review);
        return $this->ok($review, 'Review deleted.');
    }

    private function refreshVenue(Review $review): void
    {
        if (!$review->venue_id) return;
        $venue = Venue::find($review->venue_id);
        if (!$venue) return;
        $query = $venue->reviews()->where('status', 'visible');
        $venue->update([
            'rating_avg' => round((float)($query->avg('rating') ?: 0), 2),
            'reviews_count' => $query->count(),
        ]);
    }
}
