<?php

namespace Tests\Unit\Providers;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;
use Tests\Traits\InteractsWithAuthentication;
use Tests\Traits\InteractsWithRoles;

class AuthServiceProviderTest extends TestCase
{
    use RefreshDatabase, InteractsWithRoles, InteractsWithAuthentication;

    private User $user;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()
            ->forTenant($this->tenant)
            ->create();

        $this->setupRolesAndPermissions();
    }

    public function test_super_admin_bypasses_authorization(): void
    {
        $this->createRole('super-admin');
        $this->user->assignRole('super-admin');
        $this->actingAsUser($this->user);

        // Test arbitrary ability
        $this->assertTrue(Gate::allows('any-ability'));
        $this->assertTrue(Gate::allows('non-existent-ability'));
    }

    public function test_regular_user_does_not_bypass_authorization(): void
    {
        $this->actingAsUser($this->user);

        // Define a gate
        Gate::define('test-ability', fn() => false);

        // Test the gate
        $this->assertFalse(Gate::allows('test-ability'));
    }

    public function test_guest_does_not_bypass_authorization(): void
    {
        $this->actingAsGuest();

        // Define a gate
        Gate::define('test-ability', fn() => false);

        // Test the gate
        $this->assertFalse(Gate::allows('test-ability'));
    }

    public function test_super_admin_role_from_config(): void
    {
        $superAdminRole = config('permission.super_admin_role');
        $this->assertNotEmpty($superAdminRole);

        $this->createRole($superAdminRole);
        $this->user->assignRole($superAdminRole);
        $this->actingAsUser($this->user);

        $this->assertTrue(Gate::allows('any-ability'));
    }

    public function test_multiple_super_admins(): void
    {
        $this->createRole('super-admin');

        // Create multiple super admins
        $superAdmin1 = $this->user;
        $superAdmin2 = User::factory()->create();

        $superAdmin1->assignRole('super-admin');
        $superAdmin2->assignRole('super-admin');

        // Test first super admin
        $this->actingAsUser($superAdmin1);
        $this->assertTrue(Gate::allows('any-ability'));

        // Test second super admin
        $this->actingAsUser($superAdmin2);
        $this->assertTrue(Gate::allows('any-ability'));
    }

    public function test_super_admin_with_tenant(): void
    {
        $this->createRole('super-admin');
        $this->user->assignRole('super-admin');
        $this->actingAsUser($this->user);

        // Even with tenant, super admin should bypass authorization
        $this->assertTrue(Gate::allows('any-ability'));
    }

    public function test_super_admin_without_tenant(): void
    {
        $userWithoutTenant = User::factory()
            ->create(['tenant_id' => null]);
        
        $this->createRole('super-admin');
        $userWithoutTenant->assignRole('super-admin');
        $this->actingAsUser($userWithoutTenant);

        // Without tenant, super admin should still bypass authorization
        $this->assertTrue(Gate::allows('any-ability'));
    }

    public function test_gate_before_callback(): void
    {
        $this->createRole('super-admin');
        $this->user->assignRole('super-admin');
        $this->actingAsUser($this->user);

        // Define a gate that would normally return false
        Gate::define('test-ability', fn() => false);

        // Super admin should bypass the gate
        $this->assertTrue(Gate::allows('test-ability'));
    }

    public function test_gate_after_callback(): void
    {
        $this->actingAsUser($this->user);

        // Define a gate with after callback
        Gate::define('test-ability', fn() => true);
        Gate::after(function ($user, $ability, $result) {
            return $result;
        });

        // Test the gate
        $this->assertTrue(Gate::allows('test-ability'));
    }

    public function test_policies_registration(): void
    {
        $provider = new \App\Providers\AuthServiceProvider($this->app);
        $policies = $provider->policies();

        $this->assertIsArray($policies);
    }

    public function test_provider_is_registered(): void
    {
        $this->assertTrue(
            $this->app->providerIsLoaded(\App\Providers\AuthServiceProvider::class)
        );
    }
}
