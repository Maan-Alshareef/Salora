<?php
namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Service;
use App\Models\Venue;
use App\Services\ExchangeRateService;
use Illuminate\Http\Request;

class OwnerServiceController extends BaseApiController
{
    public function index(Request $r)
    {
        return $this->ok(Service::with(['provider:id,name,email','venues:id,owner_id,name_en,name_ar'])
            ->whereIn('type', ['included','hall_upgrade'])
            ->whereHas('venues', fn($q) => $q->where('owner_id', $r->user()->id))
            ->latest()
            ->get());
    }

    public function available(Request $r)
    {
        return $this->ok(Service::with('provider:id,name,email')
            ->where('is_active', true)
            ->where('approval_status', 'approved')
            ->whereIn('type', ['included','hall_upgrade'])
            ->orderBy('type')
            ->orderBy('name_en')
            ->get());
    }

    public function attach(Request $r, Venue $venue)
    {
        if($venue->owner_id!==$r->user()->id)return $this->fail('Forbidden.',403);
        $data=$r->validate([
            'service_id'=>'required|exists:services,id',
            'custom_price_usd'=>'nullable|numeric|min:0',
            'custom_price_syp'=>'nullable|numeric|min:0',
            'is_available'=>'nullable|boolean'
        ]);
        $service = Service::where('id', $data['service_id'])->whereIn('type', ['included','hall_upgrade'])->first();
        if (!$service) return $this->fail('يمكن ربط خدمات الصالة فقط. الخدمات الخارجية يديرها مقدم الخدمة من التطبيق.', 422);

        $exchangeRates = app(ExchangeRateService::class);
        $rate = $exchangeRates->rate();
        $customSyp = array_key_exists('custom_price_syp', $data) && $data['custom_price_syp'] !== null
            ? (float) $data['custom_price_syp']
            : null;
        $customUsd = array_key_exists('custom_price_usd', $data) && $data['custom_price_usd'] !== null
            ? (float) $data['custom_price_usd']
            : null;
        if ($customSyp !== null) {
            $customUsd = $exchangeRates->toUsd($customSyp, $rate);
        } elseif ($customUsd !== null) {
            $customSyp = $exchangeRates->toSyp($customUsd, $rate);
        }

        $venue->services()->syncWithoutDetaching([
            $data['service_id']=>[
                'custom_price_usd'=>$customUsd,
                'custom_price_syp'=>$customSyp,
                'is_available'=>$data['is_available']??true
            ]
        ]);
        return $this->ok($venue->load('services'),'Service attached to venue.');
    }
}
