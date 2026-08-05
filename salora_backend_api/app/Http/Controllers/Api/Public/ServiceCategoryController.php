<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\ServiceCategory;
use Illuminate\Http\Request;

class ServiceCategoryController extends BaseApiController
{
    public function index(Request $request)
    {
        $scope = $request->query('for');
        $base = ServiceCategory::query()->where('is_active', true);
        if (in_array($scope, ['provider', 'hall'], true)) {
            $base->whereIn('applies_to', [$scope, 'both']);
        }

        if ($request->boolean('tree')) {
            $roots = (clone $base)
                ->whereNull('parent_id')
                ->with(['children' => function ($query) use ($scope) {
                    $query->where('is_active', true);
                    if (in_array($scope, ['provider', 'hall'], true)) {
                        $query->whereIn('applies_to', [$scope, 'both']);
                    }
                    $query->with(['children' => function ($nested) use ($scope) {
                        $nested->where('is_active', true);
                        if (in_array($scope, ['provider', 'hall'], true)) {
                            $nested->whereIn('applies_to', [$scope, 'both']);
                        }
                    }]);
                }])
                ->orderBy('sort_order')
                ->orderBy('name_en')
                ->get();
            return $this->ok($roots);
        }

        return $this->ok($base
            ->with('parent:id,name_ar,name_en')
            ->withCount(['services'=>fn($q)=>$q->where('is_active',true)->where('approval_status','approved'),'services as providers_count'=>fn($q)=>$q->where('type','external_vendor')->where('is_active',true)->where('approval_status','approved')->whereHas('provider',fn($u)=>$u->where('status','active')->where('business_status','approved'))->selectRaw('count(distinct provider_id)')])
            ->orderByRaw('CASE WHEN parent_id IS NULL THEN 0 ELSE 1 END')
            ->orderBy('parent_id')
            ->orderBy('sort_order')
            ->orderBy('name_en')
            ->get());
    }
}
