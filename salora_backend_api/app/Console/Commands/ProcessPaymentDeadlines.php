<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\ProviderServiceRequest;
use App\Models\User;
use App\Services\NotificationService;
use App\Support\SaloraStatus;
use Illuminate\Console\Command;

class ProcessPaymentDeadlines extends Command
{
    protected $signature = 'salora:process-payment-deadlines';

    protected $description =
        'Processes six-hour payment deadlines and twelve-hour proof reviews.';

    public function handle(): int
    {
        $now = now();
        $paymentReminderHours = max(
            1,
            (int) config('salora_payments.payment_reminder_hours', 2),
        );
        $reviewReminderHours = max(
            1,
            (int) config('salora_payments.review_reminder_hours', 2),
        );

        $paymentReminders = Invoice::query()
            ->where('status', 'unpaid')
            ->whereNull('payment_reminder_sent_at')
            ->whereBetween('payment_deadline_at', [
                $now->copy()->addMinutes(30),
                $now->copy()->addHours($paymentReminderHours),
            ])
            ->get();

        foreach ($paymentReminders as $invoice) {
            NotificationService::send(
                (int) $invoice->customer_id,
                'اقترب انتهاء مهلة الدفع',
                'أكمل دفع الفاتورة '.$invoice->invoice_number.
                    ' قبل انتهاء مهلة الست ساعات.',
                'payment_deadline_reminder',
                [
                    'invoice_id' => $invoice->id,
                    'booking_id' => $invoice->booking_id,
                    'source_type' => $invoice->source_type,
                    'target_route' => 'booking_details',
                ],
            );
            $invoice->update(['payment_reminder_sent_at' => $now]);
        }

        $expired = Invoice::with(['booking', 'providerServiceRequest'])
            ->where('status', 'unpaid')
            ->whereNotNull('payment_deadline_at')
            ->where('payment_deadline_at', '<=', $now)
            ->get();

        foreach ($expired as $invoice) {
            $invoice->update(['status' => 'expired']);

            if ($invoice->source_type === 'venue_booking' && $invoice->booking) {
                $invoice->booking->update([
                    'booking_status' => SaloraStatus::BOOKING_EXPIRED,
                    'payment_status' => 'expired',
                    'expires_at' => null,
                ]);
            }

            if (
                $invoice->source_type === 'provider_service' &&
                $invoice->providerServiceRequest
            ) {
                $invoice->providerServiceRequest->update([
                    'payment_status' => 'expired',
                    'payment_deadline_at' => null,
                ]);
            }

            NotificationService::send(
                (int) $invoice->customer_id,
                'انتهت مهلة الدفع',
                $invoice->source_type === 'provider_service'
                    ? 'انتهت مطالبة دفع الخدمة لعدم رفع الإثبات خلال ست ساعات.'
                    : 'تم تحرير موعد الصالة لعدم رفع إثبات الدفع خلال ست ساعات.',
                'payment_deadline_expired',
                [
                    'invoice_id' => $invoice->id,
                    'booking_id' => $invoice->booking_id,
                    'request_id' => $invoice->source_type === 'provider_service'
                        ? $invoice->source_id
                        : null,
                    'source_type' => $invoice->source_type,
                    'target_route' => 'booking_details',
                ],
            );
        }

        $reviewReminders = Invoice::query()
            ->where('status', 'proof_uploaded')
            ->whereNull('review_reminder_sent_at')
            ->whereNotNull('review_deadline_at')
            ->whereBetween('review_deadline_at', [
                $now->copy()->addMinutes(30),
                $now->copy()->addHours($reviewReminderHours),
            ])
            ->get();

        foreach ($reviewReminders as $invoice) {
            NotificationService::send(
                (int) $invoice->payee_id,
                'اقترب انتهاء مهلة مراجعة الدفع',
                'راجع إثبات الفاتورة '.$invoice->invoice_number.
                    ' قبل تجاوز مهلة الاثنتي عشرة ساعة.',
                'payment_review_reminder',
                [
                    'invoice_id' => $invoice->id,
                    'booking_id' => $invoice->booking_id,
                    'request_id' => $invoice->source_type === 'provider_service'
                        ? $invoice->source_id
                        : null,
                    'source_type' => $invoice->source_type,
                    'target_route' => 'business_payments',
                ],
            );
            $invoice->update(['review_reminder_sent_at' => $now]);
        }

        $overdue = Invoice::query()
            ->where('status', 'proof_uploaded')
            ->whereNull('review_overdue_notified_at')
            ->whereNotNull('review_deadline_at')
            ->where('review_deadline_at', '<=', $now)
            ->get();

        $adminIds = User::query()
            ->where('role', 'admin')
            ->where('status', 'active')
            ->pluck('id');

        foreach ($overdue as $invoice) {
            $payload = [
                'invoice_id' => $invoice->id,
                'booking_id' => $invoice->booking_id,
                'request_id' => $invoice->source_type === 'provider_service'
                    ? $invoice->source_id
                    : null,
                'source_type' => $invoice->source_type,
                'target_route' => 'business_payments',
            ];

            NotificationService::send(
                (int) $invoice->payee_id,
                'تأخرت مراجعة إثبات الدفع',
                'تجاوزت الفاتورة '.$invoice->invoice_number.
                    ' مهلة المراجعة. يبقى الموعد محفوظاً حتى اتخاذ القرار.',
                'payment_review_overdue',
                $payload,
            );

            foreach ($adminIds as $adminId) {
                NotificationService::send(
                    (int) $adminId,
                    'تأخر في مراجعة دفعة',
                    'تجاوزت الفاتورة '.$invoice->invoice_number.
                        ' مهلة المراجعة لدى صاحب الحساب.',
                    'payment_review_overdue_admin',
                    $payload,
                );
            }

            $invoice->update(['review_overdue_notified_at' => $now]);
        }

        $this->info('Payment reminders: '.$paymentReminders->count());
        $this->info('Expired unpaid invoices: '.$expired->count());
        $this->info('Review reminders: '.$reviewReminders->count());
        $this->info('Overdue reviews: '.$overdue->count());

        return self::SUCCESS;
    }
}
