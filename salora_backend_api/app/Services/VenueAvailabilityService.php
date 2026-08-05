<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingStatusHistory;
use App\Support\SaloraStatus;
use Illuminate\Database\Eloquent\Builder;

class VenueAvailabilityService
{
    public const PENDING_HOLD_HOURS = 6;

    public const BLOCKING_STATUSES = [
        SaloraStatus::BOOKING_PENDING_OWNER_REVIEW,
        SaloraStatus::BOOKING_PENDING_PAYMENT,
        SaloraStatus::BOOKING_PAYMENT_UNDER_REVIEW,
        SaloraStatus::BOOKING_CONFIRMED,
        SaloraStatus::BOOKING_MODIFICATION_REQUESTED,
        SaloraStatus::BOOKING_CANCELLATION_REQUESTED,
    ];

    public function expireStalePending(?int $venueId = null, ?string $date = null): int
    {
        $cutoff = now();
        $pendingStatuses = [
            SaloraStatus::BOOKING_PENDING_OWNER_REVIEW,
            SaloraStatus::BOOKING_PENDING_PAYMENT,
        ];

        $query = Booking::query()
            ->whereIn('booking_status', $pendingStatuses)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $cutoff);

        if ($venueId) {
            $query->where('venue_id', $venueId);
        }
        if ($date) {
            $query->whereDate('event_date', $date);
        }

        $expired = $query->get();
        $expiredCount = 0;

        foreach ($expired as $booking) {
            $from = $booking->booking_status;

            // Update directly with the same stale conditions. This keeps the
            // expiry operation deterministic on SQLite and prevents a stale
            // hold from leaking into the availability query in the same request.
            $updated = Booking::query()
                ->whereKey($booking->id)
                ->whereIn('booking_status', $pendingStatuses)
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', $cutoff)
                ->update([
                    'booking_status' => SaloraStatus::BOOKING_EXPIRED,
                    'expires_at' => null,
                    'updated_at' => $cutoff,
                ]);

            if ($updated !== 1) {
                continue;
            }

            $expiredCount++;
            $booking->invoice?->update(['status' => 'cancelled']);
            $booking->providerRequests()
                ->whereIn('status', ['pending', 'accepted'])
                ->update(['status' => 'cancelled']);

            BookingStatusHistory::create([
                'booking_id' => $booking->id,
                'from_status' => $from,
                'to_status' => SaloraStatus::BOOKING_EXPIRED,
                'changed_by' => null,
                'reason' => 'Unpaid booking hold expired automatically after '.self::PENDING_HOLD_HOURS.' hours.',
            ]);
        }

        return $expiredCount;
    }

    public function unavailableIntervals(int $venueId, string $date, ?int $excludeBookingId = null): array
    {
        $this->expireStalePending($venueId, $date);
        return $this->baseQuery($venueId, $date, $excludeBookingId)
            ->orderBy('start_time')
            ->get(['id', 'booking_status', 'start_time', 'end_time', 'expires_at'])
            ->map(fn (Booking $booking) => [
                'booking_id' => $booking->id,
                'status' => $booking->booking_status,
                'start_time' => substr((string) $booking->start_time, 0, 5),
                'end_time' => substr((string) $booking->end_time, 0, 5),
                'expires_at' => $booking->expires_at?->toIso8601String(),
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
            // A pending unpaid hold blocks the slot only while its deadline is
            // still active. Review, confirmed, modification and cancellation
            // states continue to block regardless of the payment hold deadline.
            ->where(function (Builder $query) use ($pendingStatuses): void {
                $query->whereNotIn('booking_status', $pendingStatuses)
                    ->orWhereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->when($excludeBookingId, fn (Builder $q) => $q->whereKeyNot($excludeBookingId));
    }
}
