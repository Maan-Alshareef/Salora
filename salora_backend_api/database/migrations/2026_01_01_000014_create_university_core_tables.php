<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->date('birth_date')->nullable()->after('phone');
            $table->timestamp('last_login_at')->nullable()->after('email_verified_at');
        });

        Schema::create('password_reset_otps', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('service_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name_ar')->nullable();
            $table->string('name_en');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique('name_en');
        });

        Schema::table('services', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('category')->constrained('service_categories')->nullOnDelete();
            $table->string('approval_status', 30)->default('approved')->after('is_active');
            $table->text('rejection_reason')->nullable()->after('approval_status');
            $table->string('image_url', 1000)->nullable()->after('emoji');
            $table->index(['provider_id', 'approval_status']);
        });

        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('event_type_id')->constrained('event_types')->restrictOnDelete();
            $table->string('name');
            $table->date('event_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->unsignedInteger('guests_count')->nullable();
            $table->decimal('budget_syp', 14, 2)->nullable();
            $table->decimal('budget_usd', 12, 2)->nullable();
            $table->string('city')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 30)->default('active');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['customer_id', 'event_date']);
        });

        Schema::create('event_todo_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('todo_template_id')->nullable()->constrained('todo_templates')->nullOnDelete();
            $table->string('title');
            $table->boolean('is_completed')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['event_id', 'is_completed']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('event_id')->nullable()->after('event_type_id')->constrained('events')->nullOnDelete();
            $table->text('rejection_reason')->nullable()->after('notes');
            $table->index(['customer_id', 'booking_status']);
            $table->index(['owner_id', 'booking_status']);
        });

        Schema::create('booking_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->string('from_status', 50)->nullable();
            $table->string('to_status', 50);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->timestamps();
            $table->index(['booking_id', 'created_at']);
        });

        Schema::create('booking_change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 30); // modification | cancellation
            $table->json('requested_changes')->nullable();
            $table->text('reason')->nullable();
            $table->string('status', 30)->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('decision_reason')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->index(['booking_id', 'status']);
            $table->index(['customer_id', 'status']);
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->foreignId('booking_id')->unique()->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('subtotal_syp', 14, 2)->default(0);
            $table->decimal('subtotal_usd', 12, 2)->default(0);
            $table->decimal('discount_syp', 14, 2)->default(0);
            $table->decimal('discount_usd', 12, 2)->default(0);
            $table->decimal('total_syp', 14, 2)->default(0);
            $table->decimal('total_usd', 12, 2)->default(0);
            $table->string('currency', 8)->default('SYP');
            $table->string('status', 30)->default('unpaid');
            $table->timestamp('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->index(['customer_id', 'status']);
        });

        Schema::table('payment_proofs', function (Blueprint $table) {
            $table->foreignId('invoice_id')->nullable()->after('booking_id')->constrained('invoices')->nullOnDelete();
        });

        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('payment_proof_id')->nullable()->constrained('payment_proofs')->nullOnDelete();
            $table->string('method', 50)->default('manual_transfer');
            $table->string('reference')->unique();
            $table->decimal('amount', 14, 2);
            $table->string('currency', 8);
            $table->string('status', 30)->default('pending');
            $table->json('metadata')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->index(['invoice_id', 'status']);
        });

        Schema::table('offers', function (Blueprint $table) {
            $table->string('discount_currency', 8)->nullable()->after('discount_value');
            $table->text('rejection_reason')->nullable()->after('status');
            $table->index(['status', 'start_date', 'end_date']);
        });

        Schema::table('complaints', function (Blueprint $table) {
            $table->string('reference_number')->nullable()->unique()->after('id');
            $table->string('category', 50)->default('general')->after('owner_id');
            $table->json('attachments')->nullable()->after('message');
        });

        Schema::create('venue_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained('venues')->cascadeOnDelete();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->json('payload');
            $table->json('event_type_ids')->nullable();
            $table->json('service_ids')->nullable();
            $table->json('image_urls')->nullable();
            $table->string('status', 30)->default('pending');
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('decision_reason')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->index(['venue_id', 'status']);
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->index(['user_id', 'is_read', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'is_read', 'created_at']);
        });
        Schema::dropIfExists('venue_revisions');
        Schema::table('complaints', function (Blueprint $table) {
            $table->dropUnique(['reference_number']);
            $table->dropColumn(['reference_number', 'category', 'attachments']);
        });
        Schema::table('offers', function (Blueprint $table) {
            $table->dropIndex(['status', 'start_date', 'end_date']);
            $table->dropColumn(['discount_currency', 'rejection_reason']);
        });
        Schema::dropIfExists('payment_transactions');
        Schema::table('payment_proofs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('invoice_id');
        });
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('booking_change_requests');
        Schema::dropIfExists('booking_status_histories');
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['customer_id', 'booking_status']);
            $table->dropIndex(['owner_id', 'booking_status']);
            $table->dropConstrainedForeignId('event_id');
            $table->dropColumn('rejection_reason');
        });
        Schema::dropIfExists('event_todo_items');
        Schema::dropIfExists('events');
        Schema::table('services', function (Blueprint $table) {
            $table->dropIndex(['provider_id', 'approval_status']);
            $table->dropConstrainedForeignId('category_id');
            $table->dropColumn(['approval_status', 'rejection_reason', 'image_url']);
        });
        Schema::dropIfExists('service_categories');
        Schema::dropIfExists('password_reset_otps');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['birth_date', 'last_login_at']);
        });
    }
};
