<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Booking;
use App\Models\BookingChangeRequest;
use App\Services\BookingWorkflowService;
use App\Services\NotificationService;
use App\Services\SaloraBookingV2Service;
use App\Support\SaloraStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OwnerBookingChangeRequestController extends BaseApiController
{
    public function index(Request $request)
    {
        return $this->ok(BookingChangeRequest::with(['booking.venue', 'customer:id,name,email,phone'])
            ->whereHas('booking', fn($q) => $q->where('owner_id', $request->user()->id))
            ->latest()
            ->get());
    }

    public function decide(
        Request $request,
        BookingChangeRequest $changeRequest,
        BookingWorkflowService $workflow,
        SaloraBookingV2Service $bookingV2
    )
    {
        $booking = $changeRequest->booking;
        abort_unless($booking && (int)$booking->owner_id === (int)$request->user()->id, 403);
        if ($changeRequest->status !== 'pending') return $this->fail('This request has already been reviewed.', 422);

        $data = $request->validate([
            'decision' => 'required|in:approve,reject',
            'reason' => 'required_if:decision,reject|nullable|string|max:1000',
        ]);

        $result = DB::transaction(function () use ($request, $booking, $changeRequest, $data, $workflow, $bookingV2) {
            $lockedBooking = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            $lockedRequest = BookingChangeRequest::whereKey($changeRequest->id)->lockForUpdate()->firstOrFail();
            if ($lockedRequest->status !== 'pending') abort(422, 'This request has already been reviewed.');

            if ($data['decision'] === 'approve') {
                if ($lockedRequest->type === 'modification') {
                    $changes = $lockedRequest->requested_changes ?? [];
                    if (is_string($changes)) {
                        $changes = json_decode($changes, true) ?: [];
                    }
                    $bookingV2->applyApprovedChange(
                        (int) $lockedBooking->id,
                        is_array($changes) ? $changes : [],
                        (int) $request->user()->id
                    );
                    $workflow->transition(
                        $lockedBooking,
                        SaloraStatus::BOOKING_CONFIRMED,
                        $request->user(),
                        'Modification request approved.'
                    );
                } else {
                    $workflow->transition($lockedBooking, SaloraStatus::BOOKING_CANCELLED, $request->user(), 'Cancellation request approved.');
                    $lockedBooking->providerRequests()->whereIn('status', ['pending', 'accepted'])->update(['status' => 'cancelled']);
                    $lockedBooking->invoice?->update(['status' => 'cancelled']);
                }
                $lockedRequest->update([
                    'status' => 'approved',
                    'reviewed_by' => $request->user()->id,
                    'decision_reason' => $data['reason'] ?? null,
                    'decided_at' => now(),
                ]);
            } else {
                $workflow->transition($lockedBooking, SaloraStatus::BOOKING_CONFIRMED, $request->user(), $data['reason']);
                $lockedRequest->update([
                    'status' => 'rejected',
                    'reviewed_by' => $request->user()->id,
                    'decision_reason' => $data['reason'],
                    'decided_at' => now(),
                ]);
            }

            NotificationService::send(
                $lockedBooking->customer_id,
                'تمت مراجعة طلب الحجز',
                $data['decision'] === 'approve' ? 'تم قبول طلبك.' : 'تم رفض طلبك: '.$data['reason'],
                'booking_change_decision',
                ['booking_id' => $lockedBooking->id, 'request_id' => $lockedRequest->id]
            );

            return $lockedRequest->fresh(['booking.venue', 'customer:id,name,email,phone']);
        });

        if ($result instanceof \Illuminate\Http\JsonResponse) return $result;
        return $this->ok($result, 'Request reviewed.');
    }

}
