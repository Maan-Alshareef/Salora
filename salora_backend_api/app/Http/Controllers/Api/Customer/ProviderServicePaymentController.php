<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\PaymentMethod;
use App\Models\PayoutAccount;
use App\Models\ProviderServiceRequest;
use App\Services\InvoiceService;
use App\Services\PaymentWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ProviderServicePaymentController extends BaseApiController
{

    public function invoice(
        Request $request,
        ProviderServiceRequest $providerRequest,
        InvoiceService $invoices,
    ) {
        abort_unless(
            (int) $providerRequest->customer_id === (int) $request->user()->id,
            403,
        );

        if ($providerRequest->status !== 'accepted') {
            return $this->fail(
                'يجب أن يقبل مقدم الخدمة الطلب أولاً قبل تجهيز فاتورة الدفع.',
                422,
            );
        }

        $invoice = $invoices->createForProviderRequest($providerRequest->fresh());
        $invoice->load([
            'providerServiceRequest.service',
            'providerServiceRequest.provider:id,name,phone,avatar',
            'payee:id,name,phone,avatar',
            'latestPaymentProof.method',
            'latestPaymentProof.payoutAccount',
            'latestPaymentProof.transaction',
        ]);

        return $this->ok($invoice, 'فاتورة الخدمة جاهزة.');
    }

    public function store(
        Request $request,
        ProviderServiceRequest $providerRequest,
        InvoiceService $invoices,
        PaymentWorkflowService $payments,
    ) {
        abort_unless(
            (int) $providerRequest->customer_id === (int) $request->user()->id,
            403,
        );

        if ($providerRequest->status !== 'accepted') {
            return $this->fail(
                'يجب أن يقبل مقدم الخدمة الطلب أولاً قبل رفع إيصال الدفع.',
                422,
            );
        }

        if (
            !in_array(
                $providerRequest->payment_status,
                ['unpaid', 'rejected'],
                true,
            )
        ) {
            return $this->fail(
                'لا يمكن رفع إثبات جديد في الحالة الحالية.',
                422,
            );
        }

        $data = $request->validate([
            'image' => 'required|image|mimes:jpeg,jpg,png,webp|max:5120',
            'payment_method_id' => 'nullable|exists:payment_methods,id',
            'payout_account_id' => 'nullable|exists:payout_accounts,id',
            'payment_method' => 'nullable|string|max:80',
            'sender_name' => 'nullable|string|max:160',
            'transaction_reference' => 'nullable|string|max:190',
            'transferred_at' => 'nullable|date',
            'customer_notes' => 'nullable|string|max:1500',
        ]);

        $invoice = $invoices->createForProviderRequest(
            $providerRequest->fresh(),
        );

        $method = isset($data['payment_method_id'])
            ? PaymentMethod::query()->find($data['payment_method_id'])
            : PaymentMethod::query()
                ->where('slug', $data['payment_method'] ?? 'sham_cash')
                ->where('is_active', true)
                ->first();

        if (!$method) {
            throw ValidationException::withMessages([
                'payment_method_id' => ['وسيلة الدفع غير متاحة.'],
            ]);
        }

        $account = isset($data['payout_account_id'])
            ? PayoutAccount::query()
                ->whereKey($data['payout_account_id'])
                ->where('user_id', $providerRequest->provider_id)
                ->where('payment_method_id', $method->id)
                ->where('is_active', true)
                ->first()
            : PayoutAccount::query()
                ->where('user_id', $providerRequest->provider_id)
                ->where('payment_method_id', $method->id)
                ->where('is_active', true)
                ->orderByDesc('is_default')
                ->first();

        if (!$account) {
            throw ValidationException::withMessages([
                'payout_account_id' => [
                    'لم يضف مقدم الخدمة حساب استلام فعال لهذه الوسيلة.',
                ],
            ]);
        }

        $proof = $payments->submitProof(
            $request->user(),
            $invoice,
            $request->file('image'),
            [
                'payment_method_id' => $method->id,
                'payout_account_id' => $account->id,
                'sender_name' => trim(
                    (string) (
                        $data['sender_name'] ?: $request->user()->name
                    ),
                ),
                'transaction_reference' =>
                    $data['transaction_reference'] ?? null,
                'transferred_at' => $data['transferred_at'] ?? now(),
                'customer_notes' => $data['customer_notes'] ?? null,
            ],
        );

        return $this->ok(
            $proof,
            'تم رفع إيصال دفع الخدمة وهو بانتظار مقدم الخدمة.',
            201,
        );
    }
}
