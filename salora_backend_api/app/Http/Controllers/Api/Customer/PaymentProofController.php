<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Booking;
use App\Models\PaymentProof;
use App\Services\ActivityLogger;
use App\Services\BookingWorkflowService;
use App\Services\InvoiceService;
use App\Services\NotificationService;
use App\Support\SaloraStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentProofController extends BaseApiController
{
    public function store(
        Request $request,
        Booking $booking,
        InvoiceService $invoices,
        BookingWorkflowService $workflow
    ) {
        abort_unless((int)$booking->customer_id === (int)$request->user()->id, 403);

        if (!in_array($booking->booking_status, [
            SaloraStatus::BOOKING_PENDING_PAYMENT,
            SaloraStatus::BOOKING_PENDING_OWNER_REVIEW,
        ], true)) {
            return $this->fail('يمكن رفع إثبات الدفع فقط للحجز الذي ينتظر الدفع.', 422);
        }
        if (!in_array($booking->payment_status, [SaloraStatus::PAYMENT_UNPAID, SaloraStatus::PAYMENT_REJECTED], true)) {
            return $this->fail('A new payment proof is not allowed for the current payment state.', 422);
        }

        $data = $request->validate([
            'image' => 'required|image|mimes:jpeg,jpg,png,webp|max:4096',
            'amount_syp' => 'nullable|numeric|min:0',
            'amount_usd' => 'nullable|numeric|min:0',
            'payment_method' => 'required|string|max:80',
        ]);

        $proof = DB::transaction(function () use ($request, $booking, $data, $invoices, $workflow) {
            $lockedBooking = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            if (!in_array($lockedBooking->booking_status, [
                SaloraStatus::BOOKING_PENDING_PAYMENT,
                SaloraStatus::BOOKING_PENDING_OWNER_REVIEW,
            ], true)) {
                abort(422, 'Booking is no longer waiting for payment.');
            }
            if ($lockedBooking->booking_status === SaloraStatus::BOOKING_PENDING_OWNER_REVIEW) {
                $workflow->transition(
                    $lockedBooking,
                    SaloraStatus::BOOKING_PENDING_PAYMENT,
                    $request->user(),
                    'Preliminary owner approval was removed; booking moved directly to payment.'
                );
                $lockedBooking = $lockedBooking->fresh();
            }

            $invoice = $invoices->createForBooking($lockedBooking);
            $path = $request->file('image')->store('payment-proofs', 'local');
            $proof = PaymentProof::create([
                'booking_id' => $lockedBooking->id,
                'invoice_id' => $invoice->id,
                'customer_id' => $request->user()->id,
                'owner_id' => $lockedBooking->owner_id,
                'image_url' => $path,
                // The payable amount is server-owned. Client values are accepted only for
                // backward compatibility and must never override the issued invoice.
                'amount_syp' => $lockedBooking->total_syp,
                'amount_usd' => $lockedBooking->total_usd,
                'payment_method' => $data['payment_method'],
                'status' => 'pending',
                'uploaded_at' => now(),
            ]);

            $invoices->registerProof($invoice, $proof);
            $lockedBooking->update(['payment_status' => SaloraStatus::PAYMENT_PROOF_UPLOADED]);
            $workflow->transition(
                $lockedBooking,
                SaloraStatus::BOOKING_PAYMENT_UNDER_REVIEW,
                $request->user(),
                'Payment proof uploaded.',
                ['expires_at' => null]
            );

            NotificationService::send(
                $lockedBooking->owner_id,
                'تم رفع إثبات دفع',
                'تم رفع إثبات دفع للحجز '.$lockedBooking->booking_number.' وهو بانتظار مراجعتك.',
                'payment_proof',
                ['booking_id' => $lockedBooking->id, 'payment_proof_id' => $proof->id]
            );
            ActivityLogger::log('uploaded_payment_proof', 'payment_proof', $proof->id, 'Customer uploaded a private payment proof.');
            return $proof;
        });

        return $this->ok($proof->load(['invoice', 'transaction']), 'Payment proof uploaded.', 201);
    }
}
