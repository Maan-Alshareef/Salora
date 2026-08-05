<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Booking;
use App\Models\BookingChangeRequest;
use App\Services\BookingWorkflowService;
use App\Services\NotificationService;
use App\Support\SaloraStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerBookingChangeRequestController extends BaseApiController
{
    public function store(Request $request, Booking $booking, BookingWorkflowService $workflow)
    {
        abort_unless((int)$booking->customer_id === (int)$request->user()->id, 403);
        if ($booking->booking_status !== SaloraStatus::BOOKING_CONFIRMED) {
            return $this->fail('Formal modification/cancellation requests are available for confirmed bookings only. Pending unpaid bookings can be cancelled directly.', 422);
        }
        if ($booking->changeRequests()->where('status', 'pending')->exists()) {
            return $this->fail('There is already a pending request for this booking.', 422);
        }

        $data = $request->validate([
            'type' => 'required|in:modification,cancellation',
            'reason' => 'required|string|max:1000',
            'event_date' => 'required_if:type,modification|nullable|date|after_or_equal:today',
            'start_time' => 'required_if:type,modification|nullable|date_format:H:i',
            'end_time' => 'required_if:type,modification|nullable|date_format:H:i|after:start_time',
            'guests_count' => 'nullable|integer|min:1',
            'notes' => 'nullable|string|max:1000',
        ]);

        $changeRequest = DB::transaction(function () use ($request, $booking, $data, $workflow) {
            $lockedBooking = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            if ($lockedBooking->booking_status !== SaloraStatus::BOOKING_CONFIRMED) {
                abort(422, 'The booking is no longer eligible for a change request.');
            }
            if ($lockedBooking->changeRequests()->where('status', 'pending')->exists()) {
                abort(422, 'There is already a pending request for this booking.');
            }

            $changes = $data['type'] === 'modification'
                ? collect($data)->only(['event_date', 'start_time', 'end_time', 'guests_count', 'notes'])->filter(fn($v) => $v !== null)->all()
                : [];

            $changeRequest = BookingChangeRequest::create([
                'booking_id' => $lockedBooking->id,
                'customer_id' => $request->user()->id,
                'type' => $data['type'],
                'requested_changes' => $changes,
                'reason' => $data['reason'],
                'status' => 'pending',
            ]);

            $targetStatus = $data['type'] === 'modification'
                ? SaloraStatus::BOOKING_MODIFICATION_REQUESTED
                : SaloraStatus::BOOKING_CANCELLATION_REQUESTED;
            $workflow->transition($lockedBooking, $targetStatus, $request->user(), $data['reason']);

            NotificationService::send(
                $lockedBooking->owner_id,
                $data['type'] === 'modification' ? 'طلب تعديل حجز' : 'طلب إلغاء حجز',
                'يوجد طلب جديد للحجز '.$lockedBooking->booking_number,
                'booking_change_request',
                ['booking_id' => $lockedBooking->id, 'request_id' => $changeRequest->id]
            );

            return $changeRequest;
        });

        return $this->ok($changeRequest->load('booking'), 'Request submitted.', 201);
    }
}
