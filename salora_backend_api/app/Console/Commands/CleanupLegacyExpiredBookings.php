<?php

namespace App\Console\Commands;

use App\Models\Booking;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CleanupLegacyExpiredBookings extends Command
{
    protected $signature = 'salora:cleanup-legacy-expired-bookings {--apply : Delete safe legacy expired bookings instead of previewing them}';

    protected $description = 'Preview or delete legacy expired bookings created by the retired payment/review timeout flow.';

    public function handle(): int
    {
        if (!Schema::hasTable('bookings')) {
            $this->warn('The bookings table does not exist.');
            return self::SUCCESS;
        }

        $apply = (bool) $this->option('apply');
        $candidates = Booking::query()
            ->where('booking_status', 'expired')
            ->orderBy('id')
            ->get();

        if ($candidates->isEmpty()) {
            $this->info('No legacy expired bookings were found.');
            return self::SUCCESS;
        }

        $safeIds = [];
        $skipped = [];

        foreach ($candidates as $booking) {
            $reasons = $this->financialReasons((int) $booking->id);
            if ($reasons === []) {
                $safeIds[] = (int) $booking->id;
            } else {
                $skipped[] = [
                    'id' => (int) $booking->id,
                    'reason' => implode(', ', $reasons),
                ];
            }
        }

        $this->line('Legacy expired bookings found: '.$candidates->count());
        $this->line('Safe to clean: '.count($safeIds));
        $this->line('Protected because payment/financial history exists: '.count($skipped));

        if ($safeIds !== []) {
            $this->line('Safe IDs: '.implode(', ', $safeIds));
        }

        if ($skipped !== []) {
            $this->newLine();
            $this->warn('Protected rows were NOT selected for deletion:');
            foreach ($skipped as $row) {
                $this->line('#'.$row['id'].' - '.$row['reason']);
            }
        }

        if (!$apply) {
            $this->newLine();
            $this->info('Preview only. Run with --apply after reviewing the IDs.');
            return self::SUCCESS;
        }

        if ($safeIds === []) {
            $this->info('Nothing safe to delete.');
            return self::SUCCESS;
        }

        $deleted = 0;
        foreach ($safeIds as $bookingId) {
            DB::transaction(function () use ($bookingId, &$deleted): void {
                // Re-check inside the transaction. If any payment/financial evidence appeared,
                // leave the booking untouched.
                if ($this->financialReasons($bookingId) !== []) {
                    return;
                }

                if (Schema::hasTable('salora_booking_change_holds')) {
                    DB::table('salora_booking_change_holds')->where('booking_id', $bookingId)->delete();
                }

                if (Schema::hasTable('salora_booking_financial_events')) {
                    DB::table('salora_booking_financial_events')->where('booking_id', $bookingId)->delete();
                }

                if (Schema::hasTable('platform_commissions')) {
                    DB::table('platform_commissions')
                        ->where('source_type', 'booking')
                        ->where('source_id', $bookingId)
                        ->where(function ($query): void {
                            $query->whereNull('collected_syp')->orWhere('collected_syp', '<=', 0);
                        })
                        ->whereNotIn('status', ['collected', 'settled'])
                        ->delete();
                }

                if (Schema::hasTable('salora_booking_commissions')) {
                    DB::table('salora_booking_commissions')
                        ->where('booking_id', $bookingId)
                        ->where('collected_syp', '<=', 0)
                        ->whereNotIn('status', ['collected', 'settled'])
                        ->delete();
                }

                $deleted += Booking::query()
                    ->whereKey($bookingId)
                    ->where('booking_status', 'expired')
                    ->delete();
            });
        }

        $this->newLine();
        $this->info('Deleted '.$deleted.' safe legacy expired booking(s). Their time slots are now free.');

        return self::SUCCESS;
    }

    /** @return array<int, string> */
    private function financialReasons(int $bookingId): array
    {
        $reasons = [];

        if (Schema::hasTable('payment_proofs') && DB::table('payment_proofs')->where('booking_id', $bookingId)->exists()) {
            $reasons[] = 'payment proof exists';
        }

        $invoiceIds = [];
        if (Schema::hasTable('invoices')) {
            $invoiceRows = DB::table('invoices')->where('booking_id', $bookingId)->get(['id', 'status', 'paid_at']);
            $invoiceIds = $invoiceRows->pluck('id')->map(fn ($id) => (int) $id)->all();
            foreach ($invoiceRows as $invoice) {
                if (in_array(strtolower((string) $invoice->status), ['paid', 'proof_uploaded'], true) || !empty($invoice->paid_at)) {
                    $reasons[] = 'paid/proof invoice exists';
                    break;
                }
            }
        }

        if ($invoiceIds !== [] && Schema::hasTable('payment_transactions')) {
            if (DB::table('payment_transactions')->whereIn('invoice_id', $invoiceIds)->exists()) {
                $reasons[] = 'payment transaction exists';
            }
        }

        if (Schema::hasTable('salora_booking_payment_adjustments') && DB::table('salora_booking_payment_adjustments')->where('booking_id', $bookingId)->exists()) {
            $reasons[] = 'booking payment adjustment exists';
        }

        if (Schema::hasTable('platform_commissions')) {
            $commission = DB::table('platform_commissions')
                ->where('source_type', 'booking')
                ->where('source_id', $bookingId)
                ->first(['status', 'collected_syp', 'collected_usd']);
            if ($commission && (
                in_array(strtolower((string) $commission->status), ['collected', 'settled'], true)
                || (float) ($commission->collected_syp ?? 0) > 0
                || (float) ($commission->collected_usd ?? 0) > 0
            )) {
                $reasons[] = 'collected platform commission exists';
            }
        }

        if (Schema::hasTable('salora_booking_commissions')) {
            $commission = DB::table('salora_booking_commissions')
                ->where('booking_id', $bookingId)
                ->first(['status', 'collected_syp']);
            if ($commission && (
                in_array(strtolower((string) $commission->status), ['collected', 'settled'], true)
                || (float) ($commission->collected_syp ?? 0) > 0
            )) {
                $reasons[] = 'collected Salora commission exists';
            }
        }

        return array_values(array_unique($reasons));
    }
}
