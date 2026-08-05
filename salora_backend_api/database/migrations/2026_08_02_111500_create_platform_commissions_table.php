<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('platform_commissions')) {
            Schema::create('platform_commissions', function (Blueprint $table) {
                $table->id();
                $table->string('source_type', 40);
                $table->unsignedBigInteger('source_id');
                $table->string('source_reference')->nullable();
                $table->string('source_title')->nullable();
                $table->foreignId('business_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('business_role', 20);
                $table->foreignId('customer_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();
                $table->decimal('gross_syp', 16, 2)->default(0);
                $table->decimal('gross_usd', 14, 2)->default(0);
                $table->decimal('commission_rate', 5, 2)->default(10);
                $table->decimal('commission_syp', 16, 2)->default(0);
                $table->decimal('commission_usd', 14, 2)->default(0);
                $table->decimal('net_syp', 16, 2)->default(0);
                $table->decimal('net_usd', 14, 2)->default(0);
                $table->string('status', 30)->default('uncollected');
                $table->decimal('collected_syp', 16, 2)->default(0);
                $table->decimal('collected_usd', 14, 2)->default(0);
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('collected_at')->nullable();
                $table->string('collection_method', 80)->nullable();
                $table->string('collection_reference', 120)->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('collected_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['source_type', 'source_id']);
                $table->index(['status', 'business_role']);
                $table->index(['business_user_id', 'status']);
                $table->index(['approved_at', 'status']);
            });
        }

        $this->ensureCommissionSetting();
        $this->backfillBookings();
        $this->backfillProviderRequests();
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_commissions');
    }


    private function ensureCommissionSetting(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        DB::table('settings')->updateOrInsert(
            ['key' => 'platform_commission_percentage'],
            ['value' => '10', 'type' => 'number', 'updated_at' => now(), 'created_at' => now()]
        );
    }

    private function backfillBookings(): void
    {
        if (! Schema::hasTable('bookings')) {
            return;
        }

        $statuses = [
            'pending_payment', 'payment_under_review', 'confirmed', 'completed',
            'modification_requested', 'cancellation_requested',
        ];

        DB::table('bookings')
            ->whereIn('booking_status', $statuses)
            ->orderBy('id')
            ->chunkById(200, function ($bookings): void {
                foreach ($bookings as $booking) {
                    $grossSyp = (float) ($booking->total_syp ?? 0);
                    $grossUsd = (float) ($booking->total_usd ?? 0);
                    DB::table('platform_commissions')->insertOrIgnore([[
                            'source_type' => 'booking',
                            'source_id' => $booking->id,
                            'source_reference' => $booking->booking_number ?? ('BOOK-'.$booking->id),
                            'source_title' => $booking->event_name ?? 'حجز صالة',
                            'business_user_id' => $booking->owner_id ?? null,
                            'business_role' => 'owner',
                            'customer_id' => $booking->customer_id ?? null,
                            'booking_id' => $booking->id,
                            'gross_syp' => $grossSyp,
                            'gross_usd' => $grossUsd,
                            'commission_rate' => 10,
                            'commission_syp' => round($grossSyp * 0.10, 2),
                            'commission_usd' => round($grossUsd * 0.10, 2),
                            'net_syp' => round($grossSyp * 0.90, 2),
                            'net_usd' => round($grossUsd * 0.90, 2),
                            'status' => 'uncollected',
                            'collected_syp' => 0,
                            'collected_usd' => 0,
                            'approved_at' => $booking->owner_decision_at ?? $booking->updated_at ?? now(),
                            'created_at' => $booking->created_at ?? now(),
                            'updated_at' => now(),
                        ]]);
                }
            });
    }

    private function backfillProviderRequests(): void
    {
        if (! Schema::hasTable('provider_service_requests')) {
            return;
        }

        $statuses = ['accepted', 'payment_pending', 'payment_under_review', 'paid', 'payment_approved', 'completed'];

        DB::table('provider_service_requests')
            ->whereIn('status', $statuses)
            ->orderBy('id')
            ->chunkById(200, function ($requests): void {
                foreach ($requests as $item) {
                    $grossSyp = (float) ($item->price_syp ?? 0);
                    $grossUsd = (float) ($item->price_usd ?? 0);
                    DB::table('platform_commissions')->insertOrIgnore([[
                            'source_type' => 'provider_service_request',
                            'source_id' => $item->id,
                            'source_reference' => 'SRV-'.$item->id,
                            'source_title' => $item->service_name ?? 'طلب خدمة',
                            'business_user_id' => $item->provider_id ?? null,
                            'business_role' => 'provider',
                            'customer_id' => $item->customer_id ?? null,
                            'booking_id' => $item->booking_id ?? null,
                            'gross_syp' => $grossSyp,
                            'gross_usd' => $grossUsd,
                            'commission_rate' => 10,
                            'commission_syp' => round($grossSyp * 0.10, 2),
                            'commission_usd' => round($grossUsd * 0.10, 2),
                            'net_syp' => round($grossSyp * 0.90, 2),
                            'net_usd' => round($grossUsd * 0.90, 2),
                            'status' => 'uncollected',
                            'collected_syp' => 0,
                            'collected_usd' => 0,
                            'approved_at' => $item->provider_decision_at ?? $item->updated_at ?? now(),
                            'created_at' => $item->created_at ?? now(),
                            'updated_at' => now(),
                        ]]);
                }
            });
    }
};