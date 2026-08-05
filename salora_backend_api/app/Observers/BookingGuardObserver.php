<?php

namespace App\Observers;

use App\Models\Booking;
use App\Services\BookingConflictService;

class BookingGuardObserver
{
    public function __construct(
        private readonly BookingConflictService $conflicts,
    ) {
    }

    public function saving(Booking $booking): void
    {
        $this->conflicts->guard($booking);
    }
}