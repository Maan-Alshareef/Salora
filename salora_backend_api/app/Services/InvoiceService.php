<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Invoice;
use App\Models\PaymentProof;
use App\Models\PaymentTransaction;
use App\Models\ProviderServiceRequest;
use Illuminate\Support\Str;

class InvoiceService
{
    public function __construct(private readonly ExchangeRateService $exchangeRates) {}

    public function createForBooking(Booking $booking): Invoice
    {
        $invoice = Invoice::query()
            ->where('source_type', 'venue_booking')
            ->where('source_id', $booking->id)
            ->first();

        $invoice ??= Invoice::query()
            ->where('booking_id', $booking->id)
            ->where('source_type', 'venue_booking')
            ->first();

        if ($invoice && $this->isFinancialSnapshotLocked($invoice)) {
            return $invoice->fresh();
        }

        $invoice ??= new Invoice();
        $rate = $this->exchangeRates->resolveSnapshotRate(
            $booking->exchange_rate_syp_per_usd ?? null,
            $booking->total_syp,
            $booking->total_usd,
        );

        $invoice->fill(
            $this->amountsFromSyp(
                (float) $booking->subtotal_syp,
                (float) $booking->discount_syp,
                (float) $booking->total_syp,
                $rate,
            ) + [
                'invoice_number' => $invoice->invoice_number ?: $this->number('REQ'),
                'booking_id' => $booking->id,
                'customer_id' => $booking->customer_id,
                'payee_id' => $booking->owner_id,
                'source_type' => 'venue_booking',
                'source_id' => $booking->id,
                'currency' => $booking->currency ?: 'SYP',
                'exchange_rate_syp_per_usd' => $rate,
                'status' => $invoice->status ?: 'unpaid',
                'due_at' => null,
                'payment_deadline_at' => null,
                'verification_token' => $invoice->verification_token ?: Str::random(48),
            ],
        );

        if (($invoice->status ?? 'unpaid') === 'unpaid') {
            $invoice->review_deadline_at = null;
            $invoice->review_reminder_sent_at = null;
            $invoice->review_overdue_notified_at = null;
        }

        $invoice->save();

        return $invoice->fresh();
    }

    public function createForProviderRequest(ProviderServiceRequest $request): Invoice
    {
        $invoice = $request->invoice_id
            ? Invoice::query()->find($request->invoice_id)
            : null;

        $invoice ??= Invoice::query()
            ->where('source_type', 'provider_service')
            ->where('source_id', $request->id)
            ->first();

        if ($invoice && $this->isFinancialSnapshotLocked($invoice)) {
            $this->syncRequestInvoiceReference($request, $invoice);
            return $invoice->fresh();
        }

        $invoice ??= new Invoice();
        $syp = (float) $request->price_syp;
        $rate = $this->exchangeRates->resolveSnapshotRate(
            $request->exchange_rate_syp_per_usd ?? null,
            $request->price_syp,
            $request->price_usd,
        );
        $usd = $this->exchangeRates->toUsd($syp, $rate);

        $request->forceFill([
            'price_usd' => $usd,
            'exchange_rate_syp_per_usd' => $rate,
        ])->save();

        $invoice->fill(
            $this->amountsFromSyp($syp, 0, $syp, $rate) + [
                'invoice_number' => $invoice->invoice_number ?: $this->number('SRV'),
                'booking_id' => $request->booking_id,
                'customer_id' => $request->customer_id,
                'payee_id' => $request->provider_id,
                'source_type' => 'provider_service',
                'source_id' => $request->id,
                'currency' => 'SYP',
                'exchange_rate_syp_per_usd' => $rate,
                'status' => $invoice->status ?: 'unpaid',
                'due_at' => null,
                'payment_deadline_at' => null,
                'verification_token' => $invoice->verification_token ?: Str::random(48),
            ],
        );

        if (($invoice->status ?? 'unpaid') === 'unpaid') {
            $invoice->review_deadline_at = null;
            $invoice->review_reminder_sent_at = null;
            $invoice->review_overdue_notified_at = null;
        }

        $invoice->save();
        $this->syncRequestInvoiceReference($request, $invoice);

        return $invoice->fresh();
    }

    public function registerProof(Invoice $invoice, PaymentProof $proof): PaymentTransaction
    {
        $amount = $invoice->currency === 'USD'
            ? (float) $invoice->total_usd
            : (float) $invoice->total_syp;

        return PaymentTransaction::query()->firstOrCreate(
            ['payment_proof_id' => $proof->id],
            [
                'invoice_id' => $invoice->id,
                'method' => 'manual_transfer',
                'reference' => $proof->transaction_reference
                    ?: 'TX-'.strtoupper(Str::uuid()->toString()),
                'amount' => $amount,
                'currency' => $invoice->currency,
                'status' => 'pending',
                'metadata' => [
                    'payment_method_id' => $proof->payment_method_id,
                    'payout_account_id' => $proof->payout_account_id,
                ],
            ],
        );
    }

    public function approveProof(PaymentProof $proof, int $reviewerId): Invoice
    {
        $invoice = $proof->invoice;

        $invoice->update([
            'status' => 'paid',
            'paid_at' => now(),
            'accepted_at' => now(),
            'accepted_by' => $reviewerId,
            'review_deadline_at' => null,
            'review_reminder_sent_at' => null,
            'review_overdue_notified_at' => null,
            'receipt_number' => $invoice->receipt_number ?: $this->number('SAL'),
            'verification_token' => $invoice->verification_token ?: Str::random(48),
        ]);

        $proof->transaction?->update([
            'status' => 'paid',
            'processed_at' => now(),
        ]);

        return $invoice->fresh([
            'customer',
            'payee',
            'acceptedBy',
            'latestPaymentProof.method',
            'latestPaymentProof.payoutAccount',
            'booking.venue',
            'providerServiceRequest.service',
            'providerServiceRequest.provider',
        ]);
    }

    public function rejectProof(PaymentProof $proof, ?string $reason = null): void
    {
        $proof->invoice?->update([
            'status' => 'unpaid',
            'paid_at' => null,
            'accepted_at' => null,
            'accepted_by' => null,
            'review_deadline_at' => null,
            'review_reminder_sent_at' => null,
            'review_overdue_notified_at' => null,
        ]);

        $proof->transaction?->update([
            'status' => 'failed',
            'processed_at' => now(),
            'metadata' => [
                ...($proof->transaction?->metadata ?? []),
                'rejection_reason' => $reason,
            ],
        ]);
    }

    private function amountsFromSyp(
        float $subtotalSyp,
        float $discountSyp,
        float $totalSyp,
        float $exchangeRate,
    ): array {
        $commissionRate = (float) config('salora_payments.commission_percent', 10) / 100;
        $commissionSyp = round($totalSyp * $commissionRate, 2);
        $netSyp = max(0, round($totalSyp - $commissionSyp, 2));

        return [
            'subtotal_syp' => $subtotalSyp,
            'subtotal_usd' => $this->exchangeRates->toUsd($subtotalSyp, $exchangeRate),
            'discount_syp' => $discountSyp,
            'discount_usd' => $this->exchangeRates->toUsd($discountSyp, $exchangeRate),
            'total_syp' => $totalSyp,
            'total_usd' => $this->exchangeRates->toUsd($totalSyp, $exchangeRate),
            'commission_syp' => $commissionSyp,
            'commission_usd' => $this->exchangeRates->toUsd($commissionSyp, $exchangeRate),
            'net_syp' => $netSyp,
            'net_usd' => $this->exchangeRates->toUsd($netSyp, $exchangeRate),
        ];
    }

    private function syncRequestInvoiceReference(ProviderServiceRequest $request, Invoice $invoice): void
    {
        $invoiceStatus = strtolower((string) $invoice->status);
        $requestPaymentStatus = strtolower((string) ($request->payment_status ?? 'unpaid'));
        $resolvedPaymentStatus = match ($invoiceStatus) {
            'paid' => 'approved',
            'proof_uploaded' => 'proof_uploaded',
            default => $requestPaymentStatus === 'rejected' ? 'rejected' : 'unpaid',
        };

        $request->forceFill([
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'payment_status' => $resolvedPaymentStatus,
            'payment_deadline_at' => null,
        ])->save();
    }

    private function isFinancialSnapshotLocked(Invoice $invoice): bool
    {
        return in_array(strtolower((string) $invoice->status), ['proof_uploaded', 'paid', 'refund_pending', 'refunded', 'cancelled'], true);
    }

    private function number(string $prefix): string
    {
        return $prefix.'-'.now()->format('Y').'-'.
            str_pad((string) random_int(1, 99999999), 8, '0', STR_PAD_LEFT);
    }
}
