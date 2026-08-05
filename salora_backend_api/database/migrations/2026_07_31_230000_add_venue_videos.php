<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('venue_videos')) {
            Schema::create('venue_videos', function (Blueprint $table) {
                $table->id();
                $table->foreignId('venue_id')->constrained('venues')->cascadeOnDelete();
                $table->string('video_url');
                $table->string('title')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
                $table->index(['venue_id', 'sort_order']);
            });
        }

        if (Schema::hasTable('venue_revisions') && !Schema::hasColumn('venue_revisions', 'video_urls')) {
            Schema::table('venue_revisions', function (Blueprint $table) {
                $table->json('video_urls')->nullable()->after('image_urls');
            });
        }

        if (Schema::hasTable('venue_revisions') && !Schema::hasColumn('venue_revisions', 'replace_videos')) {
            Schema::table('venue_revisions', function (Blueprint $table) {
                $table->boolean('replace_videos')->default(false)->after('replace_images');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('venue_revisions') && Schema::hasColumn('venue_revisions', 'replace_videos')) {
            Schema::table('venue_revisions', function (Blueprint $table) {
                $table->dropColumn('replace_videos');
            });
        }
        if (Schema::hasTable('venue_revisions') && Schema::hasColumn('venue_revisions', 'video_urls')) {
            Schema::table('venue_revisions', function (Blueprint $table) {
                $table->dropColumn('video_urls');
            });
        }
        Schema::dropIfExists('venue_videos');
    }
};
