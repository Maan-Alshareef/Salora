<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminServiceController extends BaseApiController
{
    public function index()
    {
        return $this->ok(Service::with([
            'provider:id,name,email,phone,avatar', 'provider.providerProfile', 'categoryModel.parent', 'images', 'venues:id,owner_id,name_en,name_ar',
        ])->latest()->get());
    }

    public function store(Request $request)
    {
        $data = $this->normalizeCategory($this->validated($request, false), null);
        $service = Service::create([
            ...$data,
            'is_active' => true,
            'approval_status' => 'approved',
        ]);
        return $this->ok($service->load(['provider:id,name,email,phone,avatar', 'provider.providerProfile', 'categoryModel.parent', 'images']), 'Service created.', 201);
    }

    public function update(Request $request, Service $service)
    {
        $data = $this->validated($request, true);
        $service->update($this->normalizeCategory($data, $data['type'] ?? $service->type));
        return $this->ok($service->fresh(['provider:id,name,email,phone,avatar', 'provider.providerProfile', 'categoryModel.parent', 'images', 'venues:id,owner_id,name_en,name_ar']), 'Service updated.');
    }

    public function approve(Service $service)
    {
        if ($service->approval_status !== 'pending') return $this->fail('Only pending services can be approved.', 422);
        if ($service->type === 'external_vendor' && !$service->images()->exists()) {
            return $this->fail('لا يمكن اعتماد خدمة مقدم الخدمة قبل إضافة صورة واحدة على الأقل.', 422, ['code' => 'service_image_required']);
        }
        $service->update(['is_active' => true, 'approval_status' => 'approved', 'rejection_reason' => null]);
        $service->provider?->forceFill(['business_status' => 'approved', 'business_rejection_reason' => null])->save();
        if ($service->provider_id) {
            NotificationService::send($service->provider_id, 'تم اعتماد الخدمة', $service->name_ar ?: $service->name_en, 'service_approved', ['service_id' => $service->id]);
        }
        return $this->ok($service->fresh(['provider:id,name,email,phone,avatar', 'provider.providerProfile', 'categoryModel.parent', 'images']), 'Service approved.');
    }

    public function reject(Request $request, Service $service)
    {
        $data = $request->validate(['reason' => 'required|string|max:500']);
        if ($service->approval_status !== 'pending') return $this->fail('Only pending services can be rejected.', 422);
        $service->update(['is_active' => false, 'approval_status' => 'rejected', 'rejection_reason' => $data['reason']]);
        if ($service->provider_id) {
            NotificationService::send($service->provider_id, 'تم رفض الخدمة', $data['reason'], 'service_rejected', ['service_id' => $service->id]);
        }
        return $this->ok($service->fresh(['provider:id,name,email,phone,avatar', 'provider.providerProfile', 'categoryModel.parent', 'images']), 'Service rejected.');
    }

    public function destroy(Service $service)
    {
        $service->update(['is_active' => false]);
        return $this->ok($service, 'Service disabled.');
    }

    private function normalizeCategory(array $data, ?string $currentType): array
    {
        $type = $data['type'] ?? $currentType;
        if (! array_key_exists('category_id', $data) || $data['category_id'] === null) return $data;

        $category = ServiceCategory::whereKey($data['category_id'])->where('is_active', true)->first();
        if (! $category) {
            throw ValidationException::withMessages(['category_id' => ['Select an active service category.']]);
        }

        $allowedScopes = $type === 'external_vendor' ? ['provider', 'both'] : ['hall', 'both'];
        if (! in_array($category->applies_to, $allowedScopes, true)) {
            throw ValidationException::withMessages([
                'category_id' => ['The selected category is not valid for this service type.'],
            ]);
        }

        $data['category'] = $category->name_en;
        return $data;
    }

    private function validated(Request $request, bool $partial): array
    {
        $required = $partial ? 'sometimes' : 'required';
        return $request->validate([
            'name_ar' => 'nullable|string|max:160',
            'name_en' => "$required|string|max:160",
            'description_ar' => 'nullable|string|max:2000',
            'description_en' => 'nullable|string|max:2000',
            'emoji' => 'nullable|string|max:16',
            'image_url' => 'nullable|string|max:1000',
            'type' => "$required|in:included,hall_upgrade,external_vendor",
            'category' => 'nullable|string|max:120',
            'category_id' => 'nullable|exists:service_categories,id',
            'price_usd' => 'nullable|numeric|min:0',
            'price_syp' => 'nullable|numeric|min:0',
            'pricing_unit' => [$partial ? 'sometimes' : 'nullable', Rule::in(['per_event', 'per_hour', 'per_person', 'package'])],
            'duration_minutes' => 'nullable|integer|min:15|max:10080',
            'provider_id' => 'nullable|exists:users,id',
            'available_for' => 'nullable|array',
            'is_active' => 'sometimes|boolean',
        ]);
    }
}
