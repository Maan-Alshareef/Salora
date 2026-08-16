<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $defaultRate = max(1, (float) env('USD_TO_SYP', 14000));

        if (Schema::hasTable('settings')) {
            $setting = DB::table('settings')->where('key', 'exchange_rate_usd_to_syp')->first();
            if (!$setting) {
                DB::table('settings')->insert([
                    'key' => 'exchange_rate_usd_to_syp',
                    'value' => (string) $defaultRate,
                    'type' => 'number',
                    'updated_at' => now(),
                    'created_at' => now(),
                ]);
            } elseif (!is_numeric($setting->value) || (float) $setting->value <= 0) {
                DB::table('settings')->where('id', $setting->id)->update([
                    'value' => (string) $defaultRate,
                    'type' => 'number',
                    'updated_at' => now(),
                ]);
            } else {
                $defaultRate = (float) $setting->value;
            }
        }

        if (Schema::hasTable('bookings') && !Schema::hasColumn('bookings', 'exchange_rate_syp_per_usd')) {
            Schema::table('bookings', function (Blueprint $table): void {
                $table->decimal('exchange_rate_syp_per_usd', 14, 4)->nullable()->after('currency');
            });
        }

        if (Schema::hasTable('invoices') && !Schema::hasColumn('invoices', 'exchange_rate_syp_per_usd')) {
            Schema::table('invoices', function (Blueprint $table): void {
                $table->decimal('exchange_rate_syp_per_usd', 14, 4)->nullable()->after('currency');
            });
        }

        if (Schema::hasTable('provider_service_requests') && !Schema::hasColumn('provider_service_requests', 'exchange_rate_syp_per_usd')) {
            Schema::table('provider_service_requests', function (Blueprint $table): void {
                $table->decimal('exchange_rate_syp_per_usd', 14, 4)->nullable()->after('price_usd');
            });
        }

        $this->backfill('bookings', 'total_syp', 'total_usd', $defaultRate);
        $this->backfill('invoices', 'total_syp', 'total_usd', $defaultRate);
        $this->backfill('provider_service_requests', 'price_syp', 'price_usd', $defaultRate);
    }

    public function down(): void
    {
        foreach (['provider_service_requests', 'invoices', 'bookings'] as $tableName) {
            if (!Schema::hasTable($tableName) || !Schema::hasColumn($tableName, 'exchange_rate_syp_per_usd')) {
                continue;
            }
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn('exchange_rate_syp_per_usd');
            });
        }
    }

    private function backfill(string $table, string $sypColumn, string $usdColumn, float $fallback): void
    {
        if (!Schema::hasTable($table)
            || !Schema::hasColumn($table, 'exchange_rate_syp_per_usd')
            || !Schema::hasColumn($table, $sypColumn)
            || !Schema::hasColumn($table, $usdColumn)) {
            return;
        }

        DB::table($table)->orderBy('id')->chunkById(200, function ($rows) use ($table, $sypColumn, $usdColumn, $fallback): void {
            foreach ($rows as $row) {
                $syp = (float) ($row->{$sypColumn} ?? 0);
                $usd = (float) ($row->{$usdColumn} ?? 0);
                $rate = ($syp > 0 && $usd > 0) ? ($syp / $usd) : $fallback;
                if (!is_finite($rate) || $rate <= 0) {
                    $rate = $fallback;
                }

                DB::table($table)->where('id', $row->id)->update([
                    'exchange_rate_syp_per_usd' => round($rate, 4),
                ]);
            }
        });
    }
};
