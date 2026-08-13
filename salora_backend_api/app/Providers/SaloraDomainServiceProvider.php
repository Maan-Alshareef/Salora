<?php

namespace App\Providers;

use App\Console\Commands\AnnounceOffers;
use App\Console\Commands\CleanupLegacyExpiredBookings;
use App\Console\Commands\PushPendingNotifications;
use App\Console\Commands\ReportBookingConflicts;
use App\Models\Booking;
use App\Models\Notification;
use App\Models\Offer;
use App\Observers\BookingGuardObserver;
use App\Observers\NotificationPushObserver;
use App\Observers\OfferAnnouncementObserver;
use App\Services\BookingConflictService;
use App\Services\NotificationDeliveryService;
use App\Services\OfferAnnouncementService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;

class SaloraDomainServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(BookingConflictService::class);
        $this->app->singleton(NotificationDeliveryService::class);
        $this->app->singleton(OfferAnnouncementService::class);
    }

    public function boot(): void
    {
        Booking::observe(BookingGuardObserver::class);
        Notification::observe(NotificationPushObserver::class);
        Offer::observe(OfferAnnouncementObserver::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                ReportBookingConflicts::class,
                PushPendingNotifications::class,
                AnnounceOffers::class,
                CleanupLegacyExpiredBookings::class,
            ]);
        }

        $this->callAfterResolving(
            Schedule::class,
            function (Schedule $schedule): void {
                $schedule
                    ->command('salora:push-pending-notifications')
                    ->everyMinute()
                    ->withoutOverlapping();

                $schedule
                    ->command('salora:announce-offers')
                    ->everyMinute()
                    ->withoutOverlapping();
            },
        );
    }
}