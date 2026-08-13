<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingStatusHistory;
use App\Support\SaloraStatus;
use Illuminate\Database\Eloquent\Builder;

class VenueAvailabilityService
{
    public const BLOCKING_STATUSES = [
        SaloraStatus::BOOKING_PENDING_OWNER_REVIEW,
        SaloraStatus::BOOKING_PENDING_PAYMENT,
        SaloraStatus::BOOKING_PAYMENT_UNDER_REVIEW,
        SaloraStatus::BOOKING_CONFIRMED,
        SaloraStatus::BOOKING_MODIFICATION_REQUESTED,
    ];

    public function expireStalePending(?int $venueId = null, ?string $date = null): int
    {
        // Payment has no time limit in Salora. Pending bookings remain reserved
        // until they are paid, cancelled, or otherwise changed explicitly.
        return 0;
    }

    public function unavailableIntervals(int $venueId, string $date, ?int $excludeBookingId = null): array
    {
        $this->expireStalePending($venueId, $date);
        return $this->baseQuery($venueId, $date, $excludeBookingId)
            ->orderBy('start_time')
            ->get(['id', 'booking_status', 'start_time', 'end_time'])
            ->map(fn (Booking $booking) => [
                'booking_id' => $booking->id,
                'status' => $booking->booking_status,
                'start_time' => substr((string) $booking->start_time, 0, 5),
                'end_time' => substr((string) $booking->end_time, 0, 5),
            ])->values()->all();
    }

    public function hasConflict(
        int $venueId,
        string $date,
        string $start,
        string $end,
        ?int $excludeBookingId = null,
        bool $lockForUpdate = false,
    ): bool {
        $this->expireStalePending($venueId, $date);
        $query = $this->baseQuery($venueId, $date, $excludeBookingId)
            ->where(fn (Builder $q) => $q->where('start_time', '<', $end)->where('end_time', '>', $start));
        if ($lockForUpdate) $query->lockForUpdate();
        return $query->exists();
    }

    private function baseQuery(int $venueId, string $date, ?int $excludeBookingId): Builder
    {
        $pendingStatuses = [
            SaloraStatus::BOOKING_PENDING_OWNER_REVIEW,
            SaloraStatus::BOOKING_PENDING_PAYMENT,
        ];

        return Booking::query()
            ->where('venue_id', $venueId)
            ->whereDate('event_date', $date)
            ->whereIn('booking_status', self::BLOCKING_STATUSES)
            ->when($excludeBookingId, fn (Builder $q) => $q->whereKeyNot($excludeBookingId));
    }
}
