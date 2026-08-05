<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('provider_service_requests', function (Blueprint $table) {
            $table->string('invoice_number')->nullable()->after('payment_type');
            $table->string('payment_status', 30)->default('unpaid')->after('status');
            $table->string('payment_method', 80)->nullable()->after('payment_status');
            $table->string('payment_proof_path', 1000)->nullable()->after('payment_method');
            $table->string('payment_proof_original_name')->nullable()->after('payment_proof_path');
            $table->text('payment_rejection_reason')->nullable()->after('provider_reply');
            $table->timestamp('payment_uploaded_at')->nullable()->after('provider_decision_at');
            $table->timestamp('payment_reviewed_at')->nullable()->after('payment_uploaded_at');
            $table->decimal('provider_commission_rate', 5, 2)->default(10)->after('payment_reviewed_at');
            $table->decimal('provider_commission_syp', 14, 2)->default(0)->after('provider_commission_rate');
            $table->decimal('provider_commission_usd', 12, 2)->default(0)->after('provider_commission_syp');
            $table->decimal('provider_net_syp', 14, 2)->default(0)->after('provider_commission_usd');
            $table->decimal('provider_net_usd', 12, 2)->default(0)->after('provider_net_syp');
            $table->string('commission_status', 30)->default('not_due')->after('provider_net_usd');
            $table->timestamp('commission_collected_at')->nullable()->after('commission_status');
            $table->text('commission_notes')->nullable()->after('commission_collected_at');
            $table->index(['payment_status', 'commission_status'], 'provider_requests_payment_commission_idx');
        });

        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('token');
            $table->string('token_hash', 64)->unique();
            $table->string('platform', 30)->default('android');
            $table->string('device_name')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'platform']);
        });

        DB::table('settings')->updateOrInsert(
            ['key' => 'provider_commission_percent'],
            ['value' => '10', 'type' => 'number', 'created_at' => now(), 'updated_at' => now()]
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('device_tokens');

        Schema::table('provider_service_requests', function (Blueprint $table) {
            $table->dropIndex('provider_requests_payment_commission_idx');
            $table->dropColumn([
                'invoice_number', 'payment_status', 'payment_method', 'payment_proof_path',
                'payment_proof_original_name', 'payment_rejection_reason', 'payment_uploaded_at',
                'payment_reviewed_at', 'provider_commission_rate', 'provider_commission_syp',
                'provider_commission_usd', 'provider_net_syp', 'provider_net_usd',
                'commission_status', 'commission_collected_at', 'commission_notes',
            ]);
        });

        DB::table('settings')->where('key', 'provider_commission_percent')->delete();
    }
};
