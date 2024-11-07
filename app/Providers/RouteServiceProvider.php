<?php

namespace App\Providers;

use App\Models\Tenant;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/dashboard';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        // Configure rate limiting
        $this->configureRateLimiting();

        // Register tenant route binding
        Route::bind('tenant', function ($value) {
            return Tenant::where('domain', $value)
                ->orWhere('id', $value)
                ->firstOrFail();
        });

        $this->routes(function () {
            // API routes
            Route::middleware(['api', 'tenant'])
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            // Web routes
            Route::middleware(['web', 'tenant'])
                ->group(base_path('routes/web.php'));

            // Tenant-specific API routes
            Route::middleware(['api', 'tenant'])
                ->prefix('api/tenant/{tenant}')
                ->name('tenant.')
                ->group(base_path('routes/tenant.php'));

            // Admin routes
            Route::middleware(['web', 'auth', 'role:super-admin'])
                ->prefix('admin')
                ->name('admin.')
                ->group(base_path('routes/admin.php'));
        });
    }

    /**
     * Configure the rate limiters for the application.
     */
    protected function configureRateLimiting(): void
    {
        // Global API rate limiter
        RateLimiter::for('api', function (Request $request) {
            $tenant = $request->user()?->tenant;
            $limit = $tenant?->getSetting('capabilities.api_rate_limit') ?? 60;

            return Limit::perMinute($limit)->by(
                $request->user()?->id ?: $request->ip()
            );
        });

        // Tenant-specific rate limiter
        RateLimiter::for('tenant', function (Request $request) {
            $tenant = $request->user()?->tenant;
            if (!$tenant) {
                return Limit::none();
            }

            $limit = $tenant->getSetting('capabilities.api_rate_limit') ?? 60;
            return Limit::perMinute($limit)->by($tenant->id);
        });

        // Admin rate limiter
        RateLimiter::for('admin', function (Request $request) {
            return Limit::perMinute(60)->by(
                $request->user()?->id ?: $request->ip()
            );
        });
    }

    /**
     * Get the tenant-specific middleware configuration.
     */
    protected function getTenantMiddleware(): array
    {
        return config('tenancy.routes.middleware', ['web', 'auth', 'tenant']);
    }

    /**
     * Get the tenant-specific route prefix.
     */
    protected function getTenantPrefix(): string
    {
        return config('tenancy.routes.prefix', 'tenant');
    }

    /**
     * Get the admin middleware configuration.
     */
    protected function getAdminMiddleware(): array
    {
        return ['web', 'auth', 'role:super-admin'];
    }

    /**
     * Get the admin route prefix.
     */
    protected function getAdminPrefix(): string
    {
        return 'admin';
    }

    /**
     * Get the API middleware configuration.
     */
    protected function getApiMiddleware(): array
    {
        return ['api', 'tenant'];
    }

    /**
     * Get the API route prefix.
     */
    protected function getApiPrefix(): string
    {
        return 'api';
    }
}
