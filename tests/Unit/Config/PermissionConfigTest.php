<?php

namespace Tests\Unit\Config;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PermissionConfigTest extends TestCase
{
    public function test_permission_models_are_configured(): void
    {
        $this->assertEquals(
            Permission::class,
            config('permission.models.permission')
        );

        $this->assertEquals(
            Role::class,
            config('permission.models.role')
        );
    }

    public function test_permission_tables_are_configured(): void
    {
        $tables = config('permission.table_names');

        $this->assertEquals('roles', $tables['roles']);
        $this->assertEquals('permissions', $tables['permissions']);
        $this->assertEquals('model_has_permissions', $tables['model_has_permissions']);
        $this->assertEquals('model_has_roles', $tables['model_has_roles']);
        $this->assertEquals('role_has_permissions', $tables['role_has_permissions']);
    }

    public function test_column_names_are_configured(): void
    {
        $columns = config('permission.column_names');

        $this->assertNull($columns['role_pivot_key']);
        $this->assertNull($columns['permission_pivot_key']);
        $this->assertEquals('model_id', $columns['model_morph_key']);
        $this->assertEquals('tenant_id', $columns['team_foreign_key']);
    }

    public function test_teams_feature_is_enabled(): void
    {
        $this->assertTrue(config('permission.teams'));
    }

    public function test_tenant_aware_feature_is_enabled(): void
    {
        $this->assertTrue(config('permission.tenant_aware'));
    }

    public function test_super_admin_role_is_configured(): void
    {
        $this->assertEquals(
            'super-admin',
            config('permission.super_admin_role')
        );
    }

    public function test_permission_check_method_is_registered(): void
    {
        $this->assertTrue(config('permission.register_permission_check_method'));
    }

    public function test_super_admin_gate_check_is_registered(): void
    {
        $this->assertTrue(config('permission.register_super_admin_gate_check'));
    }

    public function test_wildcard_permission_is_disabled(): void
    {
        $this->assertFalse(config('permission.enable_wildcard_permission'));
    }

    public function test_cache_is_configured(): void
    {
        $cache = config('permission.cache');

        $this->assertInstanceOf(\DateInterval::class, $cache['expiration_time']);
        $this->assertEquals('24 hours', $cache['expiration_time']->format('%h hours'));
        $this->assertEquals('spatie.permission.cache', $cache['key']);
        $this->assertEquals('default', $cache['store']);
    }

    public function test_exception_messages_are_configured(): void
    {
        $this->assertFalse(config('permission.display_permission_in_exception'));
        $this->assertFalse(config('permission.display_role_in_exception'));
    }

    public function test_all_required_config_keys_exist(): void
    {
        $requiredKeys = [
            'models',
            'table_names',
            'column_names',
            'teams',
            'tenant_aware',
            'register_permission_check_method',
            'register_super_admin_gate_check',
            'super_admin_role',
            'enable_wildcard_permission',
            'cache',
        ];

        foreach ($requiredKeys as $key) {
            $this->assertNotNull(
                config("permission.{$key}"),
                "Missing required config key: {$key}"
            );
        }
    }

    public function test_model_config_has_required_keys(): void
    {
        $models = config('permission.models');

        $this->assertArrayHasKey('permission', $models);
        $this->assertArrayHasKey('role', $models);
    }

    public function test_table_names_config_has_required_keys(): void
    {
        $tables = config('permission.table_names');

        $this->assertArrayHasKey('roles', $tables);
        $this->assertArrayHasKey('permissions', $tables);
        $this->assertArrayHasKey('model_has_permissions', $tables);
        $this->assertArrayHasKey('model_has_roles', $tables);
        $this->assertArrayHasKey('role_has_permissions', $tables);
    }

    public function test_column_names_config_has_required_keys(): void
    {
        $columns = config('permission.column_names');

        $this->assertArrayHasKey('role_pivot_key', $columns);
        $this->assertArrayHasKey('permission_pivot_key', $columns);
        $this->assertArrayHasKey('model_morph_key', $columns);
        $this->assertArrayHasKey('team_foreign_key', $columns);
    }

    public function test_cache_config_has_required_keys(): void
    {
        $cache = config('permission.cache');

        $this->assertArrayHasKey('expiration_time', $cache);
        $this->assertArrayHasKey('key', $cache);
        $this->assertArrayHasKey('store', $cache);
    }
}
