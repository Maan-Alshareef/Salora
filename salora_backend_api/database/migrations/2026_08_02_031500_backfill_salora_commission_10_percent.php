<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('invoices')) {
            return;
        }

        foreach (['total_syp', 'total_usd', 'commission_syp', 'commission_usd', 'net_syp', 'net_usd'] as $column) {
            if (!Schema::hasColumn('invoices', $column)) {
                return;
            }
        }

        $rate = 0.10;

        DB::table('invoices')
            ->select(['id', 'total_syp', 'total_usd'])
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($rate) {
                foreach ($rows as $row) {
                    $totalSyp = (float) ($row->total_syp ?? 0);
                    $totalUsd = (float) ($row->total_usd ?? 0);
                    $commissionSyp = round($totalSyp * $rate, 2);
                    $commissionUsd = round($totalUsd * $rate, 2);

                    DB::table('invoices')->where('id', $row->id)->update([
                        'commission_syp' => $commissionSyp,
                        'commission_usd' => $commissionUsd,
                        'net_syp' => max(0, round($totalSyp - $commissionSyp, 2)),
                        'net_usd' => max(0, round($totalUsd - $commissionUsd, 2)),
                        'updated_at' => now(),
                    ]);
                }
            });

        if (Schema::hasTable('settings')) {
            DB::table('settings')->updateOrInsert(
                ['key' => 'platform_commission_percentage'],
                [
                    'value' => '10',
                    'type' => 'number',
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        // لا نعيد تصفير القيم المالية عند rollback حتى لا نفقد بيانات الفواتير.
    }
};