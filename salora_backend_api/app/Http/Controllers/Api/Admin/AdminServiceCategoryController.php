<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\ServiceCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminServiceCategoryController extends BaseApiController
{
    public function index()
    {
        return $this->ok(ServiceCategory::with(['parent:id,name_ar,name_en', 'children:id,parent_id,name_ar,name_en,is_active'])
            ->withCount(['services', 'children'])
            ->orderBy('parent_id')
            ->orderBy('sort_order')
            ->orderBy('name_en')
            ->get());
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data = $this->handleImage($request, $data);
        $category = ServiceCategory::create($data);
        return $this->ok($category->load('parent:id,name_ar,name_en'), 'تم إنشاء تصنيف الخدمة.', 201);
    }

    public function update(Request $request, ServiceCategory $serviceCategory)
    {
        $data = $this->validated($request, true, $serviceCategory);
        $data = $this->handleImage($request, $data, $serviceCategory);
        $serviceCategory->update($data);
        return $this->ok($serviceCategory->fresh(['parent:id,name_ar,name_en', 'children']), 'تم تحديث تصنيف الخدمة.');
    }

    public function destroy(ServiceCategory $serviceCategory)
    {
        $servicesCount = $serviceCategory->services()->count();
        $childrenCount = $serviceCategory->children()->count();
        if ($servicesCount > 0 || $childrenCount > 0) {
            $serviceCategory->update(['is_active' => false]);
            return $this->ok([
                'category' => $serviceCategory,
                'services_count' => $servicesCount,
                'children_count' => $childrenCount,
            ], 'تم تعطيل التصنيف لأنه مرتبط بخدمات أو تصنيفات فرعية. انقل الارتباطات قبل الحذف النهائي.');
        }

        $this->deleteLocalImage($serviceCategory->image_url);
        $serviceCategory->delete();
        return $this->ok(null, 'تم حذف تصنيف الخدمة.');
    }

    private function validated(Request $request, bool $partial = false, ?ServiceCategory $current = null): array
    {
        $rule = $partial ? 'sometimes' : 'required';
        $data = $request->validate([
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('service_categories', 'id'),
                function (string $attribute, mixed $value, \Closure $fail) use ($current) {
                    if ($current && (int) $value === (int) $current->id) {
                        $fail('لا يمكن أن يكون التصنيف أباً لنفسه.');
                    }
                },
            ],
            'name_ar' => [$rule, 'string', 'max:120'],
            'name_en' => [$rule, 'string', 'max:120', Rule::unique('service_categories', 'name_en')->ignore($current?->id)],
            'description' => 'nullable|string|max:1000',
            'image' => 'nullable|file|mimes:jpeg,jpg,png,webp,svg|max:4096',
            'image_url' => 'nullable|string|max:1000',
            'remove_image' => 'nullable|boolean',
            'applies_to' => [$partial ? 'sometimes' : 'required', Rule::in(['hall', 'provider', 'both'])],
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer|min:0|max:100000',
        ]);

        if (array_key_exists('parent_id', $data) && $data['parent_id']) {
            $parent = ServiceCategory::findOrFail($data['parent_id']);
            $scope = $data['applies_to'] ?? $current?->applies_to ?? 'both';
            if ($parent->applies_to !== 'both' && $scope !== $parent->applies_to) {
                throw ValidationException::withMessages([
                    'parent_id' => ['يجب أن يتوافق نطاق التصنيف الفرعي مع نطاق التصنيف الأب.'],
                ]);
            }
            if ($current && $this->isDescendant($parent, $current->id)) {
                throw ValidationException::withMessages([
                    'parent_id' => ['لا يمكن نقل التصنيف تحت أحد أبنائه.'],
                ]);
            }
        }

        unset($data['image'], $data['remove_image']);
        return $data;
    }

    private function handleImage(Request $request, array $data, ?ServiceCategory $current = null): array
    {
        if ($request->boolean('remove_image')) {
            if ($current) $this->deleteLocalImage($current->image_url);
            $data['image_url'] = null;
        }

        if ($request->hasFile('image')) {
            if ($current) $this->deleteLocalImage($current->image_url);
            $path = $request->file('image')->store('service-categories', 'public');
            $data['image_url'] = '/storage/'.$path;
        }

        return $data;
    }

    private function isDescendant(ServiceCategory $candidateParent, int $categoryId): bool
    {
        $cursor = $candidateParent;
        $seen = [];
        while ($cursor) {
            if ((int) $cursor->id === $categoryId) return true;
            if (in_array($cursor->id, $seen, true)) break;
            $seen[] = $cursor->id;
            $cursor = $cursor->parent;
        }
        return false;
    }

    private function deleteLocalImage(?string $url): void
    {
        $url = trim((string) $url);
        if (str_starts_with($url, '/storage/')) {
            Storage::disk('public')->delete(substr($url, strlen('/storage/')));
        }
    }
}
