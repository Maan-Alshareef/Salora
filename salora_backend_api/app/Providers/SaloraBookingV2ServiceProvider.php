<?php

namespace App\Providers;

use App\Services\SaloraBookingV2Service;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class SaloraBookingV2ServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SaloraBookingV2Service::class);
    }

        public function boot(SaloraBookingV2Service $service): void
    {
        Event::listen('eloquent.created: *', function (string $eventName, array $data) use ($service) {
            $model = $data[0] ?? null;
            if (! $model instanceof Model || ! $model->getKey()) {
                return;
            }

            try {
                if ($model->getTable() === $service->venueTable()) {
                    $service->ensureDefaultWorkingHours((int) $model->getKey());
                }
            } catch (\Throwable $error) {
                report($error);
            }
        });

        Event::listen('eloquent.saving: *', function (string $eventName, array $data) use ($service) {
            $model = $data[0] ?? null;
            if (! $model instanceof Model) {
                return;
            }
            try {
                if ($model->getTable() === $service->bookingTable()) {
                    $service->applyQuoteToModel($model);
                }
            } catch (\Illuminate\Validation\ValidationException $error) {
                throw $error;
            } catch (\Throwable $error) {
                report($error);
            }
        });

        Event::listen('eloquent.saved: *', function (string $eventName, array $data) use ($service) {
            $model = $data[0] ?? null;
            if (! $model instanceof Model || ! $model->getKey()) {
                return;
            }
            try {
                if ($model->getTable() === $service->bookingTable()) {
                    $service->syncCommission((int) $model->getKey());
                }
            } catch (\Throwable $error) {
                report($error);
            }
        });
    }
}
