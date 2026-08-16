<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Booking;
use App\Models\BookingService;
use App\Models\Event;
use App\Models\EventTodoItem;
use App\Models\EventType;
use App\Models\ProviderServiceRequest;
use App\Models\Service;
use App\Models\Venue;
use App\Services\ActivityLogger;
use App\Services\BookingPricingService;
use App\Services\ExchangeRateService;
use App\Services\InvoiceService;
use App\Services\BookingWorkflowService;
use App\Services\NotificationService;
use App\Services\SaloraBookingV2Service;
use App\Services\VenueAvailabilityService;
use App\Support\SaloraStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class CustomerBookingController extends BaseApiController
{
    public function index(Request $request, VenueAvailabilityService $availability)
    {
        $availability->expireStalePending();
        $query = Booking::with([
            'venue.images', 'eventType', 'event', 'services', 'latestPaymentProof', 'invoice',
            'providerRequests.service', 'providerRequests.provider:id,name,phone,email',
            'providerRequests.invoice.latestPaymentProof.method',
            'providerRequests.invoice.latestPaymentProof.payoutAccount', 'changeRequests',
        ])->where('customer_id', $request->user()->id);

        if ($request->boolean('active_for_services')) {
            $query->where('booking_status', SaloraStatus::BOOKING_CONFIRMED)
                ->whereDate('event_date', '>=', now()->toDateString());
        }

        return $this->ok($query->latest()->get());
    }

    public function show(Request $request, Booking $booking)
    {
        abort_unless((int)$booking->customer_id === (int)$request->user()->id, 403);
        return $this->ok($booking->load([
            'venue.images', 'eventType', 'event.todoItems', 'services', 'paymentProofs.invoice',
            'invoice.transactions', 'providerRequests.service', 'providerRequests.provider:id,name,phone,email',
            'providerRequests.invoice.latestPaymentProof.method',
            'providerRequests.invoice.latestPaymentProof.payoutAccount',
            'statusHistory.actor:id,name', 'changeRequests.reviewer:id,name',
        ]));
    }

    public function store(
        Request $request,
        BookingPricingService $pricing,
        BookingWorkflowService $workflow,
        VenueAvailabilityService $availability,
        SaloraBookingV2Service $bookingV2,
        InvoiceService $invoices,
        ExchangeRateService $exchangeRates
    ) {
        $data = $request->validate([
            'event_id' => 'nullable|exists:events,id',
            'venue_id' => 'required|exists:venues,id',
            'event_type_id' => 'required|exists:event_types,id',
            'event_name' => 'required|string|max:160',
            'host_name' => 'nullable|string|max:160',
            'event_date' => 'required|date|after_or_equal:today',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'guests_count' => 'required|integer|min:1',
            'service_ids' => 'nullable|array',
            'service_ids.*' => 'integer|exists:services,id',
            'notes' => 'nullable|string|max:1000',
            'currency' => 'nullable|in:USD,SYP',
        ]);

        $venue = Venue::with(['eventTypes', 'services', 'owner'])->findOrFail($data['venue_id']);
        if (!$venue->owner || !$venue->owner->isAvailableForNewBusiness()) {
            return $this->fail('الصالة غير متاحة لحجوزات جديدة حالياً بسبب حالة حساب المالك.', 422, ['code' => 'venue_owner_unavailable']);
        }
        if ($venue->status !== 'approved') return $this->fail('Venue is not approved.', 422);
        if ((int)$data['guests_count'] > (int)$venue->capacity) return $this->fail('Guest count exceeds venue capacity.', 422);
        if (!$venue->eventTypes->contains('id', (int)$data['event_type_id'])) {
            return $this->fail('This venue does not support the selected event type.', 422);
        }
        $startAt = Carbon::parse($data['event_date'].' '.$data['start_time'])->second(0);
        $endAt = Carbon::parse($data['event_date'].' '.$data['end_time'])->second(0);
        if ($endAt->lessThanOrEqualTo($startAt)) {
            $endAt->addDay();
        }

        // Check working hours before duration validation. This intentionally
        // preserves the API contract: a time outside the venue schedule must
        // return outside_opening_hours even when its duration is also invalid.
        $insideOpeningHours = collect($bookingV2->workingWindows($venue->id, $startAt))->contains(
            fn (array $window) =>
                $startAt->greaterThanOrEqualTo($window['start']) &&
                $endAt->lessThanOrEqualTo($window['end'])
        );
        if (!$insideOpeningHours) {
            return $this->fail(
                'الوقت المختار خارج أوقات عمل الصالة أو أن الصالة مغلقة في هذا اليوم.',
                422,
                [
                    'code' => 'outside_opening_hours',
                    'opening_hours' => $venue->opening_hours,
                ]
            );
        }

        // The final quote runs after locking the venue row inside the
        // transaction. This preserves the API's 409 conflict response while
        // still validating duration, offers and final pricing.

        if (!empty($data['event_id'])) {
            $event = Event::whereKey($data['event_id'])->where('customer_id', $request->user()->id)->first();
            if (!$event) return $this->fail('The selected event does not belong to the current customer.', 403);
            if ((int)$event->event_type_id !== (int)$data['event_type_id']) {
                return $this->fail('The booking event type must match the selected event.', 422);
            }
        }

        $result = DB::transaction(function () use ($request, $data, $venue, $pricing, $workflow, $availability, $bookingV2, $invoices, $exchangeRates, $startAt, $endAt) {
            Venue::whereKey($venue->id)->lockForUpdate()->firstOrFail();
            $availability->expireStalePending($venue->id, $data['event_date']);
            // Keep the existing conflict contract: booking collisions return
            // HTTP 409 with venue_time_conflict and unavailable intervals.
            if ($availability->hasConflict($venue->id, $data['event_date'], $data['start_time'], $data['end_time'], null, true)) {
                return $this->fail('هذا الموعد محجوز أو يتعارض مع حجز آخر. اختر وقتاً مختلفاً.', 409, [
                    'code' => 'venue_time_conflict',
                    'unavailable_intervals' => $availability->unavailableIntervals($venue->id, $data['event_date']),
                ]);
            }
            // Quote only after the locked conflict check. It validates working
            // hours, start/end steps, min/max duration, offers and final price.
            try {
                $hallQuote = $bookingV2->quote($venue->id, $startAt, $endAt);
            } catch (ValidationException $exception) {
                $quoteErrors = $exception->errors();
                $flatMessages = collect($quoteErrors)
                    ->flatten()
                    ->filter()
                    ->map(fn ($message) => (string) $message)
                    ->values();
                $quoteMessage = $flatMessages->first() ?: 'الوقت المختار غير صالح.';

                if ($flatMessages->contains(
                    fn (string $message) => str_contains($message, 'خارج أوقات عمل الصالة')
                )) {
                    return $this->fail(
                        'الوقت المختار خارج أوقات عمل الصالة أو أن الصالة مغلقة في هذا اليوم.',
                        422,
                        [
                            'code' => 'outside_opening_hours',
                            'opening_hours' => $venue->opening_hours,
                            'details' => $quoteErrors,
                        ]
                    );
                }

                if ($flatMessages->contains(
                    fn (string $message) =>
                        str_contains($message, 'محجوز') ||
                        str_contains($message, 'غير متاحة') ||
                        str_contains($message, 'إغلاق') ||
                        str_contains($message, 'صيانة')
                )) {
                    return $this->fail(
                        $quoteMessage,
                        409,
                        [
                            'code' => 'venue_time_conflict',
                            'unavailable_intervals' => $availability->unavailableIntervals($venue->id, $data['event_date']),
                            'details' => $quoteErrors,
                        ]
                    );
                }

                return $this->fail($quoteMessage, 422, [
                    'code' => 'invalid_booking_time',
                    'details' => $quoteErrors,
                ]);
            }

            $currency = $data['currency'] ?? 'SYP';
            $calculation = $pricing->calculate(
                $venue,
                (int)$data['guests_count'],
                $data['service_ids'] ?? [],
                $data['event_date'],
                $currency
            );

            $serviceSubtotalSyp = collect($calculation['service_items'])->sum('total_syp');
            $usdToSyp = max(1, (float) ($calculation['exchange_rate_syp_per_usd'] ?? 0));

            $calculation['hall_syp'] = $hallQuote['final_price_syp'];
            $calculation['hall_usd'] = $exchangeRates->toUsd($hallQuote['final_price_syp'], $usdToSyp);
            $calculation['subtotal_syp'] = round($hallQuote['price_before_discount_syp'] + $serviceSubtotalSyp, 2);
            $calculation['subtotal_usd'] = $exchangeRates->toUsd($calculation['subtotal_syp'], $usdToSyp);
            $calculation['discount_syp'] = $hallQuote['discount_syp'];
            $calculation['discount_usd'] = $exchangeRates->toUsd($hallQuote['discount_syp'], $usdToSyp);
            $calculation['total_syp'] = round($hallQuote['final_price_syp'] + $serviceSubtotalSyp, 2);
            $calculation['total_usd'] = $exchangeRates->toUsd($calculation['total_syp'], $usdToSyp);

            $event = !empty($data['event_id'])
                ? Event::whereKey($data['event_id'])->where('customer_id', $request->user()->id)->firstOrFail()
                : $this->createEventFromBooking($request, $data, $venue, $calculation);

            $booking = Booking::create([
                'booking_number' => 'BK-'.now()->format('Ymd').'-'.strtoupper(Str::random(8)),
                'customer_id' => $request->user()->id,
                'venue_id' => $venue->id,
                'owner_id' => $venue->owner_id,
                'event_type_id' => $data['event_type_id'],
                'event_id' => $event->id,
                'event_name' => $data['event_name'],
                'host_name' => $data['host_name'] ?? null,
                'event_date' => $data['event_date'],
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'guests_count' => $data['guests_count'],
                'notes' => $data['notes'] ?? null,
                'booking_status' => SaloraStatus::BOOKING_PENDING_PAYMENT,
                'payment_status' => SaloraStatus::PAYMENT_UNPAID,
                'subtotal_syp' => $calculation['subtotal_syp'],
                'subtotal_usd' => $calculation['subtotal_usd'],
                'discount_syp' => $calculation['discount_syp'],
                'discount_usd' => $calculation['discount_usd'],
                'total_syp' => $calculation['total_syp'],
                'total_usd' => $calculation['total_usd'],
                'currency' => $currency,
                'exchange_rate_syp_per_usd' => $usdToSyp,
                'expires_at' => null,
            ]);
            $workflow->recordInitialState($booking, $request->user());
            $invoice = $invoices->createForBooking($booking->fresh());

            foreach ($calculation['service_items'] as $item) {
                BookingService::create(['booking_id' => $booking->id, ...$item]);
            }

            $this->completeHallTodo($event, $request->user()->id);

            NotificationService::send(
                $venue->owner_id,
                'حجز جديد بانتظار إثبات الدفع',
                'أنشأ العميل الحجز '.$booking->booking_number.'. سيظهر لك للمراجعة فور رفع إثبات الدفع.',
                'booking_created',
                [
                    'booking_id' => $booking->id,
                    'invoice_id' => $invoice->id,
                    'target_route' => 'business_payments',
                ]
            );
            ActivityLogger::log('created_booking', 'booking', $booking->id, 'Customer created booking '.$booking->booking_number);

            return $booking->load([
                'venue.images', 'eventType', 'event.todoItems', 'services', 'latestPaymentProof', 'invoice',
                'providerRequests.service', 'providerRequests.provider:id,name,phone,email',
            'providerRequests.invoice.latestPaymentProof.method',
            'providerRequests.invoice.latestPaymentProof.payoutAccount', 'statusHistory',
            ]);
        });

        if ($result instanceof \Illuminate\Http\JsonResponse) return $result;
        return $this->ok($result, 'Booking created.', 201);
    }

    public function requestProviderServices(Request $request, Booking $booking)
    {
        abort_unless((int)$booking->customer_id === (int)$request->user()->id, 403);
        if (!SaloraStatus::bookingAllowsProviderServiceRequest($booking->booking_status)) {
            return $this->fail('Provider services can be added only to an active booking.', 422);
        }
        if ($booking->event_date?->isBefore(now()->startOfDay())) {
            return $this->fail('Provider services cannot be added to a past booking.', 422);
        }

        $data = $request->validate([
            'provider_service_ids' => 'required|array|min:1',
            'provider_service_ids.*' => 'integer|exists:services,id',
            'notes' => 'nullable|string|max:1000',
        ]);
        $booking->loadMissing(['eventType', 'venue']);
        $services = Service::with(['categoryModel', 'provider'])
            ->whereIn('id', $data['provider_service_ids'])
            ->where('type', 'external_vendor')
            ->where('is_active', true)
            ->where('approval_status', 'approved')
            ->whereNotNull('provider_id')
            ->get();

        if ($services->count() !== count(array_unique($data['provider_service_ids']))) {
            return $this->fail('One or more selected provider services are unavailable.', 422);
        }

        $unavailableProvider = $services->first(
            fn (Service $service) => !$service->provider || !$service->provider->isAvailableForNewBusiness()
        );
        if ($unavailableProvider) {
            return $this->fail(
                'إحدى الخدمات المختارة غير متاحة لطلبات جديدة بسبب حالة حساب مقدم الخدمة.',
                422,
                ['code' => 'provider_unavailable', 'service_id' => $unavailableProvider->id]
            );
        }

        $eventTypeKeys = collect([
            $booking->eventType?->name_en,
            $booking->eventType?->name_ar,
        ])->filter()->map(fn ($value) => $this->eventTypeKey((string) $value))->filter()->unique();
        $unsupported = $services->first(function (Service $service) use ($eventTypeKeys) {
            $availableFor = collect($service->available_for ?? [])
                ->filter()
                ->map(fn ($value) => $this->eventTypeKey((string) $value))
                ->filter()
                ->unique();
            return $availableFor->isNotEmpty() && $availableFor->intersect($eventTypeKeys)->isEmpty();
        });
        if ($unsupported) {
            return $this->fail('The service '.$unsupported->name_en.' does not support this booking event type.', 422);
        }

        $disallowed = $services->first(
            fn (Service $service) => ! $this->venueAllowsProviderCategory($booking, $service)
        );
        if ($disallowed) {
            return $this->fail(
                'The booked venue does not allow the provider category for '.$disallowed->name_en.'.',
                422
            );
        }

        $created = $this->createProviderRequests(
            $booking,
            $request->user()->id,
            $services->pluck('id')->all(),
            $data['notes'] ?? null
        );
        return $this->ok(
            $booking->load([
                'venue.images', 'eventType', 'event', 'services', 'latestPaymentProof', 'invoice',
                'providerRequests.service', 'providerRequests.provider:id,name,phone,email',
            'providerRequests.invoice.latestPaymentProof.method',
            'providerRequests.invoice.latestPaymentProof.payoutAccount', 'changeRequests',
            ]),
            $created > 0 ? 'Service requests sent.' : 'All selected services were already requested.'
        );
    }

    public function cancel(Request $request, Booking $booking, BookingWorkflowService $workflow, SaloraBookingV2Service $bookingV2)
    {
        abort_unless((int) $booking->customer_id === (int) $request->user()->id, 403);
        $data = $request->validate([
            'reason' => 'nullable|string|max:2000',
            'accepted_policy' => 'required|accepted',
        ]);

        // Keep the legacy endpoint for compatibility, but make the V2 cancellation
        // service the single source of truth for policy, refund, commission, holds,
        // provider requests and audit/financial events.
        $result = $bookingV2->requestCancellation(
            (int) $booking->id,
            (int) $request->user()->id,
            $data['reason'] ?? null,
        );

        if (empty($result['already_processed'])) {
            NotificationService::send(
                (int) $booking->owner_id,
                $result['status'] === 'waiting_refund' ? 'إلغاء حجز بانتظار الاسترداد' : 'تم إلغاء حجز',
                $result['status'] === 'waiting_refund'
                    ? 'ألغى العميل الحجز '.$booking->booking_number.' ويجب رد المبلغ المستحق الظاهر في تفاصيل الحجز.'
                    : 'ألغى العميل الحجز '.$booking->booking_number.'.',
                $result['status'] === 'waiting_refund' ? 'booking_cancellation_waiting_refund' : 'booking_cancelled',
                ['booking_id' => $booking->id, 'event' => $result['status'] === 'waiting_refund' ? 'booking_cancellation_waiting_refund' : 'booking_cancelled', 'target_route' => 'owner_booking_details']
            );
            ActivityLogger::log('cancelled_booking', 'booking', $booking->id, 'Customer cancelled through unified Salora V2 cancellation flow.');
        }
        return $this->ok($result, $result['status'] === 'waiting_refund' ? 'Booking cancelled and awaiting refund.' : 'Booking cancelled.');
    }

    private function createProviderRequests(Booking $booking, int $customerId, array $serviceIds, ?string $notes = null): int
    {
        $services = Service::with(['categoryModel', 'provider'])->whereIn('id', $serviceIds)
            ->where('type', 'external_vendor')
            ->where('is_active', true)
            ->where('approval_status', 'approved')
            ->whereNotNull('provider_id')
            ->get();

        $exchangeRates = app(ExchangeRateService::class);
        $currentRate = $exchangeRates->rate();
        $count = 0;
        foreach ($services as $service) {
            if (!$service->provider || !$service->provider->isAvailableForNewBusiness()) continue;

            $exists = ProviderServiceRequest::where('booking_id', $booking->id)
                ->where('service_id', $service->id)
                ->whereNotIn('status', ['cancelled'])
                ->exists();
            if ($exists) continue;

            $providerRequest = ProviderServiceRequest::create([
                'booking_id' => $booking->id,
                'customer_id' => $customerId,
                'provider_id' => $service->provider_id,
                'service_id' => $service->id,
                'service_name' => $service->name_ar ?: $service->name_en,
                'service_category' => $service->categoryModel?->name_ar ?: $service->category,
                'price_syp' => $service->price_syp,
                'price_usd' => $exchangeRates->toUsd($service->price_syp, $currentRate),
                'exchange_rate_syp_per_usd' => $currentRate,
                'payment_type' => 'manual_transfer',
                'status' => 'pending',
                'customer_notes' => $notes,
            ]);
            NotificationService::send(
                $service->provider_id,
                'طلب خدمة جديد',
                'يوجد طلب للخدمة '.$providerRequest->service_name.' مرتبط بالحجز '.$booking->booking_number.'.',
                'provider_service_request',
                ['booking_id' => $booking->id, 'request_id' => $providerRequest->id]
            );
            $count++;
        }
        return $count;
    }

    private function venueAllowsProviderCategory(Booking $booking, Service $service): bool
    {
        $allowed = collect($booking->venue?->vendor_categories ?? [])
            ->map(fn ($value) => $this->providerCategoryKey((string) $value))
            ->filter()
            ->unique();

        // Empty means the hall owner has not restricted external provider categories.
        if ($allowed->isEmpty()) return true;

        $serviceCategories = collect([
            $service->category,
            $service->categoryModel?->name_en,
            $service->categoryModel?->name_ar,
        ])->filter()
            ->map(fn ($value) => $this->providerCategoryKey((string) $value))
            ->filter()
            ->unique();

        return $allowed->intersect($serviceCategories)->isNotEmpty();
    }

    private function providerCategoryKey(string $value): string
    {
        $value = trim((string) preg_replace('/^[^\p{L}\p{N}]+/u', '', $value));
        $normalized = Str::lower((string) preg_replace('/\s+/u', ' ', $value));

        return match (true) {
            Str::contains($normalized, ['photo', 'تصوير']) => 'photography',
            Str::contains($normalized, ['hospital', 'cater', 'food', 'drink', 'ضياف', 'مأكول', 'مشروب', 'قهوة', 'شاي']) => 'hospitality',
            Str::contains($normalized, ['equipment', 'decor', 'light', 'sound', 'تجهيز', 'معدات', 'ديكور', 'إضاءة', 'صوت']) => 'equipment',
            Str::contains($normalized, ['cake', 'كيك', 'حلويات']) => 'cake',
            Str::contains($normalized, ['print', 'invitation', 'طباعة', 'دعوات']) => 'printing',
            Str::contains($normalized, ['reader', 'sheikh', 'قارئ', 'شيخ']) => 'religious',
            Str::contains($normalized, ['organ', 'planning', 'تنظيم']) => 'organization',
            default => $normalized,
        };
    }

    private function eventTypeKey(string $value): string
    {
        $normalized = Str::lower(trim((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value)));
        $normalized = trim((string) preg_replace('/\s+/u', ' ', $normalized));

        return match (true) {
            Str::contains($normalized, ['wedding', 'زفاف', 'عرس']) => 'wedding',
            Str::contains($normalized, ['engagement', 'خطوبة']) => 'engagement',
            Str::contains($normalized, ['graduation', 'تخرج']) => 'graduation',
            Str::contains($normalized, ['birthday', 'عيد ميلاد']) => 'birthday',
            Str::contains($normalized, ['family', 'عائل']) => 'family',
            Str::contains($normalized, ['condolence', 'عزاء']) => 'condolence',
            Str::contains($normalized, ['conference', 'مؤتمر']) => 'conference',
            Str::contains($normalized, ['meeting', 'اجتماع']) => 'meeting',
            default => $normalized,
        };
    }

    private function createEventFromBooking(Request $request, array $data, Venue $venue, array $calculation): Event
    {
        $event = Event::create([
            'customer_id' => $request->user()->id,
            'event_type_id' => $data['event_type_id'],
            'name' => $data['event_name'],
            'event_date' => $data['event_date'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'guests_count' => $data['guests_count'],
            'budget_syp' => $calculation['total_syp'] ?? null,
            'budget_usd' => $calculation['total_usd'] ?? null,
            'city' => $venue->city,
            'notes' => $data['notes'] ?? null,
            'status' => 'active',
        ]);

        $templates = EventType::findOrFail($data['event_type_id'])->todoTemplates()->where('is_active', true)->get();
        foreach ($templates as $template) {
            EventTodoItem::create([
                'event_id' => $event->id,
                'todo_template_id' => $template->id,
                'title' => $template->task_ar ?: $template->task_en,
                'sort_order' => $template->sort_order,
                'updated_by' => $request->user()->id,
            ]);
        }
        return $event;
    }

    private function completeHallTodo(Event $event, int $userId): void
    {
        $item = $event->todoItems()
            ->where(function ($query) {
                $query->where('title', 'like', '%صالة%')
                    ->orWhere('title', 'like', '%قاعة%')
                    ->orWhere('title', 'like', '%hall%')
                    ->orWhere('title', 'like', '%venue%');
            })
            ->first();
        if ($item) {
            $item->update(['is_completed' => true, 'completed_at' => now(), 'updated_by' => $userId]);
        }
    }

    private function withinOpeningHours(Venue $venue, string $date, string $start, string $end): bool
    {
        $hours = $venue->opening_hours ?? [];
        if ($hours === []) return true;
        $day = strtolower(\Carbon\Carbon::parse($date)->format('l'));
        $entry = $hours[$day] ?? null;
        if (!is_array($entry)) return true;
        if (!($entry['enabled'] ?? false)) return false;
        $open = substr((string) ($entry['open'] ?? ''), 0, 5);
        $close = substr((string) ($entry['close'] ?? ''), 0, 5);
        if ($open === '' || $close === '') return false;
        return $start >= $open && $end <= $close;
    }

}
