<?php

namespace Tests\Unit\Models;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\InteractsWithTenant;

class TenantTest extends TestCase
{
    use RefreshDatabase, InteractsWithTenant;

    public function test_tenant_creation(): void
    {
        $tenant = $this->setUpTenant([
            'name' => 'Test Tenant',
            'domain' => 'test.example.com',
            'settings' => [
                'features' => ['test_feature' => true],
            ],
        ]);

        $this->assertInstanceOf(Tenant::class, $tenant);
        $this->assertEquals('Test Tenant', $tenant->name);
        $this->assertEquals('test.example.com', $tenant->domain);
        $this->assertTrue($tenant->getSetting('features.test_feature'));
    }

    public function test_tenant_user_relationship(): void
    {
        $this->setUpTenant();
        $users = $this->createTenantUsers(3);

        $tenant = $this->getCurrentTenant();
        $this->assertCount(3, $tenant->users);
        $this->assertInstanceOf(User::class, $tenant->users->first());
    }

    public function test_tenant_settings_management(): void
    {
        $tenant = $this->setUpTenant();

        // Test setting individual values
        $tenant->setSetting('test_key', 'test_value');
        $this->assertEquals('test_value', $tenant->getSetting('test_key'));

        // Test setting nested values
        $tenant->setSetting('nested.key', 'nested_value');
        $this->assertEquals('nested_value', $tenant->getSetting('nested.key'));

        // Test default value
        $this->assertEquals('default', $tenant->getSetting('non.existent.key', 'default'));

        // Test removing settings
        $tenant->removeSetting('test_key');
        $this->assertNull($tenant->getSetting('test_key'));
    }

    public function test_tenant_feature_management(): void
    {
        $tenant = $this->setUpTenant([
            'settings' => [
                'features' => [
                    'feature1' => true,
                    'feature2' => false,
                ],
            ],
        ]);

        $this->assertTrue($tenant->hasFeature('feature1'));
        $this->assertFalse($tenant->hasFeature('feature2'));
        $this->assertFalse($tenant->hasFeature('non_existent_feature'));
    }

    public function test_tenant_capability_management(): void
    {
        $tenant = $this->setUpTenant([
            'settings' => [
                'capabilities' => [
                    'max_users' => 5,
                    'storage_limit' => '1GB',
                ],
            ],
        ]);

        $this->assertEquals(5, $tenant->getCapability('max_users'));
        $this->assertEquals('1GB', $tenant->getCapability('storage_limit'));
        $this->assertNull($tenant->getCapability('non_existent_capability'));
    }

    public function test_tenant_subscription_management(): void
    {
        $tenant = $this->setUpTenant([
            'settings' => [
                'subscription' => [
                    'plan' => 'premium',
                    'status' => 'active',
                ],
            ],
        ]);

        $this->assertTrue($tenant->hasPlan('premium'));
        $this->assertTrue($tenant->hasSubscriptionStatus('active'));
        $this->assertFalse($tenant->hasPlan('basic'));
        $this->assertFalse($tenant->hasSubscriptionStatus('inactive'));
    }

    public function test_tenant_domain_management(): void
    {
        $tenant = $this->setUpTenant();

        // Add domains
        $tenant->addDomain('test1.example.com');
        $tenant->addDomain('test2.example.com');

        $this->assertTrue($tenant->hasDomain('test1.example.com'));
        $this->assertTrue($tenant->hasDomain('test2.example.com'));
        $this->assertFalse($tenant->hasDomain('non.existent.com'));

        // Remove domain
        $tenant->removeDomain('test1.example.com');
        $this->assertFalse($tenant->hasDomain('test1.example.com'));
    }

    public function test_tenant_activity_logging(): void
    {
        $tenant = $this->setUpTenant();
        $user = $this->createTenantUser();

        activity()
            ->causedBy($user)
            ->forTenant($tenant)
            ->log('Test activity');

        $this->assertCount(1, $tenant->activities);
        $this->assertEquals('Test activity', $tenant->activities->first()->description);
    }

    public function test_tenant_soft_deletion(): void
    {
        $tenant = $this->setUpTenant();
        $userId = $this->createTenantUser()->id;

        // Soft delete tenant
        $tenant->delete();

        $this->assertTrue($tenant->trashed());
        $this->assertSoftDeleted('tenants', ['id' => $tenant->id]);
        
        // Users should still exist
        $this->assertDatabaseHas('users', ['id' => $userId]);

        // Restore tenant
        $tenant->restore();
        $this->assertFalse($tenant->trashed());
    }

    public function test_tenant_force_deletion(): void
    {
        $tenant = $this->setUpTenant();
        $userId = $this->createTenantUser()->id;

        // Force delete tenant
        $tenant->forceDelete();

        $this->assertDatabaseMissing('tenants', ['id' => $tenant->id]);
        // Users should be deleted due to cascade
        $this->assertDatabaseMissing('users', ['id' => $userId]);
    }

    public function test_tenant_scope(): void
    {
        // Create multiple tenants
        $tenant1 = $this->setUpTenant(['name' => 'Tenant 1']);
        $tenant2 = $this->setUpTenant(['name' => 'Tenant 2']);

        // Create users for each tenant
        $this->createTenantUser(['name' => 'User 1']);
        $this->createTenantUser(['name' => 'User 2']);

        // Test tenant scope
        $this->actingAsTenantUser();
        $users = User::query()->tenant()->get();

        $this->assertCount(1, $users);
        $this->assertEquals('User 1', $users->first()->name);
    }

    public function test_tenant_cache_management(): void
    {
        $tenant = $this->setUpTenant();
        $cache = app('cache.tenant')->forTenant($tenant);

        $cache->put('test_key', 'test_value', 60);
        $this->assertEquals('test_value', $cache->get('test_key'));

        // Update tenant to clear cache
        $tenant->update(['name' => 'Updated Name']);
        $this->assertNull($cache->get('test_key'));
    }
}
