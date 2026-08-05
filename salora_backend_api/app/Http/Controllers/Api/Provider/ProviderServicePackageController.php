<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Service;
use App\Models\ServicePackage;
use App\Services\NotificationService;
use App\Models\User;
use Illuminate\Http\Request;

class ProviderServicePackageController extends BaseApiController
{
    public function index(Request $request, Service $service)
    {
        $this->authorizeService($request, $service);
        return $this->ok($service->packages()->orderBy('sort_order')->orderBy('id')->get());
    }

    public function store(Request $request, Service $service)
    {
        $this->authorizeService($request, $service);
        $data = $this->validated($request);
        $package = $service->packages()->create($data);
        $this->markPending($service);
        return $this->ok($package, 'تمت إضافة الباقة وإرسال الخدمة للمراجعة.', 201);
    }

    public function update(Request $request, Service $service, ServicePackage $servicePackage)
    {
        $this->authorizeService($request, $service);
        abort_unless((int) $servicePackage->service_id === (int) $service->id, 404);
        $servicePackage->update($this->validated($request, true));
        $this->markPending($service);
        return $this->ok($servicePackage->fresh(), 'تم تحديث الباقة وإرسال الخدمة للمراجعة.');
    }

    public function destroy(Request $request, Service $service, ServicePackage $servicePackage)
    {
        $this->authorizeService($request, $service);
        abort_unless((int) $servicePackage->service_id === (int) $service->id, 404);
        $servicePackage->update(['is_active' => false]);
        $this->markPending($service);
        return $this->ok(null, 'تم تعطيل الباقة بأمان.');
    }

    private function validated(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';
        return $request->validate([
            'name' => "$required|string|max:180",
            'description' => 'nullable|string|max:2000',
            'price_syp' => "$required|numeric|min:0",
            'price_usd' => 'nullable|numeric|min:0',
            'duration_minutes' => 'nullable|integer|min:15|max:10080',
            'included_items' => 'nullable|array|max:50',
            'included_items.*' => 'string|max:240',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer|min:0|max:1000',
        ]);
    }

    private function authorizeService(Request $request, Service $service): void
    {
        abort_unless((int) $service->provider_id === (int) $request->user()->id, 403);
        abort_unless($service->type === 'external_vendor', 404);
    }

    private function markPending(Service $service): void
    {
        $service->update([
            'is_active' => false,
            'approval_status' => 'pending',
            'rejection_reason' => null,
        ]);
        foreach (User::where('role', 'admin')->where('status', 'active')->pluck('id') as $adminId) {
            NotificationService::send(
                $adminId,
                'تعديل باقات خدمة بانتظار المراجعة',
                $service->name_ar ?: $service->name_en,
                'service_package_pending',
                ['service_id' => $service->id]
            );
        }
    }
}
