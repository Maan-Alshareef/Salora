<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ProcessPaymentDeadlines extends Command
{
    protected $signature = 'salora:process-payment-deadlines';
    protected $description = 'Deprecated: Salora payment and proof review no longer have time limits.';

    public function handle(): int
    {
        $this->info('No action: payment and proof-review deadlines are disabled.');
        return self::SUCCESS;
    }
}
