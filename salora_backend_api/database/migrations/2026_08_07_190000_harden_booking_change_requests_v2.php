<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('booking_change_requests')) {
            $missingRequestedBy = !Schema::hasColumn('booking_change_requests', 'requested_by_user_id');
            $missingOldData = !Schema::hasColumn('booking_change_requests', 'old_data');
            $missingRequestedData = !Schema::hasColumn('booking_change_requests', 'requested_data');
            $missingQuoteSnapshot = !Schema::hasColumn('booking_change_requests', 'quote_snapshot');
            $missingDecidedBy = !Schema::hasColumn('booking_change_requests', 'decided_by_user_id');

            Schema::table('booking_change_requests', function (Blueprint $table) use (
                $missingRequestedBy,
                $missingOldData,
                $missingRequestedData,
                $missingQuoteSnapshot,
                $missingDecidedBy,
            ): void {
                if ($missingRequestedBy) {
                    $table->unsignedBigInteger('requested_by_user_id')->nullable()->after('booking_id');
                }
                if ($missingOldData) {
                    $table->json('old_data')->nullable();
                }
                if ($missingRequestedData) {
                    $table->json('requested_data')->nullable();
                }
                if ($missingQuoteSnapshot) {
                    $table->json('quote_snapshot')->nullable();
                }
                if ($missingDecidedBy) {
                    $table->unsignedBigInteger('decided_by_user_id')->nullable();
                }
            });
        }

        if (!Schema::hasTable('salora_booking_payment_adjustments')) {
            Schema::create('salora_booking_payment_adjustments', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('booking_id');
                $table->unsignedBigInteger('invoice_id')->nullable();
                $table->string('type', 40); // additional_payment | refund_due
                $table->decimal('amount_syp', 18, 2)->default(0);
                $table->decimal('amount_usd', 14, 2)->default(0);
                $table->decimal('old_total_syp', 18, 2)->default(0);
                $table->decimal('new_total_syp', 18, 2)->default(0);
                $table->decimal('paid_syp', 18, 2)->default(0);
                $table->string('status', 40)->default('pending');
                $table->json('metadata')->nullable();
                $table->unsignedBigInteger('resolved_by_user_id')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();
                $table->index(['booking_id', 'created_at'], 'salora_booking_adjustments_booking_idx');
                $table->index(['status', 'type'], 'salora_booking_adjustments_status_idx');
            });
        }
    }

    public function down(): void
    {
        // Deliberately non-destructive. Financial/history data must not be deleted.
    }
};
