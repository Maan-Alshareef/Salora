<?php

namespace App\Models\Concerns;

use App\Models\Booking;
use App\Models\ProviderServiceRequest;
use App\Services\PlatformCommissionService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

trait TracksPlatformCommission
{
    protected static function bootTracksPlatformCommission(): void
    {
        static::saved(function (Model $model): void {
            if (! Schema::hasTable('platform_commissions')) {
                return;
            }

            $service = app(PlatformCommissionService::class);

            if ($model instanceof Booking) {
                $service->syncBooking($model);
            }

            if ($model instanceof ProviderServiceRequest) {
                $service->syncProviderRequest($model);
            }
        });
    }
}