<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Notifications\SaloraBookingV2Notification;
use App\Services\SaloraBookingV2Service;
use App\Services\BookingModificationService;
use App\Services\NotificationService;
use App\Services\PaymentWorkflowService;
use App\Services\VenueOfferAnnouncementService;
use App\Models\VenueOffer;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SaloraBookingV2Controller extends Controller
{
    public function venueSettings(int $venue, SaloraBookingV2Service $service): JsonResponse
    {
        $row = $service->venue($venue);
        return response()->json([
            'venue_id' => $venue,
            'hourly_price_syp' => (float) ($row->hourly_price_syp ?? 0),
            'minimum_booking_minutes' => 120,
            'maximum_booking_minutes' => (int) ($row->maximum_booking_minutes ?? 480),
            'cleanup_minutes' => (int) ($row->cleanup_minutes ?? 0),
            'slot_minutes' => 30,
            'working_hours' => DB::table('venue_working_hours')->where('venue_id', $venue)->orderBy('day_of_week')->get(),
            'active_offers' => DB::table('venue_offers')->where('venue_id', $venue)->where('is_active', true)->orderByDesc('published_at')->get(),
        ]);
    }

    public function availability(Request $request, int $venue, SaloraBookingV2Service $service): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date', 'after_or_equal:today'],
            'start_at' => ['nullable', 'date'],
            'booking_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $excludeBookingId = null;
        if (!empty($validated['booking_id'])) {
            $excludedBooking = $service->booking((int) $validated['booking_id']);
            $this->assertBookingParticipant($excludedBooking, $request, $service);
            if ($service->bookingVenueId($excludedBooking) !== $venue) {
                abort(422, 'الحجز المستثنى لا يتبع الصالة المحددة.');
            }
            $excludeBookingId = (int) $validated['booking_id'];
        }

        $date = Carbon::parse($validated['date'])->startOfDay();
        $dayEnd = $date->copy()->addDay();
        $venueRow = $service->venue($venue);
        $minimum = max(120, (int) ($venueRow->minimum_booking_minutes ?? 120));
        $maximum = max($minimum, (int) ($venueRow->maximum_booking_minutes ?? 480));

        $hasWeeklyConfiguration = Schema::hasTable('venue_working_hours')
            && DB::table('venue_working_hours')->where('venue_id', $venue)->exists();
        $hasDateException = Schema::hasTable('venue_schedule_exceptions')
            && DB::table('venue_schedule_exceptions')
            ->where('venue_id', $venue)
            ->whereDate('exception_date', $date->toDateString())
            ->exists();
        $legacyHours = $venueRow->opening_hours ?? null;
        if (is_string($legacyHours)) {
            $legacyHours = json_decode($legacyHours, true);
        }
        $hasLegacyConfiguration = is_array($legacyHours) && $legacyHours !== [];

        // Existing venues created before Salora V2 may have neither weekly
        // working-hours rows nor legacy opening_hours. Give only those venues
        // the same safe defaults used for newly created venues, so the edit
        // picker does not become permanently empty.
        if (! $hasWeeklyConfiguration && ! $hasDateException && ! $hasLegacyConfiguration) {
            $service->ensureDefaultWorkingHours($venue);
            $hasWeeklyConfiguration = Schema::hasTable('venue_working_hours')
                && DB::table('venue_working_hours')->where('venue_id', $venue)->exists();
        }

        $configured = $hasWeeklyConfiguration || $hasDateException || $hasLegacyConfiguration;

        $windows = $configured
            ? collect($service->workingWindows($venue, $date))
            ->filter(fn(array $window) => $window['start']->lt($dayEnd) && $window['end']->gt($date))
            ->values()
            : collect();

        $isClosed = $configured && $windows->isEmpty();
        $windowLabels = $windows->map(function (array $window) use ($date, $dayEnd): array {
            $start = $window['start']->lt($date) ? $date->copy() : $window['start']->copy();
            $end = $window['start']->lt($date) && $window['end']->gt($dayEnd)
                ? $dayEnd->copy()
                : $window['end']->copy();

            return [
                'start_at' => $start->toIso8601String(),
                'end_at' => $end->toIso8601String(),
                'open' => $start->format('H:i'),
                'close' => $end->format('H:i'),
            ];
        })->values();

        $scheduleMessage = !$configured
            ? 'مالك الصالة لم يحدد ساعات العمل لهذا اليوم بعد.'
            : ($isClosed
                ? 'اليوم عطلة والصالة مغلقة. اختر تاريخاً آخر.'
                : 'ساعات العمل: ' . $windowLabels
                ->map(fn(array $window) => $window['open'] . ' - ' . $window['close'])
                ->implode('، '));

        $starts = [];
        $ends = [];

        if ($configured && !$isClosed) {
            foreach ($windows as $window) {
                $cursor = $window['start']->lt($date) ? $date->copy() : $window['start']->copy();
                if ($date->isToday() && $cursor->lessThan(now())) {
                    $cursor = now()->copy();
                }

                $cursor->second(0);
                if ((int) $cursor->minute !== 0) {
                    $cursor->addHour()->minute(0);
                }

                $windowEnd = $window['start']->lt($date) && $window['end']->gt($dayEnd)
                    ? $dayEnd->copy()
                    : $window['end']->copy();
                $lastStart = $windowEnd->copy()->subMinutes($minimum);

                // The selected date is the booking start date. An overnight
                // window may allow the end to pass midnight, but start choices
                // must remain inside the selected calendar day.
                while ($cursor->lessThanOrEqualTo($lastStart) && $cursor->lt($dayEnd)) {
                    $available = true;
                    $reason = null;
                    try {
                        $service->quote($venue, $cursor, $cursor->copy()->addMinutes($minimum), $excludeBookingId);
                    } catch (ValidationException $error) {
                        $available = false;
                        $reason = collect($error->errors())->flatten()->first();
                    }

                    $starts[] = [
                        'value' => $cursor->toIso8601String(),
                        'time' => $cursor->format('H:i'),
                        'label' => $cursor->format('H:i'),
                        'available' => $available,
                        'status' => $available ? 'available' : 'unavailable',
                        'reason' => $reason,
                    ];
                    // Start times are intentionally shown only on full hours.
                    $cursor->addHour();
                }
            }
        }

        if (!empty($validated['start_at'])) {
            $start = Carbon::parse($validated['start_at'])->second(0);
            for ($duration = $minimum; $duration <= $maximum; $duration += 30) {
                $end = $start->copy()->addMinutes($duration);
                try {
                    $quote = $service->quote($venue, $start, $end, $excludeBookingId);
                    $ends[] = [
                        'value' => $end->toIso8601String(),
                        'time' => $end->format('H:i'),
                        'label' => $end->format('H:i'),
                        'duration_minutes' => $duration,
                        'available' => true,
                        'status' => 'available',
                        'quote' => $quote,
                    ];
                } catch (ValidationException $error) {
                    $ends[] = [
                        'value' => $end->toIso8601String(),
                        'time' => $end->format('H:i'),
                        'label' => $end->format('H:i'),
                        'duration_minutes' => $duration,
                        'available' => false,
                        'status' => 'unavailable',
                        'reason' => collect($error->errors())->flatten()->first(),
                    ];
                }
            }
        }

        if ($configured && !$isClosed && $starts === []) {
            $scheduleMessage = 'الصالة مفتوحة في هذا اليوم، لكن لا توجد فترة كاملة تكفي للحد الأدنى للحجز.';
        }

        return response()->json([
            'venue_id' => $venue,
            'date' => $date->toDateString(),
            'schedule' => [
                'configured' => $configured,
                'is_closed' => $isClosed,
                'message' => $scheduleMessage,
                'windows' => $windowLabels,
                'minimum_booking_minutes' => $minimum,
                'maximum_booking_minutes' => $maximum,
                'start_step_minutes' => 60,
                'end_step_minutes' => 30,
            ],
            'starts' => collect($starts)->unique('value')->sortBy('value')->values(),
            'ends' => $ends,
        ]);
    }

    public function quote(Request $request, SaloraBookingV2Service $service): JsonResponse
    {
        $validated = $request->validate([
            'venue_id' => ['required', 'integer'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date'],
            'booking_id' => ['nullable', 'integer'],
        ]);
        return response()->json($service->quote(
            (int) $validated['venue_id'],
            $validated['start_at'],
            $validated['end_at'],
            isset($validated['booking_id']) ? (int) $validated['booking_id'] : null
        ));
    }

    public function ownerShow(Request $request, int $venue, SaloraBookingV2Service $service): JsonResponse
    {
        $service->assertVenueOwner($venue, $request->user());
        $bookingTable = $service->bookingTable();
        $venueColumn = $service->firstColumn($bookingTable, ['venue_id', 'hall_id', 'salon_id']);
        $bookingIds = $venueColumn
            ? DB::table($bookingTable)->where($venueColumn, $venue)->pluck('id')
            : collect();

        return response()->json([
            'venue' => $service->venue($venue),
            'working_hours' => DB::table('venue_working_hours')->where('venue_id', $venue)->orderBy('day_of_week')->get(),
            'exceptions' => DB::table('venue_schedule_exceptions')->where('venue_id', $venue)->orderBy('exception_date')->get(),
            'blocks' => DB::table('venue_schedule_blocks')->where('venue_id', $venue)->orderBy('start_at')->get(),
            'offers' => DB::table('venue_offers')->where('venue_id', $venue)->orderByDesc('created_at')->get(),
            'pending_change_requests' => DB::table('booking_change_requests')
                ->whereIn('booking_id', $bookingIds)
                ->where('status', 'pending')
                ->orderByDesc('created_at')
                ->get(),
            'waiting_refunds' => $venueColumn && Schema::hasColumn($bookingTable, 'cancellation_status')
                ? DB::table($bookingTable)->where($venueColumn, $venue)->where('cancellation_status', 'waiting_refund')->get()
                : [],
        ]);
    }

    public function updatePricing(Request $request, int $venue, SaloraBookingV2Service $service): JsonResponse
    {
        $service->assertVenueOwner($venue, $request->user());
        $validated = $request->validate([
            'hourly_price_syp' => ['required', 'numeric', 'gt:0'],
            'maximum_booking_minutes' => ['required', 'integer', 'min:120', function ($attribute, $value, $fail) {
                if (((int) $value) % 30 !== 0) {
                    $fail('الحد الأقصى يجب أن يكون بخطوات نصف ساعة.');
                }
            }],
            'cleanup_minutes' => ['required', 'integer', 'min:0', function ($attribute, $value, $fail) {
                if (((int) $value) % 30 !== 0) {
                    $fail('مدة التنظيف يجب أن تكون بخطوات نصف ساعة.');
                }
            }],
        ]);

        $update = [
            'hourly_price_syp' => $validated['hourly_price_syp'],
            'minimum_booking_minutes' => 120,
            'maximum_booking_minutes' => $validated['maximum_booking_minutes'],
            'cleanup_minutes' => $validated['cleanup_minutes'],
            'pricing_updated_at' => now(),
        ];
        if (Schema::hasColumn($service->venueTable(), 'updated_at')) {
            $update['updated_at'] = now();
        }
        DB::table($service->venueTable())->where('id', $venue)->update($update);

        return response()->json([
            'message' => 'تم تحديث سعر الساعة وإعدادات الحجز ونشرها مباشرة.',
            'venue' => $service->venue($venue),
        ]);
    }

    public function replaceWorkingHours(Request $request, int $venue, SaloraBookingV2Service $service): JsonResponse
    {
        $service->assertVenueOwner($venue, $request->user());
        $validated = $request->validate([
            'days' => ['required', 'array', 'size:7'],
            'days.*.day_of_week' => ['required', 'integer', 'between:0,6'],
            'days.*.is_closed' => ['required', 'boolean'],
            'days.*.open_time' => ['nullable', 'date_format:H:i'],
            'days.*.close_time' => ['nullable', 'date_format:H:i'],
        ]);
        DB::transaction(function () use ($validated, $venue) {
            foreach ($validated['days'] as $day) {
                if (!$day['is_closed'] && (empty($day['open_time']) || empty($day['close_time']))) {
                    abort(422, 'يجب تحديد وقت الفتح والإغلاق لكل يوم مفتوح.');
                }
                DB::table('venue_working_hours')->updateOrInsert(
                    ['venue_id' => $venue, 'day_of_week' => $day['day_of_week']],
                    [
                        'open_time' => $day['is_closed'] ? null : $day['open_time'],
                        'close_time' => $day['is_closed'] ? null : $day['close_time'],
                        'is_closed' => $day['is_closed'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        });
        return response()->json(['message' => 'تم نشر أوقات عمل الصالة مباشرة.']);
    }

    public function saveException(Request $request, int $venue, SaloraBookingV2Service $service): JsonResponse
    {
        $service->assertVenueOwner($venue, $request->user());
        $validated = $request->validate([
            'exception_date' => ['required', 'date'],
            'is_closed' => ['required', 'boolean'],
            'open_time' => ['nullable', 'date_format:H:i'],
            'close_time' => ['nullable', 'date_format:H:i'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        DB::table('venue_schedule_exceptions')->updateOrInsert(
            ['venue_id' => $venue, 'exception_date' => $validated['exception_date']],
            [
                'is_closed' => $validated['is_closed'],
                'open_time' => $validated['is_closed'] ? null : ($validated['open_time'] ?? null),
                'close_time' => $validated['is_closed'] ? null : ($validated['close_time'] ?? null),
                'note' => $validated['note'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
        return response()->json(['message' => 'تم حفظ الاستثناء.']);
    }

    public function createBlock(Request $request, int $venue, SaloraBookingV2Service $service): JsonResponse
    {
        $service->assertVenueOwner($venue, $request->user());
        $validated = $request->validate([
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);
        $id = DB::table('venue_schedule_blocks')->insertGetId([
            'venue_id' => $venue,
            'start_at' => $validated['start_at'],
            'end_at' => $validated['end_at'],
            'reason' => $validated['reason'] ?? null,
            'created_by_user_id' => $request->user()?->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return response()->json(['message' => 'تم حظر الفترة.', 'id' => $id], 201);
    }

    public function deleteBlock(Request $request, int $venue, int $block, SaloraBookingV2Service $service): JsonResponse
    {
        $service->assertVenueOwner($venue, $request->user());
        DB::table('venue_schedule_blocks')->where('id', $block)->where('venue_id', $venue)->delete();
        return response()->json(['message' => 'تم فتح الفترة من جديد.']);
    }

    public function createOffer(Request $request, int $venue, SaloraBookingV2Service $service): JsonResponse
    {
        $service->assertVenueOwner($venue, $request->user());
        $data = $this->validateOffer($request);
        $id = DB::table('venue_offers')->insertGetId(array_merge($data, [
            'venue_id' => $venue,
            'is_active' => true,
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]));
        $offerRow = VenueOffer::with('venue')->find($id);
        if ($offerRow) {
            app(VenueOfferAnnouncementService::class)->announce($offerRow);
        }
        return response()->json([
            'message' => 'تم نشر العرض مباشرة في التطبيق وإرسال إشعار للعملاء.',
            'offer' => DB::table('venue_offers')->where('id', $id)->first(),
        ], 201);
    }

    public function updateOffer(Request $request, int $venue, int $offer, SaloraBookingV2Service $service): JsonResponse
    {
        $service->assertVenueOwner($venue, $request->user());
        $data = $this->validateOffer($request);
        DB::table('venue_offers')->where('id', $offer)->where('venue_id', $venue)->update(array_merge($data, ['updated_at' => now()]));
        return response()->json(['message' => 'تم تحديث العرض مباشرة.', 'offer' => DB::table('venue_offers')->where('id', $offer)->first()]);
    }

    public function toggleOffer(Request $request, int $venue, int $offer, SaloraBookingV2Service $service): JsonResponse
    {
        $service->assertVenueOwner($venue, $request->user());
        $validated = $request->validate(['is_active' => ['required', 'boolean']]);
        $updates = [
            'is_active' => $validated['is_active'],
            'published_at' => $validated['is_active'] ? now() : null,
            'updated_at' => now(),
        ];
        if ($validated['is_active'] && Schema::hasColumn('venue_offers', 'announcement_sent_at')) {
            $updates['announcement_sent_at'] = null;
        }
        DB::table('venue_offers')->where('id', $offer)->where('venue_id', $venue)->update($updates);
        if ($validated['is_active'] && ($offerRow = VenueOffer::with('venue')->find($offer))) {
            app(VenueOfferAnnouncementService::class)->announce($offerRow, true);
        }
        return response()->json(['message' => $validated['is_active'] ? 'تم نشر العرض وإرسال إشعار للعملاء.' : 'تم إيقاف العرض.']);
    }

    public function bookingActionState(Request $request, int $booking, SaloraBookingV2Service $service): JsonResponse
    {
        $row = $service->booking($booking);
        $this->assertBookingParticipant($row, $request, $service);
        [$start, $end] = $service->extractDateTimes($row);
        $pending = DB::table('booking_change_requests')
            ->where('booking_id', $booking)
            ->where('status', 'pending')
            ->latest('id')
            ->first();

        $paymentAdjustment = $this->latestPaymentAdjustment($booking);
        $hasPendingAdjustment = $paymentAdjustment !== null
            && in_array((string) ($paymentAdjustment['status'] ?? ''), ['pending_payment', 'proof_uploaded', 'pending_refund', 'pending'], true);

        $status = strtolower((string) ($row->booking_status ?? $row->status ?? ''));
        $paymentStatus = strtolower((string) ($row->payment_status ?? ''));
        $cancellationStatus = strtolower((string) ($row->cancellation_status ?? ''));
        $hoursUntilEvent = now()->diffInHours($start, false);
        $underPaymentReview = $status === 'payment_under_review'
            || $paymentStatus === 'proof_uploaded';
        $closedByCancellation = in_array($cancellationStatus, ['waiting_refund', 'cancelled'], true);
        $terminalStatus = in_array($status, [
            'cancelled',
            'completed',
            'expired',
            'owner_rejected',
            'rejected',
            'cancellation_requested',
        ], true);

        // All booking edits are formal requests. There is no direct-edit path.
        $editMode = $terminalStatus ? 'blocked' : 'request';
        $canEdit = $hoursUntilEvent > 120
            && empty($row->edit_locked_at)
            && ! $closedByCancellation
            && ! $underPaymentReview
            && ! $terminalStatus
            && $pending === null
            && ! $hasPendingAdjustment;

        $editMessage = match (true) {
            $closedByCancellation => 'لا يمكن تعديل الحجز أثناء الإلغاء أو الاسترداد.',
            $underPaymentReview => 'إيصال الدفع قيد المراجعة. انتظر قرار صاحب المبلغ قبل تعديل الحجز.',
            $hoursUntilEvent <= 120 => 'لا يمكن تعديل الحجز خلال آخر 120 ساعة قبل الموعد.',
            $pending !== null => 'يوجد طلب تعديل قيد المراجعة بالفعل.',
            $hasPendingAdjustment => 'يوجد فرق دفع أو استرجاع معلق من تعديل سابق ويجب تسويته قبل تعديل جديد.',
            $terminalStatus => 'حالة الحجز الحالية لا تسمح بالتعديل.',
            default => 'يمكن اختيار تاريخ ووقت وعدد ضيوف جديد، ثم يُرسل الطلب إلى مالك الصالة للموافقة أو الرفض.',
        };

        return response()->json([
            'booking_id' => $booking,
            'venue_id' => $service->bookingVenueId($row),
            'start_at' => $start->toIso8601String(),
            'end_at' => $end->toIso8601String(),
            'guest_count' => (int) ($row->guests_count ?? $row->guest_count ?? $row->number_of_guests ?? 1),
            'booking_status' => $status,
            'payment_status' => $paymentStatus,
            'can_edit' => $canEdit,
            'edit_mode' => $editMode,
            'edit_message' => $editMessage,
            'hours_until_event' => $hoursUntilEvent,
            'pending_change_request' => $pending ? $this->changeRequestPayload($pending, $service, $row) : null,
            'cancellation_status' => $row->cancellation_status ?? null,
            'cancellation_preview' => $service->cancellationPreview($booking),
            'payment_adjustment' => $paymentAdjustment,
        ]);
    }

    public function ownerChangeRequests(Request $request, SaloraBookingV2Service $service): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            abort(401);
        }
        $status = strtolower((string) $request->query('status', 'all'));
        if (!in_array($status, ['all', 'pending', 'awaiting_payment', 'approved', 'rejected'], true)) {
            abort(422, 'حالة طلب التعديل غير صحيحة.');
        }

        $query = DB::table('booking_change_requests')->orderByDesc('id');
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $items = [];
        foreach ($query->limit(300)->get() as $change) {
            if (isset($change->type) && $change->type && $change->type !== 'modification') {
                continue;
            }
            try {
                $bookingRow = $service->booking((int) $change->booking_id);
                $venue = $service->venue($service->bookingVenueId($bookingRow));
            } catch (\Throwable) {
                continue;
            }
            if ($service->venueOwnerId($venue) !== (int) $user->id) {
                continue;
            }
            $items[] = $this->changeRequestPayload($change, $service, $bookingRow);
        }

        return response()->json(['data' => $items]);
    }

    public function adminChangeRequests(Request $request, SaloraBookingV2Service $service): JsonResponse
    {
        $this->assertAdmin($request);
        $status = strtolower((string) $request->query('status', 'all'));
        if (!in_array($status, ['all', 'pending', 'awaiting_payment', 'approved', 'rejected'], true)) {
            abort(422, 'حالة طلب التعديل غير صحيحة.');
        }

        $query = DB::table('booking_change_requests')->orderByDesc('id');
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $items = [];
        foreach ($query->limit(500)->get() as $change) {
            if (isset($change->type) && $change->type && $change->type !== 'modification') {
                continue;
            }
            try {
                $bookingRow = $service->booking((int) $change->booking_id);
            } catch (\Throwable) {
                continue;
            }
            $items[] = $this->changeRequestPayload($change, $service, $bookingRow);
        }

        return response()->json(['data' => $items]);
    }

    public function requestChange(Request $request, int $booking, SaloraBookingV2Service $service): JsonResponse
    {
        $row = $service->booking($booking);
        $this->assertClient($row, $request, $service);
        [$eventStart, $oldEnd] = $service->extractDateTimes($row);

        if (!empty($row->edit_locked_at) || in_array((string) ($row->cancellation_status ?? ''), ['waiting_refund', 'cancelled'], true)) {
            abort(422, 'لا يمكن تعديل الحجز أثناء الإلغاء أو بعده.');
        }

        $bookingStatusForEdit = strtolower((string) ($row->booking_status ?? $row->status ?? ''));
        $paymentStatusForEdit = strtolower((string) ($row->payment_status ?? ''));
        if (in_array($bookingStatusForEdit, ['cancelled', 'completed', 'expired', 'owner_rejected', 'rejected', 'cancellation_requested'], true)) {
            abort(422, 'حالة الحجز الحالية لا تسمح بطلب تعديل.');
        }
        if ($bookingStatusForEdit === 'payment_under_review' || $paymentStatusForEdit === 'proof_uploaded') {
            abort(422, 'إيصال الدفع قيد المراجعة ولا يمكن تعديل المبلغ أو الموعد قبل اتخاذ القرار.');
        }
        $activeAdjustment = $this->latestPaymentAdjustment($booking);
        if ($activeAdjustment && in_array((string) ($activeAdjustment['status'] ?? ''), ['pending_payment', 'proof_uploaded', 'pending_refund', 'pending'], true)) {
            abort(422, 'يوجد فرق مالي معلق من تعديل سابق. يجب تسويته قبل إرسال تعديل جديد.');
        }
        if (now()->diffInHours($eventStart, false) <= 120) {
            abort(422, 'لا يمكن تعديل الحجز خلال آخر خمسة أيام قبل الموعد.');
        }
        if (DB::table('booking_change_requests')->where('booking_id', $booking)->where('status', 'pending')->exists()) {
            abort(422, 'يوجد طلب تعديل قيد المراجعة بالفعل.');
        }

        $validated = $request->validate([
            'venue_id' => ['nullable', 'integer'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date'],
            'guest_count' => ['nullable', 'integer', 'min:1'],
            'number_of_guests' => ['nullable', 'integer', 'min:1'],
            'guests_count' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $venueId = isset($validated['venue_id'])
            ? (int) $validated['venue_id']
            : $service->bookingVenueId($row);
        if ($venueId !== $service->bookingVenueId($row)) {
            abort(422, 'تعديل الحجز لا يسمح بنقل الحجز إلى صالة أخرى.');
        }

        $newStart = Carbon::parse($validated['start_at'])->second(0);
        $newEnd = Carbon::parse($validated['end_at'])->second(0);
        if (now()->diffInHours($newStart, false) <= 120) {
            abort(422, 'الموعد الجديد يجب أن يكون بعد أكثر من خمسة أيام.');
        }

        $guests = (int) ($validated['guests_count']
            ?? $validated['guest_count']
            ?? $validated['number_of_guests']);
        $requested = [
            'venue_id' => $venueId,
            'start_at' => $newStart->toIso8601String(),
            'end_at' => $newEnd->toIso8601String(),
            'event_date' => $newStart->toDateString(),
            'start_time' => $newStart->format('H:i'),
            'end_time' => $newEnd->format('H:i'),
            'guests_count' => $guests,
        ];
        if (array_key_exists('notes', $validated)) {
            $requested['notes'] = $validated['notes'];
        }

        $currentGuests = (int) ($row->guests_count ?? $row->guest_count ?? $row->number_of_guests ?? 0);
        $sameSchedule = $newStart->equalTo($eventStart) && $newEnd->equalTo($oldEnd);
        $sameGuests = $guests === $currentGuests;
        $sameNotes = !array_key_exists('notes', $validated)
            || trim((string) ($validated['notes'] ?? '')) === trim((string) ($row->notes ?? ''));
        if ($sameSchedule && $sameGuests && $sameNotes) {
            abort(422, 'لم يتم تغيير التاريخ أو الوقت أو عدد الضيوف.');
        }

        // This quote is the commercial promise shown to the customer. It is stored
        // and frozen if the owner later approves the request.
        $quote = $service->quote($venueId, $newStart, $newEnd, $booking);
        $venueRow = $service->venue($venueId);
        if (isset($venueRow->capacity) && $guests > (int) $venueRow->capacity) {
            abort(422, 'عدد الضيوف يتجاوز سعة الصالة.');
        }

        $table = 'booking_change_requests';
        $payload = [
            'booking_id' => $booking,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ];
        $optional = [
            'customer_id' => $service->bookingUserId($row),
            'type' => 'modification',
            'requested_changes' => json_encode($requested, JSON_UNESCAPED_UNICODE),
            'reason' => $validated['reason'] ?? 'طلب تعديل الحجز',
            'requested_by_user_id' => $request->user()?->id,
            'old_data' => json_encode((array) $row, JSON_UNESCAPED_UNICODE),
            'requested_data' => json_encode($requested, JSON_UNESCAPED_UNICODE),
            'quote_snapshot' => json_encode($quote, JSON_UNESCAPED_UNICODE),
        ];
        foreach ($optional as $column => $value) {
            if (Schema::hasColumn($table, $column)) {
                $payload[$column] = $value;
            }
        }

        $id = DB::table($table)->insertGetId($payload);
        $change = DB::table($table)->where('id', $id)->first();
        $this->notifyUser(
            $service->venueOwnerId($venueRow),
            'طلب تعديل حجز جديد',
            'أرسل العميل طلب تعديل للحجز رقم ' . $booking . '، ويتطلب موافقتك قبل تغيير الموعد.',
            ['booking_id' => $booking, 'change_request_id' => $id, 'event' => 'change_requested']
        );

        return response()->json([
            'message' => 'تم إرسال طلب التعديل إلى مالك الصالة. يبقى الموعد القديم محجوزاً حتى اتخاذ القرار.',
            'request_id' => $id,
            'request' => $change ? $this->changeRequestPayload($change, $service, $row) : null,
            'quote' => $quote,
        ], 201);
    }

    public function approveChange(
        Request $request,
        int $booking,
        int $changeRequest,
        SaloraBookingV2Service $service,
        BookingModificationService $modifications,
    ): JsonResponse {
        $result = DB::transaction(function () use ($request, $booking, $changeRequest, $service, $modifications): array {
            DB::table($service->bookingTable())->where('id', $booking)->lockForUpdate()->first();
            $row = $service->booking($booking);
            $service->assertVenueOwner($service->bookingVenueId($row), $request->user());
            [$currentStart] = $service->extractDateTimes($row);
            if (now()->diffInHours($currentStart, false) <= 120 || !empty($row->edit_locked_at)) {
                abort(422, 'لم يعد تعديل الحجز مسموحاً خلال آخر خمسة أيام أو أثناء عملية معلقة.');
            }

            $change = DB::table('booking_change_requests')
                ->where('id', $changeRequest)
                ->where('booking_id', $booking)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->first();
            if (!$change) {
                abort(404, 'طلب التعديل غير موجود أو تمت معالجته.');
            }

            $requested = $this->decodeChangeRequestData($change);
            $frozenQuote = $this->decodeJsonColumn($change, 'quote_snapshot');

            // Serialize every approval/new booking decision for the same venue.
            // Customer booking creation locks this same venue row, so two flows
            // cannot both reserve the same slot between availability check and hold creation.
            DB::table('venues')
                ->where('id', $service->bookingVenueId($row))
                ->lockForUpdate()
                ->first();

            $preview = $service->previewApprovedChange($booking, $requested, $frozenQuote);
            $difference = round((float) ($preview['difference_syp'] ?? 0), 2);
            $wasPaid = (bool) ($preview['was_paid'] ?? false);

            if ($wasPaid && $difference > 0.01) {
                $invoice = DB::table('invoices')
                    ->where('booking_id', $booking)
                    ->when(Schema::hasColumn('invoices', 'source_type'), fn ($q) => $q->where('source_type', 'venue_booking'))
                    ->latest('id')
                    ->first();
                $usdToSyp = max(1, (float) env('USD_TO_SYP', 14000));
                $adjustmentId = DB::table('salora_booking_payment_adjustments')->insertGetId([
                    'booking_id' => $booking,
                    'change_request_id' => $changeRequest,
                    'invoice_id' => $invoice->id ?? null,
                    'type' => 'additional_payment',
                    'amount_syp' => $difference,
                    'amount_usd' => round($difference / $usdToSyp, 2),
                    'old_total_syp' => (float) ($preview['old_invoice_total_syp'] ?? 0),
                    'new_total_syp' => (float) ($preview['booking_total_syp'] ?? 0),
                    'paid_syp' => 0,
                    'status' => 'pending_payment',
                    'metadata' => json_encode([
                        'reason' => 'booking_change_owner_approved_waiting_difference',
                        'quote' => $preview,
                    ], JSON_UNESCAPED_UNICODE),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $modifications->createHold(
                    $booking,
                    $changeRequest,
                    (int) ($preview['venue_id'] ?? $service->bookingVenueId($row)),
                    (string) $preview['start_at'],
                    (string) $preview['end_at'],
                );

                $changeUpdates = ['status' => 'awaiting_payment', 'updated_at' => now()];
                foreach (['reviewed_by' => $request->user()?->id, 'decided_by_user_id' => $request->user()?->id, 'owner_approved_at' => now()] as $column => $value) {
                    if (Schema::hasColumn('booking_change_requests', $column)) {
                        $changeUpdates[$column] = $value;
                    }
                }
                DB::table('booking_change_requests')->where('id', $changeRequest)->update($changeUpdates);

                $bookingUpdates = [];
                if (Schema::hasColumn($service->bookingTable(), 'edit_locked_at')) {
                    $bookingUpdates['edit_locked_at'] = now();
                }
                if (Schema::hasColumn($service->bookingTable(), 'financial_status')) {
                    $bookingUpdates['financial_status'] = 'additional_payment_due';
                }
                if ($bookingUpdates !== []) {
                    DB::table($service->bookingTable())->where('id', $booking)->update($bookingUpdates);
                }

                return [
                    'mode' => 'awaiting_payment',
                    'row' => $row,
                    'quote' => $preview,
                    'adjustment' => (array) DB::table('salora_booking_payment_adjustments')->where('id', $adjustmentId)->first(),
                    'change' => DB::table('booking_change_requests')->where('id', $changeRequest)->first(),
                ];
            }

            $quote = $service->applyApprovedChange(
                $booking,
                $requested,
                $request->user()?->id,
                $frozenQuote,
            );
            $adjustment = $quote['payment_adjustment'] ?? null;
            if (is_array($adjustment)) {
                DB::table('salora_booking_payment_adjustments')->where('id', $adjustment['id'])->update([
                    'change_request_id' => $changeRequest,
                    'updated_at' => now(),
                ]);
                $adjustment['change_request_id'] = $changeRequest;
                if (($adjustment['type'] ?? '') === 'refund_due') {
                    $bookingUpdates = [];
                    if (Schema::hasColumn($service->bookingTable(), 'edit_locked_at')) {
                        $bookingUpdates['edit_locked_at'] = now();
                    }
                    if ($bookingUpdates !== []) {
                        DB::table($service->bookingTable())->where('id', $booking)->update($bookingUpdates);
                    }
                }
            }

            $updates = $this->changeDecisionUpdates('approved', $request->user()?->id);
            if (Schema::hasColumn('booking_change_requests', 'owner_approved_at')) {
                $updates['owner_approved_at'] = now();
            }
            if (Schema::hasColumn('booking_change_requests', 'finalized_at')) {
                $updates['finalized_at'] = now();
            }
            DB::table('booking_change_requests')->where('id', $changeRequest)->update($updates);

            return [
                'mode' => is_array($adjustment) && ($adjustment['type'] ?? '') === 'refund_due' ? 'refund_due' : 'finalized',
                'row' => $row,
                'quote' => $quote,
                'adjustment' => $adjustment,
                'change' => DB::table('booking_change_requests')->where('id', $changeRequest)->first(),
            ];
        });

        $customerId = $service->bookingUserId($result['row']);
        if ($result['mode'] === 'awaiting_payment') {
            $amount = number_format((float) ($result['adjustment']['amount_syp'] ?? 0), 0, '.', ',');
            $this->notifyUser(
                $customerId,
                'تمت الموافقة على تعديل الحجز - مطلوب دفع فرق',
                'وافق مالك الصالة على التعديل. ادفع فرق السعر '.$amount.' ل.س وارفع الإثبات لإتمام التعديل. الموعد الجديد محفوظ لك دون مهلة زمنية.',
                [
                    'booking_id' => $booking,
                    'change_request_id' => $changeRequest,
                    'payment_adjustment_id' => $result['adjustment']['id'] ?? null,
                    'event' => 'booking_change_payment_required',
                    'target_route' => 'booking_details',
                ],
            );
            return response()->json([
                'message' => 'تمت الموافقة على الطلب. ينتظر التعديل دفع فرق السعر وقبول إثباته قبل اعتماد الموعد الجديد.',
                'status' => 'awaiting_payment',
                'quote' => $result['quote'],
                'payment_adjustment' => $result['adjustment'],
                'request' => $this->changeRequestPayload($result['change'], $service),
            ]);
        }

        if ($result['mode'] === 'refund_due') {
            $amount = number_format((float) ($result['adjustment']['amount_syp'] ?? 0), 0, '.', ',');
            $this->notifyUser(
                $customerId,
                'تم اعتماد تعديل الحجز ولديك مبلغ استرجاع',
                'تم تثبيت الموعد الجديد. لديك مبلغ '.$amount.' ل.س مستحق للاسترجاع من مالك الصالة.',
                [
                    'booking_id' => $booking,
                    'change_request_id' => $changeRequest,
                    'payment_adjustment_id' => $result['adjustment']['id'] ?? null,
                    'event' => 'booking_change_refund_due',
                    'target_route' => 'booking_details',
                ],
            );
        } else {
            $this->notifyUser(
                $customerId,
                'تم اعتماد تعديل الحجز',
                'وافق مالك الصالة وتم اعتماد الموعد والسعر الجديد للحجز رقم '.$booking.'.',
                [
                    'booking_id' => $booking,
                    'change_request_id' => $changeRequest,
                    'event' => 'booking_change_finalized',
                    'target_route' => 'booking_details',
                ],
            );
        }

        return response()->json([
            'message' => $result['mode'] === 'refund_due'
                ? 'تم اعتماد التعديل ويوجد مبلغ استرجاع معلق للعميل.'
                : 'تم قبول التعديل واعتماد الموعد والضيوف والسعر الجديد.',
            'status' => $result['mode'],
            'quote' => $result['quote'],
            'payment_adjustment' => $result['adjustment'],
            'request' => $this->changeRequestPayload($result['change'], $service),
        ]);
    }

    public function rejectChange(Request $request, int $booking, int $changeRequest, SaloraBookingV2Service $service): JsonResponse
    {
        $row = $service->booking($booking);
        $service->assertVenueOwner($service->bookingVenueId($row), $request->user());
        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);
        $change = DB::table('booking_change_requests')
            ->where('id', $changeRequest)
            ->where('booking_id', $booking)
            ->where('status', 'pending')
            ->first();
        if (!$change) {
            abort(404, 'طلب التعديل غير موجود أو تمت معالجته.');
        }

        DB::table('booking_change_requests')->where('id', $changeRequest)->update(
            $this->changeDecisionUpdates(
                'rejected',
                $request->user()?->id,
                $validated['reason'] ?? null
            )
        );
        $this->notifyUser(
            $service->bookingUserId($row),
            'تم رفض تعديل الحجز',
            'بقي الحجز رقم ' . $booking . ' على تفاصيله الأصلية.' . (!empty($validated['reason']) ? ' السبب: ' . $validated['reason'] : ''),
            ['booking_id' => $booking, 'event' => 'change_rejected']
        );

        return response()->json(['message' => 'تم رفض التعديل وبقي الحجز الأصلي كما هو.']);
    }

    public function paymentAdjustmentState(
        Request $request,
        int $booking,
        SaloraBookingV2Service $service,
        PaymentWorkflowService $payments,
    ): JsonResponse {
        $row = $service->booking($booking);
        $this->assertBookingParticipant($row, $request, $service);
        $adjustment = $this->latestPaymentAdjustment($booking);
        if (!$adjustment) {
            return response()->json(['payment_adjustment' => null, 'payment_options' => []]);
        }
        $invoice = \App\Models\Invoice::query()
            ->where('booking_id', $booking)
            ->where('source_type', 'venue_booking')
            ->latest('id')
            ->first();
        return response()->json([
            'payment_adjustment' => $adjustment,
            'payment_options' => $invoice && ($adjustment['type'] ?? '') === 'additional_payment'
                && in_array((string) ($adjustment['status'] ?? ''), ['pending_payment', 'proof_rejected'], true)
                ? $payments->paymentOptions($invoice)
                : [],
        ]);
    }

    public function uploadAdjustmentProof(
        Request $request,
        int $booking,
        int $adjustment,
        SaloraBookingV2Service $service,
        PaymentWorkflowService $payments,
    ): JsonResponse {
        $row = $service->booking($booking);
        $this->assertClient($row, $request, $service);
        $data = $request->validate([
            'payment_method_id' => ['required', 'integer', 'exists:payment_methods,id'],
            'payout_account_id' => ['required', 'integer', 'exists:payout_accounts,id'],
            'sender_name' => ['required', 'string', 'max:160'],
            'transaction_reference' => ['nullable', 'string', 'max:190'],
            'transferred_at' => ['nullable', 'date'],
            'customer_notes' => ['nullable', 'string', 'max:1500'],
            'image' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
        ]);
        $proof = $payments->submitAdjustmentProof(
            $request->user(),
            $booking,
            $adjustment,
            $request->file('image'),
            $data,
        );
        return response()->json([
            'message' => 'تم رفع إثبات فرق الدفع وهو بانتظار مراجعة مالك الصالة دون مهلة زمنية.',
            'payment_proof' => $proof,
            'payment_adjustment' => $this->latestPaymentAdjustment($booking),
        ], 201);
    }

    public function confirmAdjustmentRefund(
        Request $request,
        int $booking,
        int $adjustment,
        BookingModificationService $modifications,
    ): JsonResponse {
        $result = $modifications->confirmRefund($request->user(), $booking, $adjustment);
        return response()->json([
            'message' => 'تم تأكيد رد فرق المبلغ للعميل.',
            'payment_adjustment' => $result,
        ]);
    }

    public function cancellationPreview(Request $request, int $booking, SaloraBookingV2Service $service): JsonResponse
    {
        $row = $service->booking($booking);
        $this->assertClient($row, $request, $service);
        return response()->json($service->cancellationPreview($booking));
    }

    public function cancel(Request $request, int $booking, SaloraBookingV2Service $service): JsonResponse
    {
        $row = $service->booking($booking);
        $this->assertClient($row, $request, $service);
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:2000'],
            'accepted_policy' => ['required', 'accepted'],
        ]);
        $result = $service->requestCancellation($booking, $request->user()?->id, $validated['reason'] ?? null);
        $preview = $result['preview'];
        if (empty($result['already_processed'])) {
            $waitingRefund = $result['status'] === 'waiting_refund';
            $message = 'تم إلغاء الحجز رقم ' . $booking
                . '. نسبة الخصم: ' . $preview['deduction_percentage'] . '%'
                . '، والمبلغ المسترد: ' . number_format($preview['refunded_syp']) . ' ل.س.'
                . '، والمبلغ المحتفظ به للمالك: ' . number_format($preview['owner_retained_syp']) . ' ل.س.';
            $event = $waitingRefund ? 'booking_cancellation_waiting_refund' : 'booking_cancelled';
            $this->notifyUser($service->bookingUserId($row), 'تم إلغاء الحجز', $message, [
                'booking_id' => $booking, 'event' => $event, 'target_route' => 'booking_details',
                'refund_syp' => $preview['refunded_syp'], 'refund_percentage' => $preview['refund_percentage'],
            ]);
            $this->notifyUser(
                $service->venueOwnerId($service->venue($service->bookingVenueId($row))),
                $waitingRefund ? 'إلغاء حجز - مطلوب استرداد' : 'تم إلغاء حجز',
                $waitingRefund ? $message . ' يرجى رد المبلغ ثم تأكيد الاسترداد من تفاصيل الحجز.' : $message,
                ['booking_id' => $booking, 'event' => $event, 'target_route' => 'owner_booking_details', 'refund_syp' => $preview['refunded_syp']]
            );
        }
        return response()->json($result);
    }

    public function ownerCancel(Request $request, int $booking, SaloraBookingV2Service $service): JsonResponse
    {
        $row = $service->booking($booking);
        $service->assertVenueOwner($service->bookingVenueId($row), $request->user());
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);
        $result = $service->ownerCancellation(
            $booking,
            $request->user()?->id,
            $validated['reason']
        );
        if (empty($result['already_processed'])) {
            $this->notifyUser(
                $service->bookingUserId($row),
                'ألغت الصالة الحجز',
                'ألغت الصالة الحجز رقم ' . $booking . ' ويحق لك استرداد 100% من المبلغ. السبب: ' . $validated['reason'],
                ['booking_id' => $booking, 'event' => 'owner_cancelled', 'target_route' => 'booking_details', 'refund_percentage' => 100]
            );
        }
        return response()->json($result);
    }

    public function confirmRefund(Request $request, int $booking, SaloraBookingV2Service $service): JsonResponse
    {
        $row = $service->booking($booking);
        $service->assertVenueOwner($service->bookingVenueId($row), $request->user());
        $result = $service->confirmRefund($booking, $request->user()?->id);
        if (empty($result['already_processed'])) {
            $this->notifyUser(
                $service->bookingUserId($row),
                'تم تأكيد استرداد المبلغ',
                'أكد مالك الصالة رد المبلغ المستحق للحجز رقم ' . $booking . '.',
                ['booking_id' => $booking, 'event' => 'refund_confirmed', 'target_route' => 'booking_details']
            );
        }
        return response()->json($result);
    }

    public function adminFinancials(Request $request, SaloraBookingV2Service $service): JsonResponse
    {
        $this->assertAdmin($request);
        DB::table($service->bookingTable())->orderBy('id')->pluck('id')->each(function ($bookingId) use ($service) {
            try {
                $service->syncCommission((int) $bookingId);
            } catch (\Throwable $error) {
                report($error);
            }
        });
        $commissions = DB::table('salora_booking_commissions')->orderByDesc('updated_at')->paginate(min(100, max(10, (int) $request->integer('per_page', 30))));
        $summary = DB::table('salora_booking_commissions')->selectRaw('COUNT(*) as records, COALESCE(SUM(final_price_syp),0) as final_prices_syp, COALESCE(SUM(owner_retained_syp),0) as owner_retained_syp, COALESCE(SUM(commission_syp),0) as commission_syp, COALESCE(SUM(collected_syp),0) as collected_syp, COALESCE(SUM(settlement_syp),0) as settlement_syp')->first();
        return response()->json(['summary' => $summary, 'commissions' => $commissions]);
    }

    public function adminEvents(Request $request): JsonResponse
    {
        $this->assertAdmin($request);
        return response()->json(DB::table('salora_booking_financial_events')
            ->when($request->integer('booking_id'), fn($query, $bookingId) => $query->where('booking_id', $bookingId))
            ->orderByDesc('created_at')->paginate(50));
    }

    private function validateOffer(Request $request): array
    {
        $request->merge([
            'offer_type' => $request->input('offer_type', $request->input('type', 'percentage')),
            'starts_on' => $request->input('starts_on', $request->input('start_date')),
            'ends_on' => $request->input('ends_on', $request->input('end_date')),
        ]);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'offer_type' => ['required', Rule::in(['percentage'])],
            'percentage' => ['required', 'numeric', 'min:1', 'max:50'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
        ], [
            'percentage.max' => 'ممنوع أن تتجاوز نسبة الخصم 50%.',
            'ends_on.after_or_equal' => 'تاريخ انتهاء العرض لا يمكن أن يكون قبل تاريخ البداية.',
        ]);

        return [
            'title' => $validated['title'],
            'offer_type' => 'percentage',
            'scheduled_discount_type' => null,
            'percentage' => $validated['percentage'],
            'fixed_amount_syp' => null,
            'starts_on' => $validated['starts_on'],
            'ends_on' => $validated['ends_on'],
            'days_of_week' => null,
            'start_time' => null,
            'end_time' => null,
            'minimum_booking_minutes' => null,
        ];
    }

    private function changeRequestPayload(object $change, SaloraBookingV2Service $service, ?object $booking = null): array
    {
        $booking ??= $service->booking((int) $change->booking_id);
        [$currentStart, $currentEnd] = $service->extractDateTimes($booking);
        $requested = $this->decodeChangeRequestData($change);
        $quote = $this->decodeJsonColumn($change, 'quote_snapshot');
        $oldData = $this->decodeJsonColumn($change, 'old_data');
        $venue = $service->venue($service->bookingVenueId($booking));
        $customerId = $service->bookingUserId($booking);
        $customer = $customerId && Schema::hasTable('users')
            ? DB::table('users')->where('id', $customerId)->first()
            : null;

        $oldSnapshot = $oldData ?: (array) $booking;
        try {
            [$oldStart, $oldEnd] = $service->extractDateTimes($oldSnapshot);
        } catch (\Throwable) {
            $oldStart = $currentStart->copy();
            $oldEnd = $currentEnd->copy();
        }

        $requestedStart = isset($requested['start_at'])
            ? Carbon::parse($requested['start_at'])
            : (isset($requested['event_date'], $requested['start_time'])
                ? Carbon::parse($requested['event_date'] . ' ' . $requested['start_time'])
                : $currentStart->copy());
        $requestedEnd = isset($requested['end_at'])
            ? Carbon::parse($requested['end_at'])
            : (isset($requested['event_date'], $requested['end_time'])
                ? Carbon::parse($requested['event_date'] . ' ' . $requested['end_time'])
                : $currentEnd->copy());
        if ($requestedEnd->lessThanOrEqualTo($requestedStart)) {
            $requestedEnd->addDay();
        }

        $oldGuests = (int) ($oldSnapshot['guests_count'] ?? $oldSnapshot['guest_count'] ?? $oldSnapshot['number_of_guests'] ?? 0);
        $requestedGuests = (int) ($requested['guests_count'] ?? $requested['guest_count'] ?? $requested['number_of_guests'] ?? $oldGuests);

        return [
            'id' => (int) $change->id,
            'booking_id' => (int) $change->booking_id,
            'status' => (string) ($change->status ?? 'pending'),
            'type' => (string) ($change->type ?? 'modification'),
            'reason' => $change->reason ?? null,
            'decision_reason' => $change->decision_reason ?? null,
            'created_at' => $change->created_at ?? null,
            'decided_at' => $change->decided_at ?? null,
            'customer' => [
                'id' => $customerId,
                'name' => $customer->name ?? null,
                'email' => $customer->email ?? null,
            ],
            'venue' => [
                'id' => $service->bookingVenueId($booking),
                'name' => $venue->name_ar ?? $venue->name_en ?? $venue->name ?? 'الصالة',
            ],
            'old' => [
                'start_at' => $oldStart->toIso8601String(),
                'end_at' => $oldEnd->toIso8601String(),
                'guests_count' => $oldGuests,
                'total_syp' => (float) ($oldSnapshot['total_syp'] ?? $oldSnapshot['final_price_syp'] ?? 0),
            ],
            'requested' => [
                ...$requested,
                'start_at' => $requestedStart->toIso8601String(),
                'end_at' => $requestedEnd->toIso8601String(),
                'guests_count' => $requestedGuests,
            ],
            'quote_snapshot' => $quote,
            'current_booking' => [
                'start_at' => $currentStart->toIso8601String(),
                'end_at' => $currentEnd->toIso8601String(),
                'guests_count' => (int) ($booking->guests_count ?? $booking->guest_count ?? $booking->number_of_guests ?? 0),
                'total_syp' => (float) ($booking->total_syp ?? $booking->final_price_syp ?? 0),
            ],
            'payment_adjustment' => $this->latestPaymentAdjustment((int) $change->booking_id),
        ];
    }

    private function decodeJsonColumn(object $row, string $column): ?array
    {
        $data = (array) $row;
        if (!array_key_exists($column, $data) || $data[$column] === null || $data[$column] === '') {
            return null;
        }
        $value = $data[$column];
        if (is_string($value)) {
            $value = json_decode($value, true);
        }
        return is_array($value) ? $value : null;
    }

    private function latestPaymentAdjustment(int $bookingId): ?array
    {
        if (!Schema::hasTable('salora_booking_payment_adjustments')) {
            return null;
        }
        $row = DB::table('salora_booking_payment_adjustments')
            ->where('booking_id', $bookingId)
            ->latest('id')
            ->first();
        return $row ? (array) $row : null;
    }

    private function decodeChangeRequestData(object $change): array
    {
        $row = (array) $change;
        foreach (['requested_data', 'requested_changes'] as $column) {
            if (!array_key_exists($column, $row) || $row[$column] === null || $row[$column] === '') {
                continue;
            }
            $value = $row[$column];
            if (is_string($value)) {
                $value = json_decode($value, true);
            }
            if (is_array($value)) {
                return $value;
            }
        }
        return [];
    }

    private function changeDecisionUpdates(string $status, ?int $userId, ?string $reason = null): array
    {
        $table = 'booking_change_requests';
        $updates = [
            'status' => $status,
            'updated_at' => now(),
        ];
        $optional = [
            'reviewed_by' => $userId,
            'decided_by_user_id' => $userId,
            'decision_reason' => $reason,
            'decided_at' => now(),
        ];
        foreach ($optional as $column => $value) {
            if (Schema::hasColumn($table, $column)) {
                $updates[$column] = $value;
            }
        }
        return $updates;
    }

    private function assertBookingParticipant(object $booking, Request $request, SaloraBookingV2Service $service): void
    {
        $user = $request->user();
        if (!$user) {
            abort(401);
        }
        $role = strtolower((string) ($user->role ?? $user->type ?? ''));
        if (in_array($role, ['admin', 'administrator', 'super_admin'], true)) {
            return;
        }
        $clientId = $service->bookingUserId($booking);
        if ($clientId !== null && $clientId === (int) $user->id) {
            return;
        }
        $ownerId = $service->venueOwnerId($service->venue($service->bookingVenueId($booking)));
        if ($ownerId !== null && $ownerId === (int) $user->id) {
            return;
        }
        abort(403, 'لا تملك صلاحية عرض إجراءات هذا الحجز.');
    }

    private function assertClient(object $booking, Request $request, SaloraBookingV2Service $service): void
    {
        $user = $request->user();
        if (!$user) {
            abort(401);
        }
        $role = strtolower((string) ($user->role ?? $user->type ?? ''));
        if (in_array($role, ['admin', 'administrator', 'super_admin'], true)) {
            return;
        }
        $clientId = $service->bookingUserId($booking);
        if ($clientId !== null && $clientId !== (int) $user->id) {
            abort(403, 'لا تملك صلاحية تعديل أو إلغاء هذا الحجز.');
        }
    }

    private function notifyUser(?int $userId, string $title, string $body, array $data = []): void
    {
        if (!$userId) {
            return;
        }
        try {
            NotificationService::send(
                $userId,
                $title,
                $body,
                (string) ($data['event'] ?? 'salora_booking_v2'),
                $data,
            );
        } catch (\Throwable $error) {
            report($error);
        }
    }

    private function assertAdmin(Request $request): void
    {
        $user = $request->user();
        $role = strtolower((string) ($user->role ?? $user->type ?? ''));
        if (!in_array($role, ['admin', 'administrator', 'super_admin'], true)) {
            abort(403);
        }
    }
}
