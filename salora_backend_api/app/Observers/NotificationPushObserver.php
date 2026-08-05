<?php

namespace App\Observers;

use App\Models\Notification;
use App\Services\NotificationDeliveryService;
use Illuminate\Support\Facades\DB;

class NotificationPushObserver
{
    public function __construct(
        private readonly NotificationDeliveryService $delivery,
    ) {
    }

    public function created(Notification $notification): void
    {
        $notificationId = $notification->getKey();

        DB::afterCommit(function () use ($notificationId): void {
            $fresh = Notification::query()->find($notificationId);

            if ($fresh !== null) {
                $this->delivery->deliver($fresh);
            }
        });
    }
}