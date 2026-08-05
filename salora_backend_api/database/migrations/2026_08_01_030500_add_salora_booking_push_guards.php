<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            if (!Schema::hasColumn('bookings', 'hold_expires_at')) {
                $table->timestamp('hold_expires_at')->nullable();
            }
        });

        Schema::table('notifications', function (Blueprint $table): void {
            if (!Schema::hasColumn('notifications', 'push_attempted_at')) {
                $table->timestamp('push_attempted_at')->nullable();
            }

            if (!Schema::hasColumn('notifications', 'push_sent_at')) {
                $table->timestamp('push_sent_at')->nullable();
            }

            if (!Schema::hasColumn('notifications', 'push_error')) {
                $table->text('push_error')->nullable();
            }
        });

        Schema::table('offers', function (Blueprint $table): void {
            if (!Schema::hasColumn('offers', 'push_announced_at')) {
                $table->timestamp('push_announced_at')->nullable();
            }

            if (
                !Schema::hasColumn(
                    'offers',
                    'push_announcement_signature',
                )
            ) {
                $table
                    ->string('push_announcement_signature', 64)
                    ->nullable();
            }
        });

        if (Schema::hasColumn('notifications', 'push_sent_at')) {
            $value = Schema::hasColumn('notifications', 'created_at')
                ? DB::raw('COALESCE(created_at, CURRENT_TIMESTAMP)')
                : now();

            DB::table('notifications')
                ->whereNull('push_sent_at')
                ->update(['push_sent_at' => $value]);
        }
    }

    public function down(): void
    {
        Schema::table('offers', function (Blueprint $table): void {
            $columns = [];

            foreach (
                [
                    'push_announced_at',
                    'push_announcement_signature',
                ] as $column
            ) {
                if (Schema::hasColumn('offers', $column)) {
                    $columns[] = $column;
                }
            }

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });

        Schema::table('notifications', function (Blueprint $table): void {
            $columns = [];

            foreach (
                [
                    'push_attempted_at',
                    'push_sent_at',
                    'push_error',
                ] as $column
            ) {
                if (Schema::hasColumn('notifications', $column)) {
                    $columns[] = $column;
                }
            }

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });

        Schema::table('bookings', function (Blueprint $table): void {
            if (Schema::hasColumn('bookings', 'hold_expires_at')) {
                $table->dropColumn('hold_expires_at');
            }
        });
    }
};