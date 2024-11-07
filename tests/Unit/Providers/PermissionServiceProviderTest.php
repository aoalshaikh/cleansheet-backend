<?php

namespace Tests\Unit\Providers;

use App\Http\Middleware\TenantAwarePermissions;
use App\Models\Tenant;
use App\Models\User;
use App\Providers\PermissionServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Tests\Traits\InteractsWithRoles;

class PermissionServiceProviderTest extends TestCase
{
    use RefreshDatabase, InteractsWithRoles;

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

    public function test_registers_middleware_aliases(): void
    {
        $router = $this->app['router'];

        $this->assertEquals(
            TenantAwarePermissions::class,
            $router->getMiddleware()['permission']
        );

        $this->assertEquals(
            TenantAwarePermissions::class,
            $router->getMiddleware()['role']
        );
    }

    public function test_registers_super_admin_gate_check(): void
    {
        $this->user->assignRole('super-admin');
        Auth::login($this->user);

        $this->assertTrue(Gate::check('any-ability'));
    }

    public function test_adds_tenant_scope_to_roles(): void
    {
        // Create roles for different tenants
        $this->createRole('test-role', [], true);
        $otherTenant = Tenant::factory()->create();
        $this->actingAs($this->user);

        // Should only see roles for current tenant
        $roles = Role::all();
        foreach ($roles as $role) {
            $this->assertTrue(
                $role->is_tenant_role || $role->name === config('permission.super_admin_role')
            );
        }
    }

    public function test_registers_blade_directives(): void
    {
        $this->assertArrayHasKey('tenantRole', Blade::getCustomDirectives());
        $this->assertArrayHasKey('endTenantRole', Blade::getCustomDirectives());
        $this->assertArrayHasKey('tenantPermission', Blade::getCustomDirectives());
        $this->assertArrayHasKey('endTenantPermission', Blade::getCustomDirectives());
        $this->assertArrayHasKey('anyTenantRole', Blade::getCustomDirectives());
        $this->assertArrayHasKey('endAnyTenantRole', Blade::getCustomDirectives());
        $this->assertArrayHasKey('allTenantRoles', Blade::getCustomDirectives());
        $this->assertArrayHasKey('endAllTenantRoles', Blade::getCustomDirectives());
        $this->assertArrayHasKey('anyTenantPermission', Blade::getCustomDirectives());
        $this->assertArrayHasKey('endAnyTenantPermission', Blade::getCustomDirectives());
        $this->assertArrayHasKey('allTenantPermissions', Blade::getCustomDirectives());
        $this->assertArrayHasKey('endAllTenantPermissions', Blade::getCustomDirectives());
        $this->assertArrayHasKey('unscopedRole', Blade::getCustomDirectives());
        $this->assertArrayHasKey('endUnscopedRole', Blade::getCustomDirectives());
        $this->assertArrayHasKey('unscopedPermission', Blade::getCustomDirectives());
        $this->assertArrayHasKey('endUnscopedPermission', Blade::getCustomDirectives());
    }

    public function test_blade_directives_generate_correct_php(): void
    {
        $directives = [
            '@tenantRole' => 'hasRole',
            '@tenantPermission' => 'hasPermissionTo',
            '@anyTenantRole' => 'hasAnyRole',
            '@allTenantRoles' => 'hasAllRoles',
            '@anyTenantPermission' => 'hasAnyPermission',
            '@allTenantPermissions' => 'hasAllPermissions',
            '@unscopedRole' => 'hasRole',
            '@unscopedPermission' => 'hasPermissionTo',
        ];

        foreach ($directives as $directive => $method) {
            $compiled = Blade::compileString("{$directive}('test')");
            $this->assertStringContainsString(
                "auth()->check() && auth()->user()->{$method}",
                $compiled
            );
        }
    }

    public function test_clears_permission_cache_on_tenant_switch(): void
    {
        $registrar = $this->app->make(\Spatie\Permission\PermissionRegistrar::class);
        $registrar->registerPermissions();

        // Switch tenant
        event('tenant.switched');

        // Cache should be cleared
        $this->assertEmpty($registrar->getPermissions());
    }

    public function test_provider_is_registered(): void
    {
        $this->assertTrue(
            $this->app->providerIsLoaded(PermissionServiceProvider::class)
        );
    }

    public function test_permission_registrar_is_singleton(): void
    {
        $registrar1 = $this->app->make(\Spatie\Permission\PermissionRegistrar::class);
        $registrar2 = $this->app->make(\Spatie\Permission\PermissionRegistrar::class);

        $this->assertSame($registrar1, $registrar2);
    }

    public function test_super_admin_bypass_works(): void
    {
        $this->user->assignRole('super-admin');
        Auth::login($this->user);

        $this->assertTrue(Gate::check('any-permission'));
        $this->assertTrue(Gate::check('any-role'));
    }

    public function test_tenant_scope_respects_super_admin(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super-admin');
        Auth::login($superAdmin);

        // Super admin should see all roles
        $roles = Role::all();
        $this->assertGreaterThan(0, $roles->count());
        $this->assertContains(false, $roles->pluck('is_tenant_role')->unique()->toArray());
    }

    public function test_tenant_scope_applies_to_regular_users(): void
    {
        $this->user->assignRole('tenant-user');
        Auth::login($this->user);

        // Regular user should only see tenant roles
        $roles = Role::all();
        $this->assertGreaterThan(0, $roles->count());
        $this->assertNotContains(false, $roles->pluck('is_tenant_role')->unique()->toArray());
    }
}
