<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $venueTable = collect(['venues', 'halls', 'salons'])
            ->first(fn (string $table) => Schema::hasTable($table));
        $bookingTable = collect(['bookings', 'venue_bookings', 'hall_bookings'])
            ->first(fn (string $table) => Schema::hasTable($table));

        if ($venueTable) {
            Schema::table($venueTable, function (Blueprint $table) use ($venueTable) {
                $columns = [
                    'hourly_price_syp' => fn () => $table->decimal('hourly_price_syp', 18, 2)->default(0),
                    'minimum_booking_minutes' => fn () => $table->unsignedInteger('minimum_booking_minutes')->default(120),
                    'maximum_booking_minutes' => fn () => $table->unsignedInteger('maximum_booking_minutes')->default(480),
                    'cleanup_minutes' => fn () => $table->unsignedInteger('cleanup_minutes')->default(60),
                    'pricing_updated_at' => fn () => $table->timestamp('pricing_updated_at')->nullable(),
                ];
                foreach ($columns as $name => $definition) {
                    if (!Schema::hasColumn($venueTable, $name)) {
                        $definition();
                    }
                }
            });
        }

        if ($bookingTable) {
            Schema::table($bookingTable, function (Blueprint $table) use ($bookingTable) {
                $columns = [
                    'start_at' => fn () => $table->dateTime('start_at')->nullable(),
                    'end_at' => fn () => $table->dateTime('end_at')->nullable(),
                    'duration_minutes' => fn () => $table->unsignedInteger('duration_minutes')->nullable(),
                    'hourly_price_snapshot_syp' => fn () => $table->decimal('hourly_price_snapshot_syp', 18, 2)->nullable(),
                    'price_before_discount_syp' => fn () => $table->decimal('price_before_discount_syp', 18, 2)->nullable(),
                    'offer_id' => fn () => $table->unsignedBigInteger('offer_id')->nullable(),
                    'offer_snapshot' => fn () => $table->json('offer_snapshot')->nullable(),
                    'discount_syp' => fn () => $table->decimal('discount_syp', 18, 2)->default(0),
                    'final_price_syp' => fn () => $table->decimal('final_price_syp', 18, 2)->nullable(),
                    'refund_percentage' => fn () => $table->decimal('refund_percentage', 5, 2)->default(0),
                    'refunded_syp' => fn () => $table->decimal('refunded_syp', 18, 2)->default(0),
                    'owner_retained_syp' => fn () => $table->decimal('owner_retained_syp', 18, 2)->nullable(),
                    'commission_rate' => fn () => $table->decimal('commission_rate', 5, 2)->default(10),
                    'commission_syp' => fn () => $table->decimal('commission_syp', 18, 2)->nullable(),
                    'financial_status' => fn () => $table->string('financial_status', 60)->nullable(),
                    'cancellation_status' => fn () => $table->string('cancellation_status', 60)->nullable(),
                    'cancellation_requested_at' => fn () => $table->timestamp('cancellation_requested_at')->nullable(),
                    'cancelled_at' => fn () => $table->timestamp('cancelled_at')->nullable(),
                    'cancellation_reason' => fn () => $table->text('cancellation_reason')->nullable(),
                    'refund_confirmed_at' => fn () => $table->timestamp('refund_confirmed_at')->nullable(),
                    'pricing_snapshot' => fn () => $table->json('pricing_snapshot')->nullable(),
                    'edit_locked_at' => fn () => $table->timestamp('edit_locked_at')->nullable(),
                ];
                foreach ($columns as $name => $definition) {
                    if (!Schema::hasColumn($bookingTable, $name)) {
                        $definition();
                    }
                }
            });
        }

        if (!Schema::hasTable('venue_working_hours')) {
            Schema::create('venue_working_hours', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('venue_id');
                $table->unsignedTinyInteger('day_of_week');
                $table->time('open_time')->nullable();
                $table->time('close_time')->nullable();
                $table->boolean('is_closed')->default(false);
                $table->timestamps();
                $table->unique(['venue_id', 'day_of_week'], 'venue_working_hours_unique');
            });
        }

        if (!Schema::hasTable('venue_schedule_exceptions')) {
            Schema::create('venue_schedule_exceptions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('venue_id');
                $table->date('exception_date');
                $table->time('open_time')->nullable();
                $table->time('close_time')->nullable();
                $table->boolean('is_closed')->default(false);
                $table->string('note')->nullable();
                $table->timestamps();
                $table->unique(['venue_id', 'exception_date'], 'venue_schedule_exceptions_unique');
            });
        }

        if (!Schema::hasTable('venue_schedule_blocks')) {
            Schema::create('venue_schedule_blocks', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('venue_id');
                $table->dateTime('start_at');
                $table->dateTime('end_at');
                $table->string('reason')->nullable();
                $table->unsignedBigInteger('created_by_user_id')->nullable();
                $table->timestamps();
                $table->index(['venue_id', 'start_at', 'end_at'], 'venue_schedule_blocks_lookup');
            });
        }

        if (!Schema::hasTable('venue_offers')) {
            Schema::create('venue_offers', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('venue_id');
                $table->string('title');
                $table->string('offer_type', 30);
                $table->string('scheduled_discount_type', 30)->nullable();
                $table->decimal('percentage', 5, 2)->nullable();
                $table->decimal('fixed_amount_syp', 18, 2)->nullable();
                $table->date('starts_on')->nullable();
                $table->date('ends_on')->nullable();
                $table->json('days_of_week')->nullable();
                $table->time('start_time')->nullable();
                $table->time('end_time')->nullable();
                $table->unsignedInteger('minimum_booking_minutes')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamp('published_at')->nullable();
                $table->timestamps();
                $table->index(['venue_id', 'is_active', 'starts_on', 'ends_on'], 'venue_offers_lookup');
            });
        }

        if (!Schema::hasTable('booking_change_requests')) {
            Schema::create('booking_change_requests', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('booking_id');
                $table->unsignedBigInteger('requested_by_user_id')->nullable();
                $table->json('old_data');
                $table->json('requested_data');
                $table->json('quote_snapshot')->nullable();
                $table->string('status', 30)->default('pending');
                $table->text('decision_reason')->nullable();
                $table->unsignedBigInteger('decided_by_user_id')->nullable();
                $table->timestamp('decided_at')->nullable();
                $table->timestamps();
                $table->index(['booking_id', 'status']);
            });
        }

        if (!Schema::hasTable('salora_booking_financial_events')) {
            Schema::create('salora_booking_financial_events', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('booking_id');
                $table->unsignedBigInteger('venue_id')->nullable();
                $table->string('event_type', 60);
                $table->decimal('price_before_syp', 18, 2)->nullable();
                $table->decimal('price_after_syp', 18, 2)->nullable();
                $table->decimal('commission_before_syp', 18, 2)->nullable();
                $table->decimal('commission_after_syp', 18, 2)->nullable();
                $table->json('metadata')->nullable();
                $table->unsignedBigInteger('actor_user_id')->nullable();
                $table->timestamps();
                $table->index(['booking_id', 'created_at'], 'salora_financial_events_lookup');
            });
        }

        if (!Schema::hasTable('salora_booking_commissions')) {
            Schema::create('salora_booking_commissions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('booking_id')->unique();
                $table->unsignedBigInteger('venue_id')->nullable();
                $table->decimal('final_price_syp', 18, 2)->default(0);
                $table->decimal('owner_retained_syp', 18, 2)->default(0);
                $table->decimal('commission_rate', 5, 2)->default(10);
                $table->decimal('commission_syp', 18, 2)->default(0);
                $table->decimal('collected_syp', 18, 2)->default(0);
                $table->decimal('settlement_syp', 18, 2)->default(0);
                $table->string('status', 60)->default('due');
                $table->json('snapshot')->nullable();
                $table->timestamps();
                $table->index(['status', 'updated_at']);
            });
        }
    }

    public function down(): void
    {
        // Intentionally empty: this patch must never delete existing Salora data.
    }
};
