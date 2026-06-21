<?php

namespace App\Providers;

use App\Events\AddonPurchased;
use App\Events\EmployeeProfileUpdated;
use App\Events\SubscriptionCreated;
use App\Events\TaxGovernancePolicyTransitioned;
use App\Listeners\CaptureAddonRevenue;
use App\Listeners\CaptureSubscriptionRevenue;
use App\Listeners\EnforceNotificationPreference;
use App\Listeners\SendProfileUpdateNotification;
use App\Listeners\TaxGovernancePolicyEventListener;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Notifications\Events\NotificationSending;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        TaxGovernancePolicyTransitioned::class => [
            TaxGovernancePolicyEventListener::class,
        ],
        SubscriptionCreated::class => [
            CaptureSubscriptionRevenue::class,
        ],
        AddonPurchased::class => [
            CaptureAddonRevenue::class,
        ],
        NotificationSending::class => [
            EnforceNotificationPreference::class,
        ],
        EmployeeProfileUpdated::class => [
            SendProfileUpdateNotification::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
