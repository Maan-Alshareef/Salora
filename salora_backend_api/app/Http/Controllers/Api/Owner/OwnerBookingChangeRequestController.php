<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Controllers\Api\SaloraBookingV2Controller;
use App\Models\Booking;
use App\Models\BookingChangeRequest;
use App\Services\BookingModificationService;
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
            ->where('type', 'modification')
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
        abort_unless($booking && (int) $booking->owner_id === (int) $request->user()->id, 403);
        if ($changeRequest->type !== 'modification') {
            return $this->fail('لم يعد إلغاء العميل يحتاج موافقة المالك. هذا طلب قديم وتم إيقاف هذا المسار.', 422);
        }
        if ($changeRequest->status !== 'pending') {
            return $this->fail('This request has already been reviewed.', 422);
        }

        $data = $request->validate([
            'decision' => 'required|in:approve,reject',
            'reason' => 'required_if:decision,reject|nullable|string|max:1000',
        ]);

        $controller = app(SaloraBookingV2Controller::class);
        if ($data['decision'] === 'approve') {
            return $controller->approveChange(
                $request,
                (int) $booking->id,
                (int) $changeRequest->id,
                $bookingV2,
                app(BookingModificationService::class),
            );
        }

        return $controller->rejectChange(
            $request,
            (int) $booking->id,
            (int) $changeRequest->id,
            $bookingV2,
        );
    }

}
