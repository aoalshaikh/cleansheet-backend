<?php

namespace Tests\Unit\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Tests\Traits\AssertsDatabaseSchema;

class PermissionTablesMigrationTest extends TestCase
{
    use RefreshDatabase, AssertsDatabaseSchema;

    public function test_permissions_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('permissions'));
    }

    public function test_roles_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('roles'));
    }

    public function test_model_has_permissions_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('model_has_permissions'));
    }

    public function test_model_has_roles_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('model_has_roles'));
    }

    public function test_role_has_permissions_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('role_has_permissions'));
    }

    public function test_permissions_table_has_required_columns(): void
    {
        $this->assertTableHasColumns('permissions', [
            'id',
            'name',
            'guard_name',
            'created_at',
            'updated_at',
        ]);

        $this->assertColumnType('permissions', 'id', 'bigint');
        $this->assertColumnType('permissions', 'name', 'varchar');
        $this->assertColumnType('permissions', 'guard_name', 'varchar');
        $this->assertColumnNotNullable('permissions', 'name');
        $this->assertColumnNotNullable('permissions', 'guard_name');
    }

    public function test_roles_table_has_required_columns(): void
    {
        $this->assertTableHasColumns('roles', [
            'id',
            'name',
            'guard_name',
            'is_tenant_role',
            'created_at',
            'updated_at',
        ]);

        $this->assertColumnType('roles', 'id', 'bigint');
        $this->assertColumnType('roles', 'name', 'varchar');
        $this->assertColumnType('roles', 'guard_name', 'varchar');
        $this->assertColumnType('roles', 'is_tenant_role', 'tinyint');
        $this->assertColumnNotNullable('roles', 'name');
        $this->assertColumnNotNullable('roles', 'guard_name');
    }

    public function test_model_has_permissions_table_has_required_columns(): void
    {
        $this->assertTableHasColumns('model_has_permissions', [
            'permission_id',
            'model_type',
            'model_id',
        ]);

        $this->assertColumnType('model_has_permissions', 'permission_id', 'bigint');
        $this->assertColumnType('model_has_permissions', 'model_type', 'varchar');
        $this->assertColumnType('model_has_permissions', 'model_id', 'bigint');
        $this->assertColumnNotNullable('model_has_permissions', 'permission_id');
        $this->assertColumnNotNullable('model_has_permissions', 'model_type');
        $this->assertColumnNotNullable('model_has_permissions', 'model_id');
    }

    public function test_model_has_roles_table_has_required_columns(): void
    {
        $this->assertTableHasColumns('model_has_roles', [
            'role_id',
            'model_type',
            'model_id',
        ]);

        $this->assertColumnType('model_has_roles', 'role_id', 'bigint');
        $this->assertColumnType('model_has_roles', 'model_type', 'varchar');
        $this->assertColumnType('model_has_roles', 'model_id', 'bigint');
        $this->assertColumnNotNullable('model_has_roles', 'role_id');
        $this->assertColumnNotNullable('model_has_roles', 'model_type');
        $this->assertColumnNotNullable('model_has_roles', 'model_id');
    }

    public function test_role_has_permissions_table_has_required_columns(): void
    {
        $this->assertTableHasColumns('role_has_permissions', [
            'permission_id',
            'role_id',
        ]);

        $this->assertColumnType('role_has_permissions', 'permission_id', 'bigint');
        $this->assertColumnType('role_has_permissions', 'role_id', 'bigint');
        $this->assertColumnNotNullable('role_has_permissions', 'permission_id');
        $this->assertColumnNotNullable('role_has_permissions', 'role_id');
    }

    public function test_permissions_table_has_unique_name_guard_index(): void
    {
        $this->assertTableHasIndex('permissions', 'permissions_name_guard_name_unique');
    }

    public function test_roles_table_has_unique_name_guard_index(): void
    {
        $this->assertTableHasIndex('roles', 'roles_name_guard_name_unique');
    }

    public function test_model_has_permissions_table_has_primary_key(): void
    {
        $this->assertTableHasIndex('model_has_permissions', 'model_has_permissions_permission_model_type_primary');
    }

    public function test_model_has_roles_table_has_primary_key(): void
    {
        $this->assertTableHasIndex('model_has_roles', 'model_has_roles_role_model_type_primary');
    }

    public function test_role_has_permissions_table_has_primary_key(): void
    {
        $this->assertTableHasIndex('role_has_permissions', 'role_has_permissions_permission_id_role_id_primary');
    }

    public function test_model_has_permissions_table_has_foreign_key(): void
    {
        $this->assertTableHasForeignKey('model_has_permissions', 'permission_id');
    }

    public function test_model_has_roles_table_has_foreign_key(): void
    {
        $this->assertTableHasForeignKey('model_has_roles', 'role_id');
    }

    public function test_role_has_permissions_table_has_foreign_keys(): void
    {
        $this->assertTableHasForeignKeys('role_has_permissions', [
            'permission_id',
            'role_id',
        ]);
    }
}
