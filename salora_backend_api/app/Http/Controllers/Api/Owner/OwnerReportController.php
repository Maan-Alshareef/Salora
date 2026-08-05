<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Booking;
use App\Models\Review;
use App\Models\Venue;
use App\Support\SaloraStatus;
use Illuminate\Http\Request;

class OwnerReportController extends BaseApiController
{
    public function summary(Request $request)
    {
        $ownerId = $request->user()->id;
        $paid = Booking::where('owner_id', $ownerId)->where('payment_status', SaloraStatus::PAYMENT_APPROVED);
        $recent = Booking::where('owner_id', $ownerId)->where('created_at', '>=', now()->subMonths(5)->startOfMonth())->get();
        $months = collect(range(5, 0))->map(function ($offset) use ($recent) {
            $month = now()->subMonths($offset);
            $items = $recent->filter(fn($booking) => $booking->created_at->format('Y-m') === $month->format('Y-m'));
            return [
                'month' => $month->format('Y-m'),
                'bookings' => $items->count(),
                'revenue_syp' => (float)$items->where('payment_status', SaloraStatus::PAYMENT_APPROVED)->sum('total_syp'),
                'revenue_usd' => (float)$items->where('payment_status', SaloraStatus::PAYMENT_APPROVED)->sum('total_usd'),
            ];
        });

        return $this->ok([
            'venues' => Venue::where('owner_id', $ownerId)->count(),
            'bookings' => Booking::where('owner_id', $ownerId)->count(),
            'confirmed_bookings' => Booking::where('owner_id', $ownerId)->where('booking_status', SaloraStatus::BOOKING_CONFIRMED)->count(),
            'completed_bookings' => Booking::where('owner_id', $ownerId)->where('booking_status', SaloraStatus::BOOKING_COMPLETED)->count(),
            'revenue_usd' => (float)(clone $paid)->sum('total_usd'),
            'revenue_syp' => (float)(clone $paid)->sum('total_syp'),
            'reviews' => Review::whereHas('venue', fn($q) => $q->where('owner_id', $ownerId))->where('status', 'visible')->count(),
            'average_rating' => round((float)(Review::whereHas('venue', fn($q) => $q->where('owner_id', $ownerId))->where('status', 'visible')->avg('rating') ?: 0), 2),
            'monthly' => $months,
        ]);
    }
}
