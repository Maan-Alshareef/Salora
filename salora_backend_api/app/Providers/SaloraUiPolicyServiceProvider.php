<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class SaloraUiPolicyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->preventLoginNotifications();
        $this->synchroniseHourlyVenuePrice();
    }

    private function preventLoginNotifications(): void
    {
        $models = [DatabaseNotification::class];
        if (class_exists(\App\Models\Notification::class)) {
            $models[] = \App\Models\Notification::class;
        }

        foreach (array_unique($models) as $modelClass) {
            if (! is_subclass_of($modelClass, Model::class)) {
                continue;
            }

            $modelClass::creating(function (Model $notification): bool {
                $values = [];
                foreach (['title', 'message', 'body', 'type', 'event', 'action', 'data'] as $field) {
                    $value = $notification->getAttribute($field);
                    if (is_array($value) || is_object($value)) {
                        $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    }
                    if ($value !== null) {
                        $values[] = (string) $value;
                    }
                }

                $text = mb_strtolower(implode(' ', $values), 'UTF-8');
                foreach (['تسجيل دخول', 'تم تسجيل الدخول', 'سجل دخول جديد', 'new login', 'logged in', 'login_notification', 'login notification'] as $needle) {
                    if (str_contains($text, mb_strtolower($needle, 'UTF-8'))) {
                        return false;
                    }
                }

                return true;
            });
        }
    }

    private function synchroniseHourlyVenuePrice(): void
    {
        if (! class_exists(\App\Models\Venue::class)) {
            return;
        }

        \App\Models\Venue::saving(function (Model $venue): void {
            $schema = Schema::connection($venue->getConnectionName());
            $table = $venue->getTable();
            if (! $schema->hasColumn($table, 'hourly_price_syp')) {
                return;
            }

            $request = app()->bound('request') ? request() : null;
            $candidate = $request?->input('hourly_price_syp')
                ?? $request?->input('hourlyPriceSyp')
                ?? $request?->input('price_syp')
                ?? $request?->input('priceSyp');

            if ($candidate === null && $schema->hasColumn($table, 'price_syp') && $venue->isDirty('price_syp')) {
                $candidate = $venue->getAttribute('price_syp');
            }

            if ($candidate === null && (float) $venue->getAttribute('hourly_price_syp') <= 0 && $schema->hasColumn($table, 'price_syp')) {
                $candidate = $venue->getAttribute('price_syp');
            }

            if (is_numeric($candidate) && (float) $candidate > 0) {
                $venue->setAttribute('hourly_price_syp', (float) $candidate);
                if ($schema->hasColumn($table, 'price_syp')) {
                    $venue->setAttribute('price_syp', (float) $candidate);
                }
            }
        });
    }
}