<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Notifications\SaloraBookingV2Notification;
use App\Services\SaloraBookingV2Service;
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
        $minimum = 120;
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
        $configured = $hasWeeklyConfiguration || $hasDateException || $hasLegacyConfiguration;

        $windows = $configured
            ? collect($service->workingWindows($venue, $date))
                ->filter(fn (array $window) => $window['start']->lt($dayEnd) && $window['end']->gt($date))
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
                : 'ساعات العمل: '.$windowLabels
                    ->map(fn (array $window) => $window['open'].' - '.$window['close'])
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
        return response()->json([
            'message' => 'تم نشر العرض مباشرة في التطبيق.',
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
        DB::table('venue_offers')->where('id', $offer)->where('venue_id', $venue)->update([
            'is_active' => $validated['is_active'],
            'published_at' => $validated['is_active'] ? now() : null,
            'updated_at' => now(),
        ]);
        return response()->json(['message' => $validated['is_active'] ? 'تم نشر العرض.' : 'تم إيقاف العرض.']);
    }

    public function bookingActionState(Request $request, int $booking, SaloraBookingV2Service $service): JsonResponse
    {
        $row = $service->booking($booking);
        $this->assertBookingParticipant($row, $request, $service);
        [$start] = $service->extractDateTimes($row);
        $pending = DB::table('booking_change_requests')
            ->where('booking_id', $booking)
            ->where('status', 'pending')
            ->latest('id')
            ->first();

        $status = strtolower((string) ($row->booking_status ?? $row->status ?? ''));
        $paymentStatus = strtolower((string) ($row->payment_status ?? ''));
        $cancellationStatus = strtolower((string) ($row->cancellation_status ?? ''));
        $hoursUntilEvent = now()->diffInHours($start, false);
        $underPaymentReview = $status === 'payment_under_review'
            || $paymentStatus === 'proof_uploaded';
        $closedByCancellation = in_array($cancellationStatus, ['waiting_refund', 'cancelled'], true);
        $directStatuses = [
            'pending',
            'pending_payment',
            'pending_owner_review',
            'requested',
            'awaiting_approval',
            'waiting_approval',
            'owner_approved',
        ];

        $editMode = in_array($status, $directStatuses, true)
            ? 'direct'
            : (in_array($status, ['confirmed', 'modification_requested'], true) ? 'request' : 'blocked');
        $canEdit = $hoursUntilEvent > 120
            && empty($row->edit_locked_at)
            && ! $closedByCancellation
            && ! $underPaymentReview
            && $editMode !== 'blocked';

        $editMessage = match (true) {
            $closedByCancellation => 'لا يمكن تعديل الحجز أثناء الإلغاء أو الاسترداد.',
            $underPaymentReview => 'إيصال الدفع قيد المراجعة. انتظر قرار صاحب المبلغ قبل تعديل الحجز.',
            $hoursUntilEvent <= 120 => 'لا يمكن تعديل الحجز خلال آخر 120 ساعة قبل الموعد.',
            $pending !== null => 'يوجد طلب تعديل قيد المراجعة بالفعل.',
            $editMode === 'direct' => 'يمكن تعديل الحجز مباشرة قبل التأكيد النهائي، وسيعاد حساب السعر والتوفر.',
            $editMode === 'request' => 'الحجز مؤكد؛ سيُرسل التعديل إلى مالك الصالة للموافقة.',
            default => 'حالة الحجز الحالية لا تسمح بالتعديل.',
        };

        return response()->json([
            'booking_id' => $booking,
            'can_edit' => $canEdit,
            'edit_mode' => $editMode,
            'edit_message' => $editMessage,
            'hours_until_event' => $hoursUntilEvent,
            'pending_change_request' => $pending,
            'cancellation_status' => $row->cancellation_status ?? null,
            'cancellation_preview' => $service->cancellationPreview($booking),
        ]);
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
        if ($bookingStatusForEdit === 'payment_under_review' || $paymentStatusForEdit === 'proof_uploaded') {
            abort(422, 'إيصال الدفع قيد المراجعة ولا يمكن تعديل المبلغ أو الموعد قبل اتخاذ القرار.');
        }
        if (now()->diffInHours($eventStart, false) <= 120) {
            abort(422, 'لا يمكن تعديل الحجز خلال آخر خمسة أيام قبل الموعد.');
        }
        if (DB::table('booking_change_requests')->where('booking_id', $booking)->where('status', 'pending')->exists()) {
            abort(422, 'يوجد طلب تعديل قيد المراجعة بالفعل.');
        }

        $validated = $request->validate([
            'venue_id' => ['nullable', 'integer'],
            'start_at' => ['nullable', 'date'],
            'end_at' => ['nullable', 'date'],
            'guest_count' => ['nullable', 'integer', 'min:1'],
            'number_of_guests' => ['nullable', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $venueId = isset($validated['venue_id'])
            ? (int) $validated['venue_id']
            : $service->bookingVenueId($row);
        $newStart = isset($validated['start_at'])
            ? Carbon::parse($validated['start_at'])->second(0)
            : $eventStart;
        $newEnd = isset($validated['end_at'])
            ? Carbon::parse($validated['end_at'])->second(0)
            : $oldEnd;

        if (now()->diffInHours($newStart, false) <= 120) {
            abort(422, 'الموعد الجديد يجب أن يكون بعد أكثر من خمسة أيام.');
        }

        $requested = [
            'venue_id' => $venueId,
            'start_at' => $newStart->toIso8601String(),
            'end_at' => $newEnd->toIso8601String(),
            'event_date' => $newStart->toDateString(),
            'start_time' => $newStart->format('H:i'),
            'end_time' => $newEnd->format('H:i'),
        ];
        $guests = $validated['guest_count']
            ?? $validated['number_of_guests']
            ?? $row->guests_count
            ?? $row->guest_count
            ?? $row->number_of_guests
            ?? null;
        if ($guests !== null) {
            $requested['guests_count'] = (int) $guests;
        }
        if (array_key_exists('notes', $validated)) {
            $requested['notes'] = $validated['notes'];
        }

        $quote = $service->quote($venueId, $newStart, $newEnd, $booking);
        $venueRow = $service->venue($venueId);
        if ($guests !== null && isset($venueRow->capacity) && (int) $guests > (int) $venueRow->capacity) {
            abort(422, 'عدد الضيوف يتجاوز سعة الصالة.');
        }

        $bookingStatus = strtolower((string) ($row->booking_status ?? $row->status ?? ''));
        if (in_array($bookingStatus, ['pending', 'pending_owner_review', 'pending_payment', 'requested', 'awaiting_approval', 'waiting_approval', 'owner_approved'], true)) {
            $quote = $service->applyApprovedChange($booking, $requested, $request->user()?->id);
            $this->notifyUser(
                $service->venueOwnerId($venueRow),
                'تم تعديل حجز غير مثبت نهائياً',
                'عدّل العميل تفاصيل الحجز رقم '.$booking.'، وتم تحديث السعر تلقائياً.',
                ['booking_id' => $booking, 'event' => 'booking_updated_pending']
            );
            return response()->json([
                'message' => 'تم تعديل الحجز مباشرة وإعادة فحص الموعد والسعر لأنه لم يتثبت نهائياً بعد.',
                'status' => 'updated_directly',
                'quote' => $quote,
            ]);
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
            'reason' => $validated['reason'] ?? $validated['notes'] ?? 'طلب تعديل موعد الحجز',
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
        $this->notifyUser(
            $service->venueOwnerId($venueRow),
            'طلب تعديل حجز جديد',
            'أرسل العميل طلب تعديل للحجز رقم '.$booking.'.',
            ['booking_id' => $booking, 'change_request_id' => $id, 'event' => 'change_requested']
        );

        return response()->json([
            'message' => 'تم إرسال طلب التعديل إلى مالك الصالة.',
            'request_id' => $id,
            'quote' => $quote,
        ], 201);
    }

    public function approveChange(Request $request, int $booking, int $changeRequest, SaloraBookingV2Service $service): JsonResponse
    {
        $row = $service->booking($booking);
        $service->assertVenueOwner($service->bookingVenueId($row), $request->user());
        [$currentStart] = $service->extractDateTimes($row);
        if (now()->diffInHours($currentStart, false) <= 120 || !empty($row->edit_locked_at)) {
            abort(422, 'لم يعد تعديل الحجز مسموحاً خلال آخر خمسة أيام أو أثناء الإلغاء.');
        }

        $change = DB::table('booking_change_requests')
            ->where('id', $changeRequest)
            ->where('booking_id', $booking)
            ->where('status', 'pending')
            ->first();
        if (!$change) {
            abort(404, 'طلب التعديل غير موجود أو تمت معالجته.');
        }

        $requested = $this->decodeChangeRequestData($change);
        $quote = $service->applyApprovedChange($booking, $requested, $request->user()?->id);
        DB::table('booking_change_requests')->where('id', $changeRequest)->update(
            $this->changeDecisionUpdates('approved', $request->user()?->id)
        );
        $this->notifyUser(
            $service->bookingUserId($row),
            'تم قبول تعديل الحجز',
            'وافق مالك الصالة على تعديل الحجز رقم '.$booking.' وتم تحديث المبلغ.',
            ['booking_id' => $booking, 'event' => 'change_approved']
        );

        return response()->json([
            'message' => 'تم قبول التعديل وتحديث الموعد والسعر والعمولة والأرباح.',
            'quote' => $quote,
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
            'بقي الحجز رقم '.$booking.' على تفاصيله الأصلية.'.(!empty($validated['reason']) ? ' السبب: '.$validated['reason'] : ''),
            ['booking_id' => $booking, 'event' => 'change_rejected']
        );

        return response()->json(['message' => 'تم رفض التعديل وبقي الحجز الأصلي كما هو.']);
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
        if (in_array((string) ($row->cancellation_status ?? ''), ['waiting_refund', 'cancelled'], true)) {
            abort(422, 'تم إرسال الإلغاء مسبقاً أو أن الحجز ملغى بالفعل.');
        }
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:2000'],
            'accepted_policy' => ['required', 'accepted'],
        ]);
        $result = $service->requestCancellation($booking, $request->user()?->id, $validated['reason'] ?? null);
        $preview = $result['preview'];
        $message = 'تم تسجيل إلغاء الحجز رقم ' . $booking
            . '. نسبة الخصم: ' . $preview['deduction_percentage'] . '%'
            . '، والمبلغ المتوقع استرداده: ' . number_format($preview['refunded_syp']) . ' ل.س.';
        $this->notifyUser($service->bookingUserId($row), 'تم تسجيل إلغاء الحجز', $message, ['booking_id' => $booking, 'event' => 'booking_cancelled']);
        $this->notifyUser($service->venueOwnerId($service->venue($service->bookingVenueId($row))), 'إلغاء حجز', $message, ['booking_id' => $booking, 'event' => 'booking_cancelled']);
        return response()->json($result);
    }

    public function ownerCancel(Request $request, int $booking, SaloraBookingV2Service $service): JsonResponse
    {
        $row = $service->booking($booking);
        $service->assertVenueOwner($service->bookingVenueId($row), $request->user());
        if (in_array((string) ($row->cancellation_status ?? ''), ['waiting_refund', 'cancelled'], true)) {
            abort(422, 'تم إرسال الإلغاء مسبقاً أو أن الحجز ملغى بالفعل.');
        }
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);
        $result = $service->ownerCancellation(
            $booking,
            $request->user()?->id,
            $validated['reason']
        );
        $this->notifyUser(
            $service->bookingUserId($row),
            'ألغت الصالة الحجز',
            'ألغت الصالة الحجز رقم ' . $booking . ' ويحق لك استرداد 100% من المبلغ. السبب: ' . $validated['reason'],
            ['booking_id' => $booking, 'event' => 'owner_cancelled']
        );
        return response()->json($result);
    }

    public function confirmRefund(Request $request, int $booking, SaloraBookingV2Service $service): JsonResponse
    {
        $row = $service->booking($booking);
        $service->assertVenueOwner($service->bookingVenueId($row), $request->user());
        if ((string) ($row->cancellation_status ?? '') !== 'waiting_refund') {
            abort(422, 'هذا الحجز ليس بانتظار استرداد.');
        }
        $result = $service->confirmRefund($booking, $request->user()?->id);
        $this->notifyUser(
            $service->bookingUserId($row),
            'تم تأكيد استرداد المبلغ',
            'أكد مالك الصالة رد المبلغ المستحق للحجز رقم ' . $booking . '.',
            ['booking_id' => $booking, 'event' => 'refund_confirmed']
        );
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
            ->when($request->integer('booking_id'), fn ($query, $bookingId) => $query->where('booking_id', $bookingId))
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
        if (!$userId || !class_exists(\App\Models\User::class)) {
            return;
        }
        try {
            $user = \App\Models\User::find($userId);
            if ($user && method_exists($user, 'notify')) {
                $user->notify(new SaloraBookingV2Notification($title, $body, $data));
            }
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
