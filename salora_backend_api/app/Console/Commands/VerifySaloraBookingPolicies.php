<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

class VerifySaloraBookingPolicies extends Command
{
    protected $signature = 'salora:verify-booking-policies {--expect-offers-empty}';
    protected $description = 'Verify final booking, refund, offer and no-deadline integration.';

    public function handle(): int
    {
        $checks = [
            'payment_deadlines_disabled' => !array_key_exists('payment_deadline_hours', config('salora_payments')),
            'review_deadlines_disabled' => !array_key_exists('review_deadline_hours', config('salora_payments')),
            'refund_full_before_7_days' => (int) config('salora_payments.customer_refund.full_before_days') === 7,
            'refund_half_from_5_days' => (int) config('salora_payments.customer_refund.half_from_days') === 5,
            'refund_zero_under_120h' => (int) config('salora_payments.customer_refund.zero_under_hours') === 120,
            'old_offers_empty' => !Schema::hasTable('offers') || DB::table('offers')->count() === 0,
            'venue_offers_table' => Schema::hasTable('venue_offers'),
            'payment_refunds_table' => Schema::hasTable('payment_refunds'),
            'invoice_review_deadline_columns' => Schema::hasTable('invoices')
                && Schema::hasColumn('invoices', 'review_deadline_at')
                && Schema::hasColumn('invoices', 'review_reminder_sent_at')
                && Schema::hasColumn('invoices', 'review_overdue_notified_at'),
            'admin_refund_route' => collect(Route::getRoutes())->contains(
                fn ($route) => $route->uri() === 'api/admin/payment-refunds'
            ),
            'invoice_source_identity' => $this->invoiceSourceIdentityIsSafe(),
        ];

        if ($this->option('expect-offers-empty')) {
            $checks['new_offers_empty_after_cleanup'] =
                !Schema::hasTable('venue_offers')
                || DB::table('venue_offers')->count() === 0;
        }

        foreach ($checks as $name => $passed) {
            $this->line(($passed ? '[OK] ' : '[FAIL] ').$name);
        }

        return in_array(false, $checks, true)
            ? self::FAILURE
            : self::SUCCESS;
    }

    private function invoiceSourceIdentityIsSafe(): bool
    {
        if (!Schema::hasTable('invoices')) {
            return false;
        }

        if (DB::connection()->getDriverName() !== 'sqlite') {
            return Schema::hasColumn('invoices', 'source_type')
                && Schema::hasColumn('invoices', 'source_id');
        }

        $hasSourceUnique = false;
        $hasBookingUnique = false;

        foreach (DB::select("PRAGMA index_list('invoices')") as $index) {
            $name = $index->name ?? null;
            if (!$name || !(bool) ($index->unique ?? false)) {
                continue;
            }

            $columns = array_values(array_filter(array_map(
                fn ($column) => $column->name ?? null,
                DB::select("PRAGMA index_info('$name')")
            )));

            if ($columns === ['source_type', 'source_id']) {
                $hasSourceUnique = true;
            }

            if ($columns === ['booking_id']) {
                $hasBookingUnique = true;
            }
        }

        return $hasSourceUnique && !$hasBookingUnique;
    }
}
