<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\PaymentMethod;
use App\Models\PaymentProof;
use App\Models\PayoutAccount;
use App\Models\ProviderServiceRequest;
use App\Models\User;
use App\Support\SaloraStatus;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentWorkflowService
{
    public function __construct(
        private readonly InvoiceService $invoices,
        private readonly BookingWorkflowService $bookings,
    ) {
    }

    public function paymentOptions(Invoice $invoice): array
    {
        $scope = $invoice->source_type === 'provider_service'
            ? 'for_providers'
            : 'for_venues';

        $methods = PaymentMethod::query()
            ->where('is_active', true)
            ->where($scope, true)
            ->whereIn('slug', config('salora_payments.allowed_method_slugs', []))
            ->orderBy('sort_order')
            ->get();

        $accounts = PayoutAccount::with('method')
            ->where('user_id', $invoice->payee_id)
            ->where('is_active', true)
            ->whereIn('payment_method_id', $methods->pluck('id'))
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get()
            ->groupBy('payment_method_id');

        return $methods
            ->map(function (PaymentMethod $method) use ($accounts): array {
                return [
                    ...$method->toArray(),
                    'accounts' => ($accounts[$method->id] ?? collect())
                        ->values()
                        ->all(),
                ];
            })
            ->filter(fn (array $method): bool => count($method['accounts']) > 0)
            ->values()
            ->all();
    }

    public function submitProof(
        User $customer,
        Invoice $invoice,
        UploadedFile $image,
        array $data,
    ): PaymentProof {
        return DB::transaction(function () use (
            $customer,
            $invoice,
            $image,
            $data,
        ): PaymentProof {
            $locked = Invoice::with(['booking', 'providerServiceRequest'])
                ->whereKey($invoice->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $locked->customer_id !== (int) $customer->id) {
                abort(403);
            }

            if ($locked->status === 'paid') {
                throw ValidationException::withMessages([
                    'invoice' => ['الفاتورة مدفوعة مسبقاً.'],
                ]);
            }

            if ($locked->payment_deadline_at?->isPast()) {
                throw ValidationException::withMessages([
                    'invoice' => ['انتهت مهلة الدفع لهذه الفاتورة.'],
                ]);
            }

            if (
                PaymentProof::query()
                    ->where('invoice_id', $locked->id)
                    ->where('status', 'pending')
                    ->exists()
            ) {
                throw ValidationException::withMessages([
                    'invoice' => ['يوجد إيصال دفع قيد المراجعة بالفعل.'],
                ]);
            }

            $method = PaymentMethod::query()
                ->whereKey($data['payment_method_id'])
                ->where('is_active', true)
                ->whereIn(
                    'slug',
                    config('salora_payments.allowed_method_slugs', []),
                )
                ->firstOrFail();

            $account = PayoutAccount::query()
                ->whereKey($data['payout_account_id'])
                ->where('user_id', $locked->payee_id)
                ->where('payment_method_id', $method->id)
                ->where('is_active', true)
                ->firstOrFail();

            $reference = trim((string) ($data['transaction_reference'] ?? ''));

            if (
                $reference !== '' &&
                PaymentProof::query()
                    ->where('payout_account_id', $account->id)
                    ->where('transaction_reference', $reference)
                    ->whereIn('status', ['pending', 'approved'])
                    ->exists()
            ) {
                throw ValidationException::withMessages([
                    'transaction_reference' => [
                        'رقم العملية مستخدم مسبقاً لدى نفس المستلم.',
                    ],
                ]);
            }

            $path = $image->store(
                'payment-proofs/'.now()->format('Y/m'),
                'local',
            );

            $attempt =
                ((int) PaymentProof::query()
                    ->where('invoice_id', $locked->id)
                    ->max('attempt_no')) + 1;

            $proof = PaymentProof::create([
                'booking_id' => $locked->booking_id,
                'invoice_id' => $locked->id,
                'customer_id' => $customer->id,
                'image_url' => $path,
                'amount_syp' => $locked->total_syp,
                'amount_usd' => $locked->total_usd,
                'payment_method' => $method->slug,
                'payment_method_id' => $method->id,
                'payout_account_id' => $account->id,
                'sender_name' => trim((string) $data['sender_name']),
                'transaction_reference' => $reference !== '' ? $reference : null,
                'transferred_at' => $data['transferred_at'] ?? now(),
                'customer_notes' => $data['customer_notes'] ?? null,
                'status' => 'pending',
                'attempt_no' => $attempt,
                'owner_id' => $locked->source_type === 'venue_booking'
                    ? $locked->payee_id
                    : null,
                'uploaded_at' => now(),
            ]);

            $this->invoices->registerProof($locked, $proof);
            $reviewDeadline = now()->addHours(
                (int) config('salora_payments.review_deadline_hours', 12),
            );
            $locked->update([
                'status' => 'proof_uploaded',
                'review_deadline_at' => $reviewDeadline,
                'review_reminder_sent_at' => null,
                'review_overdue_notified_at' => null,
            ]);

            if (
                $locked->source_type === 'venue_booking' &&
                $locked->booking
            ) {
                $booking = $locked->booking;

                if (
                    $booking->booking_status ===
                    SaloraStatus::BOOKING_PENDING_OWNER_REVIEW
                ) {
                    $booking = $this->bookings->transition(
                        $booking,
                        SaloraStatus::BOOKING_PENDING_PAYMENT,
                        $customer,
                        'Preliminary owner approval was removed; booking moved directly to payment.',
                    );
                }

                $this->bookings->transition(
                    $booking,
                    SaloraStatus::BOOKING_PAYMENT_UNDER_REVIEW,
                    $customer,
                    'Payment proof uploaded.',
                    [
                        'payment_status' =>
                            SaloraStatus::PAYMENT_PROOF_UPLOADED,
                        'expires_at' => null,
                    ],
                );
            }

            if ($locked->source_type === 'provider_service') {
                ProviderServiceRequest::query()
                    ->whereKey($locked->source_id)
                    ->update([
                        'payment_status' => 'proof_uploaded',
                        'payment_uploaded_at' => now(),
                        'payment_rejection_reason' => null,
                    ]);
            }

            NotificationService::send(
                (int) $locked->payee_id,
                $locked->source_type === 'provider_service'
                    ? 'دفعة خدمة جديدة بانتظار المراجعة'
                    : 'دفعة صالة جديدة بانتظار المراجعة',
                'رفع '.$customer->name.
                    ' إيصال دفع للفاتورة '.$locked->invoice_number.
                    ' بقيمة '.$this->amountLabel($locked).'.',
                'payment_proof_uploaded',
                [
                    'invoice_id' => $locked->id,
                    'payment_proof_id' => $proof->id,
                    'booking_id' => $locked->booking_id,
                    'request_id' => $locked->source_type === 'provider_service'
                        ? $locked->source_id
                        : null,
                    'source_type' => $locked->source_type,
                    'review_deadline_at' => $reviewDeadline->toIso8601String(),
                    'target_route' => 'business_payments',
                ],
            );

            return $proof->fresh([
                'invoice.customer',
                'invoice.payee',
                'invoice.booking.venue',
                'invoice.providerServiceRequest.service',
                'method',
                'payoutAccount',
                'transaction',
            ]);
        });
    }

    public function review(
        User $reviewer,
        PaymentProof $payment,
        bool $approve,
        ?string $reason = null,
    ): PaymentProof {
        return DB::transaction(function () use (
            $reviewer,
            $payment,
            $approve,
            $reason,
        ): PaymentProof {
            $locked = PaymentProof::with([
                'invoice.booking',
                'invoice.customer',
                'invoice.providerServiceRequest',
                'transaction',
            ])
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            $invoice = $locked->invoice;

            if (!$invoice || $locked->status !== 'pending') {
                throw ValidationException::withMessages([
                    'payment' => ['تمت مراجعة إيصال الدفع مسبقاً.'],
                ]);
            }

            if ((int) $invoice->payee_id !== (int) $reviewer->id) {
                abort(403);
            }

            if ($approve) {
                $locked->update([
                    'status' => 'approved',
                    'reviewer_id' => $reviewer->id,
                    'reviewer_role' => $reviewer->role,
                    'reviewed_at' => now(),
                    'rejection_reason' => null,
                ]);

                $invoice = $this->invoices->approveProof(
                    $locked,
                    $reviewer->id,
                );

                if (
                    $invoice->source_type === 'venue_booking' &&
                    $invoice->booking
                ) {
                    $this->bookings->transition(
                        $invoice->booking,
                        SaloraStatus::BOOKING_CONFIRMED,
                        $reviewer,
                        'Hall owner approved the payment proof and confirmed the booking.',
                        [
                            'payment_status' =>
                                SaloraStatus::PAYMENT_APPROVED,
                            'owner_decision_at' => now(),
                            'admin_payment_decision_at' => now(),
                            'expires_at' => null,
                        ],
                    );
                }

                if ($invoice->source_type === 'provider_service') {
                    ProviderServiceRequest::query()
                        ->whereKey($invoice->source_id)
                        ->update([
                            'payment_status' => 'approved',
                            'payment_reviewed_at' => now(),
                            'payment_rejection_reason' => null,
                        ]);
                }

                NotificationService::send(
                    (int) $invoice->customer_id,
                    $invoice->source_type === 'provider_service'
                        ? 'تم قبول دفعة الخدمة'
                        : 'تم قبول دفعة الصالة',
                    'تم تأكيد دفع الفاتورة '.$invoice->invoice_number.
                        ' وأصبح الإيصال النهائي جاهزاً للحفظ.',
                    'payment_approved',
                    [
                        'invoice_id' => $invoice->id,
                        'booking_id' => $invoice->booking_id,
                        'request_id' =>
                            $invoice->source_type === 'provider_service'
                                ? $invoice->source_id
                                : null,
                        'source_type' => $invoice->source_type,
                        'receipt_number' => $invoice->receipt_number,
                        'target_route' => 'booking_details',
                    ],
                );
            } else {
                if (trim((string) $reason) === '') {
                    throw ValidationException::withMessages([
                        'reason' => ['سبب الرفض مطلوب.'],
                    ]);
                }

                $locked->update([
                    'status' => 'rejected',
                    'reviewer_id' => $reviewer->id,
                    'reviewer_role' => $reviewer->role,
                    'reviewed_at' => now(),
                    'rejection_reason' => $reason,
                ]);

                $this->invoices->rejectProof($locked, $reason);

                $retryHours = $invoice->source_type === 'venue_booking'
                    ? VenueAvailabilityService::PENDING_HOLD_HOURS
                    : (int) config(
                        'salora_payments.payment_deadline_hours',
                        6,
                    );

                $retryDeadline = now()->addHours($retryHours);

                $invoice->update([
                    'status' => 'unpaid',
                    'due_at' => $retryDeadline,
                    'payment_deadline_at' => $retryDeadline,
                    'payment_reminder_sent_at' => null,
                    'review_deadline_at' => null,
                    'review_reminder_sent_at' => null,
                    'review_overdue_notified_at' => null,
                ]);

                if (
                    $invoice->source_type === 'venue_booking' &&
                    $invoice->booking
                ) {
                    $this->bookings->transition(
                        $invoice->booking,
                        SaloraStatus::BOOKING_PENDING_PAYMENT,
                        $reviewer,
                        'Hall owner rejected the payment proof; customer may upload a replacement.',
                        [
                            'payment_status' =>
                                SaloraStatus::PAYMENT_REJECTED,
                            'expires_at' => $retryDeadline,
                        ],
                    );
                }

                if ($invoice->source_type === 'provider_service') {
                    ProviderServiceRequest::query()
                        ->whereKey($invoice->source_id)
                        ->update([
                            'payment_status' => 'rejected',
                            'payment_reviewed_at' => now(),
                            'payment_rejection_reason' => $reason,
                            'payment_deadline_at' => $retryDeadline,
                        ]);
                }

                NotificationService::send(
                    (int) $invoice->customer_id,
                    $invoice->source_type === 'provider_service'
                        ? 'تم رفض إيصال دفع الخدمة'
                        : 'تم رفض إيصال دفع الصالة',
                    'السبب: '.$reason.
                        '. يمكنك رفع إيصال جديد قبل انتهاء المهلة.',
                    'payment_rejected',
                    [
                        'invoice_id' => $invoice->id,
                        'payment_proof_id' => $locked->id,
                        'booking_id' => $invoice->booking_id,
                        'request_id' =>
                            $invoice->source_type === 'provider_service'
                                ? $invoice->source_id
                                : null,
                        'source_type' => $invoice->source_type,
                        'target_route' => 'booking_details',
                    ],
                );
            }

            return $locked->fresh([
                'invoice.customer',
                'invoice.payee',
                'invoice.acceptedBy',
                'invoice.booking.venue',
                'invoice.providerServiceRequest.service',
                'method',
                'payoutAccount',
                'reviewer',
                'transaction',
            ]);
        });
    }

    private function amountLabel(Invoice $invoice): string
    {
        return $invoice->currency === 'USD'
            ? number_format((float) $invoice->total_usd, 2).' USD'
            : number_format((float) $invoice->total_syp, 0).' ل.س';
    }
}
