<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingStatusHistory;
use App\Models\User;
use App\Support\SaloraStatus;
use Illuminate\Validation\ValidationException;

class BookingWorkflowService
{
    private const TRANSITIONS = [
        SaloraStatus::BOOKING_PENDING_OWNER_REVIEW => [
            SaloraStatus::BOOKING_PENDING_PAYMENT,
            SaloraStatus::BOOKING_OWNER_REJECTED,
            SaloraStatus::BOOKING_CANCELLED,
        ],
        SaloraStatus::BOOKING_PENDING_PAYMENT => [
            SaloraStatus::BOOKING_PAYMENT_UNDER_REVIEW,
            SaloraStatus::BOOKING_CANCELLATION_REQUESTED,
            SaloraStatus::BOOKING_CANCELLED,
        ],
        SaloraStatus::BOOKING_PAYMENT_UNDER_REVIEW => [
            SaloraStatus::BOOKING_CONFIRMED,
            SaloraStatus::BOOKING_PENDING_PAYMENT,
            SaloraStatus::BOOKING_CANCELLED,
        ],
        SaloraStatus::BOOKING_CONFIRMED => [
            SaloraStatus::BOOKING_MODIFICATION_REQUESTED,
            SaloraStatus::BOOKING_CANCELLATION_REQUESTED,
            SaloraStatus::BOOKING_COMPLETED,
            SaloraStatus::BOOKING_CANCELLED,
        ],
        SaloraStatus::BOOKING_MODIFICATION_REQUESTED => [
            SaloraStatus::BOOKING_CONFIRMED,
            SaloraStatus::BOOKING_CANCELLED,
        ],
        SaloraStatus::BOOKING_CANCELLATION_REQUESTED => [
            SaloraStatus::BOOKING_CONFIRMED,
            SaloraStatus::BOOKING_CANCELLED,
        ],
    ];

    public function transition(Booking $booking, string $toStatus, ?User $actor = null, ?string $reason = null, array $attributes = []): Booking
    {
        $fromStatus = $booking->booking_status;
        if ($fromStatus === $toStatus) {
            return $booking;
        }

        if (!in_array($toStatus, self::TRANSITIONS[$fromStatus] ?? [], true)) {
            throw ValidationException::withMessages([
                'booking_status' => "Invalid booking transition: {$fromStatus} -> {$toStatus}",
            ]);
        }

        if ($fromStatus === SaloraStatus::BOOKING_PENDING_OWNER_REVIEW && $toStatus !== $fromStatus) {
            $attributes['expires_at'] = null;
        }

        $booking->update([
            ...$attributes,
            'booking_status' => $toStatus,
        ]);

        BookingStatusHistory::create([
            'booking_id' => $booking->id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'changed_by' => $actor?->id,
            'reason' => $reason,
        ]);

        return $booking->fresh();
    }

    public function recordInitialState(Booking $booking, ?User $actor = null): void
    {
        BookingStatusHistory::create([
            'booking_id' => $booking->id,
            'from_status' => null,
            'to_status' => $booking->booking_status,
            'changed_by' => $actor?->id,
            'reason' => 'Booking created',
        ]);
    }
}
