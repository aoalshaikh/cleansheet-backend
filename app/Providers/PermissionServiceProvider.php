<?php

namespace App\Providers;

use App\Http\Middleware\TenantAwarePermissions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Override the PermissionRegistrar singleton to add tenant awareness
        $this->app->singleton(PermissionRegistrar::class, function ($app) {
            return new PermissionRegistrar($app);
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register the middleware
        $this->app['router']->aliasMiddleware('permission', TenantAwarePermissions::class);
        $this->app['router']->aliasMiddleware('role', TenantAwarePermissions::class);

        // Register the super-admin gate check
        Gate::before(function ($user, $ability) {
            if ($user->hasRole(config('permission.super_admin_role'))) {
                return true;
            }
        });

        // Add tenant scope to roles
        Role::addGlobalScope('tenant', function ($query) {
            if (Auth::check() && !Auth::user()->hasRole(config('permission.super_admin_role'))) {
                $query->where(function ($q) {
                    $q->where('is_tenant_role', true)
                        ->orWhere('name', config('permission.super_admin_role'));
                });
            }
        });

        // Clear permission cache when tenant changes
        $this->app['events']->listen('tenant.switched', function () {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        });

        // Register blade directives
        $this->registerBladeDirectives();
    }

    /**
     * Register blade directives for tenant-aware permissions.
     */
    protected function registerBladeDirectives(): void
    {
        // Tenant role directive
        Blade::directive('tenantRole', function ($arguments) {
            return "<?php if(auth()->check() && auth()->user()->hasRole({$arguments})): ?>";
        });

        Blade::directive('endTenantRole', function () {
            return '<?php endif; ?>';
        });

        // Tenant permission directive
        Blade::directive('tenantPermission', function ($arguments) {
            return "<?php if(auth()->check() && auth()->user()->hasPermissionTo({$arguments})): ?>";
        });

        Blade::directive('endTenantPermission', function () {
            return '<?php endif; ?>';
        });

        // Any tenant role directive
        Blade::directive('anyTenantRole', function ($arguments) {
            return "<?php if(auth()->check() && auth()->user()->hasAnyRole({$arguments})): ?>";
        });

        Blade::directive('endAnyTenantRole', function () {
            return '<?php endif; ?>';
        });

        // All tenant roles directive
        Blade::directive('allTenantRoles', function ($arguments) {
            return "<?php if(auth()->check() && auth()->user()->hasAllRoles({$arguments})): ?>";
        });

        Blade::directive('endAllTenantRoles', function () {
            return '<?php endif; ?>';
        });

        // Any tenant permission directive
        Blade::directive('anyTenantPermission', function ($arguments) {
            return "<?php if(auth()->check() && auth()->user()->hasAnyPermission({$arguments})): ?>";
        });

        Blade::directive('endAnyTenantPermission', function () {
            return '<?php endif; ?>';
        });

        // All tenant permissions directive
        Blade::directive('allTenantPermissions', function ($arguments) {
            return "<?php if(auth()->check() && auth()->user()->hasAllPermissions({$arguments})): ?>";
        });

        Blade::directive('endAllTenantPermissions', function () {
            return '<?php endif; ?>';
        });

        // Unscoped role directive (ignores tenant)
        Blade::directive('unscopedRole', function ($arguments) {
            return "<?php if(auth()->check() && auth()->user()->hasRole({$arguments}, false)): ?>";
        });

        Blade::directive('endUnscopedRole', function () {
            return '<?php endif; ?>';
        });

        // Unscoped permission directive (ignores tenant)
        Blade::directive('unscopedPermission', function ($arguments) {
            return "<?php if(auth()->check() && auth()->user()->hasPermissionTo({$arguments}, false)): ?>";
        });

        Blade::directive('endUnscopedPermission', function () {
            return '<?php endif; ?>';
        });
    }
}
