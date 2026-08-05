<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('venue_videos')) {
            Schema::create('venue_videos', function (Blueprint $table) {
                $table->id();
                $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
                $table->string('video_url', 2000);
                $table->string('thumbnail_url', 2000)->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
                $table->index(['venue_id', 'sort_order']);
            });
        }

        if (Schema::hasTable('venue_revisions')) {
            $addVideoUrls = !Schema::hasColumn('venue_revisions', 'video_urls');
            $addReplaceVideos = !Schema::hasColumn('venue_revisions', 'replace_videos');
            if ($addVideoUrls || $addReplaceVideos) {
                Schema::table('venue_revisions', function (Blueprint $table) use ($addVideoUrls, $addReplaceVideos) {
                    if ($addVideoUrls) $table->json('video_urls')->nullable()->after('image_urls');
                    if ($addReplaceVideos) $table->boolean('replace_videos')->default(false)->after('replace_images');
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('venue_revisions')) {
            $dropReplaceVideos = Schema::hasColumn('venue_revisions', 'replace_videos');
            $dropVideoUrls = Schema::hasColumn('venue_revisions', 'video_urls');
            if ($dropReplaceVideos || $dropVideoUrls) {
                Schema::table('venue_revisions', function (Blueprint $table) use ($dropReplaceVideos, $dropVideoUrls) {
                    if ($dropReplaceVideos) $table->dropColumn('replace_videos');
                    if ($dropVideoUrls) $table->dropColumn('video_urls');
                });
            }
        }
        Schema::dropIfExists('venue_videos');
    }
};
