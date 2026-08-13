<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Booking;
use App\Models\PaymentProof;
use App\Services\BookingWorkflowService;
use App\Services\InvoiceService;
use App\Services\NotificationService;
use App\Services\PaymentWorkflowService;
use App\Services\VenueAvailabilityService;
use App\Support\SaloraStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OwnerBookingController extends BaseApiController
{
    public function index(Request $request, VenueAvailabilityService $availability)
    {
        $availability->expireStalePending();
        return $this->ok(Booking::with([
            'customer:id,name,email,phone', 'venue.images', 'eventType', 'event', 'services',
            'latestPaymentProof', 'invoice', 'changeRequests',
        ])->where('owner_id', $request->user()->id)->latest()->get());
    }

    public function show(Request $request, Booking $booking)
    {
        abort_unless((int)$booking->owner_id === (int)$request->user()->id, 403);
        return $this->ok($booking->load([
            'customer:id,name,email,phone', 'venue.images', 'eventType', 'event.todoItems', 'services',
            'paymentProofs.invoice', 'invoice.transactions', 'statusHistory.actor:id,name', 'changeRequests.customer:id,name',
        ]));
    }

    public function approve(
        Request $request,
        Booking $booking,
        BookingWorkflowService $workflow,
        InvoiceService $invoices,
        VenueAvailabilityService $availability
    ) {
        abort_unless((int)$booking->owner_id === (int)$request->user()->id, 403);

        $result = DB::transaction(function () use ($request, $booking, $workflow, $invoices, $availability) {
            $locked = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            if ($locked->booking_status !== SaloraStatus::BOOKING_PENDING_OWNER_REVIEW) {
                return $this->fail('يمكن قبول الحجوزات بانتظار مراجعة المالك فقط.', 422);
            }
            if ($availability->hasConflict(
                $locked->venue_id,
                $locked->event_date->toDateString(),
                substr((string) $locked->start_time, 0, 5),
                substr((string) $locked->end_time, 0, 5),
                $locked->id,
                true,
            )) {
                return $this->fail('يوجد حجز فعال آخر يتعارض مع الموعد المطلوب.', 409, ['code' => 'venue_time_conflict']);
            }

            $locked->update([
                'payment_status' => SaloraStatus::PAYMENT_UNPAID,
                'owner_decision_at' => now(),
                'rejection_reason' => null,
            ]);
            $workflow->transition($locked, SaloraStatus::BOOKING_PENDING_PAYMENT, $request->user(), 'Hall manager approved the booking.');
            $invoice = $invoices->createForBooking($locked->fresh());

            NotificationService::send(
                $locked->customer_id,
                'تم قبول الحجز',
                'تم قبول الحجز '.$locked->booking_number.'. يرجى مراجعة الفاتورة ورفع إثبات التحويل.',
                'booking_approved',
                ['booking_id' => $locked->id, 'invoice_id' => $invoice->id]
            );

            return $locked->fresh([
                'customer:id,name,email,phone', 'venue', 'eventType', 'event', 'services', 'latestPaymentProof', 'invoice',
            ]);
        });

        if ($result instanceof \Illuminate\Http\JsonResponse) return $result;
        return $this->ok($result, 'Booking approved. Invoice created and payment is required.');
    }

    public function reject(Request $request, Booking $booking, BookingWorkflowService $workflow)
    {
        abort_unless((int)$booking->owner_id === (int)$request->user()->id, 403);
        $data = $request->validate(['reason' => 'required|string|max:500']);

        $result = DB::transaction(function () use ($request, $booking, $workflow, $data) {
            $locked = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            if ($locked->booking_status !== SaloraStatus::BOOKING_PENDING_OWNER_REVIEW) {
                return $this->fail('Only bookings waiting for hall-manager review can be rejected.', 422);
            }

            $locked->update([
                'rejection_reason' => $data['reason'],
                'owner_decision_at' => now(),
            ]);
            $workflow->transition($locked, SaloraStatus::BOOKING_OWNER_REJECTED, $request->user(), $data['reason']);
            $locked->providerRequests()->whereIn('status', ['pending', 'accepted'])->update(['status' => 'cancelled']);

            NotificationService::send(
                $locked->customer_id,
                'تم رفض الحجز',
                'الحجز '.$locked->booking_number.' مرفوض: '.$data['reason'],
                'booking_rejected',
                ['booking_id' => $locked->id]
            );

            return $locked->fresh(['customer:id,name,email,phone', 'venue', 'eventType', 'event', 'services']);
        });

        if ($result instanceof \Illuminate\Http\JsonResponse) return $result;
        return $this->ok($result, 'Booking rejected.');
    }

    public function complete(Request $request, Booking $booking, BookingWorkflowService $workflow)
    {
        abort_unless((int)$booking->owner_id === (int)$request->user()->id, 403);
        if ($booking->booking_status !== SaloraStatus::BOOKING_CONFIRMED) {
            return $this->fail('Only confirmed bookings can be marked as completed.', 422);
        }

        $workflow->transition($booking, SaloraStatus::BOOKING_COMPLETED, $request->user(), 'Hall manager completed the booking.');
        NotificationService::send(
            $booking->customer_id,
            'اكتمل الحجز',
            'تم تعليم الحجز '.$booking->booking_number.' كمكتمل. يمكنك الآن إضافة تقييم.',
            'booking_completed',
            ['booking_id' => $booking->id]
        );

        return $this->ok($booking->fresh(['customer:id,name,email,phone', 'venue', 'eventType', 'services', 'invoice']), 'Booking completed.');
    }

    public function approvePayment(
        Request $request,
        PaymentProof $payment,
        PaymentWorkflowService $payments
    ) {
        abort_unless((int) $payment->invoice?->payee_id === (int) $request->user()->id, 403);
        return $this->ok(
            $payments->review($request->user(), $payment, true),
            'تم قبول الدفعة وإصدار الإيصال.'
        );
    }

    public function rejectPayment(
        Request $request,
        PaymentProof $payment,
        PaymentWorkflowService $payments
    ) {
        abort_unless((int) $payment->invoice?->payee_id === (int) $request->user()->id, 403);
        $data = $request->validate(['reason' => 'required|string|max:700']);
        return $this->ok(
            $payments->review($request->user(), $payment, false, $data['reason']),
            'تم رفض الإثبات ويمكن للعميل إعادة الرفع.'
        );
    }


}
