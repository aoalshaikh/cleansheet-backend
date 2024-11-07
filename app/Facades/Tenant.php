<?php

namespace App\Facades;

use App\Models\Tenant as TenantModel;
use Illuminate\Support\Facades\Facade;

/**
 * @method static TenantModel|null current()
 * @method static bool check()
 * @method static mixed getSetting(string $key, mixed $default = null)
 * @method static bool hasFeature(string $feature)
 * @method static bool hasCapability(string $capability)
 * @method static bool hasPlan(string $plan)
 * @method static bool hasSubscriptionStatus(string $status)
 * @method static bool hasDomain(string $domain)
 * @method static array getDomains()
 * @method static bool isActive()
 * @method static void clearCache()
 *
 * @see \App\Models\Tenant
 */
class Tenant extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'tenant';
    }

    /**
     * Get the current tenant.
     */
    public static function current(): ?TenantModel
    {
        return static::getFacadeRoot();
    }

    /**
     * Check if there is a current tenant.
     */
    public static function check(): bool
    {
        return static::current() !== null;
    }

    /**
     * Get a tenant setting.
     */
    public static function getSetting(string $key, mixed $default = null): mixed
    {
        return static::current()?->getSetting($key, $default);
    }

    /**
     * Check if tenant has a feature.
     */
    public static function hasFeature(string $feature): bool
    {
        return static::getSetting("features.{$feature}") === true;
    }

    /**
     * Check if tenant has a capability.
     */
    public static function hasCapability(string $capability): bool
    {
        return static::getSetting("capabilities.{$capability}") === true;
    }

    /**
     * Check if tenant has a specific plan.
     */
    public static function hasPlan(string $plan): bool
    {
        return static::getSetting('subscription.plan') === $plan;
    }

    /**
     * Check if tenant has a specific subscription status.
     */
    public static function hasSubscriptionStatus(string $status): bool
    {
        return static::getSetting('subscription.status') === $status;
    }

    /**
     * Check if tenant has a specific domain.
     */
    public static function hasDomain(string $domain): bool
    {
        $domains = static::current()?->domains ?? [];
        return in_array($domain, $domains);
    }

    /**
     * Get tenant domains.
     *
     * @return array<string>
     */
    public static function getDomains(): array
    {
        return static::current()?->domains ?? [];
    }

    /**
     * Check if tenant is active.
     */
    public static function isActive(): bool
    {
        return static::current()?->is_active ?? false;
    }

    /**
     * Clear tenant cache.
     */
    public static function clearCache(): void
    {
        if ($tenant = static::current()) {
            /** @var \App\Services\Cache\TenantCacheManager */
            $cache = app('cache.tenant');
            $cache->forTenant($tenant)->flush();
        }
    }
}
