<?php

namespace App\Services;

use App\Models\DeviceToken;
use App\Models\Notification;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class NotificationDeliveryService
{
    public function deliver(Notification $notification): bool
    {
        if ($notification->getAttribute('push_sent_at') !== null) {
            return true;
        }

        $data = $notification->getAttribute('data_json');

        if (is_string($data)) {
            $decoded = json_decode($data, true);
            $data = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($data)) {
            $data = [];
        }

        $hasDevice = DeviceToken::query()
            ->where('user_id', $notification->getAttribute('user_id'))
            ->whereNotNull('token')
            ->exists();

        if (!$hasDevice) {
            $this->markDelivered($notification);

            return true;
        }

        try {
            $push = app(PushNotificationService::class);

            if (!$push->isConfigured()) {
                throw new RuntimeException(
                    'Firebase backend credentials are not configured.',
                );
            }

            $sent = $push->sendToUser(
                (int) $notification->getAttribute('user_id'),
                (string) $notification->getAttribute('title'),
                $notification->getAttribute('body'),
                [
                    'notification_id' => (string) $notification->getKey(),
                    'type' => (string) (
                        $notification->getAttribute('type') ?: 'system'
                    ),
                    ...$data,
                ],
            );

            if ($sent < 1) {
                throw new RuntimeException(
                    'Firebase did not accept the notification for any registered device.',
                );
            }

            $this->markDelivered($notification);

            return true;
        } catch (Throwable $exception) {
            DB::table($notification->getTable())
                ->where(
                    $notification->getKeyName(),
                    $notification->getKey(),
                )
                ->update([
                    'push_attempted_at' => now(),
                    'push_error' => mb_substr(
                        $exception->getMessage(),
                        0,
                        1000,
                    ),
                ]);

            report($exception);

            return false;
        }
    }

    public function deliverPending(int $limit = 100): array
    {
        $processed = 0;
        $failed = 0;

        Notification::query()
            ->whereNull('push_sent_at')
            ->orderBy('id')
            ->limit(max(1, $limit))
            ->get()
            ->each(function (Notification $notification) use (
                &$processed,
                &$failed,
            ): void {
                $processed++;

                if (!$this->deliver($notification)) {
                    $failed++;
                }
            });

        return compact('processed', 'failed');
    }

    private function markDelivered(Notification $notification): void
    {
        DB::table($notification->getTable())
            ->where(
                $notification->getKeyName(),
                $notification->getKey(),
            )
            ->update([
                'push_attempted_at' => now(),
                'push_sent_at' => now(),
                'push_error' => null,
            ]);
    }
}
