<?php

namespace App\Services;

use App\Exceptions\BookingConflictException;
use App\Models\Booking;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class BookingConflictService
{
    private ?array $columns = null;

    /** @var array<int|string, Lock> */
    private array $locks = [];

    private bool $releaseRegistered = false;

    public function validateSchema(): array
    {
        return $this->columns();
    }

    public function guard(Booking $booking): void
    {
        $columns = $this->columns();
        $venueId = $booking->getAttribute($columns['venue']);

        if ($venueId === null || $venueId === '') {
            return;
        }

        $status = $this->normalizeStatus(
            $booking->getAttribute($columns['status']),
        );

        $this->applyTemporaryHold($booking, $status, $columns);

        if (!$this->blocksTime($booking, $status, $columns)) {
            return;
        }

        [$start, $end] = $this->interval($booking, $columns);

        if ($end->lessThanOrEqualTo($start)) {
            throw ValidationException::withMessages([
                $columns['end'] => 'وقت نهاية الحجز يجب أن يكون بعد وقت البداية.',
            ]);
        }

        $this->acquireVenueLock($venueId);

        try {
            $conflicting = $this->findConflict(
                $booking,
                $venueId,
                $start,
                $end,
                $columns,
            );
        } catch (Throwable $exception) {
            $this->releaseVenueLock($venueId);
            throw $exception;
        }

        if ($conflicting === null) {
            return;
        }

        $this->releaseVenueLock($venueId);

        [$existingStart, $existingEnd] = $this->interval(
            $conflicting,
            $columns,
        );

        $buffer = $this->preparationMinutes();
        $availableAt = $existingEnd->copy()->addMinutes($buffer);

        throw new BookingConflictException([
            'message' => sprintf(
                'الموعد متعارض مع الحجز رقم %s. يجب ترك %d دقيقة تجهيز بين الحجوزات. أقرب بداية بعد هذا الحجز: %s.',
                $conflicting->getKey(),
                $buffer,
                $availableAt->format('Y-m-d H:i'),
            ),
            'booking_id' => $conflicting->getKey(),
            'venue_id' => $venueId,
            'requested_start' => $start->toIso8601String(),
            'requested_end' => $end->toIso8601String(),
            'conflicting_start' => $existingStart->toIso8601String(),
            'conflicting_end' => $existingEnd->toIso8601String(),
            'preparation_minutes' => $buffer,
            'next_available_at' => $availableAt->toIso8601String(),
        ]);
    }

    public function report(): array
    {
        $columns = $this->columns();
        $buffer = $this->preparationMinutes();
        $groups = [];

        Booking::query()
            ->orderBy($columns['venue'])
            ->orderBy($columns['date'] ?: $columns['start'])
            ->orderBy($columns['start'])
            ->cursor()
            ->each(function (Booking $booking) use (&$groups, $columns): void {
                $status = $this->normalizeStatus(
                    $booking->getAttribute($columns['status']),
                );

                if (!$this->blocksTime($booking, $status, $columns)) {
                    return;
                }

                try {
                    [$start, $end] = $this->interval($booking, $columns);
                } catch (Throwable) {
                    return;
                }

                $venueId = $booking->getAttribute($columns['venue']);
                if ($venueId === null || $venueId === '') {
                    return;
                }

                $groups[(string) $venueId][] = [
                    'model' => $booking,
                    'start' => $start,
                    'end' => $end,
                ];
            });

        $conflicts = [];

        foreach ($groups as $venueId => $items) {
            usort(
                $items,
                fn (array $a, array $b): int =>
                    $a['start']->getTimestamp() <=> $b['start']->getTimestamp(),
            );

            $count = count($items);

            for ($left = 0; $left < $count; $left++) {
                for ($right = $left + 1; $right < $count; $right++) {
                    $first = $items[$left];
                    $second = $items[$right];

                    if (
                        $second['start']->greaterThanOrEqualTo(
                            $first['end']->copy()->addMinutes($buffer),
                        )
                    ) {
                        break;
                    }

                    if (
                        $this->overlaps(
                            $first['start'],
                            $first['end'],
                            $second['start'],
                            $second['end'],
                            $buffer,
                        )
                    ) {
                        $conflicts[] = [
                            'venue_id' => $venueId,
                            'preparation_minutes' => $buffer,
                            'first' => $this->bookingSummary(
                                $first['model'],
                                $first['start'],
                                $first['end'],
                                $columns,
                            ),
                            'second' => $this->bookingSummary(
                                $second['model'],
                                $second['start'],
                                $second['end'],
                                $columns,
                            ),
                        ];
                    }
                }
            }
        }

        return $conflicts;
    }

    /**
     * Automatic payment/booking hold expiry is disabled.
     * Kept for backwards-compatible callers.
     */
    public function expireTemporaryHolds(): int
    {
        return 0;
    }

    public function preparationMinutes(): int
    {
        return max(0, (int) config('salora_booking.preparation_minutes', 30));
    }


    private function findConflict(
        Booking $booking,
        int|string $venueId,
        CarbonInterface $start,
        CarbonInterface $end,
        array $columns,
    ): ?Booking {
        $query = Booking::query()
            ->where($columns['venue'], $venueId);

        if ($booking->exists) {
            $query->where(
                $booking->getKeyName(),
                '!=',
                $booking->getKey(),
            );
        }

        if ($columns['date'] !== null) {
            $query->whereBetween(
                $columns['date'],
                [
                    $start->copy()->subDay()->toDateString(),
                    $end->copy()->addDay()->toDateString(),
                ],
            );
        } else {
            $buffer = $this->preparationMinutes();

            $query
                ->where(
                    $columns['start'],
                    '<',
                    $end->copy()->addMinutes($buffer),
                )
                ->where(
                    $columns['end'],
                    '>',
                    $start->copy()->subMinutes($buffer),
                );
        }

        foreach ($query->cursor() as $existing) {
            $status = $this->normalizeStatus(
                $existing->getAttribute($columns['status']),
            );

            if (!$this->blocksTime($existing, $status, $columns)) {
                continue;
            }

            try {
                [$existingStart, $existingEnd] = $this->interval(
                    $existing,
                    $columns,
                );
            } catch (Throwable) {
                continue;
            }

            if (
                $this->overlaps(
                    $start,
                    $end,
                    $existingStart,
                    $existingEnd,
                    $this->preparationMinutes(),
                )
            ) {
                return $existing;
            }
        }

        return null;
    }

    private function overlaps(
        CarbonInterface $firstStart,
        CarbonInterface $firstEnd,
        CarbonInterface $secondStart,
        CarbonInterface $secondEnd,
        int $bufferMinutes,
    ): bool {
        return $firstStart->lessThan(
            $secondEnd->copy()->addMinutes($bufferMinutes),
        ) && $firstEnd->copy()->addMinutes($bufferMinutes)->greaterThan(
            $secondStart,
        );
    }

    private function interval(
        Booking $booking,
        array $columns,
    ): array {
        $dateValue = $columns['date'] !== null
            ? $booking->getAttribute($columns['date'])
            : null;

        $startValue = $booking->getAttribute($columns['start']);
        $endValue = $booking->getAttribute($columns['end']);

        if ($startValue === null || $endValue === null) {
            throw new RuntimeException(
                'Booking start/end values are missing.',
            );
        }

        $start = $this->toDateTime($dateValue, $startValue);
        $end = $this->toDateTime($dateValue, $endValue);

        if ($end->lessThanOrEqualTo($start)) {
            $end = $end->copy()->addDay();
        }

        return [$start, $end];
    }

    private function toDateTime(
        mixed $dateValue,
        mixed $timeValue,
    ): Carbon {
        if ($timeValue instanceof CarbonInterface) {
            return Carbon::instance($timeValue);
        }

        $timeText = trim((string) $timeValue);

        if (
            preg_match(
                '/\d{4}-\d{2}-\d{2}[T\s]/',
                $timeText,
            ) === 1
        ) {
            return Carbon::parse($timeText);
        }

        if ($dateValue instanceof CarbonInterface) {
            $dateText = $dateValue->format('Y-m-d');
        } else {
            $dateText = trim((string) $dateValue);
        }

        if ($dateText === '') {
            return Carbon::parse($timeText);
        }

        return Carbon::parse($dateText.' '.$timeText);
    }

    private function blocksTime(
        Booking $booking,
        string $status,
        array $columns,
    ): bool {
        // Pending bookings remain blocking until an explicit business action
        // changes their status. Payment/review deadlines never release a slot.
        return !in_array($status, $this->terminalStatuses(), true);
    }

    private function applyTemporaryHold(
        Booking $booking,
        string $status,
        array $columns,
    ): void {
        // Automatic booking/payment expiry was removed. Clear any legacy hold
        // timestamp whenever the model is written so no stale timer affects
        // future availability decisions.
        $holdColumn = $columns['hold'];
        if ($holdColumn !== null) {
            $booking->setAttribute($holdColumn, null);
        }
    }

    private function acquireVenueLock(int|string $venueId): void
    {
        $key = (string) $venueId;

        if (isset($this->locks[$key])) {
            return;
        }

        $lock = Cache::lock(
            'salora:booking:venue:'.$key,
            max(30, (int) config('salora_booking.lock_seconds', 120)),
        );

        $lock->block(15);
        $this->locks[$key] = $lock;

        if (!$this->releaseRegistered) {
            $this->releaseRegistered = true;

            app()->terminating(function (): void {
                $this->releaseAllLocks();
            });
        }
    }

    private function releaseVenueLock(int|string $venueId): void
    {
        $key = (string) $venueId;

        if (!isset($this->locks[$key])) {
            return;
        }

        try {
            $this->locks[$key]->release();
        } catch (Throwable) {
            // The lock may have expired; request termination remains safe.
        }

        unset($this->locks[$key]);
    }

    private function releaseAllLocks(): void
    {
        foreach (array_keys($this->locks) as $key) {
            $this->releaseVenueLock($key);
        }
    }

    private function bookingSummary(
        Booking $booking,
        CarbonInterface $start,
        CarbonInterface $end,
        array $columns,
    ): array {
        return [
            'id' => $booking->getKey(),
            'venue_id' => $booking->getAttribute($columns['venue']),
            'status' => $booking->getAttribute($columns['status']),
            'start' => $start->toIso8601String(),
            'end' => $end->toIso8601String(),
        ];
    }

    private function columns(): array
    {
        if ($this->columns !== null) {
            return $this->columns;
        }

        $table = (new Booking())->getTable();
        $listing = Schema::getColumnListing($table);

        $venue = $this->pick($listing, ['venue_id', 'hall_id']);
        $date = $this->pick(
            $listing,
            ['booking_date', 'event_date', 'reserved_date', 'date'],
            false,
        );
        $start = $this->pick(
            $listing,
            ['start_time', 'starts_at', 'start_at', 'from_time', 'time_from'],
        );
        $end = $this->pick(
            $listing,
            ['end_time', 'ends_at', 'end_at', 'to_time', 'time_to'],
        );
        $status = $this->pick(
            $listing,
            ['status', 'booking_status'],
        );
        // Legacy hold timestamps are schema compatibility only; they never
        // expire a pending booking.
        $hold = $this->pick(
            $listing,
            ['hold_expires_at'],
            false,
        );

        $this->columns = compact(
            'venue',
            'date',
            'start',
            'end',
            'status',
            'hold',
        );

        return $this->columns;
    }

    private function pick(
        array $listing,
        array $candidates,
        bool $required = true,
    ): ?string {
        foreach ($candidates as $candidate) {
            if (in_array($candidate, $listing, true)) {
                return $candidate;
            }
        }

        if ($required) {
            throw new RuntimeException(
                'Booking schema is missing one of: '.implode(', ', $candidates),
            );
        }

        return null;
    }

    private function normalizeStatus(mixed $status): string
    {
        if ($status instanceof \BackedEnum) {
            $status = $status->value;
        }

        return strtolower(trim((string) $status));
    }

    private function terminalStatuses(): array
    {
        return array_map(
            'strtolower',
            (array) config('salora_booking.terminal_statuses', []),
        );
    }

}