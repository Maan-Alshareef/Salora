<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\PaymentRefund;
use App\Models\User;
use App\Support\SaloraStatus;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RefundWorkflowService
{
    public function requestByCustomer(User $customer, Invoice $invoice, string $reason): PaymentRefund
    {
        return DB::transaction(function () use ($customer, $invoice, $reason) {
            $locked = Invoice::with('booking')->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $locked->customer_id === (int) $customer->id, 403);
            if ($locked->status !== 'paid') {
                throw ValidationException::withMessages(['invoice' => ['لا يمكن طلب استرداد لفاتورة غير مدفوعة.']]);
            }
            if (PaymentRefund::where('invoice_id', $locked->id)->whereNotIn('status', ['rejected', 'resolved', 'confirmed'])->exists()) {
                throw ValidationException::withMessages(['invoice' => ['يوجد طلب استرداد فعال لهذه الفاتورة.']]);
            }

            $percent = $this->customerPercent($locked);
            $refund = $this->create($locked, 'customer', $reason, $percent);
            $locked->booking?->update([
                'booking_status' => $percent > 0 ? SaloraStatus::BOOKING_CANCELLATION_REQUESTED : SaloraStatus::BOOKING_CANCELLED,
                'cancellation_status' => $percent > 0 ? 'waiting_refund' : 'cancelled',
                'refund_percentage' => $percent,
                'refunded_syp' => $refund->amount_syp,
                'edit_locked_at' => now(),
                'cancelled_at' => $percent > 0 ? null : now(),
            ]);
            NotificationService::send(
                (int) $locked->payee_id,
                'إلغاء حجز واسترداد مستحق',
                'ألغى العميل الحجز. الاسترداد المستحق '.number_format($percent, 0).'% ولا يمكن رفض حق العميل بالإلغاء.',
                'refund_requested',
                ['refund_id' => $refund->id, 'invoice_id' => $locked->id, 'booking_id' => $locked->booking_id]
            );
            return $refund->fresh(['invoice', 'booking.venue', 'customer', 'payee']);
        });
    }

    public function requestByPayee(User $payee, Invoice $invoice, string $reason): PaymentRefund
    {
        return DB::transaction(function () use ($payee, $invoice, $reason) {
            $locked = Invoice::with('booking')->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $locked->payee_id === (int) $payee->id, 403);
            if ($locked->status !== 'paid') {
                throw ValidationException::withMessages(['invoice' => ['لا يوجد مبلغ مدفوع لاسترداده.']]);
            }
            $refund = $this->create($locked, $payee->role, $reason, 100);
            $locked->booking?->update([
                'booking_status' => SaloraStatus::BOOKING_CANCELLATION_REQUESTED,
                'cancellation_status' => 'waiting_refund',
                'refund_percentage' => 100,
                'refunded_syp' => $refund->amount_syp,
                'edit_locked_at' => now(),
            ]);
            NotificationService::send(
                (int) $locked->customer_id,
                'ألغت الصالة أو مقدم الخدمة الطلب',
                'يحق لك استرداد كامل المبلغ. السبب: '.$reason,
                'refund_requested',
                ['refund_id' => $refund->id, 'invoice_id' => $locked->id]
            );
            return $refund->fresh(['invoice', 'booking.venue', 'customer', 'payee']);
        });
    }

    public function uploadTransfer(User $payee, PaymentRefund $refund, UploadedFile $proof, array $data): PaymentRefund
    {
        abort_unless((int) $refund->payee_id === (int) $payee->id, 403);
        if (!in_array($refund->status, ['pending_transfer', 'overdue'], true)) {
            throw ValidationException::withMessages(['refund' => ['حالة الاسترداد لا تسمح برفع إثبات.']]);
        }
        $path = $proof->store('refund-proofs/'.now()->format('Y/m'), 'local');
        $refund->update([
            'status' => 'transferred',
            'payment_method_id' => $data['payment_method_id'],
            'transaction_reference' => $data['transaction_reference'] ?? null,
            'proof_path' => $path,
            'transferred_at' => $data['transferred_at'] ?? now(),
        ]);
        NotificationService::send((int) $refund->customer_id, 'تم تحويل مبلغ الاسترداد', 'راجع إثبات الاسترداد.', 'refund_transferred', ['refund_id' => $refund->id]);
        return $refund->fresh(['invoice', 'method', 'customer', 'payee']);
    }

    public function confirm(User $customer, PaymentRefund $refund): PaymentRefund
    {
        abort_unless((int) $refund->customer_id === (int) $customer->id, 403);
        if ($refund->status !== 'transferred') {
            throw ValidationException::withMessages(['refund' => ['لا يوجد تحويل بانتظار التأكيد.']]);
        }
        $refund->update(['status' => 'confirmed', 'customer_confirmed_at' => now()]);
        $refund->invoice?->update(['status' => 'refunded']);
        $refund->booking?->update([
            'payment_status' => SaloraStatus::PAYMENT_REFUNDED,
            'booking_status' => SaloraStatus::BOOKING_CANCELLED,
            'cancellation_status' => 'cancelled',
            'refund_confirmed_at' => now(),
            'cancelled_at' => now(),
        ]);
        NotificationService::send((int) $refund->payee_id, 'تم تأكيد استلام الاسترداد', 'أكد العميل استلام مبلغ الاسترداد.', 'refund_confirmed', ['refund_id' => $refund->id]);
        return $refund->fresh(['invoice', 'customer', 'payee']);
    }

    public function dispute(User $customer, PaymentRefund $refund, string $reason): PaymentRefund
    {
        abort_unless((int) $refund->customer_id === (int) $customer->id, 403);
        if (!in_array($refund->status, ['transferred', 'overdue'], true)) {
            throw ValidationException::withMessages(['refund' => ['لا يمكن فتح نزاع بهذه الحالة.']]);
        }
        $refund->update(['status' => 'disputed', 'disputed_at' => now(), 'resolution_notes' => $reason]);
        foreach (User::where('role', 'admin')->where('status', 'active')->pluck('id') as $adminId) {
            NotificationService::send((int) $adminId, 'نزاع استرداد جديد', 'فتح العميل نزاعاً على الاسترداد رقم '.$refund->id.'.', 'refund_disputed', ['refund_id' => $refund->id]);
        }
        return $refund->fresh(['invoice', 'customer', 'payee']);
    }

    private function create(Invoice $invoice, string $role, string $reason, float $percent): PaymentRefund
    {
        return PaymentRefund::create([
            'invoice_id' => $invoice->id,
            'booking_id' => $invoice->booking_id,
            'customer_id' => $invoice->customer_id,
            'payee_id' => $invoice->payee_id,
            'requested_by_role' => $role,
            'reason' => $reason,
            'refund_percent' => $percent,
            'amount_syp' => round((float) $invoice->total_syp * $percent / 100, 2),
            'amount_usd' => round((float) $invoice->total_usd * $percent / 100, 2),
            'status' => $percent > 0 ? 'pending_transfer' : 'no_refund',
            'due_at' => $percent > 0 ? now()->addHours((int) config('salora_payments.refund_deadline_hours', 48)) : null,
        ]);
    }

    private function customerPercent(Invoice $invoice): float
    {
        $booking = $invoice->booking;
        if (!$booking) return 100;
        $event = Carbon::parse($booking->event_date->format('Y-m-d').' '.$booking->start_time);
        $hours = now()->diffInHours($event, false);
        if ($hours > 168) return 100;
        if ($hours > 120) return 50;
        return 0;
    }
}
