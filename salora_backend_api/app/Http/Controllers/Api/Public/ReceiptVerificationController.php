<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Invoice;

class ReceiptVerificationController extends BaseApiController
{
    public function show(string $token)
    {
        $invoice = Invoice::with([
            'booking.venue:id,name_ar,name_en',
            'customer:id,name',
            'payee:id,name,role',
            'acceptedBy:id,name',
            'latestPaymentProof.method:id,slug,name_ar,name_en,logo_path',
        ])->where('verification_token', $token)->where('status', 'paid')->firstOrFail();

        return $this->ok([
            'receipt_number' => $invoice->receipt_number,
            'invoice_number' => $invoice->invoice_number,
            'status' => $invoice->status,
            'currency' => $invoice->currency,
            'exchange_rate_syp_per_usd' => $invoice->exchange_rate_syp_per_usd,
            'total_syp' => $invoice->total_syp,
            'total_usd' => $invoice->total_usd,
            'paid_at' => $invoice->paid_at,
            'accepted_at' => $invoice->accepted_at,
            'customer' => $invoice->customer,
            'payee' => $invoice->payee,
            'booking' => $invoice->booking,
            'payment_method' => $invoice->latestPaymentProof?->method,
        ]);
    }
}
