<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\ProviderServiceRequest;
use App\Models\Review;
use App\Models\Service;
use Illuminate\Http\Request;

class ProviderReportController extends BaseApiController
{
    public function summary(Request $request)
    {
        $providerId = $request->user()->id;
        $accepted = ProviderServiceRequest::where('provider_id', $providerId)->where('status', 'accepted');
        return $this->ok([
            'services' => Service::where('provider_id', $providerId)->count(),
            'approved_services' => Service::where('provider_id', $providerId)->where('approval_status', 'approved')->where('is_active', true)->count(),
            'requests' => ProviderServiceRequest::where('provider_id', $providerId)->count(),
            'accepted_requests' => (clone $accepted)->count(),
            'estimated_revenue_syp' => (float)(clone $accepted)->sum('price_syp'),
            'estimated_revenue_usd' => (float)(clone $accepted)->sum('price_usd'),
            'reviews' => Review::whereHas('service', fn($q) => $q->where('provider_id', $providerId))->where('status', 'visible')->count(),
            'average_rating' => round((float)(Review::whereHas('service', fn($q) => $q->where('provider_id', $providerId))->where('status', 'visible')->avg('rating') ?: 0), 2),
        ]);
    }
}
