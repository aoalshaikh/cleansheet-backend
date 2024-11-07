<?php

namespace Tests\Unit\Seeders;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Tests\Traits\InteractsWithRoles;

class RolesAndPermissionsSeederTest extends TestCase
{
    use RefreshDatabase, InteractsWithRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_creates_all_permissions(): void
    {
        $expectedPermissions = [
            // User management
            'view users',
            'create users',
            'edit users',
            'delete users',
            'manage users',

            // Role management
            'view roles',
            'create roles',
            'edit roles',
            'delete roles',
            'manage roles',

            // Permission management
            'view permissions',
            'create permissions',
            'edit permissions',
            'delete permissions',
            'manage permissions',

            // Tenant management
            'view tenants',
            'create tenants',
            'edit tenants',
            'delete tenants',
            'manage tenants',

            // Profile management
            'edit profile',
            'change password',
            'manage preferences',

            // Activity log
            'view activity log',
            'manage activity log',

            // Settings
            'view settings',
            'manage settings',

            // Backups
            'view backups',
            'create backups',
            'download backups',
            'delete backups',
            'manage backups',

            // Reports
            'view reports',
            'create reports',
            'export reports',
            'manage reports',

            // API
            'access api',
            'manage api tokens',
        ];

        foreach ($expectedPermissions as $permission) {
            $this->assertPermissionExists($permission);
        }
    }

    public function test_creates_all_roles(): void
    {
        $expectedRoles = [
            'super-admin',
            'admin',
            'manager',
            'user',
            'tenant-admin',
            'tenant-manager',
            'tenant-user',
        ];

        foreach ($expectedRoles as $role) {
            $this->assertRoleExists($role);
        }
    }

    public function test_super_admin_has_all_permissions(): void
    {
        $superAdmin = Role::findByName('super-admin');
        $allPermissions = Permission::all();

        foreach ($allPermissions as $permission) {
            $this->assertRoleHasPermission($superAdmin, $permission->name);
        }
    }

    public function test_admin_has_correct_permissions(): void
    {
        $admin = Role::findByName('admin');
        $expectedPermissions = [
            'manage users',
            'manage roles',
            'manage permissions',
            'manage tenants',
            'manage activity log',
            'manage settings',
            'manage backups',
            'manage reports',
            'manage api tokens',
        ];

        $this->assertRoleHasPermissions($admin, $expectedPermissions);
    }

    public function test_manager_has_correct_permissions(): void
    {
        $manager = Role::findByName('manager');
        $expectedPermissions = [
            'view users',
            'create users',
            'edit users',
            'view roles',
            'view permissions',
            'view activity log',
            'view settings',
            'view reports',
            'access api',
        ];

        $this->assertRoleHasPermissions($manager, $expectedPermissions);
    }

    public function test_user_has_correct_permissions(): void
    {
        $user = Role::findByName('user');
        $expectedPermissions = [
            'edit profile',
            'change password',
            'manage preferences',
            'access api',
        ];

        $this->assertRoleHasPermissions($user, $expectedPermissions);
    }

    public function test_tenant_admin_has_correct_permissions(): void
    {
        $tenantAdmin = Role::findByName('tenant-admin');
        $expectedPermissions = [
            'manage users',
            'manage roles',
            'view activity log',
            'manage settings',
            'manage reports',
        ];

        $this->assertRoleHasPermissions($tenantAdmin, $expectedPermissions);
    }

    public function test_tenant_manager_has_correct_permissions(): void
    {
        $tenantManager = Role::findByName('tenant-manager');
        $expectedPermissions = [
            'view users',
            'edit users',
            'view roles',
            'view activity log',
            'view settings',
            'view reports',
        ];

        $this->assertRoleHasPermissions($tenantManager, $expectedPermissions);
    }

    public function test_tenant_user_has_correct_permissions(): void
    {
        $tenantUser = Role::findByName('tenant-user');
        $expectedPermissions = [
            'edit profile',
            'change password',
            'manage preferences',
        ];

        $this->assertRoleHasPermissions($tenantUser, $expectedPermissions);
    }

    public function test_tenant_roles_are_marked_as_tenant_roles(): void
    {
        $tenantRoles = Role::where('is_tenant_role', true)->get();
        $expectedTenantRoles = ['tenant-admin', 'tenant-manager', 'tenant-user'];

        $this->assertCount(count($expectedTenantRoles), $tenantRoles);
        foreach ($expectedTenantRoles as $roleName) {
            $this->assertTrue(
                $tenantRoles->contains('name', $roleName),
                "Role {$roleName} should be marked as tenant role"
            );
        }
    }

    public function test_non_tenant_roles_are_not_marked_as_tenant_roles(): void
    {
        $nonTenantRoles = Role::where('is_tenant_role', false)->get();
        $expectedNonTenantRoles = ['super-admin', 'admin', 'manager', 'user'];

        $this->assertCount(count($expectedNonTenantRoles), $nonTenantRoles);
        foreach ($expectedNonTenantRoles as $roleName) {
            $this->assertTrue(
                $nonTenantRoles->contains('name', $roleName),
                "Role {$roleName} should not be marked as tenant role"
            );
        }
    }
}
