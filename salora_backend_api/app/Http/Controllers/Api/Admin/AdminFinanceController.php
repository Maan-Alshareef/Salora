<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Booking;
use App\Models\ProviderServiceRequest;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;

class AdminFinanceController extends BaseApiController
{
    private const REVENUE_STATUSES = ['confirmed', 'completed'];

    public function summary()
    {
        $venueRevenue = Booking::query()->whereIn('booking_status', self::REVENUE_STATUSES);
        $serviceRevenue = ProviderServiceRequest::query()->where('payment_status', 'approved');
        $cancelled = Booking::query()->whereIn('booking_status', ['cancelled', 'owner_rejected', 'expired']);

        $venueEarningsSyp = (float) (clone $venueRevenue)->sum('platform_commission_syp');
        $serviceEarningsSyp = (float) (clone $serviceRevenue)->sum('provider_commission_syp');
        $venueCollectedSyp = (float) (clone $venueRevenue)->where('commission_status', 'collected')->sum('platform_commission_syp');
        $serviceCollectedSyp = (float) (clone $serviceRevenue)->where('commission_status', 'collected')->sum('provider_commission_syp');
        $venueOutstandingSyp = (float) (clone $venueRevenue)->where('commission_status', 'due')->sum('platform_commission_syp');
        $serviceOutstandingSyp = (float) (clone $serviceRevenue)->where('commission_status', 'due')->sum('provider_commission_syp');

        return $this->ok([
            'confirmed_bookings' => (clone $venueRevenue)->count(),
            'confirmed_services' => (clone $serviceRevenue)->count(),
            'gross_sales_syp' => (float) (clone $venueRevenue)->sum('total_syp'),
            'service_gross_sales_syp' => (float) (clone $serviceRevenue)->sum('price_syp'),
            'platform_earnings_syp' => $venueEarningsSyp,
            'provider_platform_earnings_syp' => $serviceEarningsSyp,
            'total_platform_earnings_syp' => $venueEarningsSyp + $serviceEarningsSyp,
            'collected_syp' => $venueCollectedSyp + $serviceCollectedSyp,
            'outstanding_syp' => $venueOutstandingSyp + $serviceOutstandingSyp,
            'owner_net_syp' => (float) (clone $venueRevenue)->sum('owner_net_syp'),
            'provider_net_syp' => (float) (clone $serviceRevenue)->sum('provider_net_syp'),
            'cancelled_bookings' => $cancelled->count(),
        ]);
    }

    public function transactions(Request $request)
    {
        $query = Booking::with([
            'customer:id,name,email', 'owner:id,name,email',
            'venue:id,name_ar,name_en,owner_id',
            'invoice:id,booking_id,invoice_number,status,paid_at',
        ])->latest('id');

        if ($request->filled('commission_status')) $query->where('commission_status', (string) $request->string('commission_status'));
        if ($request->filled('booking_status')) $query->where('booking_status', (string) $request->string('booking_status'));
        if ($request->filled('q')) {
            $search = trim((string) $request->query('q'));
            $query->where(function ($builder) use ($search) {
                $builder->where('booking_number', 'like', "%{$search}%")
                    ->orWhereHas('venue', fn ($q) => $q->where('name_ar', 'like', "%{$search}%")->orWhere('name_en', 'like', "%{$search}%"))
                    ->orWhereHas('owner', fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
            });
        }
        return $this->ok($query->paginate(min(100, max(10, (int) $request->query('per_page', 30)))));
    }

    public function serviceTransactions(Request $request)
    {
        $query = ProviderServiceRequest::with([
            'customer:id,name,email', 'provider:id,name,email',
            'service:id,name_ar,name_en', 'booking:id,booking_number,venue_id,event_date,start_time,end_time',
            'booking.venue:id,name_ar,name_en',
        ])->where('status', 'accepted')->latest('id');

        if ($request->filled('commission_status')) $query->where('commission_status', (string) $request->string('commission_status'));
        if ($request->filled('payment_status')) $query->where('payment_status', (string) $request->string('payment_status'));
        if ($request->filled('q')) {
            $search = trim((string) $request->query('q'));
            $query->where(function ($builder) use ($search) {
                $builder->where('invoice_number', 'like', "%{$search}%")
                    ->orWhere('service_name', 'like', "%{$search}%")
                    ->orWhereHas('provider', fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
            });
        }
        return $this->ok($query->paginate(min(100, max(10, (int) $request->query('per_page', 30)))));
    }

    public function updateCommission(Request $request, Booking $booking)
    {
        $data = $request->validate(['status' => 'required|in:due,collected,waived,reversed', 'notes' => 'nullable|string|max:1000']);
        if (!in_array($booking->booking_status, self::REVENUE_STATUSES, true) && in_array($data['status'], ['due', 'collected'], true)) {
            return $this->fail('لا يمكن تسجيل عمولة مستحقة أو محصلة لحجز غير مؤكد.', 422);
        }
        $booking->update([
            'commission_status' => $data['status'],
            'commission_collected_at' => $data['status'] === 'collected' ? now() : null,
            'commission_notes' => $data['notes'] ?? null,
        ]);
        ActivityLogger::log('updated_booking_commission', 'booking', $booking->id, 'Commission status: '.$data['status']);
        return $this->ok($booking->fresh(['owner:id,name,email', 'venue:id,name_ar,name_en', 'invoice']), 'تم تحديث حالة العمولة.');
    }

    public function updateServiceCommission(Request $request, ProviderServiceRequest $providerRequest)
    {
        $data = $request->validate(['status' => 'required|in:due,collected,waived,reversed', 'notes' => 'nullable|string|max:1000']);
        if ($providerRequest->payment_status !== 'approved' && in_array($data['status'], ['due', 'collected'], true)) {
            return $this->fail('لا يمكن تسجيل عمولة مستحقة أو محصلة لخدمة لم يتم اعتماد دفعها.', 422);
        }
        $providerRequest->update([
            'commission_status' => $data['status'],
            'commission_collected_at' => $data['status'] === 'collected' ? now() : null,
            'commission_notes' => $data['notes'] ?? null,
        ]);
        ActivityLogger::log('updated_provider_commission', 'provider_service_request', $providerRequest->id, 'Commission status: '.$data['status']);
        return $this->ok($providerRequest->fresh(['provider:id,name,email', 'service:id,name_ar,name_en', 'booking.venue']), 'تم تحديث حالة عمولة مقدم الخدمة.');
    }
}
