<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\BookingStatusHistory;
use App\Services\VenueAvailabilityService;
use App\Support\SaloraStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ResolveBookingConflicts extends Command
{
    protected $signature = 'salora:resolve-booking-conflicts
        {--apply-safe : Expire only conflicting unpaid temporary/pending bookings}
        {--all-dates : Include past dates in the report}';

    protected $description = 'Find overlapping venue bookings and safely release duplicate unpaid temporary holds.';

    public function handle(): int
    {
        $query = Booking::query()
            ->with(['venue:id,name_ar,name_en', 'invoice:id,booking_id,status'])
            ->whereIn('booking_status', VenueAvailabilityService::BLOCKING_STATUSES)
            ->orderBy('venue_id')
            ->orderBy('event_date')
            ->orderBy('created_at');

        if (! $this->option('all-dates')) {
            $query->whereDate('event_date', '>=', now()->startOfDay()->toDateString());
        }

        $groups = $query->get()->groupBy(fn (Booking $booking) => $booking->venue_id.'|'.$booking->event_date?->toDateString());
        $conflicts = collect();

        foreach ($groups as $group) {
            $accepted = collect();
            /** @var Collection<int, Booking> $ordered */
            $ordered = $group->sort(function (Booking $left, Booking $right): int {
                $priority = $this->priority($right) <=> $this->priority($left);
                if ($priority !== 0) return $priority;

                $created = strcmp((string) $left->created_at, (string) $right->created_at);
                return $created !== 0 ? $created : ($left->id <=> $right->id);
            })->values();

            foreach ($ordered as $candidate) {
                $winner = $accepted->first(fn (Booking $kept) => $this->overlaps($candidate, $kept));
                if ($winner) {
                    $conflicts->push(['winner' => $winner, 'loser' => $candidate]);
                } else {
                    $accepted->push($candidate);
                }
            }
        }

        if ($conflicts->isEmpty()) {
            $this->info('No overlapping active bookings were found.');
            return self::SUCCESS;
        }

        $this->warn('Overlapping bookings found: '.$conflicts->count());
        $this->table(
            ['Venue', 'Date', 'Keep', 'Keep status/time', 'Conflicting', 'Conflicting status/time', 'Safe action'],
            $conflicts->map(function (array $row) {
                /** @var Booking $winner */
                $winner = $row['winner'];
                /** @var Booking $loser */
                $loser = $row['loser'];
                return [
                    $winner->venue?->name_ar ?: $winner->venue?->name_en ?: '#'.$winner->venue_id,
                    $winner->event_date?->toDateString(),
                    $winner->booking_number ?: '#'.$winner->id,
                    $winner->booking_status.' '.$this->timeRange($winner),
                    $loser->booking_number ?: '#'.$loser->id,
                    $loser->booking_status.' '.$this->timeRange($loser),
                    $this->canExpireSafely($loser) ? 'expire unpaid hold' : 'manual review required',
                ];
            })->all()
        );

        if (! $this->option('apply-safe')) {
            $this->newLine();
            $this->line('Dry run only. Run with --apply-safe to expire conflicting unpaid temporary bookings.');
            return self::SUCCESS;
        }

        $expired = 0;
        $manual = 0;
        foreach ($conflicts as $row) {
            /** @var Booking $winner */
            $winner = $row['winner'];
            /** @var Booking $loser */
            $loser = $row['loser'];

            if (! $this->canExpireSafely($loser)) {
                $manual++;
                continue;
            }

            DB::transaction(function () use ($loser, $winner): void {
                $locked = Booking::whereKey($loser->id)->lockForUpdate()->firstOrFail();
                if (! $this->canExpireSafely($locked)) return;

                $from = $locked->booking_status;
                $locked->update([
                    'booking_status' => SaloraStatus::BOOKING_EXPIRED,
                    'expires_at' => null,
                    'rejection_reason' => 'تم تحرير الموعد تلقائياً لأن الحجز يتعارض مع حجز أقدم أو مؤكد.',
                    'commission_status' => 'not_due',
                    'commission_collected_at' => null,
                ]);
                $locked->invoice?->update(['status' => 'cancelled', 'paid_at' => null]);

                BookingStatusHistory::create([
                    'booking_id' => $locked->id,
                    'from_status' => $from,
                    'to_status' => SaloraStatus::BOOKING_EXPIRED,
                    'changed_by' => null,
                    'reason' => 'Safe conflict cleanup; kept booking '.($winner->booking_number ?: '#'.$winner->id).'.',
                ]);
            });
            $expired++;
        }

        $this->info("Safely expired {$expired} conflicting unpaid booking(s).");
        if ($manual > 0) {
            $this->warn("{$manual} conflict(s) contain payment proof/confirmed data and were not changed. Review them manually in the dashboard.");
        }

        return self::SUCCESS;
    }

    private function canExpireSafely(Booking $booking): bool
    {
        return in_array($booking->booking_status, [
            SaloraStatus::BOOKING_PENDING_OWNER_REVIEW,
            SaloraStatus::BOOKING_PENDING_PAYMENT,
            'pending',
            'owner_approved',
            'approved_by_owner',
        ], true) && in_array($booking->payment_status, [
            SaloraStatus::PAYMENT_UNPAID,
            SaloraStatus::PAYMENT_REJECTED,
            null,
            '',
        ], true);
    }

    private function priority(Booking $booking): int
    {
        return match ($booking->booking_status) {
            SaloraStatus::BOOKING_CONFIRMED,
            SaloraStatus::BOOKING_MODIFICATION_REQUESTED,
            SaloraStatus::BOOKING_CANCELLATION_REQUESTED,
            'paid', 'approved' => 100,
            SaloraStatus::BOOKING_PAYMENT_UNDER_REVIEW,
            'payment_uploaded' => 80,
            SaloraStatus::BOOKING_PENDING_PAYMENT,
            'owner_approved', 'approved_by_owner' => 50,
            default => 40,
        };
    }

    private function overlaps(Booking $a, Booking $b): bool
    {
        return $this->minutes((string) $a->start_time) < $this->minutes((string) $b->end_time)
            && $this->minutes((string) $a->end_time) > $this->minutes((string) $b->start_time);
    }

    private function timeRange(Booking $booking): string
    {
        return substr((string) $booking->start_time, 0, 5).'-'.substr((string) $booking->end_time, 0, 5);
    }

    private function minutes(string $time): int
    {
        if (! preg_match('/^(\d{1,2}):(\d{2})/', trim($time), $matches)) return 0;
        return ((int) $matches[1] * 60) + (int) $matches[2];
    }
}
