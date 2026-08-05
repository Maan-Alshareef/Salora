<?php

namespace App\Observers;

use App\Models\Offer;
use App\Services\OfferAnnouncementService;
use Illuminate\Support\Facades\DB;

class OfferAnnouncementObserver
{
    public function __construct(
        private readonly OfferAnnouncementService $announcements,
    ) {
    }

    public function saved(Offer $offer): void
    {
        $offerId = $offer->getKey();

        DB::afterCommit(function () use ($offerId): void {
            $fresh = Offer::query()->find($offerId);

            if ($fresh !== null) {
                $this->announcements->process($fresh);
            }
        });
    }
}