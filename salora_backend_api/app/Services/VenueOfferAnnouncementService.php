<?php

namespace App\Services;

use App\Models\User;
use App\Models\VenueOffer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VenueOfferAnnouncementService
{
    public function announce(VenueOffer $offer, bool $force = false): int
    {
        $offer->loadMissing('venue');
        if (!$offer->is_active || !$offer->published_at) {
            return 0;
        }
        if (!$force && $offer->announcement_sent_at) {
            return 0;
        }

        $venueName = $offer->venue?->name_ar ?: $offer->venue?->name_en ?: 'إحدى صالات Salora';
        $percentage = rtrim(rtrim(number_format((float) $offer->percentage, 2, '.', ''), '0'), '.');
        $title = 'عرض جديد من '.$venueName;
        $body = 'خصم '.$percentage.'% متاح الآن. افتح العرض للاطلاع على التفاصيل والحجز.';

        $query = User::query();
        $columns = Schema::getColumnListing((new User())->getTable());
        if (in_array('role', $columns, true)) {
            $query->where('role', 'customer');
        } elseif (in_array('type', $columns, true)) {
            $query->where('type', 'customer');
        }
        if (in_array('status', $columns, true)) {
            $query->where('status', 'active');
        } elseif (in_array('is_active', $columns, true)) {
            $query->where('is_active', true);
        }

        $sent = 0;
        $query->orderBy((new User())->getKeyName())->chunkById(100, function ($users) use ($offer, $title, $body, $venueName, &$sent): void {
            foreach ($users as $user) {
                NotificationService::send(
                    (int) $user->getKey(),
                    $title,
                    $body,
                    'offer_published',
                    [
                        'event' => 'offer_published',
                        'offer_id' => (string) $offer->id,
                        'venue_id' => (string) $offer->venue_id,
                        'venue_name' => $venueName,
                        'target_route' => 'offer_details',
                    ],
                );
                $sent++;
            }
        });

        if (Schema::hasColumn('venue_offers', 'announcement_sent_at')) {
            DB::table('venue_offers')->where('id', $offer->id)->update(['announcement_sent_at' => now()]);
            $offer->setAttribute('announcement_sent_at', now());
        }

        return $sent;
    }
}
