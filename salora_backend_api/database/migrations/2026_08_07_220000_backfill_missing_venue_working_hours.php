<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('venue_working_hours')) {
            return;
        }

        $venueTable = null;
        foreach (config('salora_booking_v2.venue_tables', ['venues', 'halls']) as $candidate) {
            if (Schema::hasTable($candidate)) {
                $venueTable = $candidate;
                break;
            }
        }

        if (! $venueTable) {
            return;
        }

        $hasOpeningHours = Schema::hasColumn($venueTable, 'opening_hours');
        $columns = ['id'];
        if ($hasOpeningHours) {
            $columns[] = 'opening_hours';
        }

        DB::table($venueTable)->select($columns)->orderBy('id')->chunkById(200, function ($venues) use ($hasOpeningHours): void {
            foreach ($venues as $venue) {
                $hasWeekly = DB::table('venue_working_hours')
                    ->where('venue_id', $venue->id)
                    ->exists();

                if ($hasWeekly) {
                    continue;
                }

                $hasLegacy = false;
                if ($hasOpeningHours) {
                    $legacy = $venue->opening_hours ?? null;
                    if (is_string($legacy)) {
                        $legacy = json_decode($legacy, true);
                    }
                    $hasLegacy = is_array($legacy) && $legacy !== [];
                }

                // Do not override real legacy hours. Only old venues with no
                // schedule at all receive defaults.
                if ($hasLegacy) {
                    continue;
                }

                for ($day = 0; $day <= 6; $day++) {
                    DB::table('venue_working_hours')->updateOrInsert(
                        ['venue_id' => $venue->id, 'day_of_week' => $day],
                        [
                            'open_time' => '09:00',
                            'close_time' => '23:00',
                            'is_closed' => false,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                }
            }
        }, 'id');
    }

    public function down(): void
    {
        // Keep schedules: owners may have edited them after the migration.
    }
};
