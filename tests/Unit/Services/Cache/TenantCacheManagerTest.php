<?php

namespace Tests\Unit\Services\Cache;

use App\Models\Tenant;
use App\Services\Cache\TenantCacheManager;
use Illuminate\Cache\CacheManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class TenantCacheManagerTest extends TestCase
{
    use RefreshDatabase;

    private TenantCacheManager $cacheManager;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->cacheManager = new TenantCacheManager(
            app(CacheManager::class),
            $this->tenant
        );
    }

    public function test_get_and_put(): void
    {
        $this->cacheManager->put('test_key', 'test_value');
        $this->assertEquals('test_value', $this->cacheManager->get('test_key'));
    }

    public function test_forget(): void
    {
        $this->cacheManager->put('test_key', 'test_value');
        $this->assertTrue($this->cacheManager->forget('test_key'));
        $this->assertNull($this->cacheManager->get('test_key'));
    }

    public function test_has(): void
    {
        $this->cacheManager->put('test_key', 'test_value');
        $this->assertTrue($this->cacheManager->has('test_key'));
        $this->cacheManager->forget('test_key');
        $this->assertFalse($this->cacheManager->has('test_key'));
    }

    public function test_remember(): void
    {
        $value = $this->cacheManager->remember('test_key', 60, fn() => 'test_value');
        $this->assertEquals('test_value', $value);
        $this->assertEquals('test_value', $this->cacheManager->get('test_key'));
    }

    public function test_remember_forever(): void
    {
        $value = $this->cacheManager->rememberForever('test_key', fn() => 'test_value');
        $this->assertEquals('test_value', $value);
        $this->assertEquals('test_value', $this->cacheManager->get('test_key'));
    }

    public function test_increment_and_decrement(): void
    {
        $this->cacheManager->put('counter', 1);
        $this->assertEquals(2, $this->cacheManager->increment('counter'));
        $this->assertEquals(1, $this->cacheManager->decrement('counter'));
    }

    public function test_many_operations(): void
    {
        $values = ['key1' => 'value1', 'key2' => 'value2'];
        $this->cacheManager->putMany($values);

        $retrieved = $this->cacheManager->many(array_keys($values));
        $this->assertEquals($values, $retrieved);
    }

    public function test_tenant_isolation(): void
    {
        // Set up two tenants
        $tenant1 = $this->tenant;
        $tenant2 = Tenant::factory()->create();

        // Create cache managers for each tenant
        $cache1 = new TenantCacheManager(app(CacheManager::class), $tenant1);
        $cache2 = new TenantCacheManager(app(CacheManager::class), $tenant2);

        // Store values for each tenant
        $cache1->put('shared_key', 'tenant1_value');
        $cache2->put('shared_key', 'tenant2_value');

        // Verify tenant isolation
        $this->assertEquals('tenant1_value', $cache1->get('shared_key'));
        $this->assertEquals('tenant2_value', $cache2->get('shared_key'));
    }

    public function test_flush(): void
    {
        // Store multiple values
        $this->cacheManager->put('key1', 'value1');
        $this->cacheManager->put('key2', 'value2');

        // Flush the cache
        $this->assertTrue($this->cacheManager->flush());

        // Verify all values are cleared
        $this->assertNull($this->cacheManager->get('key1'));
        $this->assertNull($this->cacheManager->get('key2'));
    }

    public function test_for_tenant(): void
    {
        $newTenant = Tenant::factory()->create();
        
        // Store value for current tenant
        $this->cacheManager->put('test_key', 'original_value');

        // Switch to new tenant
        $this->cacheManager->forTenant($newTenant);

        // Verify tenant isolation after switching
        $this->assertNull($this->cacheManager->get('test_key'));
        $this->cacheManager->put('test_key', 'new_value');
        $this->assertEquals('new_value', $this->cacheManager->get('test_key'));
    }

    public function test_get_tenant(): void
    {
        $this->assertEquals($this->tenant->id, $this->cacheManager->getTenant()->id);
    }

    public function test_null_tenant(): void
    {
        $cacheManager = new TenantCacheManager(app(CacheManager::class));
        $cacheManager->put('test_key', 'test_value');
        $this->assertEquals('test_value', $cacheManager->get('test_key'));
    }

    public function test_cache_prefix(): void
    {
        $key = 'test_key';
        $value = 'test_value';
        $this->cacheManager->put($key, $value);

        // Verify the value is stored with tenant prefix
        $prefixedKey = "tenant:{$this->tenant->id}:{$key}";
        $this->assertEquals($value, Cache::get($prefixedKey));
    }

    public function test_ttl(): void
    {
        $key = 'test_key';
        $value = 'test_value';
        $ttl = 1; // 1 second

        $this->cacheManager->put($key, $value, $ttl);
        $this->assertEquals($value, $this->cacheManager->get($key));

        // Wait for TTL to expire
        sleep(2);
        $this->assertNull($this->cacheManager->get($key));
    }

    public function test_default_ttl_from_config(): void
    {
        $defaultTtl = config('tenancy.cache.ttl', 3600);
        $this->assertIsInt($defaultTtl);
        $this->assertGreaterThan(0, $defaultTtl);
    }

    public function test_cache_prefix_from_config(): void
    {
        $prefix = config('tenancy.cache.prefix', 'tenant');
        $this->assertIsString($prefix);
        $this->assertEquals('tenant', $prefix);
    }
}
