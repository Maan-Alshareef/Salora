<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\EventType;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServiceImage;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\ExchangeRateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProviderServiceController extends BaseApiController
{
    private const MAX_IMAGES = 6;

    public function index(Request $request)
    {
        return $this->ok(Service::with(['categoryModel.parent', 'images'])
            ->where('provider_id', $request->user()->id)
            ->latest()
            ->get());
    }

    public function store(Request $request)
    {
        $data = $this->normalizedPayload($request, false);
        $service = Service::create([
            ...$data,
            'name_en' => $data['name_en'] ?? $data['name_ar'],
            'description_en' => $data['description_en'] ?? ($data['description_ar'] ?? null),
            'type' => 'external_vendor',
            'pricing_unit' => 'per_event',
            'provider_id' => $request->user()->id,
            'is_active' => false,
            'approval_status' => 'pending',
        ]);

        $this->notifyAdmins($service);

        return $this->ok(
            $service->load(['categoryModel.parent', 'images']),
            'تم حفظ الخدمة. أضف صورة واحدة على الأقل (حتى 6 صور) ثم ستبقى بانتظار اعتماد الأدمن.',
            201
        );
    }

    public function update(Request $request, Service $service)
    {
        $this->authorizeService($request, $service);
        $data = $this->normalizedPayload($request, true);
        $service->update([
            ...$data,
            'type' => 'external_vendor',
            'pricing_unit' => 'per_event',
            'provider_id' => $request->user()->id,
            'is_active' => false,
            'approval_status' => 'pending',
            'rejection_reason' => null,
        ]);

        $this->notifyAdmins($service);

        return $this->ok(
            $service->fresh(['categoryModel.parent', 'images']),
            'تم تحديث الخدمة وإعادتها لمراجعة الأدمن.'
        );
    }

    /**
     * Accepts either a legacy single "image" field or an "images[]" gallery.
     */
    public function uploadImages(Request $request, Service $service)
    {
        $this->authorizeService($request, $service);
        $request->validate([
            'image' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:4096',
            'images' => 'nullable|array|min:1|max:6',
            'images.*' => 'image|mimes:jpeg,jpg,png,webp|max:4096',
            'make_first_main' => 'nullable|boolean',
        ]);

        $files = collect($request->file('images', []));
        if ($request->hasFile('image')) $files->prepend($request->file('image'));
        if ($files->isEmpty()) return $this->fail('اختر صورة واحدة على الأقل.', 422);

        $existingCount = $service->images()->count();
        if ($existingCount + $files->count() > self::MAX_IMAGES) {
            return $this->fail('يمكن إضافة 6 صور كحد أقصى لكل خدمة.', 422, [
                'code' => 'service_images_limit',
                'max_images' => self::MAX_IMAGES,
                'current_count' => $existingCount,
            ]);
        }

        $created = DB::transaction(function () use ($request, $service, $files, $existingCount) {
            $makeFirstMain = $request->boolean('make_first_main') || !$service->images()->where('is_main', true)->exists();
            if ($request->boolean('make_first_main')) $service->images()->update(['is_main' => false]);

            $images = collect();
            foreach ($files as $index => $file) {
                $path = $file->store('services/'.$service->id, 'public');
                $images->push(ServiceImage::create([
                    'service_id' => $service->id,
                    'image_url' => '/storage/'.$path,
                    'is_main' => $makeFirstMain && $index === 0,
                    'sort_order' => $existingCount + $index + 1,
                ]));
            }

            $service->update([
                'image_url' => $service->images()->where('is_main', true)->value('image_url')
                    ?: $service->images()->orderBy('sort_order')->value('image_url'),
                'is_active' => false,
                'approval_status' => 'pending',
                'rejection_reason' => null,
            ]);

            return $images;
        });

        $this->notifyAdmins($service);
        return $this->ok([
            'uploaded' => $created,
            'service' => $service->fresh(['categoryModel.parent', 'images']),
            'remaining_slots' => self::MAX_IMAGES - $service->images()->count(),
        ], 'تم رفع صور الخدمة وإعادتها لمراجعة الأدمن.', 201);
    }

    public function deleteImage(Request $request, Service $service, ServiceImage $image)
    {
        $this->authorizeService($request, $service);
        abort_unless((int) $image->service_id === (int) $service->id, 404);

        $wasMain = $image->is_main;
        $this->deleteLocalFile($image->image_url);
        $image->delete();

        if ($wasMain) {
            $next = $service->images()->orderBy('sort_order')->first();
            if ($next) $next->update(['is_main' => true]);
        }
        $this->renumberImages($service);
        $this->markPendingAndSyncCover($service);

        return $this->ok($service->fresh(['categoryModel.parent', 'images']), 'تم حذف الصورة.');
    }

    public function setMainImage(Request $request, Service $service, ServiceImage $image)
    {
        $this->authorizeService($request, $service);
        abort_unless((int) $image->service_id === (int) $service->id, 404);

        DB::transaction(function () use ($service, $image) {
            $service->images()->update(['is_main' => false]);
            $image->update(['is_main' => true]);
            $this->markPendingAndSyncCover($service);
        });

        return $this->ok($service->fresh(['categoryModel.parent', 'images']), 'تم تعيين صورة الغلاف.');
    }

    public function reorderImages(Request $request, Service $service)
    {
        $this->authorizeService($request, $service);
        $data = $request->validate([
            'image_ids' => 'required|array|min:1|max:6',
            'image_ids.*' => 'integer|distinct',
        ]);

        $existingIds = $service->images()->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
        $providedIds = collect($data['image_ids'])->map(fn ($id) => (int) $id)->sort()->values()->all();
        if ($existingIds !== $providedIds) {
            return $this->fail('يجب إرسال جميع صور الخدمة الموجودة عند إعادة الترتيب.', 422);
        }

        DB::transaction(function () use ($service, $data) {
            foreach ($data['image_ids'] as $index => $id) {
                ServiceImage::whereKey($id)->where('service_id', $service->id)->update(['sort_order' => $index + 1]);
            }
            $this->markPendingAndSyncCover($service);
        });

        return $this->ok($service->fresh(['categoryModel.parent', 'images']), 'تم ترتيب الصور.');
    }

    public function destroy(Request $request, Service $service)
    {
        $this->authorizeService($request, $service);
        $service->update(['is_active' => false]);
        return $this->ok($service, 'تم تعطيل الخدمة وإخفاؤها عن الطلبات الجديدة.');
    }

    private function normalizedPayload(Request $request, bool $partial): array
    {
        $data = $this->validated($request, $partial);

        if (array_key_exists('category_id', $data)) {
            $category = ServiceCategory::whereKey($data['category_id'])
                ->where('is_active', true)
                ->whereIn('applies_to', ['provider', 'both'])
                ->firstOrFail();
            $data['category'] = $category->name_en;
        }

        if (array_key_exists('event_type_ids', $data)) {
            $data['available_for'] = EventType::whereIn('id', $data['event_type_ids'])
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->pluck('name_en')
                ->values()
                ->all();
            unset($data['event_type_ids']);
        }

        $exchangeRates = app(ExchangeRateService::class);
        $rate = $exchangeRates->rate();
        if (array_key_exists('price_syp', $data) && (float) $data['price_syp'] > 0) {
            $data['price_usd'] = $exchangeRates->toUsd($data['price_syp'], $rate);
        } elseif (array_key_exists('price_usd', $data) && (float) $data['price_usd'] > 0) {
            $data['price_syp'] = $exchangeRates->toSyp($data['price_usd'], $rate);
        }

        $data['pricing_unit'] = 'per_event';
        unset($data['image_url']);
        return $data;
    }

    private function validated(Request $request, bool $partial): array
    {
        $required = $partial ? 'sometimes' : 'required';
        $eventArrayRule = $partial ? 'sometimes|array|min:1' : 'nullable|array|min:1|required_without:available_for';
        $availableForRule = $partial ? 'sometimes|array|min:1' : 'nullable|array|min:1|required_without:event_type_ids';

        return $request->validate([
            'name_ar' => "$required|string|max:160",
            'name_en' => 'nullable|string|max:160',
            'description_ar' => $partial ? 'sometimes|string|min:10|max:2000' : 'required|string|min:10|max:2000',
            'description_en' => 'nullable|string|max:2000',
            'emoji' => 'nullable|string|max:16',
            'category_id' => [
                $required,
                'integer',
                Rule::exists('service_categories', 'id')->where(
                    fn ($query) => $query->where('is_active', true)->whereIn('applies_to', ['provider', 'both'])
                ),
            ],
            'price_syp' => "$required|numeric|min:1",
            'price_usd' => 'nullable|numeric|min:0',
            'duration_minutes' => 'nullable|integer|min:15|max:10080',
            'event_type_ids' => $eventArrayRule,
            'event_type_ids.*' => [
                'integer',
                Rule::exists('event_types', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'available_for' => $availableForRule,
            'available_for.*' => 'string|max:120',
        ]);
    }

    private function authorizeService(Request $request, Service $service): void
    {
        abort_unless((int) $service->provider_id === (int) $request->user()->id, 403);
        abort_unless($service->type === 'external_vendor', 404);
    }

    private function renumberImages(Service $service): void
    {
        foreach ($service->images()->orderBy('sort_order')->orderBy('id')->get() as $index => $image) {
            if ($image->sort_order !== $index + 1) $image->update(['sort_order' => $index + 1]);
        }
    }

    private function markPendingAndSyncCover(Service $service): void
    {
        $cover = $service->images()->where('is_main', true)->value('image_url')
            ?: $service->images()->orderBy('sort_order')->value('image_url');
        $service->update([
            'image_url' => $cover,
            'is_active' => false,
            'approval_status' => 'pending',
            'rejection_reason' => null,
        ]);
    }

    private function deleteLocalFile(?string $url): void
    {
        $url = trim((string) $url);
        if (str_starts_with($url, '/storage/')) {
            Storage::disk('public')->delete(substr($url, strlen('/storage/')));
        }
    }

    private function notifyAdmins(Service $service): void
    {
        foreach (User::where('role', 'admin')->where('status', 'active')->pluck('id') as $adminId) {
            NotificationService::send(
                $adminId,
                'خدمة بانتظار الاعتماد',
                $service->name_ar ?: $service->name_en,
                'service_pending',
                ['service_id' => $service->id]
            );
        }
    }
}
