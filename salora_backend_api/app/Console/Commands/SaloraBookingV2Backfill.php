<?php

namespace App\Console\Commands;

use App\Services\SaloraBookingV2Service;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SaloraBookingV2Backfill extends Command
{
    protected $signature = 'salora:v2-backfill';
    protected $description = 'Backfill Salora booking times and commissions without deleting old data.';

    public function handle(SaloraBookingV2Service $service): int
    {
        $table = $service->bookingTable();
        $updated = 0;
        $skipped = 0;

        DB::table($table)->orderBy('id')->chunkById(100, function ($rows) use ($service, $table, &$updated, &$skipped) {
            foreach ($rows as $row) {
                try {
                    [$start, $end] = $service->extractDateTimes($row);
                    $data = [];
                    if (Schema::hasColumn($table, 'start_at') && empty($row->start_at)) {
                        $data['start_at'] = $start->toDateTimeString();
                    }
                    if (Schema::hasColumn($table, 'end_at') && empty($row->end_at)) {
                        $data['end_at'] = $end->toDateTimeString();
                    }
                    if (Schema::hasColumn($table, 'duration_minutes') && empty($row->duration_minutes)) {
                        $data['duration_minutes'] = $start->diffInMinutes($end);
                    }
                    if ($data) {
                        DB::table($table)->where('id', $row->id)->update($data);
                    }
                    $service->syncCommission((int) $row->id);
                    $updated++;
                } catch (\Throwable $error) {
                    $skipped++;
                    $this->warn("Skipped booking {$row->id}: {$error->getMessage()}");
                }
            }
        });

        $this->info("Backfill completed. Updated: {$updated}; skipped: {$skipped}");
        return self::SUCCESS;
    }
}
