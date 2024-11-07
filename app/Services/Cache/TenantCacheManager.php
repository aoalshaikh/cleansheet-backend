<?php

namespace App\Services\Cache;

use App\Models\Tenant;
use Illuminate\Cache\CacheManager;
use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Cache;

class TenantCacheManager
{
    private CacheManager $cache;
    private ?Tenant $tenant;

    public function __construct(CacheManager $cache, ?Tenant $tenant = null)
    {
        $this->cache = $cache;
        $this->tenant = $tenant;
    }

    /**
     * Get a cache store instance.
     */
    public function store(): Repository
    {
        $store = $this->cache->store();

        if (method_exists($store, 'tags')) {
            return $store->tags($this->getTags());
        }

        return $store;
    }

    /**
     * Get cache tags for the current tenant.
     *
     * @return array<string>
     */
    protected function getTags(): array
    {
        return ['tenant:' . ($this->tenant?->id ?? 'null')];
    }

    /**
     * Get a cached value.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->store()->get($this->getKey($key), $default);
    }

    /**
     * Set a cached value.
     */
    public function put(string $key, mixed $value, int $ttl = null): bool
    {
        return $this->store()->put($this->getKey($key), $value, $ttl);
    }

    /**
     * Remove a cached value.
     */
    public function forget(string $key): bool
    {
        return $this->store()->forget($this->getKey($key));
    }

    /**
     * Clear all cached values for the current tenant.
     */
    public function flush(): bool
    {
        return $this->store()->flush();
    }

    /**
     * Remember a value in cache.
     */
    public function remember(string $key, int $ttl, \Closure $callback): mixed
    {
        return $this->store()->remember($this->getKey($key), $ttl, $callback);
    }

    /**
     * Remember a value in cache forever.
     */
    public function rememberForever(string $key, \Closure $callback): mixed
    {
        return $this->store()->rememberForever($this->getKey($key), $callback);
    }

    /**
     * Get a prefixed cache key.
     */
    protected function getKey(string $key): string
    {
        return "tenant:{$this->tenant?->id}:{$key}";
    }

    /**
     * Set the tenant for this cache manager.
     */
    public function forTenant(?Tenant $tenant): self
    {
        $this->tenant = $tenant;
        return $this;
    }

    /**
     * Get the current tenant.
     */
    public function getTenant(): ?Tenant
    {
        return $this->tenant;
    }

    /**
     * Determine if the cache has a value.
     */
    public function has(string $key): bool
    {
        return $this->store()->has($this->getKey($key));
    }

    /**
     * Increment a value.
     */
    public function increment(string $key, int $value = 1): int|bool
    {
        return $this->store()->increment($this->getKey($key), $value);
    }

    /**
     * Decrement a value.
     */
    public function decrement(string $key, int $value = 1): int|bool
    {
        return $this->store()->decrement($this->getKey($key), $value);
    }

    /**
     * Get multiple cached values.
     *
     * @param array<string> $keys
     * @return array<string, mixed>
     */
    public function many(array $keys): array
    {
        $prefixedKeys = array_map(
            fn($key) => $this->getKey($key),
            $keys
        );

        return $this->store()->many($prefixedKeys);
    }

    /**
     * Set multiple cached values.
     *
     * @param array<string, mixed> $values
     * @param int|null $ttl
     */
    public function putMany(array $values, ?int $ttl = null): bool
    {
        $prefixedValues = [];
        foreach ($values as $key => $value) {
            $prefixedValues[$this->getKey($key)] = $value;
        }

        return $this->store()->putMany($prefixedValues, $ttl);
    }

    /**
     * Get the cache TTL from config.
     */
    protected function getDefaultTTL(): int
    {
        return config('tenancy.cache.ttl', 3600);
    }

    /**
     * Get the cache prefix from config.
     */
    protected function getCachePrefix(): string
    {
        return config('tenancy.cache.prefix', 'tenant');
    }
}
