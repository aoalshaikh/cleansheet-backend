<?php

namespace Tests\Unit\Traits;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\InteractsWithTenant;

class InteractsWithTenantTest extends TestCase
{
    use RefreshDatabase, InteractsWithTenant;

    public function test_setup_tenant(): void
    {
        $tenant = $this->setUpTenant([
            'name' => 'Test Tenant',
            'domain' => 'test.example.com',
        ]);

        $this->assertInstanceOf(Tenant::class, $tenant);
        $this->assertEquals('Test Tenant', $tenant->name);
        $this->assertEquals('test.example.com', $tenant->domain);
        $this->assertTrue($tenant->is_active);
    }

    public function test_create_tenant_user(): void
    {
        $user = $this->createTenantUser([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('John Doe', $user->name);
        $this->assertEquals('john@example.com', $user->email);
        $this->assertBelongsToCurrentTenant($user);
    }

    public function test_acting_as_tenant_user(): void
    {
        $this->actingAsTenantUser();

        $this->assertAuthenticatedAs($this->getCurrentTenantUser());
        $this->assertBelongsToCurrentTenant($this->getCurrentTenantUser());
    }

    public function test_create_multiple_tenant_users(): void
    {
        $users = $this->createTenantUsers(3, [
            'name' => 'Test User',
        ]);

        $this->assertCount(3, $users);
        foreach ($users as $user) {
            $this->assertInstanceOf(User::class, $user);
            $this->assertEquals('Test User', $user->name);
            $this->assertBelongsToCurrentTenant($user);
        }
    }

    public function test_assert_belongs_to_current_tenant(): void
    {
        $user = $this->createTenantUser();
        $this->assertBelongsToCurrentTenant($user);

        // Create user for different tenant
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->forTenant($otherTenant)->create();
        
        $this->expectException(\PHPUnit\Framework\ExpectationFailedException::class);
        $this->assertBelongsToCurrentTenant($otherUser);
    }

    public function test_assert_does_not_belong_to_current_tenant(): void
    {
        // Create user for different tenant
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->forTenant($otherTenant)->create();
        
        $this->assertDoesNotBelongToCurrentTenant($otherUser);

        $user = $this->createTenantUser();
        $this->expectException(\PHPUnit\Framework\ExpectationFailedException::class);
        $this->assertDoesNotBelongToCurrentTenant($user);
    }

    public function test_assert_tenant_has_feature(): void
    {
        $this->setUpTenant([
            'settings' => [
                'features' => [
                    'test_feature' => true,
                ],
            ],
        ]);

        $this->assertTenantHasFeature('test_feature');

        $this->expectException(\PHPUnit\Framework\ExpectationFailedException::class);
        $this->assertTenantHasFeature('non_existent_feature');
    }

    public function test_assert_tenant_has_capability(): void
    {
        $this->setUpTenant([
            'settings' => [
                'capabilities' => [
                    'max_users' => 5,
                ],
            ],
        ]);

        $this->assertTenantHasCapability('max_users', 5);

        $this->expectException(\PHPUnit\Framework\ExpectationFailedException::class);
        $this->assertTenantHasCapability('max_users', 10);
    }

    public function test_assert_tenant_on_plan(): void
    {
        $this->setUpTenant([
            'settings' => [
                'subscription' => [
                    'plan' => 'premium',
                ],
            ],
        ]);

        $this->assertTenantOnPlan('premium');

        $this->expectException(\PHPUnit\Framework\ExpectationFailedException::class);
        $this->assertTenantOnPlan('basic');
    }

    public function test_assert_tenant_subscription_status(): void
    {
        $this->setUpTenant([
            'settings' => [
                'subscription' => [
                    'status' => 'active',
                ],
            ],
        ]);

        $this->assertTenantSubscriptionStatus('active');

        $this->expectException(\PHPUnit\Framework\ExpectationFailedException::class);
        $this->assertTenantSubscriptionStatus('inactive');
    }

    public function test_assert_tenant_active_status(): void
    {
        $this->setUpTenant(['is_active' => true]);
        $this->assertTenantActive();

        $this->currentTenant->update(['is_active' => false]);
        $this->expectException(\PHPUnit\Framework\ExpectationFailedException::class);
        $this->assertTenantActive();
    }

    public function test_assert_tenant_inactive_status(): void
    {
        $this->setUpTenant(['is_active' => false]);
        $this->assertTenantInactive();

        $this->currentTenant->update(['is_active' => true]);
        $this->expectException(\PHPUnit\Framework\ExpectationFailedException::class);
        $this->assertTenantInactive();
    }

    public function test_get_current_tenant(): void
    {
        $tenant = $this->getCurrentTenant();
        $this->assertInstanceOf(Tenant::class, $tenant);
        $this->assertTrue($tenant->is_active);
    }

    public function test_get_current_tenant_user(): void
    {
        $user = $this->getCurrentTenantUser();
        $this->assertInstanceOf(User::class, $user);
        $this->assertBelongsToCurrentTenant($user);
    }

    public function test_tear_down_tenant(): void
    {
        $this->setUpTenant();
        $this->createTenantUser();
        $this->actingAsTenantUser();

        $this->tearDownTenant();

        $this->assertNull($this->currentTenant);
        $this->assertNull($this->currentUser);
        $this->assertGuest();
    }
}
