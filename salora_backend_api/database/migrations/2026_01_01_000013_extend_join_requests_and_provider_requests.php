<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('owner_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('owner_requests', 'request_type')) {
                $table->string('request_type', 30)->default('owner')->after('id');
            }
            if (!Schema::hasColumn('owner_requests', 'service_category')) {
                $table->string('service_category')->nullable()->after('address');
            }
            if (!Schema::hasColumn('owner_requests', 'service_description')) {
                $table->text('service_description')->nullable()->after('service_category');
            }
            if (!Schema::hasColumn('owner_requests', 'sample_work_url')) {
                $table->string('sample_work_url', 1000)->nullable()->after('service_description');
            }
        });

        Schema::create('provider_service_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('provider_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->string('service_name');
            $table->string('service_category')->nullable();
            $table->decimal('price_syp', 14, 2)->default(0);
            $table->decimal('price_usd', 12, 2)->default(0);
            $table->string('payment_type', 30)->default('manual_transfer');
            $table->enum('status', ['pending','accepted','rejected','cancelled'])->default('pending');
            $table->text('customer_notes')->nullable();
            $table->text('provider_reply')->nullable();
            $table->timestamp('provider_decision_at')->nullable();
            $table->timestamps();
            $table->index(['provider_id','status']);
            $table->index(['booking_id','service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_service_requests');
        Schema::table('owner_requests', function (Blueprint $table) {
            foreach (['sample_work_url','service_description','service_category','request_type'] as $column) {
                if (Schema::hasColumn('owner_requests', $column)) $table->dropColumn($column);
            }
        });
    }
};
