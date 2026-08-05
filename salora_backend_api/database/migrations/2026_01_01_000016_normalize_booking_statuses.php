<?php

use App\Support\SaloraStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('bookings')) {
            return;
        }

        // Normalize legacy status names before repairing missing values.
        DB::table('bookings')
            ->whereIn('booking_status', ['owner_approved', 'approved_by_owner'])
            ->update(['booking_status' => SaloraStatus::BOOKING_PENDING_PAYMENT]);

        DB::table('bookings')
            ->whereIn('booking_status', ['pending', 'owner_review'])
            ->update(['booking_status' => SaloraStatus::BOOKING_PENDING_OWNER_REVIEW]);

        DB::table('bookings')
            ->whereIn('booking_status', ['pending_admin_verification', 'payment_review'])
            ->update(['booking_status' => SaloraStatus::BOOKING_PAYMENT_UNDER_REVIEW]);

        DB::table('bookings')
            ->where('booking_status', 'rejected')
            ->update(['booking_status' => SaloraStatus::BOOKING_OWNER_REJECTED]);

        DB::table('bookings')
            ->where('booking_status', 'canceled')
            ->update(['booking_status' => SaloraStatus::BOOKING_CANCELLED]);

        // Infer the safest workflow state for legacy rows that have no booking status.
        DB::table('bookings')
            ->where(fn ($query) => $this->whereMissing($query, 'booking_status'))
            ->whereNotNull('rejection_reason')
            ->whereRaw("TRIM(rejection_reason) <> ''")
            ->update(['booking_status' => SaloraStatus::BOOKING_OWNER_REJECTED]);

        DB::table('bookings')
            ->where(fn ($query) => $this->whereMissing($query, 'booking_status'))
            ->where('payment_status', SaloraStatus::PAYMENT_APPROVED)
            ->update(['booking_status' => SaloraStatus::BOOKING_CONFIRMED]);

        DB::table('bookings')
            ->where(fn ($query) => $this->whereMissing($query, 'booking_status'))
            ->where('payment_status', SaloraStatus::PAYMENT_PROOF_UPLOADED)
            ->update(['booking_status' => SaloraStatus::BOOKING_PAYMENT_UNDER_REVIEW]);

        DB::table('bookings')
            ->where(fn ($query) => $this->whereMissing($query, 'booking_status'))
            ->whereNotNull('owner_decision_at')
            ->update(['booking_status' => SaloraStatus::BOOKING_PENDING_PAYMENT]);

        DB::table('bookings')
            ->where(fn ($query) => $this->whereMissing($query, 'booking_status'))
            ->update(['booking_status' => SaloraStatus::BOOKING_PENDING_OWNER_REVIEW]);

        // Repair missing payment status after booking status has been normalized.
        DB::table('bookings')
            ->where(fn ($query) => $this->whereMissing($query, 'payment_status'))
            ->whereIn('booking_status', [
                SaloraStatus::BOOKING_CONFIRMED,
                SaloraStatus::BOOKING_COMPLETED,
            ])
            ->update(['payment_status' => SaloraStatus::PAYMENT_APPROVED]);

        DB::table('bookings')
            ->where(fn ($query) => $this->whereMissing($query, 'payment_status'))
            ->where('booking_status', SaloraStatus::BOOKING_PAYMENT_UNDER_REVIEW)
            ->update(['payment_status' => SaloraStatus::PAYMENT_PROOF_UPLOADED]);

        DB::table('bookings')
            ->where(fn ($query) => $this->whereMissing($query, 'payment_status'))
            ->update(['payment_status' => SaloraStatus::PAYMENT_UNPAID]);
    }

    public function down(): void
    {
        // Data normalization is intentionally irreversible.
    }

    private function whereMissing(Builder $query, string $column): void
    {
        $query->whereNull($column)
            ->orWhereRaw("TRIM(COALESCE({$column}, '')) = ''");
    }
};
