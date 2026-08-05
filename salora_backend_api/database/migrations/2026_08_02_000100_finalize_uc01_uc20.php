<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'pending_email')) $table->string('pending_email', 190)->nullable()->after('email');
            if (!Schema::hasColumn('users', 'pending_email_requested_at')) $table->timestamp('pending_email_requested_at')->nullable()->after('pending_email');
            if (!Schema::hasColumn('users', 'business_status')) $table->string('business_status', 30)->default('approved')->after('status');
            if (!Schema::hasColumn('users', 'business_rejection_reason')) $table->text('business_rejection_reason')->nullable()->after('business_status');
        });

        if (Schema::hasTable('owner_requests')) {
            Schema::table('owner_requests', function (Blueprint $table) {
                if (!Schema::hasColumn('owner_requests', 'application_source')) $table->string('application_source', 30)->default('direct')->after('request_type');
            });
        }

        if (Schema::hasTable('provider_profiles')) {
            Schema::table('provider_profiles', function (Blueprint $table) {
                if (!Schema::hasColumn('provider_profiles', 'business_name')) $table->string('business_name')->nullable()->after('user_id');
                if (!Schema::hasColumn('provider_profiles', 'coverage_areas')) $table->json('coverage_areas')->nullable()->after('city');
                if (!Schema::hasColumn('provider_profiles', 'working_hours')) $table->json('working_hours')->nullable()->after('coverage_areas');
                if (!Schema::hasColumn('provider_profiles', 'days_off')) $table->json('days_off')->nullable()->after('working_hours');
            });
        }

        Schema::table('services', function (Blueprint $table) {
            if (!Schema::hasColumn('services', 'pricing_type')) $table->string('pricing_type', 30)->default('fixed')->after('price_usd');
            if (!Schema::hasColumn('services', 'coverage_areas')) $table->json('coverage_areas')->nullable()->after('duration_minutes');
            if (!Schema::hasColumn('services', 'terms')) $table->text('terms')->nullable()->after('description_en');
            if (!Schema::hasColumn('services', 'pending_revision')) $table->json('pending_revision')->nullable()->after('rejection_reason');
            if (!Schema::hasColumn('services', 'deleted_at')) $table->softDeletes();
        });

        if (!Schema::hasTable('service_packages')) {
            Schema::create('service_packages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
                $table->string('name');
                $table->text('description')->nullable();
                $table->decimal('price_syp', 14, 2)->default(0);
                $table->decimal('price_usd', 12, 2)->default(0);
                $table->unsignedInteger('duration_minutes')->nullable();
                $table->json('included_items')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
                $table->index(['service_id', 'is_active']);
            });
        }

        if (!Schema::hasTable('service_media')) {
            Schema::create('service_media', function (Blueprint $table) {
                $table->id();
                $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
                $table->string('media_type', 20)->default('image');
                $table->string('url', 1200);
                $table->string('thumbnail_url', 1200)->nullable();
                $table->boolean('is_main')->default(false);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
                $table->index(['service_id', 'media_type', 'sort_order']);
            });
        }

        Schema::table('venues', function (Blueprint $table) {
            if (!Schema::hasColumn('venues', 'contact_phone')) $table->string('contact_phone', 30)->nullable()->after('address');
            if (!Schema::hasColumn('venues', 'contact_whatsapp')) $table->string('contact_whatsapp', 30)->nullable()->after('contact_phone');
            if (!Schema::hasColumn('venues', 'cancellation_policy')) $table->text('cancellation_policy')->nullable()->after('description_en');
            if (!Schema::hasColumn('venues', 'booking_terms')) $table->text('booking_terms')->nullable()->after('cancellation_policy');
        });

        if (!Schema::hasTable('venue_videos')) {
            Schema::create('venue_videos', function (Blueprint $table) {
                $table->id();
                $table->foreignId('venue_id')->constrained('venues')->cascadeOnDelete();
                $table->string('video_url', 1200);
                $table->string('thumbnail_url', 1200)->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
                $table->index(['venue_id', 'sort_order']);
            });
        }

        if (Schema::hasTable('event_todo_items')) {
            Schema::table('event_todo_items', function (Blueprint $table) {
                if (!Schema::hasColumn('event_todo_items', 'description')) $table->text('description')->nullable()->after('title');
                if (!Schema::hasColumn('event_todo_items', 'due_at')) $table->timestamp('due_at')->nullable()->after('description');
                if (!Schema::hasColumn('event_todo_items', 'status')) $table->string('status', 30)->default('not_started')->after('is_completed');
                if (!Schema::hasColumn('event_todo_items', 'priority')) $table->string('priority', 20)->default('normal')->after('status');
                if (!Schema::hasColumn('event_todo_items', 'notes')) $table->text('notes')->nullable()->after('priority');
                if (!Schema::hasColumn('event_todo_items', 'linked_type')) $table->string('linked_type', 40)->nullable()->after('notes');
                if (!Schema::hasColumn('event_todo_items', 'linked_id')) $table->unsignedBigInteger('linked_id')->nullable()->after('linked_type');
                if (!Schema::hasColumn('event_todo_items', 'reminder_24h_sent_at')) $table->timestamp('reminder_24h_sent_at')->nullable()->after('completed_at');
                if (!Schema::hasColumn('event_todo_items', 'reminder_due_sent_at')) $table->timestamp('reminder_due_sent_at')->nullable()->after('reminder_24h_sent_at');
            });
        }

        if (!Schema::hasTable('payment_methods')) {
            Schema::create('payment_methods', function (Blueprint $table) {
                $table->id();
                $table->string('slug', 60)->unique();
                $table->string('name_ar');
                $table->string('name_en');
                $table->string('logo_path', 1000)->nullable();
                $table->text('instructions')->nullable();
                $table->string('method_type', 30)->default('manual_transfer');
                $table->boolean('for_venues')->default(true);
                $table->boolean('for_providers')->default(true);
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('payout_accounts')) {
            Schema::create('payout_accounts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('payment_method_id')->constrained('payment_methods')->restrictOnDelete();
                $table->string('account_name');
                $table->text('account_number')->nullable();
                $table->string('account_number_hash', 64)->nullable();
                $table->string('phone', 30)->nullable();
                $table->string('city', 120)->nullable();
                $table->string('branch', 190)->nullable();
                $table->string('qr_path', 1000)->nullable();
                $table->text('instructions')->nullable();
                $table->boolean('is_default')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->unique(['user_id', 'payment_method_id', 'account_number_hash'], 'payout_account_unique');
                $table->index(['user_id', 'is_active']);
            });
        }

        Schema::table('invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('invoices', 'payee_id')) $table->foreignId('payee_id')->nullable()->after('customer_id')->constrained('users')->nullOnDelete();
            if (!Schema::hasColumn('invoices', 'source_type')) $table->string('source_type', 40)->default('venue_booking')->after('booking_id');
            if (!Schema::hasColumn('invoices', 'source_id')) $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
            if (!Schema::hasColumn('invoices', 'commission_syp')) $table->decimal('commission_syp', 14, 2)->default(0)->after('total_syp');
            if (!Schema::hasColumn('invoices', 'commission_usd')) $table->decimal('commission_usd', 12, 2)->default(0)->after('total_usd');
            if (!Schema::hasColumn('invoices', 'net_syp')) $table->decimal('net_syp', 14, 2)->default(0)->after('commission_syp');
            if (!Schema::hasColumn('invoices', 'net_usd')) $table->decimal('net_usd', 12, 2)->default(0)->after('commission_usd');
            if (!Schema::hasColumn('invoices', 'payment_deadline_at')) $table->timestamp('payment_deadline_at')->nullable()->after('due_at');
            if (!Schema::hasColumn('invoices', 'payment_reminder_sent_at')) $table->timestamp('payment_reminder_sent_at')->nullable()->after('payment_deadline_at');
            if (!Schema::hasColumn('invoices', 'receipt_number')) $table->string('receipt_number')->nullable()->unique()->after('invoice_number');
            if (!Schema::hasColumn('invoices', 'verification_token')) $table->string('verification_token', 80)->nullable()->unique()->after('receipt_number');
            if (!Schema::hasColumn('invoices', 'accepted_by')) $table->foreignId('accepted_by')->nullable()->after('paid_at')->constrained('users')->nullOnDelete();
            if (!Schema::hasColumn('invoices', 'accepted_at')) $table->timestamp('accepted_at')->nullable()->after('accepted_by');
        });

        Schema::table('payment_proofs', function (Blueprint $table) {
            if (!Schema::hasColumn('payment_proofs', 'invoice_id')) $table->foreignId('invoice_id')->nullable()->after('booking_id')->constrained('invoices')->nullOnDelete();
            if (!Schema::hasColumn('payment_proofs', 'payment_method_id')) $table->foreignId('payment_method_id')->nullable()->after('payment_method')->constrained('payment_methods')->nullOnDelete();
            if (!Schema::hasColumn('payment_proofs', 'payout_account_id')) $table->foreignId('payout_account_id')->nullable()->after('payment_method_id')->constrained('payout_accounts')->nullOnDelete();
            if (!Schema::hasColumn('payment_proofs', 'sender_name')) $table->string('sender_name')->nullable()->after('payout_account_id');
            if (!Schema::hasColumn('payment_proofs', 'transaction_reference')) $table->string('transaction_reference', 190)->nullable()->after('sender_name');
            if (!Schema::hasColumn('payment_proofs', 'transferred_at')) $table->timestamp('transferred_at')->nullable()->after('transaction_reference');
            if (!Schema::hasColumn('payment_proofs', 'customer_notes')) $table->text('customer_notes')->nullable()->after('transferred_at');
            if (!Schema::hasColumn('payment_proofs', 'reviewer_id')) $table->foreignId('reviewer_id')->nullable()->after('admin_id')->constrained('users')->nullOnDelete();
            if (!Schema::hasColumn('payment_proofs', 'reviewer_role')) $table->string('reviewer_role', 30)->nullable()->after('reviewer_id');
            if (!Schema::hasColumn('payment_proofs', 'attempt_no')) $table->unsignedInteger('attempt_no')->default(1)->after('status');
            $table->index(['invoice_id', 'status']);
        });

        if (Schema::hasTable('provider_service_requests')) {
            Schema::table('provider_service_requests', function (Blueprint $table) {
                if (!Schema::hasColumn('provider_service_requests', 'invoice_id')) $table->foreignId('invoice_id')->nullable()->after('service_id')->constrained('invoices')->nullOnDelete();
                if (!Schema::hasColumn('provider_service_requests', 'payment_status')) $table->string('payment_status', 30)->default('unpaid')->after('status');
                if (!Schema::hasColumn('provider_service_requests', 'payment_deadline_at')) $table->timestamp('payment_deadline_at')->nullable()->after('provider_decision_at');
            });
        }

        if (!Schema::hasTable('payment_refunds')) {
            Schema::create('payment_refunds', function (Blueprint $table) {
                $table->id();
                $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
                $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();
                $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('payee_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('requested_by_role', 30);
                $table->text('reason');
                $table->decimal('refund_percent', 5, 2)->default(0);
                $table->decimal('amount_syp', 14, 2)->default(0);
                $table->decimal('amount_usd', 12, 2)->default(0);
                $table->string('status', 40)->default('pending_approval');
                $table->timestamp('due_at')->nullable();
                $table->foreignId('payment_method_id')->nullable()->constrained('payment_methods')->nullOnDelete();
                $table->string('transaction_reference', 190)->nullable();
                $table->string('proof_path', 1000)->nullable();
                $table->timestamp('transferred_at')->nullable();
                $table->timestamp('customer_confirmed_at')->nullable();
                $table->timestamp('disputed_at')->nullable();
                $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('resolution_notes')->nullable();
                $table->timestamps();
                $table->index(['payee_id', 'status']);
                $table->index(['customer_id', 'status']);
            });
        }

        $methods = [
            ['slug'=>'sham_cash','name_ar'=>'شام كاش','name_en'=>'Sham Cash','logo_path'=>'/payment-methods/sham-cash.svg','instructions'=>'حوّل المبلغ كاملاً إلى الحساب الظاهر ثم ارفع صورة الوصل ورقم العملية.','sort_order'=>1],
            ['slug'=>'syriatel_cash','name_ar'=>'سيريتل كاش','name_en'=>'Syriatel Cash','logo_path'=>'/payment-methods/syriatel-cash.svg','instructions'=>'حوّل المبلغ كاملاً إلى محفظة سيريتل كاش الظاهرة ثم ارفع إثبات التحويل.','sort_order'=>2],
            ['slug'=>'al_haram','name_ar'=>'الهرم للحوالات المالية','name_en'=>'Al Haram Transfer','logo_path'=>'/payment-methods/al-haram.svg','instructions'=>'نفّذ الحوالة باسم المستلم والفرع الظاهرين ثم ارفع صورة الوصل ورقم الحوالة.','sort_order'=>3],
        ];
        foreach ($methods as $method) {
            DB::table('payment_methods')->updateOrInsert(['slug'=>$method['slug']], [
                ...$method, 'method_type'=>'manual_transfer', 'for_venues'=>true, 'for_providers'=>true,
                'is_active'=>true, 'updated_at'=>now(), 'created_at'=>now(),
            ]);
        }
        DB::table('payment_methods')->whereNotIn('slug', ['sham_cash','syriatel_cash','al_haram'])->update(['is_active'=>false,'updated_at'=>now()]);
        if (Schema::hasTable('provider_service_requests') && Schema::hasColumn('provider_service_requests', 'payment_type')) {
            DB::table('provider_service_requests')->where('payment_type', 'cash')->update(['payment_type' => 'manual_transfer']);
        }

        DB::table('users')->whereIn('role', ['owner','provider'])->whereNull('business_status')->update(['business_status'=>'approved']);
        DB::table('invoices')->whereNull('verification_token')->orderBy('id')->chunkById(100, function ($rows) {
            foreach ($rows as $row) DB::table('invoices')->where('id',$row->id)->update(['verification_token'=>Str::random(48)]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_refunds');
        Schema::dropIfExists('payout_accounts');
        Schema::dropIfExists('payment_methods');
        Schema::dropIfExists('venue_videos');
        Schema::dropIfExists('service_media');
        Schema::dropIfExists('service_packages');
    }
};
