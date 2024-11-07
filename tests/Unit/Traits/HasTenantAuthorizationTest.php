<?php

namespace Tests\Unit\Traits;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Tests\Traits\InteractsWithRoles;

class HasTenantAuthorizationTest extends TestCase
{
    use RefreshDatabase, InteractsWithRoles;

    private User $user;
    private Tenant $tenant;
    private Tenant $otherTenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->otherTenant = Tenant::factory()->create();
        $this->user = User::factory()
            ->forTenant($this->tenant)
            ->create();

        $this->setupRolesAndPermissions();
    }

    public function test_super_admin_has_all_permissions(): void
    {
        $this->user->assignRole('super-admin');

        $permission = Permission::create(['name' => 'test-permission']);

        $this->assertTrue($this->user->hasPermissionTo('test-permission'));
        $this->assertTrue($this->user->hasAnyPermission(['test-permission', 'non-existent']));
        $this->assertTrue($this->user->hasAllPermissions(['test-permission']));
    }

    public function test_tenant_user_has_scoped_permissions(): void
    {
        // Create permissions for both tenants
        $permission = Permission::create(['name' => 'test-permission']);
        
        // Create users for both tenants
        $otherUser = User::factory()
            ->forTenant($this->otherTenant)
            ->create();

        // Assign same permission to both users
        $this->user->givePermissionTo($permission);
        $otherUser->givePermissionTo($permission);

        // Each user should only see their tenant's permissions
        $this->assertTrue($this->user->hasPermissionTo('test-permission'));
        $this->assertTrue($otherUser->hasPermissionTo('test-permission'));

        // But they should have different permission instances
        $this->assertNotEquals(
            $this->user->permissions->first()->id,
            $otherUser->permissions->first()->id
        );
    }

    public function test_tenant_user_has_scoped_roles(): void
    {
        // Create roles for both tenants
        $role = Role::create(['name' => 'test-role', 'is_tenant_role' => true]);
        
        // Create users for both tenants
        $otherUser = User::factory()
            ->forTenant($this->otherTenant)
            ->create();

        // Assign same role to both users
        $this->user->assignRole($role);
        $otherUser->assignRole($role);

        // Each user should only see their tenant's roles
        $this->assertTrue($this->user->hasRole('test-role'));
        $this->assertTrue($otherUser->hasRole('test-role'));

        // But they should have different role instances
        $this->assertNotEquals(
            $this->user->roles->first()->id,
            $otherUser->roles->first()->id
        );
    }

    public function test_tenant_user_cannot_access_other_tenant_permissions(): void
    {
        $permission = Permission::create(['name' => 'test-permission']);
        
        $otherUser = User::factory()
            ->forTenant($this->otherTenant)
            ->create();
        $otherUser->givePermissionTo($permission);

        $this->assertFalse($this->user->hasPermissionTo('test-permission'));
    }

    public function test_tenant_user_cannot_access_other_tenant_roles(): void
    {
        $role = Role::create(['name' => 'test-role', 'is_tenant_role' => true]);
        
        $otherUser = User::factory()
            ->forTenant($this->otherTenant)
            ->create();
        $otherUser->assignRole($role);

        $this->assertFalse($this->user->hasRole('test-role'));
    }

    public function test_can_check_multiple_permissions(): void
    {
        $permissions = collect(['permission1', 'permission2', 'permission3'])
            ->map(fn($name) => Permission::create(['name' => $name]));

        $this->user->givePermissionTo($permissions->take(2));

        $this->assertTrue($this->user->hasAnyPermission(['permission1', 'permission2']));
        $this->assertFalse($this->user->hasAllPermissions(['permission1', 'permission2', 'permission3']));
    }

    public function test_can_check_multiple_roles(): void
    {
        $roles = collect(['role1', 'role2', 'role3'])
            ->map(fn($name) => Role::create(['name' => $name, 'is_tenant_role' => true]));

        $this->user->assignRole($roles->take(2));

        $this->assertTrue($this->user->hasAnyRole(['role1', 'role2']));
        $this->assertFalse($this->user->hasAllRoles(['role1', 'role2', 'role3']));
    }

    public function test_scope_queries_by_role(): void
    {
        $role = Role::create(['name' => 'test-role', 'is_tenant_role' => true]);
        $this->user->assignRole($role);

        $result = User::role('test-role')->get();

        $this->assertTrue($result->contains($this->user));
        $this->assertCount(1, $result);
    }

    public function test_scope_queries_by_permission(): void
    {
        $permission = Permission::create(['name' => 'test-permission']);
        $this->user->givePermissionTo($permission);

        $result = User::permission('test-permission')->get();

        $this->assertTrue($result->contains($this->user));
        $this->assertCount(1, $result);
    }

    public function test_get_all_permissions(): void
    {
        $directPermission = Permission::create(['name' => 'direct-permission']);
        $rolePermission = Permission::create(['name' => 'role-permission']);
        
        $role = Role::create(['name' => 'test-role', 'is_tenant_role' => true]);
        $role->givePermissionTo($rolePermission);
        
        $this->user->givePermissionTo($directPermission);
        $this->user->assignRole($role);

        $permissions = $this->user->getAllPermissions();

        $this->assertTrue($permissions->contains('name', 'direct-permission'));
        $this->assertTrue($permissions->contains('name', 'role-permission'));
    }

    public function test_get_direct_permissions(): void
    {
        $directPermission = Permission::create(['name' => 'direct-permission']);
        $rolePermission = Permission::create(['name' => 'role-permission']);
        
        $role = Role::create(['name' => 'test-role', 'is_tenant_role' => true]);
        $role->givePermissionTo($rolePermission);
        
        $this->user->givePermissionTo($directPermission);
        $this->user->assignRole($role);

        $permissions = $this->user->getDirectPermissions();

        $this->assertTrue($permissions->contains('name', 'direct-permission'));
        $this->assertFalse($permissions->contains('name', 'role-permission'));
    }

    public function test_get_permissions_via_roles(): void
    {
        $directPermission = Permission::create(['name' => 'direct-permission']);
        $rolePermission = Permission::create(['name' => 'role-permission']);
        
        $role = Role::create(['name' => 'test-role', 'is_tenant_role' => true]);
        $role->givePermissionTo($rolePermission);
        
        $this->user->givePermissionTo($directPermission);
        $this->user->assignRole($role);

        $permissions = $this->user->getPermissionsViaRoles();

        $this->assertFalse($permissions->contains('name', 'direct-permission'));
        $this->assertTrue($permissions->contains('name', 'role-permission'));
    }
}
