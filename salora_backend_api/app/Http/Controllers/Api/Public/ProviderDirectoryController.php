<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ProviderDirectoryController extends BaseApiController
{
    public function index(Request $request)
    {
        $categoryIds = $this->categoryIds($request->integer('category_id') ?: null);
        $query = User::query()
            ->select(['id', 'name', 'phone', 'avatar', 'role', 'status', 'created_at'])
            ->where('role', 'provider')
            ->where('status', 'active')
            ->where('business_status','approved')
            ->whereHas('services', function (Builder $serviceQuery) use ($categoryIds) {
                $serviceQuery->where('type', 'external_vendor')
                    ->where('is_active', true)
                    ->where('approval_status', 'approved');
                if ($categoryIds !== null) {
                    $serviceQuery->whereIn('category_id', $categoryIds);
                }
            })
            ->with([
                'providerProfile',
                'services' => function ($serviceQuery) use ($categoryIds) {
                    $serviceQuery->where('type', 'external_vendor')
                        ->where('is_active', true)
                        ->where('approval_status', 'approved')
                        ->with(['categoryModel.parent', 'images', 'reviews.customer:id,name,avatar'])
                        ->withAvg(['reviews as rating_avg' => fn ($q) => $q->where('status', 'visible')], 'rating')
                        ->withCount(['reviews as reviews_count' => fn ($q) => $q->where('status', 'visible')])
                        ->orderBy('name_ar');
                    if ($categoryIds !== null) {
                        $serviceQuery->whereIn('category_id', $categoryIds);
                    }
                },
            ]);

        if ($request->filled('city')) {
            $city = trim((string) $request->query('city'));
            $query->whereHas('providerProfile', fn (Builder $q) => $q->where('city', 'like', "%{$city}%"));
        }

        if ($request->filled('q')) {
            $term = trim((string) $request->query('q'));
            $query->where(function (Builder $q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhereHas('providerProfile', fn (Builder $profile) => $profile
                        ->where('bio', 'like', "%{$term}%")
                        ->orWhere('city', 'like', "%{$term}%"))
                    ->orWhereHas('services', fn (Builder $service) => $service
                        ->where('name_ar', 'like', "%{$term}%")
                        ->orWhere('name_en', 'like', "%{$term}%")
                        ->orWhere('description_ar', 'like', "%{$term}%")
                        ->orWhere('description_en', 'like', "%{$term}%"));
            });
        }

        $sort = (string) $request->query('sort', 'latest');
        match ($sort) {
            'name' => $query->orderBy('name'),
            'oldest' => $query->oldest(),
            default => $query->latest(),
        };

        $paginator = $query->paginate(min(max($request->integer('per_page', 20), 1), 100));
        $paginator->setCollection($paginator->getCollection()->map(fn (User $provider) => $this->serializeProvider($provider, false)));

        // Sorting by aggregate values is done after loading because the rating belongs
        // to service reviews, not directly to the provider account.
        if (in_array($sort, ['rating', 'services_count', 'price_asc'], true)) {
            $collection = $paginator->getCollection();
            $collection = match ($sort) {
                'rating' => $collection->sortByDesc('rating_avg'),
                'services_count' => $collection->sortByDesc('services_count'),
                'price_asc' => $collection->sortBy('lowest_price_syp'),
            };
            $paginator->setCollection($collection->values());
        }

        return $this->ok($paginator);
    }

    public function show(User $provider)
    {
        abort_unless($provider->role==='provider' && $provider->status==='active' && $provider->business_status==='approved' && !$provider->trashed(), 404);

        $provider->load([
            'providerProfile',
            'services' => fn ($q) => $q->where('type', 'external_vendor')
                ->where('is_active', true)
                ->where('approval_status', 'approved')
                ->with(['categoryModel.parent', 'images', 'reviews' => fn ($review) => $review
                    ->where('status', 'visible')
                    ->latest()
                    ->with('customer:id,name,avatar')])
                ->withAvg(['reviews as rating_avg' => fn ($review) => $review->where('status', 'visible')], 'rating')
                ->withCount(['reviews as reviews_count' => fn ($review) => $review->where('status', 'visible')])
                ->orderBy('name_ar'),
        ]);

        abort_if($provider->services->isEmpty(), 404);
        return $this->ok($this->serializeProvider($provider, true));
    }

    private function serializeProvider(User $provider, bool $includeReviews): array
    {
        $services = $provider->services->map(function ($service) use ($includeReviews) {
            $array = $service->toArray();
            $array['rating_avg'] = round((float) ($service->rating_avg ?? 0), 2);
            $array['reviews_count'] = (int) ($service->reviews_count ?? $service->reviews?->count() ?? 0);
            if (!$includeReviews) unset($array['reviews']);
            return $array;
        })->values();

        $ratings = $services->filter(fn ($service) => (int) ($service['reviews_count'] ?? 0) > 0);
        $weightedTotal = $ratings->sum(fn ($service) => (float) $service['rating_avg'] * (int) $service['reviews_count']);
        $reviewsCount = (int) $ratings->sum('reviews_count');
        $profile = $provider->providerProfile;
        // Legacy provider accounts may not have a provider_profiles row yet.
        // In that case preserve the agreed default: expose the account phone for
        // external contact until the provider explicitly changes the switches.
        $allowPhone = $profile ? (bool) $profile->allow_phone : true;
        $allowWhatsapp = $profile ? (bool) $profile->allow_whatsapp : true;
        $contactPhone = $profile?->contact_phone ?: $provider->phone;
        $whatsappPhone = $profile?->whatsapp_phone ?: $contactPhone;

        return [
            'id' => $provider->id,
            'name' => $provider->name,
            'avatar_url' => $provider->avatar_url,
            'city' => $profile?->city,
            'bio' => $profile?->bio,
            'contact_phone' => $allowPhone ? $contactPhone : null,
            'whatsapp_phone' => $allowWhatsapp ? $whatsappPhone : null,
            'allow_phone' => $allowPhone,
            'allow_whatsapp' => $allowWhatsapp,
            'rating_avg' => $reviewsCount > 0 ? round($weightedTotal / $reviewsCount, 2) : 0,
            'reviews_count' => $reviewsCount,
            'services_count' => $services->count(),
            'lowest_price_syp' => (float) ($services->min('price_syp') ?? 0),
            'categories' => $services->map(fn ($service) => $service['category_model'] ?? null)->filter()->unique('id')->values(),
            'services' => $services,
        ];
    }

    private function categoryIds(?int $categoryId): ?array
    {
        if (!$categoryId) return null;
        $category = ServiceCategory::whereKey($categoryId)->where('is_active', true)->firstOrFail();
        $ids = [$category->id];
        $frontier = [$category->id];

        while ($frontier !== []) {
            $children = ServiceCategory::whereIn('parent_id', $frontier)
                ->where('is_active', true)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
            $new = array_values(array_diff($children, $ids));
            if ($new === []) break;
            $ids = [...$ids, ...$new];
            $frontier = $new;
        }

        return array_values(array_unique($ids));
    }
}
