<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (!Schema::hasColumn('bookings', 'platform_commission_rate')) {
                $table->decimal('platform_commission_rate', 5, 2)->default(10);
            }
            if (!Schema::hasColumn('bookings', 'platform_commission_syp')) {
                $table->decimal('platform_commission_syp', 14, 2)->default(0);
            }
            if (!Schema::hasColumn('bookings', 'platform_commission_usd')) {
                $table->decimal('platform_commission_usd', 12, 2)->default(0);
            }
            if (!Schema::hasColumn('bookings', 'owner_net_syp')) {
                $table->decimal('owner_net_syp', 14, 2)->default(0);
            }
            if (!Schema::hasColumn('bookings', 'owner_net_usd')) {
                $table->decimal('owner_net_usd', 12, 2)->default(0);
            }
            if (!Schema::hasColumn('bookings', 'commission_status')) {
                $table->string('commission_status', 30)->default('not_due')->index();
            }
            if (!Schema::hasColumn('bookings', 'commission_collected_at')) {
                $table->timestamp('commission_collected_at')->nullable();
            }
            if (!Schema::hasColumn('bookings', 'commission_notes')) {
                $table->text('commission_notes')->nullable();
            }
        });

        DB::table('settings')->updateOrInsert(
            ['key' => 'platform_commission_percent'],
            ['value' => '10', 'type' => 'number', 'updated_at' => now(), 'created_at' => now()]
        );

        DB::table('offers')
            ->whereNotNull('owner_id')
            ->where('status', 'pending')
            ->update(['status' => 'active', 'updated_at' => now()]);

        $cutoff = now()->subMinutes(15);
        DB::table('bookings')
            ->whereIn('booking_status', ['pending_owner_review', 'pending'])
            ->where('created_at', '<=', $cutoff)
            ->update([
                'booking_status' => 'expired',
                'expires_at' => null,
                'updated_at' => now(),
            ]);

        DB::table('bookings')
            ->whereIn('booking_status', ['pending_owner_review', 'pending'])
            ->where('created_at', '>', $cutoff)
            ->update([
                'booking_status' => 'pending_payment',
                'expires_at' => now()->addMinutes(15),
                'updated_at' => now(),
            ]);

        DB::table('invoices')
            ->whereIn('booking_id', DB::table('bookings')->select('id')->where('booking_status', 'expired'))
            ->where('status', 'unpaid')
            ->update(['status' => 'cancelled', 'updated_at' => now()]);

        DB::table('bookings')->orderBy('id')->chunkById(100, function ($bookings) {
            foreach ($bookings as $booking) {
                $rate = 10.0;
                $commissionSyp = round((float) $booking->total_syp * $rate / 100, 2);
                $commissionUsd = round((float) $booking->total_usd * $rate / 100, 2);
                $isRevenue = in_array($booking->booking_status, ['confirmed', 'completed'], true);

                DB::table('bookings')->where('id', $booking->id)->update([
                    'platform_commission_rate' => $rate,
                    'platform_commission_syp' => $commissionSyp,
                    'platform_commission_usd' => $commissionUsd,
                    'owner_net_syp' => max(0, round((float) $booking->total_syp - $commissionSyp, 2)),
                    'owner_net_usd' => max(0, round((float) $booking->total_usd - $commissionUsd, 2)),
                    'commission_status' => $isRevenue ? 'due' : 'not_due',
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            foreach ([
                'platform_commission_rate', 'platform_commission_syp', 'platform_commission_usd',
                'owner_net_syp', 'owner_net_usd', 'commission_status', 'commission_collected_at',
                'commission_notes',
            ] as $column) {
                if (Schema::hasColumn('bookings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
