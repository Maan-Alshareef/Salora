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

        DB::table('invoices')
            ->whereNull('source_type')
            ->update(['source_type' => 'venue_booking']);

        DB::table('invoices')
            ->where('source_type', 'venue_booking')
            ->whereNull('source_id')
            ->whereNotNull('booking_id')
            ->orderBy('id')
            ->each(function ($invoice): void {
                DB::table('invoices')
                    ->where('id', $invoice->id)
                    ->update(['source_id' => $invoice->booking_id]);
            });

        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            DB::statement(
                'DROP INDEX IF EXISTS "invoices_booking_id_unique"',
            );
            DB::statement(
                'CREATE INDEX IF NOT EXISTS "invoices_booking_id_index" '.
                'ON "invoices" ("booking_id")',
            );
            DB::statement(
                'CREATE UNIQUE INDEX IF NOT EXISTS '.
                '"invoices_source_type_source_id_unique" '.
                'ON "invoices" ("source_type", "source_id") '.
                'WHERE "source_id" IS NOT NULL',
            );

            return;
        }

        if ($driver === 'mysql') {
            if ($this->mysqlIndexExists('invoices_booking_id_unique')) {
                DB::statement(
                    'ALTER TABLE invoices DROP INDEX invoices_booking_id_unique',
                );
            }

            if (!$this->mysqlIndexExists('invoices_booking_id_index')) {
                DB::statement(
                    'ALTER TABLE invoices ADD INDEX '.
                    'invoices_booking_id_index (booking_id)',
                );
            }

            if (
                !$this->mysqlIndexExists(
                    'invoices_source_type_source_id_unique',
                )
            ) {
                DB::statement(
                    'ALTER TABLE invoices ADD UNIQUE INDEX '.
                    'invoices_source_type_source_id_unique '.
                    '(source_type, source_id)',
                );
            }

            return;
        }

        try {
            DB::statement(
                'DROP INDEX invoices_booking_id_unique',
            );
        } catch (\Throwable) {
            // The index may already be absent.
        }

        try {
            DB::statement(
                'CREATE INDEX invoices_booking_id_index '.
                'ON invoices (booking_id)',
            );
        } catch (\Throwable) {
            // The index may already exist.
        }

        try {
            DB::statement(
                'CREATE UNIQUE INDEX invoices_source_type_source_id_unique '.
                'ON invoices (source_type, source_id)',
            );
        } catch (\Throwable) {
            // The index may already exist.
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('invoices')) {
            return;
        }

        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            DB::statement(
                'DROP INDEX IF EXISTS "invoices_source_type_source_id_unique"',
            );

            return;
        }

        if (
            $driver === 'mysql' &&
            $this->mysqlIndexExists(
                'invoices_source_type_source_id_unique',
            )
        ) {
            DB::statement(
                'ALTER TABLE invoices DROP INDEX '.
                'invoices_source_type_source_id_unique',
            );
        }
    }

    private function mysqlIndexExists(string $name): bool
    {
        return count(
            DB::select(
                'SHOW INDEX FROM invoices WHERE Key_name = ?',
                [$name],
            ),
        ) > 0;
    }
};
