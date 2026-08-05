<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Http\Request;

class ServiceController extends BaseApiController
{
    public function index(Request $request)
    {
        $query = Service::with([
            'provider:id,name,phone,avatar,status',
            'provider.providerProfile',
            'categoryModel.parent',
            'images',
        ])->where('is_active', true)
            ->where('approval_status', 'approved')
            ->where(function ($scope) {
                $scope->where('type', '!=', 'external_vendor')
                    ->orWhereHas('provider', fn ($provider) => $provider->where('status', 'active'));
            });

        if ($request->filled('category_id')) {
            $query->whereIn('category_id', $this->categoryIds((int) $request->query('category_id')));
        }
        if ($request->filled('type')) {
            $query->where('type', $request->query('type'));
        } else {
            $query->where('type', 'external_vendor');
        }
        if ($request->filled('q')) {
            $term = trim((string) $request->query('q'));
            $query->where(fn ($q) => $q->where('name_ar', 'like', "%{$term}%")
                ->orWhere('name_en', 'like', "%{$term}%")
                ->orWhere('description_ar', 'like', "%{$term}%")
                ->orWhere('description_en', 'like', "%{$term}%")
                ->orWhereHas('provider', fn ($provider) => $provider->where('name', 'like', "%{$term}%")));
        }

        return $this->ok($query->orderBy('name_ar')->get());
    }

    public function show(Service $service)
    {
        $providerAvailable = $service->type !== 'external_vendor'
            || ($service->provider && $service->provider->isAvailableForNewBusiness());
        abort_unless($service->is_active && $service->approval_status === 'approved' && $providerAvailable, 404);
        return $this->ok($service->load([
            'provider:id,name,phone,avatar,status',
            'provider.providerProfile',
            'categoryModel.parent',
            'images',
            'reviews' => fn ($q) => $q->where('status', 'visible')->latest()->with('customer:id,name,avatar'),
            'venues.images',
        ]));
    }

    private function categoryIds(int $categoryId): array
    {
        $category = ServiceCategory::whereKey($categoryId)->where('is_active', true)->firstOrFail();
        $ids = [$category->id];
        $frontier = [$category->id];
        while ($frontier !== []) {
            $children = ServiceCategory::whereIn('parent_id', $frontier)
                ->where('is_active', true)
                ->pluck('id')->map(fn ($id) => (int) $id)->all();
            $new = array_values(array_diff($children, $ids));
            if ($new === []) break;
            $ids = [...$ids, ...$new];
            $frontier = $new;
        }
        return array_values(array_unique($ids));
    }
}
