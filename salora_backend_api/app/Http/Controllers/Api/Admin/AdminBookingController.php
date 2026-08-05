<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Booking;
use App\Services\BookingWorkflowService;
use App\Services\NotificationService;
use App\Support\SaloraStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminBookingController extends BaseApiController
{
    public function index(Request $request)
    {
        $query = Booking::with([
            'customer:id,name,email,phone', 'owner:id,name,email', 'venue', 'eventType', 'event',
            'services', 'latestPaymentProof', 'invoice', 'changeRequests',
        ])->latest();
        if ($request->filled('status')) $query->where('booking_status', $request->query('status'));
        return $this->ok($query->get());
    }

    public function show(Booking $booking)
    {
        return $this->ok($booking->load([
            'customer:id,name,email,phone', 'owner:id,name,email,phone', 'venue.images', 'eventType',
            'event.todoItems', 'services', 'paymentProofs.invoice', 'invoice.transactions',
            'statusHistory.actor:id,name', 'changeRequests.customer:id,name',
        ]));
    }

    public function cancel(Request $request, Booking $booking, BookingWorkflowService $workflow)
    {
        $data = $request->validate(['reason' => 'required|string|max:500']);
        if (in_array($booking->booking_status, [SaloraStatus::BOOKING_CANCELLED, SaloraStatus::BOOKING_COMPLETED, SaloraStatus::BOOKING_OWNER_REJECTED], true)) {
            return $this->fail('A final booking cannot be cancelled again.', 422);
        }

        DB::transaction(function () use ($request, $booking, $workflow, $data) {
            $locked = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            $workflow->transition($locked, SaloraStatus::BOOKING_CANCELLED, $request->user(), $data['reason']);
            $locked->providerRequests()->whereIn('status', ['pending', 'accepted'])->update(['status' => 'cancelled']);
            $locked->invoice?->update(['status' => 'cancelled']);
            NotificationService::send($locked->customer_id, 'تم إلغاء الحجز من الإدارة', $data['reason'], 'booking_cancelled', ['booking_id' => $locked->id]);
            NotificationService::send($locked->owner_id, 'تم إلغاء الحجز من الإدارة', $data['reason'], 'booking_cancelled', ['booking_id' => $locked->id]);
        });

        return $this->ok($booking->fresh(['invoice', 'statusHistory']), 'Booking cancelled by administrator.');
    }
}
