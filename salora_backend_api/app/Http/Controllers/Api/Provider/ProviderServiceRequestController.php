<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Booking;
use App\Models\ProviderServiceRequest;
use App\Services\InvoiceService;
use App\Services\NotificationService;
use App\Services\PaymentWorkflowService;
use App\Support\SaloraStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProviderServiceRequestController extends BaseApiController
{
    public function index(Request $request)
    {
        return $this->ok(
            ProviderServiceRequest::with([
                'booking.venue',
                'booking.eventType',
                'booking.event',
                'customer:id,name,email,phone',
                'service',
                'invoice.latestPaymentProof.method',
                'invoice.latestPaymentProof.payoutAccount',
            ])
                ->where('provider_id', $request->user()->id)
                ->latest()
                ->get(),
        );
    }

    public function accept(
        Request $request,
        ProviderServiceRequest $providerRequest,
        InvoiceService $invoices,
    ) {
        abort_unless(
            (int) $providerRequest->provider_id ===
                (int) $request->user()->id,
            403,
        );

        $data = $request->validate([
            'reply' => 'nullable|string|max:1000',
        ]);

        $result = DB::transaction(function () use (
            $request,
            $providerRequest,
            $data,
            $invoices,
        ) {
            $locked = ProviderServiceRequest::with(['booking', 'invoice'])
                ->whereKey($providerRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === 'accepted') {
                $invoices->createForProviderRequest($locked);

                return $locked->fresh([
                    'booking.venue',
                    'booking.eventType',
                    'customer:id,name,email,phone',
                    'service',
                    'invoice.latestPaymentProof.method',
                    'invoice.latestPaymentProof.payoutAccount',
                ]);
            }

            if ($locked->status !== 'pending') {
                return $this->fail(
                    'تمت مراجعة هذا الطلب مسبقاً.',
                    422,
                );
            }

            $booking = Booking::query()
                ->whereKey($locked->booking_id)
                ->lockForUpdate()
                ->first();

            if (
                !$booking ||
                !SaloraStatus::bookingAllowsProviderServiceRequest(
                    $booking->booking_status,
                )
            ) {
                return $this->fail(
                    'طلب الخدمة مرتبط بحجز لم يعد فعالاً.',
                    422,
                );
            }

            if ($booking->event_date?->isBefore(now()->startOfDay())) {
                return $this->fail(
                    'طلب الخدمة مرتبط بحجز انتهى تاريخه.',
                    422,
                );
            }

            $hasConflict = ProviderServiceRequest::query()
                ->where('provider_id', $request->user()->id)
                ->whereKeyNot($locked->id)
                ->where('status', 'accepted')
                ->whereHas('booking', function ($query) use ($booking): void {
                    $query
                        ->whereDate('event_date', $booking->event_date)
                        ->whereRaw(
                            "time(start_time, '-30 minutes') < time(?)",
                            [$booking->end_time],
                        )
                        ->whereRaw(
                            "time(end_time, '+30 minutes') > time(?)",
                            [$booking->start_time],
                        )
                        ->whereNotIn(
                            'booking_status',
                            ['cancelled', 'owner_rejected', 'completed'],
                        );
                })
                ->lockForUpdate()
                ->exists();

            if ($hasConflict) {
                return $this->fail(
                    'لديك طلب خدمة مقبول في نفس الفترة الزمنية.',
                    422,
                );
            }

            $locked->update([
                'status' => 'accepted',
                'payment_status' => 'unpaid',
                'provider_reply' => $data['reply'] ?? null,
                'provider_decision_at' => now(),
            ]);

            $invoice = $invoices->createForProviderRequest(
                $locked->fresh(),
            );

            NotificationService::send(
                (int) $locked->customer_id,
                'تم قبول طلب الخدمة',
                'وافق مقدم الخدمة على '.$locked->service_name.
                    '. أصبحت مطالبة الدفع جاهزة.',
                'provider_service_accepted',
                [
                    'booking_id' => $locked->booking_id,
                    'request_id' => $locked->id,
                    'invoice_id' => $invoice->id,
                    'payment_deadline_at' =>
                        $invoice->payment_deadline_at?->toIso8601String(),
                    'target_route' => 'booking_details',
                ],
            );

            return $locked->fresh([
                'booking.venue',
                'booking.eventType',
                'customer:id,name,email,phone',
                'service',
                'invoice.latestPaymentProof.method',
                'invoice.latestPaymentProof.payoutAccount',
            ]);
        });

        if ($result instanceof \Illuminate\Http\JsonResponse) {
            return $result;
        }

        return $this->ok($result, 'تم قبول طلب الخدمة.');
    }

    public function reject(
        Request $request,
        ProviderServiceRequest $providerRequest,
    ) {
        abort_unless(
            (int) $providerRequest->provider_id ===
                (int) $request->user()->id,
            403,
        );

        $data = $request->validate([
            'reply' => 'required|string|max:1000',
        ]);

        $result = DB::transaction(function () use (
            $providerRequest,
            $request,
            $data,
        ) {
            $locked = ProviderServiceRequest::query()
                ->whereKey($providerRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === 'rejected') {
                return $locked->fresh([
                    'booking.venue',
                    'booking.eventType',
                    'customer:id,name,email,phone',
                    'service',
                    'invoice',
                ]);
            }

            if ($locked->status !== 'pending') {
                return $this->fail(
                    'تمت مراجعة هذا الطلب مسبقاً.',
                    422,
                );
            }

            $locked->update([
                'status' => 'rejected',
                'provider_reply' => $data['reply'],
                'provider_decision_at' => now(),
            ]);

            NotificationService::send(
                (int) $locked->customer_id,
                'تم رفض طلب الخدمة',
                $locked->service_name.': '.$data['reply'],
                'provider_service_rejected',
                [
                    'booking_id' => $locked->booking_id,
                    'request_id' => $locked->id,
                    'target_route' => 'booking_details',
                ],
            );

            return $locked->fresh([
                'booking.venue',
                'booking.eventType',
                'customer:id,name,email,phone',
                'service',
                'invoice',
            ]);
        });

        if ($result instanceof \Illuminate\Http\JsonResponse) {
            return $result;
        }

        return $this->ok($result, 'تم رفض طلب الخدمة.');
    }

    public function approvePayment(
        Request $request,
        ProviderServiceRequest $providerRequest,
        PaymentWorkflowService $payments,
    ) {
        abort_unless(
            (int) $providerRequest->provider_id ===
                (int) $request->user()->id,
            403,
        );

        $locked = ProviderServiceRequest::with(
            'invoice.latestPaymentProof',
        )->findOrFail($providerRequest->id);

        $proof = $locked->invoice?->latestPaymentProof;

        if (!$proof || $proof->status !== 'pending') {
            return $this->fail(
                'لا يوجد إيصال دفع قيد المراجعة لهذا الطلب.',
                422,
            );
        }

        return $this->ok(
            $payments->review($request->user(), $proof, true),
            'تم قبول الدفعة وإصدار الإيصال.',
        );
    }

    public function rejectPayment(
        Request $request,
        ProviderServiceRequest $providerRequest,
        PaymentWorkflowService $payments,
    ) {
        abort_unless(
            (int) $providerRequest->provider_id ===
                (int) $request->user()->id,
            403,
        );

        $data = $request->validate([
            'reason' => 'required|string|max:700',
        ]);

        $locked = ProviderServiceRequest::with(
            'invoice.latestPaymentProof',
        )->findOrFail($providerRequest->id);

        $proof = $locked->invoice?->latestPaymentProof;

        if (!$proof || $proof->status !== 'pending') {
            return $this->fail(
                'لا يوجد إيصال دفع قيد المراجعة لهذا الطلب.',
                422,
            );
        }

        return $this->ok(
            $payments->review(
                $request->user(),
                $proof,
                false,
                $data['reason'],
            ),
            'تم رفض الإيصال ويمكن للعميل إعادة الرفع.',
        );
    }
}
