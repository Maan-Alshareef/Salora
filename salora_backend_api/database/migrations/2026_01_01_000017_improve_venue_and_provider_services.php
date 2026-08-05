<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('service_categories', function (Blueprint $table) {
            $table->string('applies_to', 20)->default('both')->after('description');
            $table->index(['applies_to', 'is_active']);
        });

        Schema::table('services', function (Blueprint $table) {
            $table->string('pricing_unit', 30)->default('per_event')->after('price_usd');
            $table->unsignedInteger('duration_minutes')->nullable()->after('pricing_unit');
        });

        // Ensure a clean existing database still has the minimum categories needed by
        // the hall and provider forms. Existing categories are never duplicated.
        $now = now();
        $requiredCategories = [
            ['name_ar' => 'خدمات الصالة', 'name_en' => 'Hall services', 'description' => 'خدمات داخلية مرتبطة بالصالة.', 'applies_to' => 'hall', 'sort_order' => 1],
            ['name_ar' => 'التصوير', 'name_en' => 'Photography', 'description' => 'خدمات التصوير والفيديو.', 'applies_to' => 'provider', 'sort_order' => 2],
            ['name_ar' => 'الضيافة', 'name_en' => 'Hospitality', 'description' => 'الضيافة والطعام والمشروبات.', 'applies_to' => 'both', 'sort_order' => 3],
            ['name_ar' => 'التجهيزات', 'name_en' => 'Equipment', 'description' => 'الإضاءة والصوت والديكور والتجهيزات.', 'applies_to' => 'both', 'sort_order' => 4],
        ];

        foreach ($requiredCategories as $category) {
            $exists = DB::table('service_categories')
                ->whereRaw('LOWER(name_en) = ?', [strtolower($category['name_en'])])
                ->exists();
            if (! $exists) {
                DB::table('service_categories')->insert([
                    ...$category,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        DB::table('service_categories')
            ->whereRaw('LOWER(name_en) = ?', ['hall services'])
            ->update(['applies_to' => 'hall']);

        DB::table('service_categories')
            ->whereRaw('LOWER(name_en) = ?', ['photography'])
            ->update(['applies_to' => 'provider']);

        DB::table('service_categories')
            ->whereRaw('LOWER(name_en) IN (?, ?)', ['hospitality', 'equipment'])
            ->update(['applies_to' => 'both']);

        $categories = DB::table('service_categories')
            ->where('is_active', true)
            ->get(['id', 'name_en', 'applies_to'])
            ->keyBy(fn ($category) => strtolower(trim((string) $category->name_en)));

        $providerFallback = $categories
            ->first(fn ($category) => in_array($category->applies_to, ['provider', 'both'], true));

        if ($providerFallback) {
            DB::table('services')
                ->where('type', 'external_vendor')
                ->orderBy('id')
                ->chunkById(100, function ($services) use ($categories, $providerFallback) {
                    foreach ($services as $service) {
                        $currentCategory = $service->category_id
                            ? DB::table('service_categories')->where('id', $service->category_id)->first(['id', 'name_en', 'applies_to'])
                            : null;

                        if ($currentCategory && in_array($currentCategory->applies_to, ['provider', 'both'], true)) {
                            continue;
                        }

                        $search = strtolower(trim(implode(' ', array_filter([
                            (string) ($service->category ?? ''),
                            (string) ($service->name_en ?? ''),
                            (string) ($service->name_ar ?? ''),
                        ]))));

                        $target = (match (true) {
                            str_contains($search, 'photo'), str_contains($search, 'تصوير') => $categories->get('photography'),
                            str_contains($search, 'hospital'), str_contains($search, 'cater'), str_contains($search, 'ضياف') => $categories->get('hospitality'),
                            str_contains($search, 'equipment'), str_contains($search, 'decor'), str_contains($search, 'light'), str_contains($search, 'sound'), str_contains($search, 'تجهيز'), str_contains($search, 'ديكور'), str_contains($search, 'إضاءة'), str_contains($search, 'صوت') => $categories->get('equipment'),
                            default => $providerFallback,
                        }) ?? $providerFallback;

                        DB::table('services')->where('id', $service->id)->update([
                            'category_id' => $target->id,
                            'category' => $target->name_en,
                            'pricing_unit' => 'per_event',
                        ]);
                    }
                });
        }

        // Old dashboard versions could create a venue without persisting its event-type pivot.
        // The original selections cannot be recovered, so assign one safe active fallback type;
        // owners can then review and correct the list from the venue edit screen.
        $defaultEventTypeId = DB::table('event_types')
            ->where('is_active', true)
            ->orderByRaw("CASE WHEN LOWER(name_en) = 'wedding' THEN 0 ELSE 1 END")
            ->orderBy('sort_order')
            ->value('id');

        if ($defaultEventTypeId) {
            DB::table('venues')
                ->whereNotExists(function ($query) {
                    $query->selectRaw('1')
                        ->from('venue_event_types')
                        ->whereColumn('venue_event_types.venue_id', 'venues.id');
                })
                ->orderBy('id')
                ->pluck('id')
                ->each(function ($venueId) use ($defaultEventTypeId) {
                    DB::table('venue_event_types')->insertOrIgnore([
                        'venue_id' => $venueId,
                        'event_type_id' => $defaultEventTypeId,
                    ]);
                });
        }
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['pricing_unit', 'duration_minutes']);
        });

        Schema::table('service_categories', function (Blueprint $table) {
            $table->dropIndex(['applies_to', 'is_active']);
            $table->dropColumn('applies_to');
        });
    }
};
