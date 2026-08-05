<?php

namespace App\Console\Commands;

use App\Services\BookingConflictService;
use Illuminate\Console\Command;

class ReportBookingConflicts extends Command
{
    protected $signature = 'salora:booking-conflicts {--json}';

    protected $description =
        'يعرض تعارضات الحجوزات الحالية مع فترة تجهيز الصالة.';

    public function handle(BookingConflictService $conflicts): int
    {
        $schema = $conflicts->validateSchema();
        $items = $conflicts->report();

        if ($this->option('json')) {
            $this->line(json_encode([
                'schema' => $schema,
                'preparation_minutes' => $conflicts->preparationMinutes(),
                'conflicts' => $items,
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->info(
            'فترة التجهيز: '.$conflicts->preparationMinutes().' دقيقة.',
        );

        if ($items === []) {
            $this->info('لا توجد تعارضات حالية.');
            return self::SUCCESS;
        }

        $rows = array_map(
            fn (array $item): array => [
                $item['venue_id'],
                $item['first']['id'],
                $item['first']['start'],
                $item['first']['end'],
                $item['second']['id'],
                $item['second']['start'],
                $item['second']['end'],
            ],
            $items,
        );

        $this->table(
            [
                'venue',
                'booking 1',
                'start 1',
                'end 1',
                'booking 2',
                'start 2',
                'end 2',
            ],
            $rows,
        );

        $this->warn(
            'لم يتم حذف أو إلغاء أي حجز قديم تلقائياً. راجع الحجوزات الظاهرة من الإدارة.',
        );

        return self::SUCCESS;
    }
}