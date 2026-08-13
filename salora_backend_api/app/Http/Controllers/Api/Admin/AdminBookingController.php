<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Booking;
use App\Services\BookingWorkflowService;
use App\Services\NotificationService;
use App\Services\SaloraBookingV2Service;
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

    public function cancel(Request $request, Booking $booking, BookingWorkflowService $workflow, SaloraBookingV2Service $bookingV2)
    {
        $data = $request->validate(['reason' => 'required|string|max:2000']);
        $result = $bookingV2->ownerCancellation(
            (int) $booking->id,
            (int) $request->user()->id,
            $data['reason'],
            'admin',
        );

        if (empty($result['already_processed'])) {
            NotificationService::send(
                (int) $booking->customer_id,
                'تم إلغاء الحجز من الإدارة',
                'ألغت إدارة Salora الحجز '.$booking->booking_number.' ويحق لك استرداد 100% من المبلغ المدفوع. السبب: '.$data['reason'],
                'admin_booking_cancelled',
                ['event' => 'admin_booking_cancelled', 'booking_id' => $booking->id, 'target_route' => 'booking_details', 'refund_percentage' => 100]
            );
            NotificationService::send(
                (int) $booking->owner_id,
                'تم إلغاء الحجز من الإدارة',
                'ألغت إدارة Salora الحجز '.$booking->booking_number.'. السبب: '.$data['reason'],
                'admin_booking_cancelled',
                ['event' => 'admin_booking_cancelled', 'booking_id' => $booking->id, 'target_route' => 'owner_booking_details']
            );
        }

        return $this->ok(
            $booking->fresh(['invoice', 'statusHistory']),
            'Booking cancelled by administrator through unified cancellation flow.'
        );
    }
}
