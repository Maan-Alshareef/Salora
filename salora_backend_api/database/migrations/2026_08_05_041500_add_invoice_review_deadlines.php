<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('invoices')) {
            return;
        }

        Schema::table('invoices', function (Blueprint $table): void {
            if (!Schema::hasColumn('invoices', 'review_deadline_at')) {
                $table->timestamp('review_deadline_at')->nullable();
            }
            if (!Schema::hasColumn('invoices', 'review_reminder_sent_at')) {
                $table->timestamp('review_reminder_sent_at')->nullable();
            }
            if (!Schema::hasColumn('invoices', 'review_overdue_notified_at')) {
                $table->timestamp('review_overdue_notified_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('invoices')) {
            return;
        }

        Schema::table('invoices', function (Blueprint $table): void {
            $columns = array_values(array_filter([
                Schema::hasColumn('invoices', 'review_deadline_at')
                    ? 'review_deadline_at'
                    : null,
                Schema::hasColumn('invoices', 'review_reminder_sent_at')
                    ? 'review_reminder_sent_at'
                    : null,
                Schema::hasColumn('invoices', 'review_overdue_notified_at')
                    ? 'review_overdue_notified_at'
                    : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
