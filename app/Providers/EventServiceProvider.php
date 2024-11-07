<?php

namespace App\Providers;

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        'tenant.switched' => [
            // Add any listeners for tenant switching events
        ],
        'tenant.created' => [
            // Add any listeners for tenant creation events
        ],
        'tenant.deleted' => [
            // Add any listeners for tenant deletion events
        ],
        'tenant.updated' => [
            // Add any listeners for tenant update events
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        // Register tenant-related events
        Event::listen('tenant.*', function ($eventName, array $data) {
            // Log tenant events
            activity()
                ->withProperties($data)
                ->log("Tenant event: {$eventName}");
        });

        // Clear tenant cache when tenant is updated
        Event::listen('tenant.updated', function ($tenantId) {
            /** @var \App\Services\Cache\TenantCacheManager */
            $cache = app('cache.tenant');
            $cache->forTenant(\App\Models\Tenant::find($tenantId))->flush();
        });

        // Clear tenant cache when tenant is deleted
        Event::listen('tenant.deleted', function ($tenantId) {
            /** @var \App\Services\Cache\TenantCacheManager */
            $cache = app('cache.tenant');
            $cache->forTenant(\App\Models\Tenant::find($tenantId))->flush();
        });

        // Initialize tenant settings when created
        Event::listen('tenant.created', function ($tenantId) {
            $tenant = \App\Models\Tenant::find($tenantId);
            if ($tenant) {
                $tenant->settings = array_merge(
                    config('tenancy.default_settings', []),
                    $tenant->settings ?? []
                );
                $tenant->save();
            }
        });
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
