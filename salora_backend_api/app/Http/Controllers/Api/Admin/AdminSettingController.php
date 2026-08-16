<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Setting;
use App\Services\ActivityLogger;
use App\Services\ExchangeRateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminSettingController extends BaseApiController
{
    public function index(ExchangeRateService $exchangeRates)
    {
        Setting::query()->firstOrCreate(
            ['key' => ExchangeRateService::SETTING_KEY],
            ['value' => (string) $exchangeRates->rate(), 'type' => 'number'],
        );

        return $this->ok(Setting::query()->orderBy('key')->get());
    }

    public function update(Request $request, ExchangeRateService $exchangeRates)
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:190'],
            'value' => ['required'],
            'type' => ['nullable', 'string', Rule::in(['string', 'number', 'boolean', 'json'])],
        ]);

        if ($data['key'] === ExchangeRateService::SETTING_KEY) {
            $validated = $request->validate([
                'value' => ['required', 'numeric', 'min:1', 'max:1000000000'],
            ]);

            $oldRate = $exchangeRates->rate();
            $newRate = (float) $validated['value'];
            [$setting, $synchronised] = DB::transaction(function () use ($exchangeRates, $oldRate, $newRate): array {
                $setting = $exchangeRates->setRate($newRate);
                $synchronised = abs($oldRate - $newRate) > 0.0001
                    ? $exchangeRates->syncOpenFinancialRecords($newRate)
                    : [
                        'invoices' => 0,
                        'bookings' => 0,
                        'booking_services' => 0,
                        'provider_requests' => 0,
                        'transactions' => 0,
                        'services' => 0,
                        'venues' => 0,
                        'venue_services' => 0,
                        'platform_commissions' => 0,
                    ];

                return [$setting, $synchronised];
            });

            ActivityLogger::log(
                'updated_exchange_rate',
                'setting',
                $setting->id,
                'USD/SYP exchange rate changed from '.$oldRate.' to '.$newRate.'. Open financial records were resynchronised; issued financial snapshots were preserved.',
            );

            return $this->ok([
                'setting' => $setting->fresh(),
                'exchange_rate_usd_to_syp' => $newRate,
                'synchronised' => $synchronised,
                'historical_invoices_preserved' => true,
            ], 'Exchange rate saved and open financial records synchronized.');
        }

        $setting = Setting::query()->updateOrCreate(
            ['key' => $data['key']],
            [
                'value' => is_scalar($data['value']) ? (string) $data['value'] : json_encode($data['value'], JSON_UNESCAPED_UNICODE),
                'type' => $data['type'] ?? 'string',
            ],
        );

        return $this->ok($setting, 'Setting saved.');
    }
}
