<?php

namespace App\Console\Commands;

use App\Services\BookingConflictService;
use Illuminate\Console\Command;

class ExpireBookingHolds extends Command
{
    protected $signature = 'salora:expire-booking-holds';

    protected $description =
        'ينهي الحجوزات المؤقتة التي تجاوزت مدة الانتظار.';

    public function handle(BookingConflictService $conflicts): int
    {
        $count = $conflicts->expireTemporaryHolds();
        $this->info('Expired booking holds: '.$count);

        return self::SUCCESS;
    }
}