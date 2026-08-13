<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Invoice;
use App\Services\PaymentWorkflowService;
use Illuminate\Http\Request;

class InvoicePaymentController extends BaseApiController
{
    public function index(Request $request)
    {
        return $this->ok(
            Invoice::with([
                'booking.venue',
                'providerServiceRequest.service',
                'providerServiceRequest.provider:id,name,phone,avatar',
                'payee:id,name,phone,avatar',
                'latestPaymentProof.method',
                'latestPaymentProof.payoutAccount',
                'refunds',
            ])
                ->where('customer_id', $request->user()->id)
                ->latest()
                ->get(),
        );
    }

    public function show(
        Request $request,
        Invoice $invoice,
        PaymentWorkflowService $payments,
    ) {
        abort_unless(
            (int) $invoice->customer_id === (int) $request->user()->id,
            403,
        );

        $invoice->load([
            'booking.venue',
            'booking.eventType',
            'providerServiceRequest.service',
            'providerServiceRequest.provider:id,name,phone,avatar',
            'payee:id,name,phone,avatar',
            'acceptedBy:id,name',
            'latestPaymentProof.method',
            'latestPaymentProof.payoutAccount',
            'latestPaymentProof.transaction',
            'refunds',
        ]);

        return $this->ok([
            'invoice' => $invoice,
            'payment_options' => $payments->canAcceptPayment($invoice)
                ? $payments->paymentOptions($invoice)
                : [],
        ]);
    }

    public function uploadProof(
        Request $request,
        Invoice $invoice,
        PaymentWorkflowService $payments,
    ) {
        abort_unless(
            (int) $invoice->customer_id === (int) $request->user()->id,
            403,
        );

        $data = $request->validate([
            'payment_method_id' => 'required|exists:payment_methods,id',
            'payout_account_id' => 'required|exists:payout_accounts,id',
            'sender_name' => 'required|string|max:160',
            'transaction_reference' => 'nullable|string|max:190',
            'transferred_at' => 'nullable|date',
            'customer_notes' => 'nullable|string|max:1500',
            'image' => 'required|image|mimes:jpeg,jpg,png,webp|max:5120',
        ]);

        $proof = $payments->submitProof(
            $request->user(),
            $invoice,
            $request->file('image'),
            $data,
        );

        return $this->ok(
            $proof,
            'تم رفع إيصال الدفع وهو بانتظار مراجعة صاحب المبلغ.',
            201,
        );
    }


}
