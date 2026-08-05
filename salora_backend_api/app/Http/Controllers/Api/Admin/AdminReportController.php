<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Booking;
use App\Models\Complaint;
use App\Models\Invoice;
use App\Models\PaymentProof;
use App\Models\Service;
use App\Models\User;
use App\Models\Venue;
use App\Support\SaloraStatus;

class AdminReportController extends BaseApiController
{
    public function summary()
    {
        $paidInvoices = Invoice::query()->where('status', 'paid');
        $ownerInvoices = (clone $paidInvoices)->where('source_type', 'venue_booking');
        $providerInvoices = (clone $paidInvoices)->where('source_type', 'provider_service');

        $recentBookings = Booking::query()
            ->where('created_at', '>=', now()->subMonths(5)->startOfMonth())
            ->get();

        $recentInvoices = Invoice::query()
            ->where('status', 'paid')
            ->where(function ($query) {
                $query->where('paid_at', '>=', now()->subMonths(5)->startOfMonth())
                    ->orWhere(function ($fallback) {
                        $fallback->whereNull('paid_at')
                            ->where('updated_at', '>=', now()->subMonths(5)->startOfMonth());
                    });
            })
            ->get();

        $months = collect(range(5, 0))->map(function ($offset) use ($recentBookings, $recentInvoices) {
            $month = now()->subMonths($offset);
            $monthKey = $month->format('Y-m');

            $bookings = $recentBookings->filter(
                fn ($booking) => optional($booking->created_at)->format('Y-m') === $monthKey
            );

            $invoices = $recentInvoices->filter(function ($invoice) use ($monthKey) {
                $date = $invoice->paid_at ?: $invoice->updated_at;
                return optional($date)->format('Y-m') === $monthKey;
            });

            $owner = $invoices->where('source_type', 'venue_booking');
            $provider = $invoices->where('source_type', 'provider_service');

            return [
                'month' => $monthKey,
                'bookings' => $bookings->count(),
                'paid_invoices' => $invoices->count(),
                'gross_syp' => (float) $invoices->sum('total_syp'),
                'gross_usd' => (float) $invoices->sum('total_usd'),
                'commission_syp' => (float) $invoices->sum('commission_syp'),
                'commission_usd' => (float) $invoices->sum('commission_usd'),
                'owner_commission_syp' => (float) $owner->sum('commission_syp'),
                'owner_commission_usd' => (float) $owner->sum('commission_usd'),
                'provider_commission_syp' => (float) $provider->sum('commission_syp'),
                'provider_commission_usd' => (float) $provider->sum('commission_usd'),
                'net_syp' => (float) $invoices->sum('net_syp'),
                'net_usd' => (float) $invoices->sum('net_usd'),
                // توافق مع الواجهات القديمة: revenue تعني ربح المنصة، وليس إجمالي المدفوعات.
                'revenue_syp' => (float) $invoices->sum('commission_syp'),
                'revenue_usd' => (float) $invoices->sum('commission_usd'),
            ];
        });

        $statusCounts = Booking::selectRaw('booking_status, COUNT(*) as total')
            ->groupBy('booking_status')
            ->pluck('total', 'booking_status');

        $commissionSyp = (float) (clone $paidInvoices)->sum('commission_syp');
        $commissionUsd = (float) (clone $paidInvoices)->sum('commission_usd');

        return $this->ok([
            'users' => User::count(),
            'users_by_role' => User::selectRaw('role, COUNT(*) as total')->groupBy('role')->pluck('total', 'role'),
            'venues' => Venue::count(),
            'approved_venues' => Venue::where('status', 'approved')->count(),
            'bookings' => Booking::count(),
            'booking_statuses' => $statusCounts,
            'confirmed_bookings' => Booking::where('booking_status', SaloraStatus::BOOKING_CONFIRMED)->count(),
            'pending_payments' => PaymentProof::where('status', 'pending')->count(),
            'services' => Service::where('approval_status', 'approved')->count(),
            'open_complaints' => Complaint::whereIn('status', ['open', 'in_progress'])->count(),

            'commission_percent' => 10,
            'paid_invoice_count' => (clone $paidInvoices)->count(),
            'gross_revenue_syp' => (float) (clone $paidInvoices)->sum('total_syp'),
            'gross_revenue_usd' => (float) (clone $paidInvoices)->sum('total_usd'),
            'commission_syp' => $commissionSyp,
            'commission_usd' => $commissionUsd,
            'net_payees_syp' => (float) (clone $paidInvoices)->sum('net_syp'),
            'net_payees_usd' => (float) (clone $paidInvoices)->sum('net_usd'),

            'owner_gross_syp' => (float) (clone $ownerInvoices)->sum('total_syp'),
            'owner_gross_usd' => (float) (clone $ownerInvoices)->sum('total_usd'),
            'owner_commission_syp' => (float) (clone $ownerInvoices)->sum('commission_syp'),
            'owner_commission_usd' => (float) (clone $ownerInvoices)->sum('commission_usd'),
            'owner_net_syp' => (float) (clone $ownerInvoices)->sum('net_syp'),
            'owner_net_usd' => (float) (clone $ownerInvoices)->sum('net_usd'),

            'provider_gross_syp' => (float) (clone $providerInvoices)->sum('total_syp'),
            'provider_gross_usd' => (float) (clone $providerInvoices)->sum('total_usd'),
            'provider_commission_syp' => (float) (clone $providerInvoices)->sum('commission_syp'),
            'provider_commission_usd' => (float) (clone $providerInvoices)->sum('commission_usd'),
            'provider_net_syp' => (float) (clone $providerInvoices)->sum('net_syp'),
            'provider_net_usd' => (float) (clone $providerInvoices)->sum('net_usd'),

            // توافق مع الكود القديم في Dashboard.
            'revenue_syp' => $commissionSyp,
            'revenue_usd' => $commissionUsd,
            'monthly' => $months,
            'top_venues' => Venue::withCount([
                'bookings as completed_bookings_count' => fn ($query) => $query->whereIn('booking_status', [
                    SaloraStatus::BOOKING_CONFIRMED,
                    SaloraStatus::BOOKING_COMPLETED,
                ]),
            ])
                ->orderByDesc('completed_bookings_count')
                ->limit(5)
                ->get(['id', 'name_ar', 'name_en', 'rating_avg']),
        ]);
    }
}