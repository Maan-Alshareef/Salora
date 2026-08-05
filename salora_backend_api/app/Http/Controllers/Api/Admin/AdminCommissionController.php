<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\PlatformCommission;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminCommissionController extends BaseApiController
{
    public function index(Request $request)
    {
        $query = PlatformCommission::query()->with([
            'businessUser:id,name,email,role',
            'customer:id,name,email',
            'booking:id,booking_number,venue_id,event_date',
            'booking.venue:id,name_ar,name_en',
            'collector:id,name,email',
        ]);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('source_type')) {
            $query->where('source_type', $request->string('source_type')->toString());
        }

        if ($request->filled('search')) {
            $search = trim($request->string('search')->toString());
            $query->where(function ($inner) use ($search): void {
                $inner->where('source_reference', 'like', '%'.$search.'%')
                    ->orWhere('source_title', 'like', '%'.$search.'%')
                    ->orWhereHas('businessUser', fn ($user) => $user->where('name', 'like', '%'.$search.'%'))
                    ->orWhereHas('customer', fn ($user) => $user->where('name', 'like', '%'.$search.'%'));
            });
        }

        return $this->ok([
            'summary' => $this->summary(),
            'items' => $query->latest('approved_at')->latest('id')->limit(500)->get(),
        ]);
    }

    public function collect(Request $request, PlatformCommission $commission)
    {
        $data = $request->validate([
            'collection_method' => 'nullable|string|max:80',
            'collection_reference' => 'nullable|string|max:120',
            'notes' => 'nullable|string|max:2000',
        ]);

        if (in_array($commission->status, [PlatformCommission::STATUS_CANCELLED, PlatformCommission::STATUS_WAIVED], true)) {
            return $this->fail('لا يمكن تحصيل عمولة ملغاة أو معفاة.', 422);
        }

        $saved = DB::transaction(function () use ($request, $commission, $data): PlatformCommission {
            $locked = PlatformCommission::query()->whereKey($commission->id)->lockForUpdate()->firstOrFail();
            $locked->update([
                'status' => PlatformCommission::STATUS_COLLECTED,
                'collected_syp' => $locked->commission_syp,
                'collected_usd' => $locked->commission_usd,
                'collected_at' => now(),
                'collection_method' => $data['collection_method'] ?? $locked->collection_method,
                'collection_reference' => $data['collection_reference'] ?? $locked->collection_reference,
                'notes' => $data['notes'] ?? $locked->notes,
                'collected_by' => $request->user()->id,
            ]);

            ActivityLogger::log(
                'commission_collected',
                'platform_commission',
                $locked->id,
                'تم تحصيل عمولة '.$locked->source_reference.' بنسبة '.$locked->commission_rate.'%.'
            );

            return $locked;
        });

        return $this->ok($saved->fresh($this->relations()), 'تم تسجيل تحصيل العمولة وإضافتها إلى أرباح Salora.');
    }

    public function update(Request $request, PlatformCommission $commission)
    {
        $data = $request->validate([
            'status' => 'required|in:uncollected,partial,collected,overdue,waived,disputed,cancelled',
            'collected_syp' => 'nullable|numeric|min:0',
            'collected_usd' => 'nullable|numeric|min:0',
            'collection_method' => 'nullable|string|max:80',
            'collection_reference' => 'nullable|string|max:120',
            'notes' => 'nullable|string|max:2000',
        ]);

        if ($data['status'] === PlatformCommission::STATUS_COLLECTED) {
            return $this->collect($request, $commission);
        }

        $collectedSyp = min((float) ($data['collected_syp'] ?? 0), (float) $commission->commission_syp);
        $collectedUsd = min((float) ($data['collected_usd'] ?? 0), (float) $commission->commission_usd);

        if ($data['status'] === PlatformCommission::STATUS_PARTIAL && $collectedSyp <= 0 && $collectedUsd <= 0) {
            return $this->fail('أدخل المبلغ المحصل عند اختيار تحصيل جزئي.', 422);
        }

        if (in_array($data['status'], [PlatformCommission::STATUS_UNCOLLECTED, PlatformCommission::STATUS_WAIVED, PlatformCommission::STATUS_CANCELLED], true)) {
            $collectedSyp = 0;
            $collectedUsd = 0;
        }

        $commission->update([
            'status' => $data['status'],
            'collected_syp' => $collectedSyp,
            'collected_usd' => $collectedUsd,
            'collected_at' => ($collectedSyp > 0 || $collectedUsd > 0) ? ($commission->collected_at ?: now()) : null,
            'collection_method' => $data['collection_method'] ?? null,
            'collection_reference' => $data['collection_reference'] ?? null,
            'notes' => $data['notes'] ?? null,
            'collected_by' => ($collectedSyp > 0 || $collectedUsd > 0) ? $request->user()->id : null,
        ]);

        ActivityLogger::log(
            'commission_status_updated',
            'platform_commission',
            $commission->id,
            'تم تحديث حالة العمولة إلى '.$data['status'].'.'
        );

        return $this->ok($commission->fresh($this->relations()), 'تم تحديث حالة العمولة.');
    }

    private function summary(): array
    {
        $active = PlatformCommission::query()->whereNotIn('status', [
            PlatformCommission::STATUS_CANCELLED,
            PlatformCommission::STATUS_WAIVED,
        ]);

        $commissionSyp = (float) (clone $active)->sum('commission_syp');
        $commissionUsd = (float) (clone $active)->sum('commission_usd');
        $collectedSyp = (float) PlatformCommission::query()->sum('collected_syp');
        $collectedUsd = (float) PlatformCommission::query()->sum('collected_usd');

        return [
            'records' => PlatformCommission::count(),
            'active_records' => (clone $active)->count(),
            'gross_syp' => (float) (clone $active)->sum('gross_syp'),
            'gross_usd' => (float) (clone $active)->sum('gross_usd'),
            'commission_syp' => $commissionSyp,
            'commission_usd' => $commissionUsd,
            'collected_syp' => $collectedSyp,
            'collected_usd' => $collectedUsd,
            'uncollected_syp' => max(0, $commissionSyp - $collectedSyp),
            'uncollected_usd' => max(0, $commissionUsd - $collectedUsd),
            'owner_commission_syp' => (float) (clone $active)->where('business_role', 'owner')->sum('commission_syp'),
            'owner_commission_usd' => (float) (clone $active)->where('business_role', 'owner')->sum('commission_usd'),
            'provider_commission_syp' => (float) (clone $active)->where('business_role', 'provider')->sum('commission_syp'),
            'provider_commission_usd' => (float) (clone $active)->where('business_role', 'provider')->sum('commission_usd'),
            'collected_records' => PlatformCommission::where('status', PlatformCommission::STATUS_COLLECTED)->count(),
            'uncollected_records' => PlatformCommission::whereIn('status', [
                PlatformCommission::STATUS_UNCOLLECTED,
                PlatformCommission::STATUS_PARTIAL,
                PlatformCommission::STATUS_OVERDUE,
                PlatformCommission::STATUS_DISPUTED,
            ])->count(),
        ];
    }

    private function relations(): array
    {
        return [
            'businessUser:id,name,email,role',
            'customer:id,name,email',
            'booking:id,booking_number,venue_id,event_date',
            'booking.venue:id,name_ar,name_en',
            'collector:id,name,email',
        ];
    }
}