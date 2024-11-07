<?php

namespace Tests\Traits;

use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

trait InteractsWithRoles
{
    /**
     * Create a new role.
     */
    protected function createRole(string $name, array $permissions = []): Role
    {
        $role = Role::create(['name' => $name]);

        if (!empty($permissions)) {
            $role->givePermissionTo($permissions);
        }

        return $role;
    }

    /**
     * Create multiple roles.
     *
     * @param array<string> $names
     * @return array<Role>
     */
    protected function createRoles(array $names): array
    {
        return array_map(fn($name) => $this->createRole($name), $names);
    }

    /**
     * Create a new permission.
     */
    protected function createPermission(string $name): Permission
    {
        return Permission::create(['name' => $name]);
    }

    /**
     * Create multiple permissions.
     *
     * @param array<string> $names
     * @return array<Permission>
     */
    protected function createPermissions(array $names): array
    {
        return array_map(fn($name) => $this->createPermission($name), $names);
    }

    /**
     * Assign roles to a model.
     *
     * @param Model $model
     * @param array<string>|string $roles
     */
    protected function assignRoles(Model $model, array|string $roles): void
    {
        $model->assignRole($roles);
    }

    /**
     * Give permissions to a model.
     *
     * @param Model $model
     * @param array<string>|string $permissions
     */
    protected function givePermissions(Model $model, array|string $permissions): void
    {
        $model->givePermissionTo($permissions);
    }

    /**
     * Assert model has role.
     */
    protected function assertHasRole(Model $model, string $role): void
    {
        $this->assertTrue(
            $model->hasRole($role),
            "Failed asserting that model has role '{$role}'."
        );
    }

    /**
     * Assert model has roles.
     *
     * @param array<string> $roles
     */
    protected function assertHasRoles(Model $model, array $roles): void
    {
        foreach ($roles as $role) {
            $this->assertHasRole($model, $role);
        }
    }

    /**
     * Assert model has permission.
     */
    protected function assertHasPermission(Model $model, string $permission): void
    {
        $this->assertTrue(
            $model->hasPermissionTo($permission),
            "Failed asserting that model has permission '{$permission}'."
        );
    }

    /**
     * Assert model has permissions.
     *
     * @param array<string> $permissions
     */
    protected function assertHasPermissions(Model $model, array $permissions): void
    {
        foreach ($permissions as $permission) {
            $this->assertHasPermission($model, $permission);
        }
    }

    /**
     * Assert model does not have role.
     */
    protected function assertDoesNotHaveRole(Model $model, string $role): void
    {
        $this->assertFalse(
            $model->hasRole($role),
            "Failed asserting that model does not have role '{$role}'."
        );
    }

    /**
     * Assert model does not have roles.
     *
     * @param array<string> $roles
     */
    protected function assertDoesNotHaveRoles(Model $model, array $roles): void
    {
        foreach ($roles as $role) {
            $this->assertDoesNotHaveRole($model, $role);
        }
    }

    /**
     * Assert model does not have permission.
     */
    protected function assertDoesNotHavePermission(Model $model, string $permission): void
    {
        $this->assertFalse(
            $model->hasPermissionTo($permission),
            "Failed asserting that model does not have permission '{$permission}'."
        );
    }

    /**
     * Assert model does not have permissions.
     *
     * @param array<string> $permissions
     */
    protected function assertDoesNotHavePermissions(Model $model, array $permissions): void
    {
        foreach ($permissions as $permission) {
            $this->assertDoesNotHavePermission($model, $permission);
        }
    }

    /**
     * Assert role exists.
     */
    protected function assertRoleExists(string $role): void
    {
        $this->assertDatabaseHas('roles', ['name' => $role]);
    }

    /**
     * Assert permission exists.
     */
    protected function assertPermissionExists(string $permission): void
    {
        $this->assertDatabaseHas('permissions', ['name' => $permission]);
    }

    /**
     * Assert role has permission.
     */
    protected function assertRoleHasPermission(Role $role, string $permission): void
    {
        $this->assertTrue(
            $role->hasPermissionTo($permission),
            "Failed asserting that role has permission '{$permission}'."
        );
    }

    /**
     * Assert role has permissions.
     *
     * @param array<string> $permissions
     */
    protected function assertRoleHasPermissions(Role $role, array $permissions): void
    {
        foreach ($permissions as $permission) {
            $this->assertRoleHasPermission($role, $permission);
        }
    }

    /**
     * Create default roles and permissions for testing.
     */
    protected function setupRolesAndPermissions(): void
    {
        // Create default roles
        $this->createRole('admin', [
            'manage users',
            'manage roles',
            'manage permissions',
            'manage tenants',
        ]);

        $this->createRole('manager', [
            'manage users',
            'manage roles',
        ]);

        $this->createRole('user', [
            'edit profile',
            'change password',
        ]);

        // Create additional permissions
        $this->createPermissions([
            'view activity log',
            'manage settings',
            'manage backups',
            'view reports',
            'export data',
        ]);
    }
}
