<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ExpireBookingHolds extends Command
{
    protected $signature = 'salora:expire-booking-holds';

    protected $description = 'أمر توافق قديم؛ لا توجد مهلة دفع أو انتهاء تلقائي للحجوزات.';

    public function handle(): int
    {
        $this->info('No action: automatic booking/payment hold expiry is disabled in Salora.');
        return self::SUCCESS;
    }
}
