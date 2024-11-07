<?php

namespace App\Providers;

use App\Models\Tenant;
use App\Models\User;
use App\Services\Cache\TenantCacheManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class TenancyServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../config/tenancy.php',
            'tenancy'
        );

        // Register tenant singleton
        $this->app->singleton('tenant', function ($app) {
            if (Auth::check()) {
                /** @var User */
                $user = Auth::user();
                return $user->tenant;
            }
            return null;
        });

        // Register tenant cache manager
        $this->app->singleton('cache.tenant', function ($app) {
            return new TenantCacheManager(
                $app['cache'],
                $app['tenant']
            );
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->registerMigrations();
        $this->registerBladeDirectives();
        $this->registerModelEvents();
        $this->registerMacros();
        $this->registerGates();
    }

    /**
     * Register tenant migrations.
     */
    protected function registerMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }

    /**
     * Register blade directives.
     */
    protected function registerBladeDirectives(): void
    {
        // Tenant check directive
        Blade::if('tenant', function () {
            return app('tenant') !== null;
        });

        // Tenant feature check directive
        Blade::if('tenantFeature', function ($feature) {
            return app('tenant')?->getSetting("features.{$feature}") === true;
        });

        // Tenant plan check directive
        Blade::if('tenantPlan', function ($plan) {
            return app('tenant')?->getSetting('subscription.plan') === $plan;
        });

        // Tenant capability check directive
        Blade::if('tenantCapability', function ($capability) {
            return app('tenant')?->getSetting("capabilities.{$capability}") === true;
        });
    }

    /**
     * Register model events.
     */
    protected function registerModelEvents(): void
    {
        // Auto-set tenant ID on model creation
        foreach (config('tenancy.database.tenant_aware_models', []) as $model) {
            $model::creating(function (Model $model) {
                if (!$model->tenant_id && Auth::check()) {
                    /** @var User */
                    $user = Auth::user();
                    $model->tenant_id = $user->tenant_id;
                }
            });
        }

        // Clear tenant cache when tenant is updated
        Tenant::updated(function (Tenant $tenant) {
            /** @var TenantCacheManager */
            $cache = app('cache.tenant');
            $cache->forTenant($tenant)->flush();
        });

        // Clear tenant cache when tenant is deleted
        Tenant::deleted(function (Tenant $tenant) {
            /** @var TenantCacheManager */
            $cache = app('cache.tenant');
            $cache->forTenant($tenant)->flush();
        });
    }

    /**
     * Register macros.
     */
    protected function registerMacros(): void
    {
        // Add tenant scope to query builder
        \Illuminate\Database\Query\Builder::macro('tenant', function () {
            /** @var \Illuminate\Database\Query\Builder $this */
            if (Auth::check()) {
                /** @var User */
                $user = Auth::user();
                if (!$user->hasRole(config('permission.super_admin_role'))) {
                    return $this->where('tenant_id', $user->tenant_id);
                }
            }
            return $this;
        });

        // Add tenant scope to activity log
        if (class_exists(\Spatie\Activitylog\ActivityLogger::class)) {
            \Spatie\Activitylog\ActivityLogger::macro('forTenant', function (Tenant $tenant) {
                /** @var \Spatie\Activitylog\ActivityLogger $this */
                return $this->withProperties(['tenant_id' => $tenant->id]);
            });
        }

        // Add tenant helper to Str
        Str::macro('tenantDomain', function (string $subdomain) {
            return $subdomain . '.' . config('tenancy.domain.subdomain.suffix');
        });
    }

    /**
     * Register gates.
     */
    protected function registerGates(): void
    {
        // Register tenant impersonation gate
        Gate::define('impersonate-tenant', function (User $user) {
            return config('tenancy.features.tenant_impersonation') &&
                   $user->hasRole(config('permission.super_admin_role'));
        });

        // Register tenant switching gate
        Gate::define('switch-tenant', function (User $user) {
            return config('tenancy.features.tenant_switching') &&
                   $user->hasRole(config('permission.super_admin_role'));
        });

        // Register tenant deletion gate
        Gate::define('delete-tenant', function (User $user, Tenant $tenant) {
            return config('tenancy.features.tenant_deletion') &&
                   ($user->hasRole(config('permission.super_admin_role')) || $user->tenant_id === $tenant->id);
        });

        // Register tenant backup gate
        Gate::define('backup-tenant', function (User $user, Tenant $tenant) {
            return config('tenancy.features.tenant_backup') &&
                   ($user->hasRole(config('permission.super_admin_role')) || $user->tenant_id === $tenant->id);
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            'tenant',
            'cache.tenant',
        ];
    }
}
