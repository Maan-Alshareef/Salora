<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\EventType;
use App\Models\Service;
use App\Models\Venue;
use App\Models\VenueImage;
use App\Models\VenueVideo;
use App\Models\VenueRevision;
use App\Services\ActivityLogger;
use App\Services\ExchangeRateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OwnerVenueController extends BaseApiController
{
    private const MAX_IMAGES = 10;
    private const MAX_VIDEOS = 5;
    private const MAX_VIDEO_KILOBYTES = 51200;
    private const WEEK_DAYS = ['saturday', 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday'];

    public function index(Request $request)
    {
        return $this->ok(Venue::with([
            'images', 'videos', 'eventTypes', 'services.images', 'pendingRevision',
        ])->where('owner_id', $request->user()->id)->latest()->get());
    }

    public function show(Request $request, Venue $venue)
    {
        $this->authorizeVenue($request, $venue);
        return $this->ok($venue->load([
            'images', 'videos', 'eventTypes', 'services.images', 'reviews.customer:id,name,avatar',
            'bookings.latestPaymentProof', 'revisions.admin:id,name',
        ]));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request, false);
        $payload = $this->venuePayload($data);
        $eventTypeIds = $this->eventTypeIds($data);
        if ($eventTypeIds === []) {
            throw ValidationException::withMessages(['event_type_ids' => ['اختر نوع مناسبة فعالاً واحداً على الأقل.']]);
        }
        $serviceIds = $this->serviceIds($data);
        $imageUrls = $this->imageUrls($data);
        $videoUrls = $this->videoUrls($data);

        $venue = DB::transaction(function () use ($request, $data, $payload, $eventTypeIds, $serviceIds, $imageUrls, $videoUrls) {
            $venue = Venue::create([
                ...$payload,
                'owner_id' => $request->user()->id,
                'status' => 'pending',
                'currency_base' => $data['currency_base'] ?? 'SYP',
            ]);
            $venue->eventTypes()->sync($eventTypeIds);
            if ($serviceIds !== null) $venue->services()->sync($serviceIds);
            $this->replaceImages($venue, $imageUrls, deleteOldFiles: false);
            $this->replaceVideos($venue, $videoUrls, deleteOldFiles: false);
            return $venue;
        });

        ActivityLogger::log('submitted_venue', 'venue', $venue->id, 'Owner submitted a venue for approval.');
        return $this->ok($venue->load(['images', 'videos', 'eventTypes', 'services.images']), 'تم إرسال الصالة لمراجعة الأدمن.', 201);
    }

    public function update(Request $request, Venue $venue)
    {
        $this->authorizeVenue($request, $venue);
        $data = $this->validated($request, true);
        $payload = $this->venuePayload($data, partial: true);
        $eventTypeIds = $this->eventTypeIds($data);
        if ($eventTypeIds === []) {
            throw ValidationException::withMessages(['event_type_ids' => ['اختر نوع مناسبة فعالاً واحداً على الأقل.']]);
        }
        $serviceIds = $this->serviceIds($data);
        $replaceImages = (bool) ($data['replace_images'] ?? false);
        $imageUrls = $replaceImages ? $this->imageUrls($data) : null;
        if ($replaceImages && $imageUrls === []) {
            throw ValidationException::withMessages(['image_urls' => ['يجب إبقاء صورة واحدة على الأقل للصالة.']]);
        }
        $replaceVideos = (bool) ($data['replace_videos'] ?? false);
        $videoUrls = $replaceVideos ? $this->videoUrls($data) : null;

        if ($venue->status === 'approved') {
            if ($venue->revisions()->where('status', 'pending')->exists()) {
                return $this->fail('يوجد تعديل آخر للصالة بانتظار مراجعة الأدمن.', 422, ['code' => 'venue_revision_pending']);
            }

            // The owner form sends a convenient full snapshot. Persist only values
            // that are actually different from the published venue so the admin
            // revision page shows the real change count instead of 20+ unchanged
            // fields. This also prevents no-op revisions.
            $payload = $this->onlyChangedVenuePayload($venue, $payload);

            // price_usd/price_syp may be derived from the exchange rate. Do not
            // report a derived counterpart as a user edit when the explicitly sent
            // price itself did not change.
            if (array_key_exists('price_syp', $data) && !array_key_exists('price_syp', $payload) && !array_key_exists('price_usd', $data)) {
                unset($payload['price_usd']);
            }
            if (array_key_exists('price_usd', $data) && !array_key_exists('price_usd', $payload) && !array_key_exists('price_syp', $data)) {
                unset($payload['price_syp']);
            }

            if ($eventTypeIds !== null && $this->sameIntegerSet(
                $venue->eventTypes()->pluck('event_types.id')->all(),
                $eventTypeIds,
            )) {
                $eventTypeIds = null;
            }
            if ($serviceIds !== null && $this->sameIntegerSet(
                $venue->services()->pluck('services.id')->all(),
                $serviceIds,
            )) {
                $serviceIds = null;
            }
            if ($replaceImages && $this->sameStringList(
                $venue->images()->orderByDesc('is_main')->orderBy('sort_order')->pluck('image_url')->all(),
                $imageUrls ?? [],
            )) {
                $replaceImages = false;
                $imageUrls = null;
            }
            if ($replaceVideos && $this->sameStringList(
                $venue->videos()->orderBy('sort_order')->pluck('video_url')->all(),
                $videoUrls ?? [],
            )) {
                $replaceVideos = false;
                $videoUrls = null;
            }

            if ($payload === [] && $eventTypeIds === null && $serviceIds === null && ! $replaceImages && ! $replaceVideos) {
                return $this->ok([
                    'venue' => $venue->load(['images', 'videos', 'eventTypes', 'services.images']),
                    'revision' => null,
                    'no_changes' => true,
                ], 'لا توجد تغييرات جديدة لإرسالها للمراجعة.');
            }

            $revision = VenueRevision::create([
                'venue_id' => $venue->id,
                'owner_id' => $request->user()->id,
                'payload' => $payload,
                'event_type_ids' => $eventTypeIds,
                'service_ids' => $serviceIds,
                'image_urls' => $imageUrls,
                'video_urls' => $videoUrls,
                'replace_images' => $replaceImages,
                'replace_videos' => $replaceVideos,
                'status' => 'pending',
            ]);

            ActivityLogger::log('submitted_venue_revision', 'venue_revision', $revision->id, 'Owner submitted venue changes while keeping the published version active.');
            return $this->ok([
                'venue' => $venue->load(['images', 'videos', 'eventTypes', 'services.images']),
                'revision' => $revision,
            ], 'تم إرسال التعديلات. تبقى النسخة الحالية ظاهرة حتى موافقة الأدمن.');
        }

        DB::transaction(function () use ($venue, $payload, $eventTypeIds, $serviceIds, $replaceImages, $imageUrls, $replaceVideos, $videoUrls) {
            $venue->update([...$payload, 'status' => 'pending', 'rejection_reason' => null]);
            if ($eventTypeIds !== null) $venue->eventTypes()->sync($eventTypeIds);
            if ($serviceIds !== null) $venue->services()->sync($serviceIds);
            if ($replaceImages) $this->replaceImages($venue, $imageUrls ?? []);
            if ($replaceVideos) $this->replaceVideos($venue, $videoUrls ?? []);
        });

        ActivityLogger::log('updated_pending_venue', 'venue', $venue->id, 'Owner updated a venue that is not published yet.');
        return $this->ok($venue->fresh(['images', 'videos', 'eventTypes', 'services.images']), 'تم تحديث الصالة وإعادتها للمراجعة.');
    }

    public function uploadImage(Request $request, Venue $venue)
    {
        $this->authorizeVenue($request, $venue);
        $request->validate([
            'image' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:4096',
            'images' => 'nullable|array|min:1|max:10',
            'images.*' => 'image|mimes:jpeg,jpg,png,webp|max:4096',
            'is_main' => 'nullable|boolean',
        ]);

        $files = collect($request->file('images', []));
        if ($request->hasFile('image')) $files->prepend($request->file('image'));
        if ($files->isEmpty()) return $this->fail('اختر صورة واحدة على الأقل.', 422);

        $baseUrls = $this->editableImageUrls($venue);
        if (count($baseUrls) + $files->count() > self::MAX_IMAGES) {
            return $this->fail('يمكن إضافة 10 صور كحد أقصى لكل صالة.', 422, [
                'code' => 'venue_images_limit',
                'max_images' => self::MAX_IMAGES,
                'current_count' => count($baseUrls),
            ]);
        }

        $newUrls = $files->map(function ($file) use ($venue) {
            $path = $file->store('venues/'.$venue->id, 'public');
            return '/storage/'.$path;
        })->values()->all();

        if ($request->boolean('is_main')) {
            $finalUrls = [...$newUrls, ...$baseUrls];
        } else {
            $finalUrls = [...$baseUrls, ...$newUrls];
        }
        $finalUrls = array_values(array_unique($finalUrls));

        if ($venue->status === 'approved') {
            $revision = $venue->revisions()->where('status', 'pending')->latest()->first();
            if (!$revision) {
                $revision = VenueRevision::create([
                    'venue_id' => $venue->id,
                    'owner_id' => $request->user()->id,
                    'payload' => [],
                    'image_urls' => $finalUrls,
                    'replace_images' => true,
                    'status' => 'pending',
                ]);
            } else {
                $revision->update(['image_urls' => $finalUrls, 'replace_images' => true]);
            }

            return $this->ok([
                'uploaded_urls' => $newUrls,
                'final_image_urls' => $finalUrls,
                'pending_revision_id' => $revision->id,
                'remaining_slots' => self::MAX_IMAGES - count($finalUrls),
            ], 'تم رفع الصور إلى طلب تعديل الصالة.', 201);
        }

        $this->replaceImages($venue, $finalUrls);
        return $this->ok([
            'uploaded_urls' => $newUrls,
            'images' => $venue->fresh('images')->images,
            'remaining_slots' => self::MAX_IMAGES - count($finalUrls),
        ], 'تم رفع صور الصالة.', 201);
    }

    public function deleteImage(Request $request, Venue $venue, VenueImage $image)
    {
        $this->authorizeVenue($request, $venue);
        abort_unless((int) $image->venue_id === (int) $venue->id, 404);

        $finalUrls = array_values(array_filter(
            $this->editableImageUrls($venue),
            fn ($url) => $url !== $image->image_url,
        ));
        if ($finalUrls === []) {
            return $this->fail('يجب إبقاء صورة واحدة على الأقل للصالة.', 422);
        }

        if ($venue->status === 'approved') {
            $revision = $this->upsertImageRevision($request, $venue, $finalUrls);
            return $this->ok([
                'final_image_urls' => $finalUrls,
                'pending_revision_id' => $revision->id,
            ], 'تمت إضافة حذف الصورة إلى طلب التعديل بانتظار الأدمن.');
        }

        $this->replaceImages($venue, $finalUrls);
        return $this->ok($venue->fresh('images'), 'تم حذف الصورة.');
    }

    public function setMainImage(Request $request, Venue $venue, VenueImage $image)
    {
        $this->authorizeVenue($request, $venue);
        abort_unless((int) $image->venue_id === (int) $venue->id, 404);

        $urls = $this->editableImageUrls($venue);
        $finalUrls = [$image->image_url, ...array_values(array_filter($urls, fn ($url) => $url !== $image->image_url))];

        if ($venue->status === 'approved') {
            $revision = $this->upsertImageRevision($request, $venue, $finalUrls);
            return $this->ok(['final_image_urls' => $finalUrls, 'pending_revision_id' => $revision->id], 'تم تعيين الغلاف ضمن طلب التعديل.');
        }

        $this->replaceImages($venue, $finalUrls, deleteOldFiles: false);
        return $this->ok($venue->fresh('images'), 'تم تعيين صورة الغلاف.');
    }

    public function reorderImages(Request $request, Venue $venue)
    {
        $this->authorizeVenue($request, $venue);
        $data = $request->validate([
            'image_urls' => 'required|array|min:1|max:10',
            'image_urls.*' => 'required|string|max:2000|distinct',
        ]);

        $existing = collect($this->editableImageUrls($venue))->sort()->values()->all();
        $provided = collect($data['image_urls'])->sort()->values()->all();
        if ($existing !== $provided) {
            return $this->fail('يجب إرسال جميع صور الصالة الموجودة عند إعادة الترتيب.', 422);
        }

        if ($venue->status === 'approved') {
            $revision = $this->upsertImageRevision($request, $venue, array_values($data['image_urls']));
            return $this->ok(['final_image_urls' => $data['image_urls'], 'pending_revision_id' => $revision->id], 'تم ترتيب الصور ضمن طلب التعديل.');
        }

        $this->replaceImages($venue, array_values($data['image_urls']), deleteOldFiles: false);
        return $this->ok($venue->fresh('images'), 'تم ترتيب صور الصالة.');
    }

    public function uploadVideo(Request $request, Venue $venue)
    {
        $this->authorizeVenue($request, $venue);
        $request->validate([
            'video' => 'nullable|file|mimes:mp4,mov,webm,m4v|max:'.self::MAX_VIDEO_KILOBYTES,
            'videos' => 'nullable|array|min:1|max:'.self::MAX_VIDEOS,
            'videos.*' => 'file|mimes:mp4,mov,webm,m4v|max:'.self::MAX_VIDEO_KILOBYTES,
        ]);

        $files = collect($request->file('videos', []));
        if ($request->hasFile('video')) $files->prepend($request->file('video'));
        if ($files->isEmpty()) return $this->fail('اختر فيديو واحداً على الأقل.', 422);

        $baseUrls = $this->editableVideoUrls($venue);
        if (count($baseUrls) + $files->count() > self::MAX_VIDEOS) {
            return $this->fail('يمكن إضافة 5 فيديوهات كحد أقصى لكل صالة.', 422, [
                'code' => 'venue_videos_limit',
                'max_videos' => self::MAX_VIDEOS,
                'current_count' => count($baseUrls),
            ]);
        }

        $newUrls = $files->map(function ($file) use ($venue) {
            $path = $file->store('venues/'.$venue->id.'/videos', 'public');
            return '/storage/'.$path;
        })->values()->all();
        $finalUrls = array_values(array_unique([...$baseUrls, ...$newUrls]));

        if ($venue->status === 'approved') {
            $revision = $this->upsertVideoRevision($request, $venue, $finalUrls);
            return $this->ok([
                'uploaded_urls' => $newUrls,
                'final_video_urls' => $finalUrls,
                'pending_revision_id' => $revision->id,
                'remaining_slots' => self::MAX_VIDEOS - count($finalUrls),
            ], 'تم رفع الفيديوهات إلى طلب تعديل الصالة.', 201);
        }

        $this->replaceVideos($venue, $finalUrls);
        return $this->ok([
            'uploaded_urls' => $newUrls,
            'videos' => $venue->fresh('videos')->videos,
            'remaining_slots' => self::MAX_VIDEOS - count($finalUrls),
        ], 'تم رفع فيديوهات الصالة.', 201);
    }

    public function deleteVideo(Request $request, Venue $venue, VenueVideo $video)
    {
        $this->authorizeVenue($request, $venue);
        abort_unless((int) $video->venue_id === (int) $venue->id, 404);

        $finalUrls = array_values(array_filter(
            $this->editableVideoUrls($venue),
            fn ($url) => $url !== $video->video_url,
        ));

        if ($venue->status === 'approved') {
            $revision = $this->upsertVideoRevision($request, $venue, $finalUrls);
            return $this->ok([
                'final_video_urls' => $finalUrls,
                'pending_revision_id' => $revision->id,
            ], 'تمت إضافة حذف الفيديو إلى طلب التعديل بانتظار الأدمن.');
        }

        $this->replaceVideos($venue, $finalUrls);
        return $this->ok($venue->fresh('videos'), 'تم حذف الفيديو.');
    }

    public function reorderVideos(Request $request, Venue $venue)
    {
        $this->authorizeVenue($request, $venue);
        $data = $request->validate([
            'video_urls' => 'required|array|max:'.self::MAX_VIDEOS,
            'video_urls.*' => 'required|string|max:2000|distinct',
        ]);

        $existing = collect($this->editableVideoUrls($venue))->sort()->values()->all();
        $provided = collect($data['video_urls'])->sort()->values()->all();
        if ($existing !== $provided) {
            return $this->fail('يجب إرسال جميع فيديوهات الصالة الموجودة عند إعادة الترتيب.', 422);
        }

        if ($venue->status === 'approved') {
            $revision = $this->upsertVideoRevision($request, $venue, array_values($data['video_urls']));
            return $this->ok(['final_video_urls' => $data['video_urls'], 'pending_revision_id' => $revision->id], 'تم ترتيب الفيديوهات ضمن طلب التعديل.');
        }

        $this->replaceVideos($venue, array_values($data['video_urls']), deleteOldFiles: false);
        return $this->ok($venue->fresh('videos'), 'تم ترتيب فيديوهات الصالة.');
    }

    public function destroy(Request $request, Venue $venue)
    {
        $this->authorizeVenue($request, $venue);
        if ($venue->bookings()->whereIn('booking_status', ['pending_owner_review', 'pending_payment', 'payment_under_review', 'confirmed'])->exists()) {
            return $this->fail('لا يمكن تعطيل الصالة بينما لديها حجوزات فعالة.', 422);
        }
        $venue->update(['status' => 'disabled']);
        return $this->ok($venue, 'تم تعطيل الصالة.');
    }

    private function validated(Request $request, bool $partial): array
    {
        $required = $partial ? 'sometimes' : 'required';
        $data = $request->validate([
            'name_ar' => 'nullable|string|max:180',
            'name_en' => "$required|string|max:180",
            'description_ar' => 'nullable|string|max:2000',
            'description_en' => 'nullable|string|max:2000',
            'city' => "$required|string|max:120",
            'address' => "$required|string|max:255",
            'map_url' => 'nullable|string|max:700',
            'google_place_id' => 'nullable|string|max:255',
            'latitude' => $partial ? 'sometimes|numeric|between:-90,90|required_with:longitude' : 'required|numeric|between:-90,90',
            'longitude' => $partial ? 'sometimes|numeric|between:-180,180|required_with:latitude' : 'required|numeric|between:-180,180',
            'capacity' => "$required|integer|min:1|max:100000",
            'price_usd' => $partial ? 'sometimes|nullable|numeric|min:0' : 'nullable|numeric|min:0|required_without:price_syp',
            'price_syp' => $partial ? 'sometimes|nullable|numeric|min:0' : 'nullable|numeric|min:0|required_without:price_usd',
            'currency_base' => 'nullable|in:USD,SYP',
            'event_type_ids' => $partial ? 'sometimes|array' : 'nullable|array|required_without:event_types',
            'event_type_ids.*' => ['integer', Rule::exists('event_types', 'id')->where(fn ($q) => $q->where('is_active', true))],
            'event_types' => $partial ? 'sometimes|array|min:1' : 'nullable|array|min:1|required_without:event_type_ids',
            'event_types.*' => 'string',
            'included_services' => 'nullable|array',
            'included_services.*' => 'string',
            'paid_upgrades' => 'nullable|array',
            'paid_upgrades.*' => 'string',
            'service_ids' => 'nullable|array',
            'service_ids.*' => 'integer|exists:services,id',
            'amenities' => 'nullable|array',
            'policies' => 'nullable|array',
            'vendor_categories' => 'nullable|array',
            'vendor_categories.*' => 'string|max:120',
            'opening_hours' => 'nullable|array',
            'opening_hours.*.enabled' => 'nullable|boolean',
            'opening_hours.*.open' => 'nullable|date_format:H:i',
            'opening_hours.*.close' => 'nullable|date_format:H:i',
            'replace_images' => 'nullable|boolean',
            'image_url' => 'nullable|string|max:2000',
            'image_urls' => 'nullable|array|max:10',
            'image_urls.*' => 'string|max:2000|distinct',
            'replace_videos' => 'nullable|boolean',
            'video_url' => 'nullable|string|max:2000',
            'video_urls' => 'nullable|array|max:5',
            'video_urls.*' => 'string|max:2000|distinct',
        ]);

        if ($partial && (array_key_exists('latitude', $data) xor array_key_exists('longitude', $data))) {
            throw ValidationException::withMessages(['latitude' => ['يجب إرسال خط العرض والطول معاً.']]);
        }

        if (($partial && (array_key_exists('event_type_ids', $data) || array_key_exists('event_types', $data))) || !$partial) {
            $hasEventType = collect($data['event_type_ids'] ?? [])->filter()->isNotEmpty()
                || collect($data['event_types'] ?? [])->filter(fn ($value) => trim((string) $value) !== '')->isNotEmpty();
            if (!$hasEventType) {
                throw ValidationException::withMessages(['event_type_ids' => ['اختر نوع مناسبة واحداً على الأقل.']]);
            }
        }

        if (array_key_exists('opening_hours', $data)) {
            $data['opening_hours'] = $this->normalizeOpeningHours($data['opening_hours'] ?? []);
        }

        return $data;
    }

    private function onlyChangedVenuePayload(Venue $venue, array $payload): array
    {
        $changed = [];
        foreach ($payload as $field => $value) {
            if (! $this->venueValuesEqual($field, $venue->{$field} ?? null, $value)) {
                $changed[$field] = $value;
            }
        }
        return $changed;
    }

    private function venueValuesEqual(string $field, mixed $current, mixed $proposed): bool
    {
        if (in_array($field, ['latitude', 'longitude', 'price_syp', 'price_usd'], true)) {
            if (($current === null || $current === '') && ($proposed === null || $proposed === '')) return true;
            return abs((float) $current - (float) $proposed) <= 0.000001;
        }
        if ($field === 'capacity') {
            return (int) $current === (int) $proposed;
        }
        if (in_array($field, ['amenities', 'policies', 'vendor_categories'], true)) {
            return $this->sameStringSet((array) $current, (array) $proposed);
        }
        if ($field === 'opening_hours') {
            return $this->canonicalArray((array) $current) === $this->canonicalArray((array) $proposed);
        }

        $left = trim((string) ($current ?? ''));
        $right = trim((string) ($proposed ?? ''));
        return $left === $right;
    }

    private function sameIntegerSet(array $left, array $right): bool
    {
        $normalize = fn (array $values) => collect($values)->map(fn ($value) => (int) $value)->filter()->unique()->sort()->values()->all();
        return $normalize($left) === $normalize($right);
    }

    private function sameStringSet(array $left, array $right): bool
    {
        $normalize = fn (array $values) => collect($values)
            ->map(fn ($value) => trim((string) $value))
            ->filter(fn ($value) => $value !== '')
            ->unique()
            ->sort()
            ->values()
            ->all();
        return $normalize($left) === $normalize($right);
    }

    private function sameStringList(array $left, array $right): bool
    {
        $normalize = fn (array $values) => collect($values)
            ->map(fn ($value) => trim((string) $value))
            ->filter(fn ($value) => $value !== '')
            ->values()
            ->all();
        return $normalize($left) === $normalize($right);
    }

    private function canonicalArray(array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) $value[$key] = $this->canonicalArray($item);
        }
        if (! array_is_list($value)) ksort($value);
        return $value;
    }

    private function venuePayload(array $data, bool $partial = false): array
    {
        $payload = collect($data)->only([
            'name_ar', 'name_en', 'description_ar', 'description_en', 'city', 'address',
            'map_url', 'google_place_id', 'latitude', 'longitude', 'capacity', 'price_usd', 'price_syp',
            'currency_base', 'amenities', 'policies', 'vendor_categories', 'opening_hours',
        ])->toArray();

        if (!$partial) {
            $payload['name_ar'] = $payload['name_ar'] ?? $payload['name_en'];
            $payload['description_ar'] = $payload['description_ar'] ?? ($payload['description_en'] ?? '');
            $payload['description_en'] = $payload['description_en'] ?? ($payload['description_ar'] ?? '');
        } else {
            // Working hours have their own immediate owner endpoint/page and must
            // never be accidentally reset by the general venue edit snapshot.
            unset($payload['opening_hours']);
        }

        if (isset($payload['latitude'], $payload['longitude']) && empty($payload['map_url'])) {
            $payload['map_url'] = 'https://www.google.com/maps/search/?api=1&query='.$payload['latitude'].','.$payload['longitude'];
        }

        $exchangeRates = app(ExchangeRateService::class);
        $rate = $exchangeRates->rate();
        $baseCurrency = strtoupper((string) ($payload['currency_base'] ?? ''));

        if ($baseCurrency === 'USD' && array_key_exists('price_usd', $payload) && (float) $payload['price_usd'] > 0) {
            $payload['price_syp'] = $exchangeRates->toSyp($payload['price_usd'], $rate);
        } elseif (array_key_exists('price_syp', $payload) && (float) $payload['price_syp'] > 0) {
            $payload['price_usd'] = $exchangeRates->toUsd($payload['price_syp'], $rate);
        } elseif (array_key_exists('price_usd', $payload) && (float) $payload['price_usd'] > 0) {
            $payload['price_syp'] = $exchangeRates->toSyp($payload['price_usd'], $rate);
        }

        return $payload;
    }

    private function normalizeOpeningHours(array $value): array
    {
        $normalized = [];
        foreach (self::WEEK_DAYS as $day) {
            $entry = $value[$day] ?? null;
            if (!is_array($entry)) continue;
            $enabled = filter_var($entry['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $open = $entry['open'] ?? null;
            $close = $entry['close'] ?? null;
            if ($enabled && (!$open || !$close || $open >= $close)) {
                throw ValidationException::withMessages([
                    "opening_hours.$day" => ['حدد وقت فتح وإغلاق صحيحاً، ويجب أن يكون الإغلاق بعد الفتح.'],
                ]);
            }
            $normalized[$day] = ['enabled' => $enabled, 'open' => $enabled ? $open : null, 'close' => $enabled ? $close : null];
        }
        return $normalized;
    }

    private function eventTypeIds(array $data): ?array
    {
        $ids = collect($data['event_type_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->unique()->values()->all();
        if ($ids !== []) return $ids;
        if (!array_key_exists('event_types', $data)) return array_key_exists('event_type_ids', $data) ? [] : null;

        $names = collect($data['event_types'] ?? [])->map(fn ($name) => trim((string) $name))->filter()->unique()->values()->all();
        $types = EventType::where('is_active', true)->where(function ($query) use ($names) {
            $query->whereIn('name_en', $names)->orWhereIn('name_ar', $names);
        })->get(['id', 'name_en', 'name_ar']);
        $resolved = $types->flatMap(fn ($type) => [$type->name_en, $type->name_ar])->filter()->map(fn ($name) => mb_strtolower(trim((string) $name)))->unique();
        $unresolved = collect($names)->map(fn ($name) => mb_strtolower(trim((string) $name)))->contains(fn ($name) => !$resolved->contains($name));
        return $unresolved ? [] : $types->pluck('id')->all();
    }

    private function serviceIds(array $data): ?array
    {
        if (array_key_exists('service_ids', $data)) return array_values(array_unique($data['service_ids'] ?? []));
        if (!array_key_exists('included_services', $data) && !array_key_exists('paid_upgrades', $data)) return null;
        return $this->serviceIdsFromNames($data['included_services'] ?? [], 'included')
            ->merge($this->serviceIdsFromNames($data['paid_upgrades'] ?? [], 'hall_upgrade'))
            ->unique()->values()->all();
    }

    private function imageUrls(array $data): array
    {
        $urls = $data['image_urls'] ?? [];
        if (!empty($data['image_url'])) array_unshift($urls, $data['image_url']);
        return collect($urls)
            ->map(fn ($url) => trim((string) $url))
            ->filter(fn ($url) => $url !== '' && !Str::startsWith($url, ['blob:', 'data:']))
            ->unique()->take(self::MAX_IMAGES)->values()->all();
    }

    private function videoUrls(array $data): array
    {
        $urls = $data['video_urls'] ?? [];
        if (!empty($data['video_url'])) array_unshift($urls, $data['video_url']);
        return collect($urls)
            ->map(fn ($url) => trim((string) $url))
            ->filter(fn ($url) => $url !== '' && !Str::startsWith($url, ['blob:', 'data:']))
            ->unique()->take(self::MAX_VIDEOS)->values()->all();
    }

    private function editableImageUrls(Venue $venue): array
    {
        $pending = $venue->revisions()->where('status', 'pending')->latest()->first();
        if ($pending?->replace_images && is_array($pending->image_urls)) {
            return array_values($pending->image_urls);
        }
        return $venue->images()->orderByDesc('is_main')->orderBy('sort_order')->pluck('image_url')->values()->all();
    }

    private function upsertImageRevision(Request $request, Venue $venue, array $finalUrls): VenueRevision
    {
        $revision = $venue->revisions()->where('status', 'pending')->latest()->first();
        if (!$revision) {
            return VenueRevision::create([
                'venue_id' => $venue->id,
                'owner_id' => $request->user()->id,
                'payload' => [],
                'image_urls' => array_values($finalUrls),
                'replace_images' => true,
                'status' => 'pending',
            ]);
        }
        $revision->update(['image_urls' => array_values($finalUrls), 'replace_images' => true]);
        return $revision->fresh();
    }

    private function editableVideoUrls(Venue $venue): array
    {
        $pending = $venue->revisions()->where('status', 'pending')->latest()->first();
        if ($pending?->replace_videos && is_array($pending->video_urls)) {
            return array_values($pending->video_urls);
        }
        return $venue->videos()->orderBy('sort_order')->pluck('video_url')->values()->all();
    }

    private function upsertVideoRevision(Request $request, Venue $venue, array $finalUrls): VenueRevision
    {
        $revision = $venue->revisions()->where('status', 'pending')->latest()->first();
        if (!$revision) {
            return VenueRevision::create([
                'venue_id' => $venue->id,
                'owner_id' => $request->user()->id,
                'payload' => [],
                'video_urls' => array_values($finalUrls),
                'replace_videos' => true,
                'status' => 'pending',
            ]);
        }
        $revision->update(['video_urls' => array_values($finalUrls), 'replace_videos' => true]);
        return $revision->fresh();
    }

    private function replaceVideos(Venue $venue, array $urls, bool $deleteOldFiles = true): void
    {
        $oldUrls = $venue->videos()->pluck('video_url')->all();
        $venue->videos()->delete();
        foreach (array_values(array_unique($urls)) as $index => $url) {
            VenueVideo::create([
                'venue_id' => $venue->id,
                'video_url' => $url,
                'sort_order' => $index + 1,
            ]);
        }
        if ($deleteOldFiles) {
            foreach (array_diff($oldUrls, $urls) as $url) $this->deleteLocalFile($url);
        }
    }

    private function replaceImages(Venue $venue, array $urls, bool $deleteOldFiles = true): void
    {
        $oldUrls = $venue->images()->pluck('image_url')->all();
        $venue->images()->delete();
        foreach (array_values(array_unique($urls)) as $index => $url) {
            VenueImage::create([
                'venue_id' => $venue->id,
                'image_url' => $url,
                'is_main' => $index === 0,
                'sort_order' => $index + 1,
            ]);
        }
        if ($deleteOldFiles) {
            foreach (array_diff($oldUrls, $urls) as $url) $this->deleteLocalFile($url);
        }
    }

    private function deleteLocalFile(?string $url): void
    {
        $url = trim((string) $url);
        if (str_starts_with($url, '/storage/')) Storage::disk('public')->delete(substr($url, strlen('/storage/')));
    }

    private function serviceIdsFromNames(array $names, string $type)
    {
        $normalized = collect($names)
            ->map(fn ($rawName) => trim((string) preg_replace('/^[^A-Za-z\x{0600}-\x{06FF}]+/u', '', (string) $rawName)))
            ->filter()->unique()->values();
        if ($normalized->isEmpty()) return collect();
        return Service::where('type', $type)->where('is_active', true)->where('approval_status', 'approved')
            ->where(fn ($q) => $q->whereIn('name_en', $normalized)->orWhereIn('name_ar', $normalized))->pluck('id');
    }

    private function authorizeVenue(Request $request, Venue $venue): void
    {
        abort_unless((int) $venue->owner_id === (int) $request->user()->id, 403);
    }
}
