<?php

namespace Tests\Unit\Facades;

use App\Facades\Tenant;
use App\Models\Tenant as TenantModel;
use App\Models\User;
use App\Services\Cache\TenantCacheManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\InteractsWithAuthentication;

class TenantFacadeTest extends TestCase
{
    use RefreshDatabase, InteractsWithAuthentication;

    private TenantModel $tenant;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = TenantModel::factory()->create([
            'is_active' => true,
            'settings' => [
                'features' => [
                    'test_feature' => true,
                    'disabled_feature' => false,
                ],
                'capabilities' => [
                    'test_capability' => true,
                    'disabled_capability' => false,
                ],
                'subscription' => [
                    'plan' => 'premium',
                    'status' => 'active',
                ],
            ],
            'domains' => ['test.example.com'],
        ]);

        $this->user = User::factory()
            ->forTenant($this->tenant)
            ->create();

        $this->actingAsUser($this->user);
    }

    public function test_current_tenant(): void
    {
        $this->assertInstanceOf(TenantModel::class, Tenant::current());
        $this->assertEquals($this->tenant->id, Tenant::current()->id);
    }

    public function test_check_tenant(): void
    {
        $this->assertTrue(Tenant::check());

        $this->actingAsGuest();
        $this->assertFalse(Tenant::check());
    }

    public function test_get_setting(): void
    {
        $this->assertTrue(Tenant::getSetting('features.test_feature'));
        $this->assertFalse(Tenant::getSetting('features.disabled_feature'));
        $this->assertEquals('premium', Tenant::getSetting('subscription.plan'));
        $this->assertNull(Tenant::getSetting('non.existent.key'));
    }

    public function test_has_feature(): void
    {
        $this->assertTrue(Tenant::hasFeature('test_feature'));
        $this->assertFalse(Tenant::hasFeature('disabled_feature'));
        $this->assertFalse(Tenant::hasFeature('non_existent_feature'));
    }

    public function test_has_capability(): void
    {
        $this->assertTrue(Tenant::hasCapability('test_capability'));
        $this->assertFalse(Tenant::hasCapability('disabled_capability'));
        $this->assertFalse(Tenant::hasCapability('non_existent_capability'));
    }

    public function test_has_plan(): void
    {
        $this->assertTrue(Tenant::hasPlan('premium'));
        $this->assertFalse(Tenant::hasPlan('basic'));
    }

    public function test_has_subscription_status(): void
    {
        $this->assertTrue(Tenant::hasSubscriptionStatus('active'));
        $this->assertFalse(Tenant::hasSubscriptionStatus('inactive'));
    }

    public function test_has_domain(): void
    {
        $this->assertTrue(Tenant::hasDomain('test.example.com'));
        $this->assertFalse(Tenant::hasDomain('other.example.com'));
    }

    public function test_get_domains(): void
    {
        $this->assertEquals(['test.example.com'], Tenant::getDomains());
    }

    public function test_is_active(): void
    {
        $this->assertTrue(Tenant::isActive());

        $this->tenant->update(['is_active' => false]);
        $this->assertFalse(Tenant::isActive());
    }

    public function test_clear_cache(): void
    {
        /** @var TenantCacheManager */
        $cache = app('cache.tenant');
        $cache->put('test_key', 'test_value');

        Tenant::clearCache();

        $this->assertNull($cache->get('test_key'));
    }

    public function test_facade_with_guest_user(): void
    {
        $this->actingAsGuest();

        $this->assertNull(Tenant::current());
        $this->assertFalse(Tenant::check());
        $this->assertNull(Tenant::getSetting('any.key'));
        $this->assertFalse(Tenant::hasFeature('any_feature'));
        $this->assertFalse(Tenant::hasCapability('any_capability'));
        $this->assertFalse(Tenant::hasPlan('any_plan'));
        $this->assertFalse(Tenant::hasSubscriptionStatus('any_status'));
        $this->assertFalse(Tenant::hasDomain('any.domain'));
        $this->assertEquals([], Tenant::getDomains());
        $this->assertFalse(Tenant::isActive());
    }

    public function test_facade_with_different_tenants(): void
    {
        // Create another tenant with different settings
        $otherTenant = TenantModel::factory()->create([
            'settings' => [
                'features' => [
                    'other_feature' => true,
                ],
                'subscription' => [
                    'plan' => 'basic',
                ],
            ],
        ]);

        $otherUser = User::factory()
            ->forTenant($otherTenant)
            ->create();

        // Test current tenant
        $this->assertTrue(Tenant::hasFeature('test_feature'));
        $this->assertFalse(Tenant::hasFeature('other_feature'));
        $this->assertTrue(Tenant::hasPlan('premium'));

        // Switch to other tenant
        $this->actingAsUser($otherUser);

        // Test other tenant
        $this->assertFalse(Tenant::hasFeature('test_feature'));
        $this->assertTrue(Tenant::hasFeature('other_feature'));
        $this->assertTrue(Tenant::hasPlan('basic'));
    }

    public function test_facade_caching(): void
    {
        /** @var TenantCacheManager */
        $cache = app('cache.tenant');

        // Cache some tenant-specific data
        $cache->put('test_key', 'test_value');
        $this->assertEquals('test_value', $cache->get('test_key'));

        // Switch tenant
        $otherTenant = TenantModel::factory()->create();
        $otherUser = User::factory()
            ->forTenant($otherTenant)
            ->create();
        $this->actingAsUser($otherUser);

        // Verify cache isolation
        $this->assertNull($cache->get('test_key'));
    }
}
