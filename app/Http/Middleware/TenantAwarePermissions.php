<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Symfony\Component\HttpFoundation\Response;

class TenantAwarePermissions
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $permission, ?string $guard = null): Response
    {
        $authGuard = Auth::guard($guard);

        if ($authGuard->guest()) {
            throw UnauthorizedException::notLoggedIn();
        }

        $user = $authGuard->user();

        // Check if user belongs to a tenant
        if (!$user->tenant_id) {
            throw UnauthorizedException::notLoggedIn();
        }

        // Super admin bypass
        if ($user->hasRole(config('permission.super_admin_role'))) {
            return $next($request);
        }

        $permissions = is_array($permission)
            ? $permission
            : explode('|', $permission);

        // Check if user has any of the required permissions within their tenant context
        foreach ($permissions as $permission) {
            if ($user->hasPermissionTo($permission)) {
                return $next($request);
            }
        }

        throw UnauthorizedException::forPermissions($permissions);
    }

    /**
     * Create a middleware instance for multiple permissions (AND).
     */
    public static function allOf(array $permissions, ?string $guard = null): array
    {
        return array_map(
            fn($permission) => TenantAwarePermissions::class . ':' . $permission . ($guard ? ',' . $guard : ''),
            $permissions
        );
    }

    /**
     * Create a middleware instance for multiple permissions (OR).
     */
    public static function anyOf(array $permissions, ?string $guard = null): string
    {
        return TenantAwarePermissions::class . ':' . implode('|', $permissions) . ($guard ? ',' . $guard : '');
    }

    /**
     * Create a middleware instance for a role.
     */
    public static function role(string $role, ?string $guard = null): string
    {
        return TenantAwarePermissions::class . ':' . $role . ($guard ? ',' . $guard : '');
    }

    /**
     * Create a middleware instance for multiple roles (AND).
     */
    public static function allRoles(array $roles, ?string $guard = null): array
    {
        return array_map(
            fn($role) => TenantAwarePermissions::class . ':' . $role . ($guard ? ',' . $guard : ''),
            $roles
        );
    }

    /**
     * Create a middleware instance for multiple roles (OR).
     */
    public static function anyRole(array $roles, ?string $guard = null): string
    {
        return TenantAwarePermissions::class . ':' . implode('|', $roles) . ($guard ? ',' . $guard : '');
    }

    /**
     * Create a middleware instance for a permission.
     */
    public static function permission(string $permission, ?string $guard = null): string
    {
        return TenantAwarePermissions::class . ':' . $permission . ($guard ? ',' . $guard : '');
    }

    /**
     * Create a middleware instance for a permission within a tenant.
     */
    public static function tenantPermission(string $permission, ?string $guard = null): string
    {
        return TenantAwarePermissions::class . ':' . $permission . ($guard ? ',' . $guard : '');
    }

    /**
     * Create a middleware instance for a role within a tenant.
     */
    public static function tenantRole(string $role, ?string $guard = null): string
    {
        return TenantAwarePermissions::class . ':' . $role . ($guard ? ',' . $guard : '');
    }

    /**
     * Create a middleware instance for multiple permissions within a tenant (AND).
     */
    public static function allTenantPermissions(array $permissions, ?string $guard = null): array
    {
        return array_map(
            fn($permission) => TenantAwarePermissions::class . ':' . $permission . ($guard ? ',' . $guard : ''),
            $permissions
        );
    }

    /**
     * Create a middleware instance for multiple permissions within a tenant (OR).
     */
    public static function anyTenantPermission(array $permissions, ?string $guard = null): string
    {
        return TenantAwarePermissions::class . ':' . implode('|', $permissions) . ($guard ? ',' . $guard : '');
    }

    /**
     * Create a middleware instance for multiple roles within a tenant (AND).
     */
    public static function allTenantRoles(array $roles, ?string $guard = null): array
    {
        return array_map(
            fn($role) => TenantAwarePermissions::class . ':' . $role . ($guard ? ',' . $guard : ''),
            $roles
        );
    }

    /**
     * Create a middleware instance for multiple roles within a tenant (OR).
     */
    public static function anyTenantRole(array $roles, ?string $guard = null): string
    {
        return TenantAwarePermissions::class . ':' . implode('|', $roles) . ($guard ? ',' . $guard : '');
    }
}
