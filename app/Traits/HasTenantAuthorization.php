<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Spatie\Permission\Exceptions\RoleDoesNotExist;
use Spatie\Permission\Traits\HasRoles;

trait HasTenantAuthorization
{
    use HasRoles {
        hasPermissionTo as protected baseHasPermissionTo;
        hasRole as protected baseHasRole;
        hasAnyRole as protected baseHasAnyRole;
        hasAllRoles as protected baseHasAllRoles;
    }

    /**
     * Check if the model has a permission, taking tenant into account.
     */
    public function hasPermissionTo(string|Collection $permission, ?string $guardName = null): bool
    {
        if ($this->hasRole(config('permission.super_admin_role'))) {
            return true;
        }

        try {
            return $this->baseHasPermissionTo($permission, $guardName);
        } catch (PermissionDoesNotExist $e) {
            return false;
        }
    }

    /**
     * Check if the model has a role, taking tenant into account.
     */
    public function hasRole(string|Collection $role, ?string $guardName = null): bool
    {
        if (is_string($role) && $role === config('permission.super_admin_role')) {
            return $this->getRoleNames()->contains($role);
        }

        try {
            return $this->baseHasRole($role, $guardName);
        } catch (RoleDoesNotExist $e) {
            return false;
        }
    }

    /**
     * Check if the model has any of the given roles, taking tenant into account.
     */
    public function hasAnyRole(string|array|Collection $roles): bool
    {
        if ($this->hasRole(config('permission.super_admin_role'))) {
            return true;
        }

        try {
            return $this->baseHasAnyRole($roles);
        } catch (RoleDoesNotExist $e) {
            return false;
        }
    }

    /**
     * Check if the model has all of the given roles, taking tenant into account.
     */
    public function hasAllRoles(string|array|Collection $roles, ?string $guardName = null): bool
    {
        if ($this->hasRole(config('permission.super_admin_role'))) {
            return true;
        }

        try {
            return $this->baseHasAllRoles($roles, $guardName);
        } catch (RoleDoesNotExist $e) {
            return false;
        }
    }

    /**
     * Scope the query to models with the given role, taking tenant into account.
     */
    public function scopeRole(Builder $query, string|array|Collection $roles): Builder
    {
        if ($this->hasRole(config('permission.super_admin_role'))) {
            return $query;
        }

        return $query->whereHas('roles', function ($query) use ($roles) {
            $query->where(function ($query) use ($roles) {
                $roleNames = collect($roles)->flatten();
                foreach ($roleNames as $role) {
                    $query->orWhere(function ($query) use ($role) {
                        $query->where('name', $role)
                            ->where(function ($query) {
                                $query->where('is_tenant_role', true)
                                    ->orWhere('name', config('permission.super_admin_role'));
                            });
                    });
                }
            });
        });
    }

    /**
     * Scope the query to models with the given permission, taking tenant into account.
     */
    public function scopePermission(Builder $query, string|array|Collection $permissions): Builder
    {
        if ($this->hasRole(config('permission.super_admin_role'))) {
            return $query;
        }

        return $query->whereHas('permissions', function ($query) use ($permissions) {
            $permissionNames = collect($permissions)->flatten();
            $query->whereIn('name', $permissionNames);
        });
    }

    /**
     * Get all permissions for the model, taking tenant into account.
     */
    public function getAllPermissions(): Collection
    {
        if ($this->hasRole(config('permission.super_admin_role'))) {
            return $this->getPermissionClass()::all();
        }

        return $this->permissions->merge(
            $this->roles->flatMap(function ($role) {
                return $role->permissions;
            })
        )->unique('id');
    }

    /**
     * Get direct permissions for the model, taking tenant into account.
     */
    public function getDirectPermissions(): Collection
    {
        if ($this->hasRole(config('permission.super_admin_role'))) {
            return $this->getPermissionClass()::all();
        }

        return $this->permissions;
    }

    /**
     * Get permissions via roles for the model, taking tenant into account.
     */
    public function getPermissionsViaRoles(): Collection
    {
        if ($this->hasRole(config('permission.super_admin_role'))) {
            return $this->getPermissionClass()::all();
        }

        return $this->roles->flatMap(function ($role) {
            return $role->permissions;
        })->unique('id');
    }

    /**
     * Get all roles for the model, taking tenant into account.
     */
    public function getRoles(): Collection
    {
        if ($this->hasRole(config('permission.super_admin_role'))) {
            return $this->getRoleClass()::all();
        }

        return $this->roles;
    }

    /**
     * Check if the model has any permission from a set, taking tenant into account.
     */
    public function hasAnyPermission(string|array|Collection $permissions): bool
    {
        if ($this->hasRole(config('permission.super_admin_role'))) {
            return true;
        }

        $permissions = collect($permissions)->flatten();

        foreach ($permissions as $permission) {
            if ($this->hasPermissionTo($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the model has all permissions from a set, taking tenant into account.
     */
    public function hasAllPermissions(string|array|Collection $permissions): bool
    {
        if ($this->hasRole(config('permission.super_admin_role'))) {
            return true;
        }

        $permissions = collect($permissions)->flatten();

        foreach ($permissions as $permission) {
            if (!$this->hasPermissionTo($permission)) {
                return false;
            }
        }

        return true;
    }
}
