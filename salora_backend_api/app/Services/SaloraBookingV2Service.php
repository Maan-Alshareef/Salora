<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class SaloraBookingV2Service
{
    private ?string $venueTableCache = null;
    private ?string $bookingTableCache = null;

    public function venueTable(): string
    {
        if ($this->venueTableCache) {
            return $this->venueTableCache;
        }
        foreach (config('salora_booking_v2.venue_tables', ['venues', 'halls']) as $table) {
            if (Schema::hasTable($table)) {
                return $this->venueTableCache = $table;
            }
        }
        throw ValidationException::withMessages(['venue' => 'تعذر العثور على جدول الصالات.']);
    }

    public function bookingTable(): string
    {
        if ($this->bookingTableCache) {
            return $this->bookingTableCache;
        }
        foreach (config('salora_booking_v2.booking_tables', ['bookings']) as $table) {
            if (Schema::hasTable($table)) {
                return $this->bookingTableCache = $table;
            }
        }
        throw ValidationException::withMessages(['booking' => 'تعذر العثور على جدول الحجوزات.']);
    }

    public function venue(int $venueId): object
    {
        $row = DB::table($this->venueTable())->where('id', $venueId)->first();
        if (!$row) {
            throw ValidationException::withMessages(['venue_id' => 'الصالة غير موجودة.']);
        }
        return $row;
    }

    public function booking(int $bookingId): object
    {
        $row = DB::table($this->bookingTable())->where('id', $bookingId)->first();
        if (!$row) {
            throw ValidationException::withMessages(['booking_id' => 'الحجز غير موجود.']);
        }
        return $row;
    }

    public function firstColumn(string $table, array $columns): ?string
    {
        foreach ($columns as $column) {
            if (Schema::hasColumn($table, $column)) {
                return $column;
            }
        }
        return null;
    }

    public function bookingVenueId(object|array $booking): int
    {
        $row = (array) $booking;
        foreach (['venue_id', 'hall_id', 'salon_id'] as $column) {
            if (!empty($row[$column])) {
                return (int) $row[$column];
            }
        }
        throw ValidationException::withMessages(['venue_id' => 'الحجز غير مرتبط بصالة معروفة.']);
    }

    public function bookingUserId(object|array $booking): ?int
    {
        $row = (array) $booking;
        foreach (['user_id', 'client_id', 'customer_id', 'booked_by_user_id'] as $column) {
            if (!empty($row[$column])) {
                return (int) $row[$column];
            }
        }
        return null;
    }

    public function venueOwnerId(object|array $venue): ?int
    {
        $row = (array) $venue;
        foreach (['owner_id', 'user_id', 'venue_owner_id', 'hall_owner_id'] as $column) {
            if (!empty($row[$column])) {
                return (int) $row[$column];
            }
        }
        return null;
    }

    public function assertVenueOwner(int $venueId, ?object $user): void
    {
        if (!$user) {
            abort(401);
        }
        $role = strtolower((string) ($user->role ?? $user->type ?? ''));
        if (in_array($role, ['admin', 'administrator', 'super_admin'], true)) {
            return;
        }
        $ownerId = $this->venueOwnerId($this->venue($venueId));
        if ($ownerId !== null && $ownerId !== (int) $user->id) {
            abort(403, 'لا تملك صلاحية إدارة هذه الصالة.');
        }
    }

    public function extractDateTimes(object|array $booking): array
    {
        $row = (array) $booking;
        if (!empty($row['start_at']) && !empty($row['end_at'])) {
            return [Carbon::parse($row['start_at']), Carbon::parse($row['end_at'])];
        }

        $date = null;
        foreach (['booking_date', 'event_date', 'date', 'reserved_date'] as $column) {
            if (!empty($row[$column])) {
                $date = Carbon::parse($row[$column])->format('Y-m-d');
                break;
            }
        }
        $startTime = null;
        foreach (['start_time', 'from_time', 'booking_start_time'] as $column) {
            if (!empty($row[$column])) {
                $startTime = $row[$column];
                break;
            }
        }
        $endTime = null;
        foreach (['end_time', 'to_time', 'booking_end_time'] as $column) {
            if (!empty($row[$column])) {
                $endTime = $row[$column];
                break;
            }
        }
        if (!$date || !$startTime || !$endTime) {
            throw ValidationException::withMessages(['time' => 'لا يمكن تحديد وقت بداية ونهاية الحجز.']);
        }
        $start = Carbon::parse($date . ' ' . $startTime);
        $end = Carbon::parse($date . ' ' . $endTime);
        if ($end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }
        return [$start, $end];
    }

    private function validateHalfHour(Carbon $value, string $field): void
    {
        if (!in_array((int) $value->minute, [0, 30], true) || (int) $value->second !== 0) {
            throw ValidationException::withMessages([$field => 'اختيار الوقت يجب أن يكون كل نصف ساعة فقط.']);
        }
    }

    public function workingWindows(int $venueId, Carbon $referenceDate): array
    {
        $hasWeekly = DB::table('venue_working_hours')->where('venue_id', $venueId)->exists();
        $hasExceptions = DB::table('venue_schedule_exceptions')->where('venue_id', $venueId)->exists();

        $venue = (array) $this->venue($venueId);
        $legacyHours = $venue['opening_hours'] ?? null;
        if (is_string($legacyHours)) {
            $legacyHours = json_decode($legacyHours, true);
        }
        $hasLegacy = is_array($legacyHours) && $legacyHours !== [];

        if (!$hasWeekly && !$hasExceptions && !$hasLegacy) {
            return [[
                'start' => $referenceDate->copy()->subDay()->startOfDay(),
                'end' => $referenceDate->copy()->addDays(2)->endOfDay(),
                'configured' => false,
            ]];
        }

        $windows = [];
        foreach ([-1, 0, 1] as $offset) {
            $day = $referenceDate->copy()->startOfDay()->addDays($offset);
            $exception = DB::table('venue_schedule_exceptions')
                ->where('venue_id', $venueId)
                ->whereDate('exception_date', $day->toDateString())
                ->first();

            if ($exception) {
                if ((bool) $exception->is_closed) {
                    continue;
                }
                $open = $exception->open_time;
                $close = $exception->close_time;
                $source = 'exception';
            } elseif ($hasWeekly) {
                $weekly = DB::table('venue_working_hours')
                    ->where('venue_id', $venueId)
                    ->where('day_of_week', $day->dayOfWeek)
                    ->first();
                if (!$weekly || (bool) $weekly->is_closed) {
                    continue;
                }
                $open = $weekly->open_time;
                $close = $weekly->close_time;
                $source = 'weekly';
            } else {
                $key = strtolower($day->format('l'));
                $entry = $legacyHours[$key] ?? null;
                if (!is_array($entry) || !($entry['enabled'] ?? false)) {
                    continue;
                }
                $open = $entry['open'] ?? null;
                $close = $entry['close'] ?? null;
                $source = 'legacy_opening_hours';
            }

            if (!$open || !$close) {
                continue;
            }
            $start = Carbon::parse($day->toDateString().' '.$open);
            $end = Carbon::parse($day->toDateString().' '.$close);
            if ($end->lessThanOrEqualTo($start)) {
                $end->addDay();
            }
            $windows[] = [
                'start' => $start,
                'end' => $end,
                'configured' => true,
                'source' => $source,
            ];
        }
        return $windows;
    }

    public function quote(int $venueId, Carbon|string $start, Carbon|string $end, ?int $excludeBookingId = null): array
    {
        $start = $start instanceof Carbon ? $start->copy() : Carbon::parse($start);
        $end = $end instanceof Carbon ? $end->copy() : Carbon::parse($end);
        $start->second(0);
        $end->second(0);
        if ((int) $start->minute !== 0 || (int) $start->second !== 0) {
            throw ValidationException::withMessages(['start_at' => 'وقت البداية يجب أن يكون على رأس الساعة دون نصف ساعة.']);
        }
        $this->validateHalfHour($end, 'end_at');

        if ($end->lessThanOrEqualTo($start)) {
            throw ValidationException::withMessages(['end_at' => 'وقت النهاية يجب أن يكون بعد وقت البداية.']);
        }
        if ($start->lessThanOrEqualTo(now())) {
            throw ValidationException::withMessages(['start_at' => 'يجب اختيار وقت قادم للحجز.']);
        }

        $venue = $this->venue($venueId);
        $duration = $start->diffInMinutes($end);
        $minimum = 120;
        $maximum = max($minimum, (int) ($venue->maximum_booking_minutes ?? 480));
        $cleanup = max(0, (int) ($venue->cleanup_minutes ?? 0));
        $hourlyPrice = (float) ($venue->hourly_price_syp ?? 0);
        if ($hourlyPrice <= 0) {
            // Compatibility for venues created before hourly pricing was added.
            $hourlyPrice = (float) ($venue->price_syp ?? 0);
        }

        if ($duration < $minimum) {
            throw ValidationException::withMessages(['duration' => 'الحد الأدنى للحجز هو ساعتان.']);
        }
        if ($duration > $maximum) {
            throw ValidationException::withMessages(['duration' => 'المدة تتجاوز الحد الأقصى الذي حدده مالك الصالة.']);
        }
        if ($duration % 30 !== 0) {
            throw ValidationException::withMessages(['duration' => 'مدة الحجز يجب أن تكون بخطوات نصف ساعة.']);
        }
        if ($hourlyPrice <= 0) {
            throw ValidationException::withMessages(['hourly_price' => 'مالك الصالة لم يحدد سعر الساعة بعد.']);
        }

        $inside = collect($this->workingWindows($venueId, $start))->contains(
            fn (array $window) => $start->greaterThanOrEqualTo($window['start']) && $end->lessThanOrEqualTo($window['end'])
        );
        if (!$inside) {
            throw ValidationException::withMessages(['time' => 'الوقت المختار خارج أوقات عمل الصالة.']);
        }

        $blockedEnd = $end->copy()->addMinutes($cleanup);
        $this->assertNoManualBlock($venueId, $start, $blockedEnd);
        $this->assertNoBookingOverlap($venueId, $start, $blockedEnd, $excludeBookingId, $cleanup);

        $before = round(($duration / 60) * $hourlyPrice, 2);
        $offer = $this->bestOffer($venueId, $start, $end, $duration, $before);
        $discount = (float) ($offer['discount_syp'] ?? 0);
        $final = max(0, round($before - $discount, 2));
        $rate = (float) config('salora_booking_v2.commission_rate', 10);
        $commission = round($final * ($rate / 100), 2);

        return [
            'venue_id' => $venueId,
            'start_at' => $start->toDateTimeString(),
            'end_at' => $end->toDateTimeString(),
            'duration_minutes' => $duration,
            'duration_hours' => round($duration / 60, 2),
            'minimum_booking_minutes' => $minimum,
            'maximum_booking_minutes' => $maximum,
            'cleanup_minutes' => $cleanup,
            'hourly_price_syp' => $hourlyPrice,
            'price_before_discount_syp' => $before,
            'offer' => $offer,
            'discount_syp' => $discount,
            'final_price_syp' => $final,
            'commission_rate' => $rate,
            'commission_syp' => $commission,
            'owner_net_before_refund_syp' => round($final - $commission, 2),
            'available' => true,
        ];
    }

    private function assertNoManualBlock(int $venueId, Carbon $start, Carbon $blockedEnd): void
    {
        $exists = DB::table('venue_schedule_blocks')
            ->where('venue_id', $venueId)
            ->where('start_at', '<', $blockedEnd->toDateTimeString())
            ->where('end_at', '>', $start->toDateTimeString())
            ->exists();
        if ($exists) {
            throw ValidationException::withMessages(['time' => 'الفترة غير متاحة بسبب إغلاق أو صيانة خاصة.']);
        }
    }

    private function assertNoBookingOverlap(
        int $venueId,
        Carbon $start,
        Carbon $blockedEnd,
        ?int $excludeBookingId,
        int $cleanup,
    ): void {
        $table = $this->bookingTable();
        $venueColumn = $this->firstColumn(
            $table,
            ['venue_id', 'hall_id', 'salon_id'],
        );
        if (!$venueColumn) {
            return;
        }

        $statusColumn = $this->firstColumn(
            $table,
            ['booking_status', 'status'],
        );

        $baseQuery = function () use (
            $table,
            $venueColumn,
            $venueId,
            $excludeBookingId,
            $statusColumn,
        ) {
            $query = DB::table($table)->where($venueColumn, $venueId);
            if ($excludeBookingId) {
                $query->where('id', '!=', $excludeBookingId);
            }
            if ($statusColumn) {
                $query->whereNotIn(
                    $statusColumn,
                    config('salora_booking_v2.inactive_booking_statuses', []),
                );
            }

            return $query;
        };

        $hasDateTimeColumns =
            Schema::hasColumn($table, 'start_at') &&
            Schema::hasColumn($table, 'end_at');

        if ($hasDateTimeColumns) {
            $dateTimeQuery = $baseQuery()
                ->whereNotNull('start_at')
                ->whereNotNull('end_at')
                ->where('start_at', '<', $blockedEnd->toDateTimeString())
                ->where(
                    'end_at',
                    '>',
                    $start->copy()->subMinutes($cleanup)->toDateTimeString(),
                );

            foreach ($dateTimeQuery->get() as $row) {
                $existingStart = Carbon::parse($row->start_at);
                $existingBlockedEnd = Carbon::parse($row->end_at)
                    ->addMinutes($cleanup);

                if (
                    $existingStart->lessThan($blockedEnd) &&
                    $existingBlockedEnd->greaterThan($start)
                ) {
                    throw ValidationException::withMessages([
                        'time' => 'الوقت محجوز أو يتعارض مع فترة التجهيز والتنظيف.',
                    ]);
                }
            }
        }

        $dateColumn = $this->firstColumn(
            $table,
            ['event_date', 'booking_date', 'reserved_date', 'date'],
        );
        $startColumn = $this->firstColumn(
            $table,
            ['start_time', 'from_time', 'time_from'],
        );
        $endColumn = $this->firstColumn(
            $table,
            ['end_time', 'to_time', 'time_to'],
        );

        if (!$dateColumn || !$startColumn || !$endColumn) {
            return;
        }

        $legacyQuery = $baseQuery()
            ->whereNotNull($dateColumn)
            ->whereNotNull($startColumn)
            ->whereNotNull($endColumn)
            ->whereBetween($dateColumn, [
                $start->copy()->subDay()->toDateString(),
                $blockedEnd->copy()->addDay()->toDateString(),
            ]);

        if ($hasDateTimeColumns) {
            $legacyQuery->where(function ($query): void {
                $query->whereNull('start_at')->orWhereNull('end_at');
            });
        }

        foreach ($legacyQuery->get() as $row) {
            $date = Carbon::parse((string) $row->{$dateColumn})
                ->toDateString();
            $existingStart = Carbon::parse(
                $date.' '.substr((string) $row->{$startColumn}, 0, 8),
            );
            $existingEnd = Carbon::parse(
                $date.' '.substr((string) $row->{$endColumn}, 0, 8),
            );

            if ($existingEnd->lessThanOrEqualTo($existingStart)) {
                $existingEnd->addDay();
            }

            $existingBlockedEnd = $existingEnd->copy()->addMinutes($cleanup);
            $requestedBufferedStart = $start->copy()->subMinutes($cleanup);

            if (
                $existingStart->lessThan($blockedEnd) &&
                $existingBlockedEnd->greaterThan($requestedBufferedStart)
            ) {
                throw ValidationException::withMessages([
                    'time' => 'الوقت محجوز أو يتعارض مع فترة التجهيز والتنظيف.',
                ]);
            }
        }
    }

    public function bestOffer(int $venueId, Carbon $start, Carbon $end, int $duration, float $basePrice): ?array
    {
        $offers = DB::table('venue_offers')
            ->where('venue_id', $venueId)
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_on')->orWhereDate('starts_on', '<=', $start->toDateString()))
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $start->toDateString()))
            ->get();

        $valid = [];
        foreach ($offers as $offer) {
            if ((int) ($offer->minimum_booking_minutes ?? 0) > $duration) {
                continue;
            }
            $days = $offer->days_of_week ? json_decode($offer->days_of_week, true) : null;
            if (is_array($days) && $days !== [] && !in_array($start->dayOfWeek, array_map('intval', $days), true)) {
                continue;
            }
            if ($offer->offer_type === 'scheduled' && ($offer->start_time || $offer->end_time)) {
                if (!$offer->start_time || !$offer->end_time) {
                    continue;
                }
                $windowStart = Carbon::parse($start->toDateString() . ' ' . $offer->start_time);
                $windowEnd = Carbon::parse($start->toDateString() . ' ' . $offer->end_time);
                if ($windowEnd->lessThanOrEqualTo($windowStart)) {
                    $windowEnd->addDay();
                }
                if ($start->lessThan($windowStart) || $end->greaterThan($windowEnd)) {
                    continue;
                }
            }

            $type = $offer->offer_type === 'scheduled' ? $offer->scheduled_discount_type : $offer->offer_type;
            if ($type === 'percentage') {
                $percent = min(50, max(0, (float) ($offer->percentage ?? 0)));
                if ($percent <= 0) {
                    continue;
                }
                $discount = round($basePrice * ($percent / 100), 2);
            } elseif ($type === 'fixed') {
                $discount = max(0, (float) ($offer->fixed_amount_syp ?? 0));
            } else {
                continue;
            }

            $discount = min($discount, max(0, $basePrice - 1));
            if ($discount <= 0) {
                continue;
            }
            $valid[] = [
                'id' => (int) $offer->id,
                'title' => $offer->title,
                'offer_type' => $offer->offer_type,
                'discount_type' => $type,
                'percentage' => $offer->percentage !== null ? (float) $offer->percentage : null,
                'fixed_amount_syp' => $offer->fixed_amount_syp !== null ? (float) $offer->fixed_amount_syp : null,
                'discount_syp' => $discount,
                'final_price_syp' => round($basePrice - $discount, 2),
            ];
        }
        return collect($valid)->sortByDesc('discount_syp')->first();
    }

    public function applyQuoteToModel(Model $model): ?array
    {
        if ($model->getTable() !== $this->bookingTable()) {
            return null;
        }
        try {
            $venueId = $this->bookingVenueId($model->getAttributes());
            [$start, $end] = $this->extractDateTimes($model->getAttributes());
        } catch (\Throwable) {
            return null;
        }

        $timeDirty = !$model->exists;
        foreach (['start_at', 'end_at', 'booking_date', 'event_date', 'date', 'start_time', 'end_time', 'venue_id', 'hall_id'] as $column) {
            if (array_key_exists($column, $model->getAttributes()) && $model->isDirty($column)) {
                $timeDirty = true;
            }
        }
        if (!$timeDirty) {
            return null;
        }

        $quote = $this->quote($venueId, $start, $end, $model->exists ? (int) $model->getKey() : null);
        $values = [
            'start_at' => $quote['start_at'],
            'end_at' => $quote['end_at'],
            'duration_minutes' => $quote['duration_minutes'],
            'hourly_price_snapshot_syp' => $quote['hourly_price_syp'],
            'price_before_discount_syp' => $quote['price_before_discount_syp'],
            'offer_id' => $quote['offer']['id'] ?? null,
            'offer_snapshot' => $quote['offer'] ? json_encode($quote['offer'], JSON_UNESCAPED_UNICODE) : null,
            'discount_syp' => $quote['discount_syp'],
            'final_price_syp' => $quote['final_price_syp'],
            'owner_retained_syp' => $quote['final_price_syp'],
            'commission_rate' => $quote['commission_rate'],
            'commission_syp' => $quote['commission_syp'],
            'financial_status' => 'due',
            'pricing_snapshot' => json_encode($quote, JSON_UNESCAPED_UNICODE),
        ];
        foreach ($values as $column => $value) {
            if (Schema::hasColumn($model->getTable(), $column)) {
                $model->setAttribute($column, $value);
            }
        }
        foreach (['total_price', 'price', 'amount', 'total_amount'] as $column) {
            if (Schema::hasColumn($model->getTable(), $column)) {
                $model->setAttribute($column, $quote['final_price_syp']);
                break;
            }
        }
        return $quote;
    }

    public function bookingFinalPrice(object|array $booking): float
    {
        $row = (array) $booking;
        foreach (['final_price_syp', 'total_syp', 'total_price', 'price', 'amount', 'total_amount'] as $column) {
            if (array_key_exists($column, $row) && $row[$column] !== null) {
                return (float) $row[$column];
            }
        }
        return 0;
    }

    private function bookingInvoiceTotal(int $bookingId, object|array $booking): float
    {
        if (Schema::hasTable('invoices')) {
            $query = DB::table('invoices')->where('booking_id', $bookingId);
            if (Schema::hasColumn('invoices', 'source_type')) {
                $query->where('source_type', 'venue_booking');
            }
            $invoice = $query->orderByDesc('id')->first();
            if ($invoice && isset($invoice->total_syp)) {
                return (float) $invoice->total_syp;
            }
        }

        $row = (array) $booking;
        if (array_key_exists('total_syp', $row) && $row['total_syp'] !== null) {
            return (float) $row['total_syp'];
        }

        return $this->bookingFinalPrice($booking);
    }

    public function isPaid(object|array $booking): bool
    {
        $row = (array) $booking;
        if (!empty($row['is_paid'])) {
            return true;
        }
        foreach (['paid_at', 'payment_approved_at', 'payment_confirmed_at'] as $column) {
            if (!empty($row[$column])) {
                return true;
            }
        }
        foreach (['payment_status', 'payment_state'] as $column) {
            if (!empty($row[$column]) && in_array(strtolower((string) $row[$column]), ['paid', 'approved', 'confirmed', 'collected'], true)) {
                return true;
            }
        }
        return false;
    }

    public function cancellationPreview(int $bookingId): array
    {
        $booking = $this->booking($bookingId);
        [$start] = $this->extractDateTimes($booking);
        $hours = now()->diffInHours($start, false);
        $policyRefundPercent = $hours > 168 ? 100 : ($hours > 120 ? 50 : 0);
        $paid = $this->isPaid($booking);
        $refundPercent = $paid ? $policyRefundPercent : 0;
        $final = $this->bookingInvoiceTotal($bookingId, $booking);
        $refund = $paid ? round($final * ($refundPercent / 100), 2) : 0.0;
        $retained = $paid ? round($final - $refund, 2) : 0.0;
        $commission = $paid ? round($retained * 0.10, 2) : 0.0;

        return [
            'booking_id' => $bookingId,
            'event_start_at' => $start->toDateTimeString(),
            'hours_until_event' => $hours,
            'paid' => $paid,
            'final_price_syp' => $final,
            'policy_refund_percentage' => $policyRefundPercent,
            'refund_percentage' => $refundPercent,
            'deduction_percentage' => $paid ? 100 - $refundPercent : 0,
            'refunded_syp' => $refund,
            'owner_retained_syp' => $retained,
            'commission_syp' => $commission,
            'requires_refund_confirmation' => $paid && $refund > 0,
            'payment_message' => $paid
                ? 'تم احتساب الاسترداد على المبلغ المدفوع فعلياً.'
                : 'لم يتم دفع مبلغ لهذا الحجز؛ الإلغاء فوري ولا يوجد مبلغ مسترد.',
            'policy' => [
                ['condition' => 'أكثر من 7 أيام', 'refund_percentage' => 100],
                ['condition' => 'من 5 إلى 7 أيام', 'refund_percentage' => 50],
                ['condition' => 'خلال آخر 5 أيام', 'refund_percentage' => 0],
                ['condition' => 'إلغاء مالك الصالة', 'refund_percentage' => 100],
            ],
            'policy_message' => 'أكثر من 7 أيام: استرداد 100%، من 5 إلى 7 أيام: 50%، خلال آخر 5 أيام: لا يوجد استرداد. إلغاء المالك يعني استرداداً كاملاً.',
        ];
    }

    public function requestCancellation(int $bookingId, ?int $actorUserId, ?string $reason): array
    {
        $booking = $this->booking($bookingId);
        $preview = $this->cancellationPreview($bookingId);
        $table = $this->bookingTable();
        $updates = $this->filterColumns($table, [
            'refund_percentage' => $preview['refund_percentage'],
            'refunded_syp' => $preview['refunded_syp'],
            'owner_retained_syp' => $preview['owner_retained_syp'],
            'commission_syp' => $preview['commission_syp'],
            'cancellation_reason' => $reason,
            'cancellation_requested_at' => now(),
            'edit_locked_at' => now(),
            'financial_status' => 'recalculated_after_cancellation',
            'cancellation_status' => $preview['requires_refund_confirmation'] ? 'waiting_refund' : 'cancelled',
            'cancelled_at' => $preview['requires_refund_confirmation'] ? null : now(),
        ]);
        $this->setBookingStatus($table, $updates, $preview['requires_refund_confirmation'] ? 'cancellation_pending_refund' : 'cancelled');

        DB::transaction(function () use ($table, $bookingId, $updates, $booking, $preview, $reason, $actorUserId) {
            DB::table($table)->where('id', $bookingId)->update($updates);
            $this->syncPaymentRefund($bookingId, $booking, $preview, 'customer', $reason);
            $this->addFinancialEvent($bookingId, $this->bookingVenueId($booking), 'cancellation_requested', $preview['final_price_syp'], $preview['owner_retained_syp'], (float) ($booking->commission_syp ?? 0), $preview['commission_syp'], $preview, $actorUserId);
            $this->syncCommission($bookingId);
        });

        return ['status' => $preview['requires_refund_confirmation'] ? 'waiting_refund' : 'cancelled', 'preview' => $preview];
    }

    public function ownerCancellation(int $bookingId, ?int $actorUserId, ?string $reason): array
    {
        $booking = $this->booking($bookingId);
        $final = $this->bookingInvoiceTotal($bookingId, $booking);
        $paid = $this->isPaid($booking);
        $table = $this->bookingTable();
        $actualRefund = $paid ? $final : 0.0;
        $updates = $this->filterColumns($table, [
            'refund_percentage' => $paid ? 100 : 0,
            'refunded_syp' => $actualRefund,
            'owner_retained_syp' => 0,
            'commission_syp' => 0,
            'cancellation_reason' => $reason,
            'cancellation_requested_at' => now(),
            'edit_locked_at' => now(),
            'financial_status' => 'owner_cancelled_full_refund',
            'cancellation_status' => $paid ? 'waiting_refund' : 'cancelled',
            'cancelled_at' => $paid ? null : now(),
        ]);
        $this->setBookingStatus($table, $updates, $paid ? 'cancellation_pending_refund' : 'cancelled');

        DB::transaction(function () use ($table, $bookingId, $updates, $booking, $final, $actualRefund, $paid, $reason, $actorUserId) {
            DB::table($table)->where('id', $bookingId)->update($updates);
            $this->syncPaymentRefund($bookingId, $booking, [
                'paid' => $paid,
                'refund_percentage' => $paid ? 100 : 0,
                'refunded_syp' => $actualRefund,
            ], 'owner', $reason);
            $this->addFinancialEvent(
                $bookingId,
                $this->bookingVenueId($booking),
                'owner_cancellation',
                $final,
                0,
                (float) ($booking->commission_syp ?? round($final * 0.10, 2)),
                0,
                [
                    'policy_refund_percentage' => 100,
                    'refund_percentage' => $paid ? 100 : 0,
                    'refunded_syp' => $actualRefund,
                    'paid' => $paid,
                ],
                $actorUserId
            );
            $this->syncCommission($bookingId);
        });

        return [
            'status' => $paid ? 'waiting_refund' : 'cancelled',
            'policy_refund_percentage' => 100,
            'refund_percentage' => $paid ? 100 : 0,
            'refunded_syp' => $actualRefund,
            'commission_syp' => 0,
            'payment_message' => $paid
                ? 'إلغاء مالك الصالة: استرداد كامل للمبلغ المدفوع.'
                : 'لم يدفع العميل مبلغاً، لذلك تم الإلغاء فوراً ولا توجد حوالة استرداد.',
        ];
    }

    public function confirmRefund(int $bookingId, ?int $actorUserId): array
    {
        $booking = $this->booking($bookingId);
        $table = $this->bookingTable();
        $updates = $this->filterColumns($table, [
            'cancellation_status' => 'cancelled',
            'refund_confirmed_at' => now(),
            'cancelled_at' => now(),
            'financial_status' => 'refund_confirmed',
        ]);
        $this->setBookingStatus($table, $updates, 'cancelled');
        DB::transaction(function () use ($table, $bookingId, $updates, $booking, $actorUserId) {
            DB::table($table)->where('id', $bookingId)->update($updates);
            if (Schema::hasTable('payment_refunds')) {
                $refund = DB::table('payment_refunds')
                    ->where('booking_id', $bookingId)
                    ->whereIn('status', ['pending_transfer', 'overdue', 'transferred'])
                    ->orderByDesc('id')
                    ->first();

                if ($refund) {
                    DB::table('payment_refunds')
                        ->where('id', $refund->id)
                        ->update([
                            'status' => 'confirmed',
                            'transferred_at' => now(),
                            'updated_at' => now(),
                        ]);

                    if (Schema::hasTable('invoices') && !empty($refund->invoice_id)) {
                        DB::table('invoices')
                            ->where('id', $refund->invoice_id)
                            ->update([
                                'status' => (float) ($refund->refund_percent ?? 0) >= 100
                                    ? 'refunded'
                                    : 'partially_refunded',
                                'updated_at' => now(),
                            ]);
                    }
                }
            }
            $this->addFinancialEvent($bookingId, $this->bookingVenueId($booking), 'refund_confirmed', $this->bookingInvoiceTotal($bookingId, $booking), (float) ($booking->owner_retained_syp ?? 0), (float) ($booking->commission_syp ?? 0), (float) ($booking->commission_syp ?? 0), [], $actorUserId);
            $this->syncCommission($bookingId);
        });
        return ['status' => 'cancelled'];
    }

    private function syncPaymentRefund(
        int $bookingId,
        object $booking,
        array $preview,
        string $requestedByRole,
        ?string $reason,
    ): void {
        if (!Schema::hasTable('payment_refunds') || empty($preview['paid']) || (float) ($preview['refunded_syp'] ?? 0) <= 0) {
            return;
        }

        $invoice = DB::table('invoices')
            ->where('booking_id', $bookingId)
            ->when(Schema::hasColumn('invoices', 'source_type'), fn ($query) => $query->where('source_type', 'venue_booking'))
            ->orderByDesc('id')
            ->first();
        if (!$invoice) {
            return;
        }

        $percent = (float) ($preview['refund_percentage'] ?? 0);
        $amountSyp = (float) ($preview['refunded_syp'] ?? 0);
        $amountUsd = round((float) ($invoice->total_usd ?? 0) * ($percent / 100), 2);
        $payload = [
            'booking_id' => $bookingId,
            'customer_id' => (int) ($invoice->customer_id ?? $this->bookingUserId($booking)),
            'payee_id' => $invoice->payee_id ?? $this->venueOwnerId($this->venue($this->bookingVenueId($booking))),
            'requested_by_role' => $requestedByRole,
            'reason' => $reason ?: 'إلغاء الحجز حسب سياسة Salora',
            'refund_percent' => $percent,
            'amount_syp' => $amountSyp,
            'amount_usd' => $amountUsd,
            'status' => 'pending_transfer',
            'due_at' => now()->addHours((int) config('salora_payments.refund_deadline_hours', 48)),
            'updated_at' => now(),
        ];

        $existing = DB::table('payment_refunds')->where('invoice_id', $invoice->id)->orderByDesc('id')->first();
        if ($existing) {
            DB::table('payment_refunds')->where('id', $existing->id)->update($payload);
        } else {
            DB::table('payment_refunds')->insert(array_merge($payload, [
                'invoice_id' => $invoice->id,
                'created_at' => now(),
            ]));
        }

        DB::table('invoices')->where('id', $invoice->id)->update([
            'status' => 'refund_pending',
            'updated_at' => now(),
        ]);
    }

    public function applyApprovedChange(int $bookingId, array $requested, ?int $actorUserId): array
    {
        $booking = $this->booking($bookingId);
        [$oldStart, $oldEnd] = $this->extractDateTimes($booking);
        $venueId = isset($requested['venue_id'])
            ? (int) $requested['venue_id']
            : $this->bookingVenueId($booking);

        $date = $requested['event_date'] ?? $oldStart->toDateString();
        $start = isset($requested['start_at'])
            ? Carbon::parse($requested['start_at'])
            : (isset($requested['start_time'])
                ? Carbon::parse($date.' '.$requested['start_time'])
                : $oldStart->copy());
        $end = isset($requested['end_at'])
            ? Carbon::parse($requested['end_at'])
            : (isset($requested['end_time'])
                ? Carbon::parse($date.' '.$requested['end_time'])
                : $oldEnd->copy());
        $start->second(0);
        $end->second(0);
        if ($end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        $guests = $requested['guests_count']
            ?? $requested['guest_count']
            ?? $requested['number_of_guests']
            ?? $booking->guests_count
            ?? $booking->guest_count
            ?? $booking->number_of_guests
            ?? null;
        $venue = $this->venue($venueId);
        if ($guests !== null && isset($venue->capacity) && (int) $guests > (int) $venue->capacity) {
            throw ValidationException::withMessages([
                'guests_count' => 'عدد الضيوف يتجاوز سعة الصالة.',
            ]);
        }

        $quote = $this->quote($venueId, $start, $end, $bookingId);
        $table = $this->bookingTable();
        $oldHallPrice = $this->bookingFinalPrice($booking);
        $oldTotalSyp = isset($booking->total_syp) && $booking->total_syp !== null
            ? (float) $booking->total_syp
            : $oldHallPrice;
        $extraServicesSyp = max(0, round($oldTotalSyp - $oldHallPrice, 2));
        $newSubtotalSyp = round($quote['price_before_discount_syp'] + $extraServicesSyp, 2);
        $newTotalSyp = round($quote['final_price_syp'] + $extraServicesSyp, 2);

        $usdToSyp = 0.0;
        if (!empty($booking->total_usd) && (float) $booking->total_usd > 0 && $oldTotalSyp > 0) {
            $usdToSyp = $oldTotalSyp / (float) $booking->total_usd;
        }
        if ($usdToSyp <= 0) {
            $usdToSyp = max(1, (float) env('USD_TO_SYP', 14000));
        }
        $newSubtotalUsd = round($newSubtotalSyp / $usdToSyp, 2);
        $newDiscountUsd = round($quote['discount_syp'] / $usdToSyp, 2);
        $newTotalUsd = round($newTotalSyp / $usdToSyp, 2);

        $updates = $this->filterColumns($table, [
            'venue_id' => $venueId,
            'hall_id' => $venueId,
            'event_date' => $start->toDateString(),
            'booking_date' => $start->toDateString(),
            'date' => $start->toDateString(),
            'start_time' => $start->format('H:i'),
            'end_time' => $end->format('H:i'),
            'start_at' => $quote['start_at'],
            'end_at' => $quote['end_at'],
            'duration_minutes' => $quote['duration_minutes'],
            'guests_count' => $guests,
            'guest_count' => $guests,
            'number_of_guests' => $guests,
            'notes' => array_key_exists('notes', $requested) ? $requested['notes'] : ($booking->notes ?? null),
            'hourly_price_snapshot_syp' => $quote['hourly_price_syp'],
            'price_before_discount_syp' => $quote['price_before_discount_syp'],
            'offer_id' => $quote['offer']['id'] ?? null,
            'offer_snapshot' => $quote['offer'] ? json_encode($quote['offer'], JSON_UNESCAPED_UNICODE) : null,
            'subtotal_syp' => $newSubtotalSyp,
            'subtotal_usd' => $newSubtotalUsd,
            'discount_syp' => $quote['discount_syp'],
            'discount_usd' => $newDiscountUsd,
            'total_syp' => $newTotalSyp,
            'total_usd' => $newTotalUsd,
            'final_price_syp' => $quote['final_price_syp'],
            'owner_retained_syp' => $quote['final_price_syp'],
            'commission_rate' => $quote['commission_rate'],
            'commission_syp' => $quote['commission_syp'],
            'platform_commission_rate' => $quote['commission_rate'],
            'platform_commission_syp' => $quote['commission_syp'],
            'owner_net_syp' => round($newTotalSyp - $quote['commission_syp'], 2),
            'pricing_snapshot' => json_encode($quote, JSON_UNESCAPED_UNICODE),
            'financial_status' => 'updated_after_booking_change',
        ]);
        foreach (['total_price', 'price', 'amount', 'total_amount'] as $column) {
            if (Schema::hasColumn($table, $column)) {
                $updates[$column] = $newTotalSyp;
                break;
            }
        }

        DB::transaction(function () use (
            $table,
            $bookingId,
            $updates,
            $booking,
            $quote,
            $actorUserId,
            $start,
            $end,
            $guests,
            $requested,
            $newSubtotalSyp,
            $newSubtotalUsd,
            $newDiscountUsd,
            $newTotalSyp,
            $newTotalUsd,
            $oldHallPrice,
            $venueId
        ) {
            DB::table($table)->where('id', $bookingId)->update($updates);

            if (Schema::hasTable('events') && !empty($booking->event_id)) {
                $eventData = [
                    'event_date' => $start->toDateString(),
                    'start_time' => $start->format('H:i'),
                    'end_time' => $end->format('H:i'),
                    'guests_count' => $guests,
                    'updated_at' => now(),
                ];
                if (array_key_exists('notes', $requested)) {
                    $eventData['notes'] = $requested['notes'];
                }
                $eventUpdates = $this->filterColumns('events', $eventData);
                if ($eventUpdates !== []) {
                    DB::table('events')->where('id', $booking->event_id)->update($eventUpdates);
                }
            }

            if (Schema::hasTable('invoices')) {
                $invoiceUpdates = $this->filterColumns('invoices', [
                    'subtotal_syp' => $newSubtotalSyp,
                    'subtotal_usd' => $newSubtotalUsd,
                    'discount_syp' => $quote['discount_syp'],
                    'discount_usd' => $newDiscountUsd,
                    'total_syp' => $newTotalSyp,
                    'total_usd' => $newTotalUsd,
                    'commission_syp' => $quote['commission_syp'],
                    'net_syp' => round($newTotalSyp - $quote['commission_syp'], 2),
                    'updated_at' => now(),
                ]);
                if ($invoiceUpdates !== []) {
                    $invoiceQuery = DB::table('invoices')
                        ->where('booking_id', $bookingId);
                    if (Schema::hasColumn('invoices', 'source_type')) {
                        $invoiceQuery->where('source_type', 'venue_booking');
                    }
                    $invoiceQuery->update($invoiceUpdates);
                }
            }

            $this->addFinancialEvent(
                $bookingId,
                $venueId,
                'booking_change_approved',
                $oldHallPrice,
                $quote['final_price_syp'],
                (float) ($booking->commission_syp ?? 0),
                $quote['commission_syp'],
                $quote,
                $actorUserId
            );
            $this->syncCommission($bookingId);
        });

        return [
            ...$quote,
            'extra_services_syp' => $extraServicesSyp,
            'booking_total_syp' => $newTotalSyp,
        ];
    }

    public function syncCommission(int $bookingId): void
    {
        $booking = $this->booking($bookingId);
        $venueId = $this->bookingVenueId($booking);
        $final = $this->bookingFinalPrice($booking);
        $retained = isset($booking->owner_retained_syp) && $booking->owner_retained_syp !== null ? (float) $booking->owner_retained_syp : $final;
        $rate = (float) ($booking->commission_rate ?? 10);
        $commission = isset($booking->commission_syp) && $booking->commission_syp !== null ? (float) $booking->commission_syp : round($retained * $rate / 100, 2);
        $existing = DB::table('salora_booking_commissions')->where('booking_id', $bookingId)->first();
        $legacyCollected = $this->legacyCollected($bookingId);
        $collected = max($existing ? (float) $existing->collected_syp : 0, $legacyCollected);
        $settlement = round($collected - $commission, 2);
        $status = $commission <= 0 ? 'cancelled' : 'due';
        if ($collected > 0 && abs($settlement) <= 0.01) {
            $status = 'collected';
        } elseif ($collected > 0 && abs($settlement) > 0.01) {
            $status = 'settlement_required';
        } elseif ($retained < $final) {
            $status = 'partially_due';
        }

        DB::table('salora_booking_commissions')->updateOrInsert(
            ['booking_id' => $bookingId],
            [
                'venue_id' => $venueId,
                'final_price_syp' => $final,
                'owner_retained_syp' => $retained,
                'commission_rate' => $rate,
                'commission_syp' => $commission,
                'collected_syp' => $collected,
                'settlement_syp' => $settlement,
                'status' => $status,
                'snapshot' => json_encode((array) $booking, JSON_UNESCAPED_UNICODE),
                'created_at' => $existing->created_at ?? now(),
                'updated_at' => now(),
            ]
        );
        $this->syncLegacyCommission($bookingId, $commission, $status);
    }

    private function legacyCollected(int $bookingId): float
    {
        foreach (config('salora_booking_v2.legacy_commission_tables', []) as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            $bookingColumn = $this->firstColumn($table, ['booking_id', 'source_id', 'reference_id']);
            if (!$bookingColumn) {
                continue;
            }
            $row = DB::table($table)->where($bookingColumn, $bookingId)->first();
            if (!$row) {
                continue;
            }
            $data = (array) $row;
            foreach (['collected_syp', 'collected_amount', 'paid_amount'] as $column) {
                if (array_key_exists($column, $data)) {
                    return (float) $data[$column];
                }
            }
            if (!empty($data['is_collected']) || !empty($data['collected_at'])) {
                foreach (['commission_syp', 'amount_syp', 'commission_amount', 'amount'] as $column) {
                    if (array_key_exists($column, $data)) {
                        return (float) $data[$column];
                    }
                }
            }
            $status = strtolower((string) ($data['status'] ?? ''));
            if (in_array($status, ['collected', 'paid'], true)) {
                foreach (['commission_syp', 'amount_syp', 'commission_amount', 'amount'] as $column) {
                    if (array_key_exists($column, $data)) {
                        return (float) $data[$column];
                    }
                }
            }
        }
        return 0;
    }

    private function syncLegacyCommission(int $bookingId, float $commission, string $status): void
    {
        foreach (config('salora_booking_v2.legacy_commission_tables', []) as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            $bookingColumn = $this->firstColumn($table, ['booking_id', 'source_id', 'reference_id']);
            $amountColumn = $this->firstColumn($table, ['commission_syp', 'amount_syp', 'commission_amount', 'amount']);
            if (!$bookingColumn || !$amountColumn) {
                continue;
            }
            $row = DB::table($table)->where($bookingColumn, $bookingId)->first();
            if (!$row) {
                continue;
            }
            $data = (array) $row;
            $currentStatus = strtolower((string) ($data['status'] ?? ''));
            $alreadyCollected = !empty($data['is_collected']) || !empty($data['collected_at']) || in_array($currentStatus, ['collected', 'paid'], true);
            $updates = [];

            // A collected historical amount is never rewritten silently.
            // Any difference is shown as a settlement in salora_booking_commissions.
            if (!$alreadyCollected) {
                $updates[$amountColumn] = $commission;
            }
            if (Schema::hasColumn($table, 'status')) {
                $updates['status'] = $alreadyCollected
                    ? ($currentStatus ?: 'collected')
                    : ($status === 'cancelled' ? 'cancelled' : 'pending');
            }
            if (Schema::hasColumn($table, 'updated_at')) {
                $updates['updated_at'] = now();
            }
            if ($updates) {
                DB::table($table)->where($bookingColumn, $bookingId)->update($updates);
            }
            return;
        }
    }

    private function filterColumns(string $table, array $data): array
    {
        return collect($data)->filter(fn ($value, $column) => Schema::hasColumn($table, $column))->all();
    }

    private function setBookingStatus(string $table, array &$updates, string $value): void
    {
        foreach (['status', 'booking_status'] as $column) {
            if (Schema::hasColumn($table, $column)) {
                $updates[$column] = $value;
                return;
            }
        }
    }

    private function addFinancialEvent(int $bookingId, int $venueId, string $type, float $priceBefore, float $priceAfter, float $commissionBefore, float $commissionAfter, array $metadata, ?int $actor): void
    {
        DB::table('salora_booking_financial_events')->insert([
            'booking_id' => $bookingId,
            'venue_id' => $venueId,
            'event_type' => $type,
            'price_before_syp' => $priceBefore,
            'price_after_syp' => $priceAfter,
            'commission_before_syp' => $commissionBefore,
            'commission_after_syp' => $commissionAfter,
            'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
            'actor_user_id' => $actor,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
