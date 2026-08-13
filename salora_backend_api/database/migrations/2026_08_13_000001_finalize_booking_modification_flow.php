<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('booking_change_requests')) {
            Schema::table('booking_change_requests', function (Blueprint $table): void {
                if (!Schema::hasColumn('booking_change_requests', 'owner_approved_at')) {
                    $table->timestamp('owner_approved_at')->nullable();
                }
                if (!Schema::hasColumn('booking_change_requests', 'finalized_at')) {
                    $table->timestamp('finalized_at')->nullable();
                }
            });
        }

        if (Schema::hasTable('salora_booking_payment_adjustments')) {
            Schema::table('salora_booking_payment_adjustments', function (Blueprint $table): void {
                if (!Schema::hasColumn('salora_booking_payment_adjustments', 'change_request_id')) {
                    $table->unsignedBigInteger('change_request_id')->nullable();
                }
                if (!Schema::hasColumn('salora_booking_payment_adjustments', 'payment_proof_id')) {
                    $table->unsignedBigInteger('payment_proof_id')->nullable();
                }
            });
        }

        if (Schema::hasTable('payment_proofs') && !Schema::hasColumn('payment_proofs', 'payment_adjustment_id')) {
            Schema::table('payment_proofs', function (Blueprint $table): void {
                $table->unsignedBigInteger('payment_adjustment_id')->nullable();
                $table->index('payment_adjustment_id', 'payment_proofs_adjustment_idx');
            });
        }

        if (!Schema::hasTable('salora_booking_change_holds')) {
            Schema::create('salora_booking_change_holds', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('booking_id');
                $table->unsignedBigInteger('change_request_id')->unique();
                $table->unsignedBigInteger('venue_id');
                $table->timestamp('start_at');
                $table->timestamp('end_at');
                $table->string('status', 30)->default('active');
                $table->timestamp('released_at')->nullable();
                $table->timestamps();
                $table->index(['venue_id', 'status', 'start_at', 'end_at'], 'salora_change_holds_slot_idx');
            });
        }

        if (Schema::hasTable('venue_offers') && !Schema::hasColumn('venue_offers', 'announcement_sent_at')) {
            Schema::table('venue_offers', function (Blueprint $table): void {
                $table->timestamp('announcement_sent_at')->nullable();
            });
        }

        // Payment and proof-review deadlines are retired. Keep historical columns for
        // backward-compatible schema reads, but clear every active value.
        if (Schema::hasTable('invoices')) {
            $updates = [];
            foreach (['due_at', 'payment_deadline_at', 'payment_reminder_sent_at', 'review_deadline_at', 'review_reminder_sent_at', 'review_overdue_notified_at'] as $column) {
                if (Schema::hasColumn('invoices', $column)) {
                    $updates[$column] = null;
                }
            }
            if ($updates !== []) {
                DB::table('invoices')->update($updates);
            }
        }

        if (Schema::hasTable('provider_service_requests') && Schema::hasColumn('provider_service_requests', 'payment_deadline_at')) {
            DB::table('provider_service_requests')->update(['payment_deadline_at' => null]);
        }

        if (Schema::hasTable('bookings') && Schema::hasColumn('bookings', 'expires_at')) {
            DB::table('bookings')
                ->whereIn('booking_status', ['pending_payment', 'payment_under_review'])
                ->update(['expires_at' => null]);
        }
    }

    public function down(): void
    {
        // Deliberately non-destructive. Booking, payment and audit history must remain intact.
    }
};
