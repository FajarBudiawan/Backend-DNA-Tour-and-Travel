<?php

namespace App\Providers;

use App\Events\SosCreated;
use App\Events\SosStatusUpdated;
use App\Listeners\SendSosCreatedNotification;
use App\Listeners\SendSosStatusUpdatedNotification;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        
        // ✅ SOS Events
        SosCreated::class => [
            SendSosCreatedNotification::class,
        ],
        SosStatusUpdated::class => [
            SendSosStatusUpdatedNotification::class,
        ],
    ];

    /**
     * Discover and register events and listeners using attributes.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}