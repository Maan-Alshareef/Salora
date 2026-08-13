<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Controllers\Api\SaloraBookingV2Controller;
use App\Models\Booking;
use App\Models\BookingChangeRequest;
use App\Services\BookingWorkflowService;
use App\Services\NotificationService;
use App\Services\SaloraBookingV2Service;
use App\Support\SaloraStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerBookingChangeRequestController extends BaseApiController
{
    public function store(Request $request, Booking $booking, BookingWorkflowService $workflow)
    {
        abort_unless((int)$booking->customer_id === (int)$request->user()->id, 403);

        if ($request->input('type') === 'modification') {
            $data = $request->validate([
                'type' => 'required|in:modification',
                'reason' => 'nullable|string|max:1000',
                'event_date' => 'required|date',
                'start_time' => 'required|date_format:H:i',
                'end_time' => 'required|date_format:H:i',
                'guests_count' => 'required|integer|min:1',
                'notes' => 'nullable|string|max:1000',
            ]);
            $start = \Illuminate\Support\Carbon::parse($data['event_date'].' '.$data['start_time'])->second(0);
            $end = \Illuminate\Support\Carbon::parse($data['event_date'].' '.$data['end_time'])->second(0);
            if ($end->lessThanOrEqualTo($start)) {
                $end->addDay();
            }
            $request->merge([
                'start_at' => $start->toIso8601String(),
                'end_at' => $end->toIso8601String(),
                'guests_count' => (int) $data['guests_count'],
            ]);

            return app(SaloraBookingV2Controller::class)->requestChange(
                $request,
                (int) $booking->id,
                app(SaloraBookingV2Service::class),
            );
        }
        return $this->fail(
            'تم إلغاء مسار طلب موافقة المالك على الإلغاء. استخدم شاشة إلغاء الحجز لعرض السياسة والمبلغ ثم التأكيد مباشرة.',
            422,
            ['code' => 'use_direct_cancellation_flow']
        );

    }
}
