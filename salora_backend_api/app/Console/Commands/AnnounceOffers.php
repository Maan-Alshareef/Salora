<?php

namespace App\Console\Commands;

use App\Services\OfferAnnouncementService;
use Illuminate\Console\Command;

class AnnounceOffers extends Command
{
    protected $signature = 'salora:announce-offers {--prime}';

    protected $description =
        'يرسل العروض المعتمدة الجديدة للعملاء أو يعلّم العروض القديمة دون إرسال.';

    public function handle(OfferAnnouncementService $announcements): int
    {
        $result = $announcements->processAll(
            (bool) $this->option('prime'),
        );

        $this->info(
            sprintf(
                'Processed: %d, changed: %d, prime: %s',
                $result['processed'],
                $result['announced'],
                $this->option('prime') ? 'yes' : 'no',
            ),
        );

        return self::SUCCESS;
    }
}