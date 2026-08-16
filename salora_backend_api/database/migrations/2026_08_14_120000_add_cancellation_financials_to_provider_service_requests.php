<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('provider_service_requests')) {
            return;
        }

        Schema::table('provider_service_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('provider_service_requests', 'cancelled_by')) {
                $table->string('cancelled_by', 40)->nullable()->after('status');
            }
            if (!Schema::hasColumn('provider_service_requests', 'cancellation_reason')) {
                $table->text('cancellation_reason')->nullable()->after('cancelled_by');
            }
            if (!Schema::hasColumn('provider_service_requests', 'cancellation_status')) {
                $table->string('cancellation_status', 40)->nullable()->after('cancellation_reason');
            }
            if (!Schema::hasColumn('provider_service_requests', 'refund_percentage')) {
                $table->decimal('refund_percentage', 8, 2)->default(0)->after('cancellation_status');
            }
            if (!Schema::hasColumn('provider_service_requests', 'refunded_syp')) {
                $table->decimal('refunded_syp', 14, 2)->default(0)->after('refund_percentage');
            }
            if (!Schema::hasColumn('provider_service_requests', 'provider_retained_syp')) {
                $table->decimal('provider_retained_syp', 14, 2)->default(0)->after('refunded_syp');
            }
            if (!Schema::hasColumn('provider_service_requests', 'refund_confirmed_at')) {
                $table->timestamp('refund_confirmed_at')->nullable()->after('provider_retained_syp');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('provider_service_requests')) {
            return;
        }

        Schema::table('provider_service_requests', function (Blueprint $table) {
            foreach ([
                'refund_confirmed_at',
                'provider_retained_syp',
                'refunded_syp',
                'refund_percentage',
                'cancellation_status',
                'cancellation_reason',
                'cancelled_by',
            ] as $column) {
                if (Schema::hasColumn('provider_service_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
