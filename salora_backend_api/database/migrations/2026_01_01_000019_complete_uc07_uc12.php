<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('owner_requests', function (Blueprint $table) {
            $table->foreignId('applicant_user_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
            $table->timestamp('email_verified_at')->nullable()->after('email');
            $table->foreignId('service_category_id')->nullable()->after('service_category')->constrained('service_categories')->nullOnDelete();
            $table->index(['applicant_user_id', 'status']);
        });

        Schema::create('provider_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('city', 120)->nullable();
            $table->text('bio')->nullable();
            $table->string('contact_phone', 40)->nullable();
            $table->string('whatsapp_phone', 40)->nullable();
            $table->boolean('allow_phone')->default(true);
            $table->boolean('allow_whatsapp')->default(true);
            $table->timestamps();
            $table->index(['city', 'allow_phone', 'allow_whatsapp']);
        });

        Schema::table('service_categories', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('id')->constrained('service_categories')->nullOnDelete();
            $table->string('image_url', 1000)->nullable()->after('description');
            $table->index(['parent_id', 'is_active', 'sort_order']);
        });

        Schema::create('service_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->string('image_url', 1000);
            $table->boolean('is_main')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['service_id', 'sort_order']);
        });

        // Preserve every existing single service image as the first gallery image.
        DB::table('services')
            ->whereNotNull('image_url')
            ->where('image_url', '!=', '')
            ->orderBy('id')
            ->each(function ($service): void {
                DB::table('service_images')->insert([
                    'service_id' => $service->id,
                    'image_url' => $service->image_url,
                    'is_main' => true,
                    'sort_order' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        Schema::table('venues', function (Blueprint $table) {
            $table->string('google_place_id')->nullable()->after('map_url');
            $table->json('opening_hours')->nullable()->after('vendor_categories');
        });

        Schema::table('venue_revisions', function (Blueprint $table) {
            $table->boolean('replace_images')->default(false)->after('image_urls');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->after('admin_payment_decision_at');
            $table->index(['venue_id', 'event_date', 'booking_status', 'expires_at'], 'bookings_availability_idx');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex('bookings_availability_idx');
            $table->dropColumn('expires_at');
        });

        Schema::table('venue_revisions', function (Blueprint $table) {
            $table->dropColumn('replace_images');
        });

        Schema::table('venues', function (Blueprint $table) {
            $table->dropColumn(['google_place_id', 'opening_hours']);
        });

        Schema::dropIfExists('service_images');

        Schema::table('service_categories', function (Blueprint $table) {
            $table->dropIndex(['parent_id', 'is_active', 'sort_order']);
            $table->dropConstrainedForeignId('parent_id');
            $table->dropColumn('image_url');
        });

        Schema::dropIfExists('provider_profiles');

        Schema::table('owner_requests', function (Blueprint $table) {
            $table->dropIndex(['applicant_user_id', 'status']);
            $table->dropConstrainedForeignId('applicant_user_id');
            $table->dropConstrainedForeignId('service_category_id');
            $table->dropColumn('email_verified_at');
        });
    }
};
