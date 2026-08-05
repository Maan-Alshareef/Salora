<?php

namespace App\Console\Commands;

use App\Services\NotificationDeliveryService;
use Illuminate\Console\Command;

class PushPendingNotifications extends Command
{
    protected $signature =
        'salora:push-pending-notifications {--limit=100}';

    protected $description =
        'يعيد محاولة إرسال الإشعارات الجديدة غير المعالجة.';

    public function handle(NotificationDeliveryService $delivery): int
    {
        $result = $delivery->deliverPending(
            max(1, (int) $this->option('limit')),
        );

        $this->info(
            sprintf(
                'Processed: %d, failed: %d',
                $result['processed'],
                $result['failed'],
            ),
        );

        return $result['failed'] > 0
            ? self::FAILURE
            : self::SUCCESS;
    }
}