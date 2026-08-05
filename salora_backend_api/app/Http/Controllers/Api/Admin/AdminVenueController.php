<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\EventType;
use App\Models\Service;
use App\Models\Venue;
use App\Models\VenueImage;
use App\Models\VenueVideo;
use App\Models\VenueRevision;
use App\Services\ActivityLogger;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AdminVenueController extends BaseApiController
{
    private const MAX_IMAGES = 10;
    private const MAX_VIDEOS = 5;

    public function index()
    {
        return $this->ok(Venue::with([
            'owner:id,name,email,phone,avatar,status', 'images', 'videos', 'eventTypes', 'services.images', 'pendingRevision',
        ])->latest()->get());
    }

    public function show(Venue $venue)
    {
        return $this->ok($venue->load([
            'owner:id,name,email,phone,avatar,status', 'images', 'videos', 'eventTypes', 'services.images',
            'reviews.customer:id,name,avatar', 'revisions.owner:id,name', 'revisions.admin:id,name',
        ]));
    }

    public function approve(Venue $venue)
    {
        if ($venue->status !== 'pending') return $this->fail('يمكن اعتماد الصالات قيد المراجعة فقط.', 422);
        if (!$venue->images()->exists()) {
            return $this->fail('لا يمكن اعتماد الصالة قبل إضافة صورة واحدة على الأقل.', 422, ['code' => 'venue_image_required']);
        }
        if ($venue->latitude === null || $venue->longitude === null) {
            return $this->fail('لا يمكن اعتماد الصالة قبل تحديد موقعها الدقيق على الخريطة.', 422, ['code' => 'venue_location_required']);
        }
        $venue->update(['status' => 'approved', 'rejection_reason' => null]);
        $venue->owner?->forceFill(['business_status' => 'approved', 'business_rejection_reason' => null])->save();
        NotificationService::send($venue->owner_id, 'تم اعتماد الصالة', $venue->name_ar ?: $venue->name_en, 'venue_approved', ['venue_id' => $venue->id]);
        ActivityLogger::log('approved_venue', 'venue', $venue->id);
        return $this->ok($venue->fresh(['images', 'videos', 'eventTypes', 'services.images']), 'تم اعتماد الصالة.');
    }

    public function reject(Request $request, Venue $venue)
    {
        $data = $request->validate(['reason' => 'required|string|max:500']);
        if ($venue->status !== 'pending') return $this->fail('يمكن رفض الصالات قيد المراجعة فقط.', 422);
        $venue->update(['status' => 'rejected', 'rejection_reason' => $data['reason']]);
        NotificationService::send($venue->owner_id, 'تم رفض الصالة', $data['reason'], 'venue_rejected', ['venue_id' => $venue->id]);
        ActivityLogger::log('rejected_venue', 'venue', $venue->id, $data['reason']);
        return $this->ok($venue, 'تم رفض الصالة.');
    }

    public function disable(Venue $venue)
    {
        if ($venue->bookings()->whereIn('booking_status', ['pending_owner_review', 'pending_payment', 'payment_under_review', 'confirmed'])->exists()) {
            return $this->fail('لا يمكن تعطيل الصالة بينما لديها حجوزات فعالة.', 422);
        }
        $venue->update(['status' => 'disabled']);
        NotificationService::send($venue->owner_id, 'تم تعطيل الصالة', $venue->name_ar ?: $venue->name_en, 'venue_disabled', ['venue_id' => $venue->id]);
        ActivityLogger::log('disabled_venue', 'venue', $venue->id);
        return $this->ok($venue, 'تم تعطيل الصالة.');
    }

    public function revisions(Request $request)
    {
        $query = VenueRevision::with([
            'venue.owner:id,name,email,phone,avatar',
            'venue.images', 'venue.videos', 'venue.eventTypes', 'venue.services.images',
            'owner:id,name,email,phone,avatar', 'admin:id,name',
        ])->latest();
        if ($request->filled('status')) $query->where('status', $request->query('status'));

        $items = $query->get()->map(fn (VenueRevision $revision) => $this->decorateRevision($revision));
        return $this->ok($items);
    }

    public function approveRevision(Request $request, VenueRevision $revision)
    {
        if ($revision->status !== 'pending') return $this->fail('تمت مراجعة هذا التعديل مسبقاً.', 422);
        if ($revision->replace_images) {
            $images = array_values(array_filter((array) $revision->image_urls));
            if ($images === [] || count($images) > self::MAX_IMAGES) {
                return $this->fail('يجب أن تحتوي النسخة المعدلة على صورة واحدة إلى 10 صور.', 422);
            }
        }
        if ($revision->replace_videos) {
            $videos = array_values(array_filter((array) $revision->video_urls));
            if (count($videos) > self::MAX_VIDEOS) {
                return $this->fail('يمكن إضافة 5 فيديوهات كحد أقصى لكل صالة.', 422);
            }
        }

        $oldUrlsToDelete = [];
        $oldVideoUrlsToDelete = [];
        $revision = DB::transaction(function () use ($request, $revision, &$oldUrlsToDelete, &$oldVideoUrlsToDelete) {
            $locked = VenueRevision::whereKey($revision->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'pending') abort(409, 'Revision was reviewed by another administrator.');
            $venue = Venue::whereKey($locked->venue_id)->lockForUpdate()->firstOrFail();
            $venue->update([...($locked->payload ?? []), 'status' => 'approved', 'rejection_reason' => null]);
            $venue->owner?->forceFill(['business_status' => 'approved', 'business_rejection_reason' => null])->save();

            if ($locked->event_type_ids !== null) $venue->eventTypes()->sync($locked->event_type_ids);
            if ($locked->service_ids !== null) $venue->services()->sync($locked->service_ids);

            if ($locked->replace_images) {
                $newUrls = array_values(array_unique((array) $locked->image_urls));
                $oldUrls = $venue->images()->pluck('image_url')->all();
                $venue->images()->delete();
                foreach ($newUrls as $index => $url) {
                    VenueImage::create([
                        'venue_id' => $venue->id,
                        'image_url' => $url,
                        'is_main' => $index === 0,
                        'sort_order' => $index + 1,
                    ]);
                }
                $oldUrlsToDelete = array_values(array_diff($oldUrls, $newUrls));
            }

            if ($locked->replace_videos) {
                $newVideoUrls = array_values(array_unique((array) $locked->video_urls));
                $oldVideoUrls = $venue->videos()->pluck('video_url')->all();
                $venue->videos()->delete();
                foreach ($newVideoUrls as $index => $url) {
                    VenueVideo::create([
                        'venue_id' => $venue->id,
                        'video_url' => $url,
                        'sort_order' => $index + 1,
                    ]);
                }
                $oldVideoUrlsToDelete = array_values(array_diff($oldVideoUrls, $newVideoUrls));
            }

            $locked->update([
                'status' => 'approved',
                'admin_id' => $request->user()->id,
                'decision_reason' => null,
                'decided_at' => now(),
            ]);

            NotificationService::send($venue->owner_id, 'تم اعتماد تعديلات الصالة', $venue->name_ar ?: $venue->name_en, 'venue_revision_approved', ['venue_id' => $venue->id, 'revision_id' => $locked->id]);
            ActivityLogger::log('approved_venue_revision', 'venue_revision', $locked->id);
            return $locked->fresh([
                'venue.owner:id,name,email,phone,avatar', 'venue.images', 'venue.videos', 'venue.eventTypes', 'venue.services.images',
                'owner:id,name,email', 'admin:id,name',
            ]);
        });

        foreach ($oldUrlsToDelete as $url) $this->deleteLocalFile($url);
        foreach ($oldVideoUrlsToDelete as $url) $this->deleteLocalFile($url);
        return $this->ok($this->decorateRevision($revision), 'تم اعتماد التعديل ونشر النسخة الجديدة دفعة واحدة.');
    }

    public function rejectRevision(Request $request, VenueRevision $revision)
    {
        if ($revision->status !== 'pending') return $this->fail('تمت مراجعة هذا التعديل مسبقاً.', 422);
        $data = $request->validate(['reason' => 'required|string|max:500']);
        $revision->update([
            'status' => 'rejected',
            'admin_id' => $request->user()->id,
            'decision_reason' => $data['reason'],
            'decided_at' => now(),
        ]);
        NotificationService::send($revision->owner_id, 'تم رفض تعديلات الصالة', $data['reason'], 'venue_revision_rejected', ['venue_id' => $revision->venue_id, 'revision_id' => $revision->id]);
        ActivityLogger::log('rejected_venue_revision', 'venue_revision', $revision->id, $data['reason']);
        return $this->ok($revision->fresh(['venue', 'owner:id,name,email', 'admin:id,name']), 'تم رفض تعديل الصالة مع توضيح السبب.');
    }

    private function decorateRevision(VenueRevision $revision): array
    {
        $venue = $revision->venue;
        $eventTypes = $revision->event_type_ids === null
            ? $venue?->eventTypes
            : EventType::whereIn('id', (array) $revision->event_type_ids)->get();
        $services = $revision->service_ids === null
            ? $venue?->services
            : Service::with('images')->whereIn('id', (array) $revision->service_ids)->get();
        $imageUrls = $revision->replace_images
            ? array_values((array) $revision->image_urls)
            : ($venue?->images?->pluck('image_url')->values()->all() ?? []);
        $videoUrls = $revision->replace_videos
            ? array_values((array) $revision->video_urls)
            : ($venue?->videos?->pluck('video_url')->values()->all() ?? []);

        $current = $venue?->toArray() ?? [];
        $proposed = [
            ...$current,
            ...($revision->payload ?? []),
            'event_types' => $eventTypes?->values(),
            'services' => $services?->values(),
            'images' => collect($imageUrls)->values()->map(fn ($url, $index) => [
                'image_url' => $url,
                'is_main' => $index === 0,
                'sort_order' => $index + 1,
            ]),
            'videos' => collect($videoUrls)->values()->map(fn ($url, $index) => [
                'video_url' => $url,
                'sort_order' => $index + 1,
            ]),
        ];

        return [
            ...$revision->toArray(),
            'current_snapshot' => $current,
            'proposed_snapshot' => $proposed,
            'changed_fields' => array_values(array_unique([
                ...array_keys($revision->payload ?? []),
                ...($revision->event_type_ids !== null ? ['event_types'] : []),
                ...($revision->service_ids !== null ? ['services'] : []),
                ...($revision->replace_images ? ['images'] : []),
                ...($revision->replace_videos ? ['videos'] : []),
            ])),
        ];
    }

    private function deleteLocalFile(?string $url): void
    {
        $url = trim((string) $url);
        if (str_starts_with($url, '/storage/')) Storage::disk('public')->delete(substr($url, strlen('/storage/')));
    }
}
