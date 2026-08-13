<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('venues') || ! Schema::hasTable('venue_working_hours')) {
            return;
        }

        $venues = DB::table('venues')->select('id')->orderBy('id')->get();

        DB::transaction(function () use ($venues): void {
            foreach ($venues as $venue) {
                for ($day = 0; $day <= 6; $day++) {
                    $existing = DB::table('venue_working_hours')
                        ->where('venue_id', $venue->id)
                        ->where('day_of_week', $day)
                        ->first();

                    $valid = $existing
                        && ((bool) $existing->is_closed
                            || (! empty($existing->open_time)
                                && ! empty($existing->close_time)));

                    if ($valid) {
                        continue;
                    }

                    DB::table('venue_working_hours')->updateOrInsert(
                        [
                            'venue_id' => $venue->id,
                            'day_of_week' => $day,
                        ],
                        [
                            'open_time' => '09:00',
                            'close_time' => '23:00',
                            'is_closed' => false,
                            'created_at' => $existing->created_at ?? now(),
                            'updated_at' => now(),
                        ]
                    );
                }
            }
        });
    }

    public function down(): void
    {
        // لا نحذف بيانات ساعات العمل لأن المالك قد يكون عدلها بعد التطبيق.
    }
};
