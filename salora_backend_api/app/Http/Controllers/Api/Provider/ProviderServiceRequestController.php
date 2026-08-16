<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Booking;
use App\Models\ProviderServiceRequest;
use App\Services\ExchangeRateService;
use App\Services\InvoiceService;
use App\Services\NotificationService;
use App\Services\PaymentWorkflowService;
use App\Services\PlatformCommissionService;
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
                            [
                                'cancelled',
                                'owner_rejected',
                                'rejected',
                                'completed',
                                'expired',
                                'refunded',
                            ],
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


    public function cancel(
        Request $request,
        ProviderServiceRequest $providerRequest,
        PlatformCommissionService $commissions,
        ExchangeRateService $exchangeRates,
    ) {
        abort_unless((int) $providerRequest->provider_id === (int) $request->user()->id, 403);

        $data = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $result = DB::transaction(function () use ($providerRequest, $request, $data, $commissions) {
            $locked = ProviderServiceRequest::with(['booking', 'invoice'])
                ->whereKey($providerRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((string) $locked->status === 'pending') {
                return $this->fail('الطلب ما زال بانتظار قرارك؛ استخدم رفض الطلب بدل الإلغاء.', 422);
            }
            if ((string) $locked->status !== 'accepted') {
                return $this->fail('لا يمكن إلغاء هذا الطلب في حالته الحالية.', 422);
            }

            $total = round((float) ($locked->price_syp ?? 0), 2);
            $paid = in_array(strtolower((string) ($locked->payment_status ?? '')), ['paid', 'approved', 'verified', 'payment_approved'], true);
            $refund = $paid ? $total : 0.0;
            $refundPercent = $paid && $total > 0 ? 100.0 : 0.0;
            $cancellationStatus = $paid && $refund > 0 ? 'waiting_refund' : 'cancelled';
            $paymentStatus = $paid && $refund > 0 ? 'pending_refund' : 'cancelled';

            $locked->update([
                'status' => 'cancelled',
                'cancelled_by' => 'provider',
                'cancellation_reason' => $data['reason'],
                'cancellation_status' => $cancellationStatus,
                'refund_percentage' => $refundPercent,
                'refunded_syp' => $refund,
                'provider_retained_syp' => $paid ? max(0, $total - $refund) : 0,
                'payment_status' => $paymentStatus,
            ]);

            if ($locked->invoice) {
                $locked->invoice->update([
                    'status' => $paid && $refund > 0 ? 'refund_pending' : ($paid ? 'paid' : 'cancelled'),
                ]);
            }

            if ($paid && $refund > 0 && $locked->invoice_id) {
                $existing = DB::table('payment_refunds')
                    ->where('invoice_id', $locked->invoice_id)
                    ->where('reason', 'provider_cancelled')
                    ->first();
                if (!$existing) {
                    $refundExchangeRate = $exchangeRates->resolveSnapshotRate(
                        $locked->invoice?->exchange_rate_syp_per_usd,
                        $locked->invoice?->total_syp ?? $locked->price_syp,
                        $locked->invoice?->total_usd ?? $locked->price_usd,
                    );
                    DB::table('payment_refunds')->insert([
                        'booking_id' => $locked->booking_id,
                        'invoice_id' => $locked->invoice_id,
                        'customer_id' => $locked->customer_id,
                        'payee_id' => $locked->provider_id,
                        'amount_syp' => $refund,
                        'amount_usd' => $exchangeRates->toUsd($refund, $refundExchangeRate),
                        'refund_percent' => $refundPercent,
                        'status' => 'pending',
                        'requested_by_role' => 'provider',
                        'reason' => 'provider_cancelled',
                        'due_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            $commissions->syncProviderRequest($locked->fresh());

            NotificationService::send(
                (int) $locked->customer_id,
                'ألغى مقدم الخدمة الطلب',
                'قام مقدم الخدمة بإلغاء خدمة '.$locked->service_name.' مع استرداد كامل'.($refund > 0 ? ' بانتظار تأكيد رد المبلغ.' : '.'),
                'provider_service_cancelled_by_provider',
                [
                    'event' => 'provider_service_cancelled_by_provider',
                    'booking_id' => (string) $locked->booking_id,
                    'request_id' => (string) $locked->id,
                    'target_route' => 'booking_details',
                ],
            );

            try {
                NotificationService::send(
                    (int) $request->user()->id,
                    'تم تسجيل إلغاء الخدمة',
                    $refund > 0
                        ? 'لا يكتمل الإلغاء نهائياً حتى تؤكد أنك أعدت المبلغ للعميل.'
                        : 'تم إلغاء الطلب دون استرداد لأن الطلب غير مدفوع.',
                    'provider_service_cancelled_notice',
                    [
                        'event' => 'provider_service_cancelled_notice',
                        'booking_id' => (string) $locked->booking_id,
                        'request_id' => (string) $locked->id,
                        'target_route' => 'provider_requests',
                    ],
                );
            } catch (\Throwable $error) {
                report($error);
            }

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

        return $this->ok($result, 'تم إلغاء طلب الخدمة.');
    }

    public function confirmRefund(
        Request $request,
        ProviderServiceRequest $providerRequest,
        PlatformCommissionService $commissions,
    ) {
        abort_unless((int) $providerRequest->provider_id === (int) $request->user()->id, 403);

        $result = DB::transaction(function () use ($providerRequest, $commissions) {
            $locked = ProviderServiceRequest::with(['booking', 'invoice'])
                ->whereKey($providerRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((string) $locked->status !== 'cancelled' || (string) $locked->cancellation_status !== 'waiting_refund') {
                return $this->fail('لا يوجد استرداد معلّق لهذا الطلب.', 422);
            }

            $locked->update([
                'cancellation_status' => 'cancelled',
                'refund_confirmed_at' => now(),
                'payment_status' => 'refunded',
            ]);

            if ($locked->invoice_id) {
                DB::table('payment_refunds')
                    ->where('invoice_id', $locked->invoice_id)
                    ->where('status', 'pending')
                    ->update([
                        'status' => 'confirmed',
                        'transferred_at' => now(),
                        'updated_at' => now(),
                    ]);
                DB::table('invoices')->where('id', $locked->invoice_id)->update([
                    'status' => 'refunded',
                    'updated_at' => now(),
                ]);
            }

            $commissions->syncProviderRequest($locked->fresh());

            NotificationService::send(
                (int) $locked->customer_id,
                'تم تأكيد استرداد خدمة مقدم الخدمة',
                'أكد مقدم الخدمة أنه أعاد مبلغ خدمة '.$locked->service_name.' إليك.',
                'provider_service_refund_confirmed',
                [
                    'event' => 'provider_service_refund_confirmed',
                    'booking_id' => (string) $locked->booking_id,
                    'request_id' => (string) $locked->id,
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

        return $this->ok($result, 'تم تأكيد تنفيذ الاسترداد.');
    }

}
