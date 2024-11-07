<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Symfony\Component\HttpFoundation\Response;

class TenantMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::guest()) {
            throw UnauthorizedException::notLoggedIn();
        }

        $user = Auth::user();

        // Super admin bypass
        if ($user->hasRole(config('permission.super_admin_role'))) {
            return $next($request);
        }

        // Check if user belongs to a tenant
        if (!$user->tenant_id) {
            throw UnauthorizedException::notLoggedIn();
        }

        // Check if tenant is active
        if (!$user->tenant->is_active) {
            throw UnauthorizedException::forPermissions(['access-tenant']);
        }

        // Set tenant context in config
        config([
            'tenant.id' => $user->tenant_id,
            'tenant.name' => $user->tenant->name,
            'tenant.settings' => $user->tenant->settings,
            'tenant.domains' => $user->tenant->domains,
        ]);

        // Set tenant context in session if web guard
        if ($request->hasSession()) {
            $request->session()->put('tenant_id', $user->tenant_id);
        }

        // Add tenant headers to response
        $response = $next($request);
        if (method_exists($response, 'header')) {
            $response->header('X-Tenant-ID', $user->tenant_id);
        }

        // Clear tenant context after response
        $this->clearTenantContext();

        return $response;
    }

    /**
     * Clear tenant context.
     */
    protected function clearTenantContext(): void
    {
        config([
            'tenant.id' => null,
            'tenant.name' => null,
            'tenant.settings' => null,
            'tenant.domains' => null,
        ]);

        // Clear permission cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Fire tenant.switched event
        event('tenant.switched');
    }

    /**
     * Create a middleware instance for tenant check.
     */
    public static function tenant(): string
    {
        return static::class;
    }

    /**
     * Create a middleware instance for active tenant check.
     */
    public static function activeTenant(): string
    {
        return static::class . ':active';
    }

    /**
     * Create a middleware instance for tenant with specific settings.
     */
    public static function tenantWithSettings(array $settings): string
    {
        return static::class . ':settings,' . implode(',', $settings);
    }

    /**
     * Create a middleware instance for tenant with specific features.
     */
    public static function tenantWithFeatures(array $features): string
    {
        return static::class . ':features,' . implode(',', $features);
    }

    /**
     * Create a middleware instance for tenant with specific domains.
     */
    public static function tenantWithDomains(array $domains): string
    {
        return static::class . ':domains,' . implode(',', $domains);
    }

    /**
     * Create a middleware instance for tenant with specific capabilities.
     */
    public static function tenantWithCapabilities(array $capabilities): string
    {
        return static::class . ':capabilities,' . implode(',', $capabilities);
    }

    /**
     * Create a middleware instance for tenant with specific plan.
     */
    public static function tenantWithPlan(string $plan): string
    {
        return static::class . ':plan,' . $plan;
    }

    /**
     * Create a middleware instance for tenant with specific subscription status.
     */
    public static function tenantWithSubscriptionStatus(string $status): string
    {
        return static::class . ':subscription,' . $status;
    }
}
