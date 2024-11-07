<?php

namespace Tests\Unit\Migrations;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\AssertsDatabaseSchema;

class TenantMigrationTest extends TestCase
{
    use RefreshDatabase, AssertsDatabaseSchema;

    public function test_tenants_table_schema(): void
    {
        $this->assertTableExists('tenants');

        // Check columns
        $this->assertTableHasColumns('tenants', [
            'id',
            'name',
            'domain',
            'domains',
            'settings',
            'is_active',
            'created_at',
            'updated_at',
            'deleted_at',
        ]);

        // Check column types
        $this->assertColumnType('tenants', 'id', 'bigint');
        $this->assertColumnType('tenants', 'name', 'string');
        $this->assertColumnType('tenants', 'domain', 'string');
        $this->assertColumnType('tenants', 'domains', 'json');
        $this->assertColumnType('tenants', 'settings', 'json');
        $this->assertColumnType('tenants', 'is_active', 'boolean');

        // Check nullable columns
        $this->assertColumnNotNullable('tenants', 'name');
        $this->assertColumnNullable('tenants', 'domain');
        $this->assertColumnNullable('tenants', 'domains');
        $this->assertColumnNullable('tenants', 'settings');
        $this->assertColumnNotNullable('tenants', 'is_active');

        // Check defaults
        $this->assertColumnHasDefault('tenants', 'is_active', true);

        // Check indexes
        $this->assertTableHasIndex('tenants', ['is_active']);
        $this->assertTableHasIndex('tenants', ['created_at']);
        $this->assertTableHasIndex('tenants', ['deleted_at']);
    }

    public function test_users_table_tenant_relationship(): void
    {
        $this->assertColumnExists('users', 'tenant_id');
        $this->assertColumnNullable('users', 'tenant_id');
        $this->assertTableHasIndex('users', ['tenant_id']);
        $this->assertTableHasForeignKey('users', 'tenant_id', 'tenants');
    }

    public function test_activity_log_tenant_relationship(): void
    {
        $this->assertColumnExists('activity_log', 'tenant_id');
        $this->assertColumnNullable('activity_log', 'tenant_id');
        $this->assertTableHasIndex('activity_log', ['tenant_id', 'created_at']);
        $this->assertTableHasForeignKey('activity_log', 'tenant_id', 'tenants');
    }

    public function test_personal_access_tokens_tenant_relationship(): void
    {
        $this->assertColumnExists('personal_access_tokens', 'tenant_id');
        $this->assertColumnNullable('personal_access_tokens', 'tenant_id');
        $this->assertTableHasIndex('personal_access_tokens', ['tenant_id']);
        $this->assertTableHasForeignKey('personal_access_tokens', 'tenant_id', 'tenants');
    }

    public function test_model_has_roles_tenant_relationship(): void
    {
        $this->assertColumnExists('model_has_roles', 'tenant_id');
        $this->assertColumnNullable('model_has_roles', 'tenant_id');
        $this->assertTableHasIndex('model_has_roles', ['tenant_id']);
        $this->assertTableHasForeignKey('model_has_roles', 'tenant_id', 'tenants');
    }

    public function test_model_has_permissions_tenant_relationship(): void
    {
        $this->assertColumnExists('model_has_permissions', 'tenant_id');
        $this->assertColumnNullable('model_has_permissions', 'tenant_id');
        $this->assertTableHasIndex('model_has_permissions', ['tenant_id']);
        $this->assertTableHasForeignKey('model_has_permissions', 'tenant_id', 'tenants');
    }

    public function test_jobs_tenant_relationship(): void
    {
        $this->assertColumnExists('jobs', 'tenant_id');
        $this->assertColumnNullable('jobs', 'tenant_id');
        $this->assertTableHasIndex('jobs', ['tenant_id', 'queue']);
        $this->assertTableHasForeignKey('jobs', 'tenant_id', 'tenants');
    }

    public function test_failed_jobs_tenant_relationship(): void
    {
        $this->assertColumnExists('failed_jobs', 'tenant_id');
        $this->assertColumnNullable('failed_jobs', 'tenant_id');
        $this->assertTableHasIndex('failed_jobs', ['tenant_id']);
        $this->assertTableHasForeignKey('failed_jobs', 'tenant_id', 'tenants');
    }

    public function test_notifications_tenant_relationship(): void
    {
        $this->assertColumnExists('notifications', 'tenant_id');
        $this->assertColumnNullable('notifications', 'tenant_id');
        $this->assertTableHasIndex('notifications', ['tenant_id', 'created_at']);
        $this->assertTableHasForeignKey('notifications', 'tenant_id', 'tenants');
    }

    public function test_cache_tenant_relationship(): void
    {
        $this->assertColumnExists('cache', 'tenant_id');
        $this->assertColumnNullable('cache', 'tenant_id');
        $this->assertTableHasIndex('cache', ['tenant_id', 'key']);
    }

    public function test_sessions_tenant_relationship(): void
    {
        $this->assertColumnExists('sessions', 'tenant_id');
        $this->assertColumnNullable('sessions', 'tenant_id');
        $this->assertTableHasIndex('sessions', ['tenant_id']);
    }

    public function test_cascade_deletes(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->for($tenant)->create();

        // Test soft delete
        $tenant->delete();
        $this->assertSoftDeleted('tenants', ['id' => $tenant->id]);
        $this->assertDatabaseHas('users', ['id' => $user->id]);

        // Test force delete
        $tenant->forceDelete();
        $this->assertDatabaseMissing('tenants', ['id' => $tenant->id]);
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_unique_domain_constraint(): void
    {
        Tenant::factory()->create(['domain' => 'test.example.com']);

        $this->expectException(\Illuminate\Database\QueryException::class);
        Tenant::factory()->create(['domain' => 'test.example.com']);
    }

    public function test_json_columns(): void
    {
        $tenant = Tenant::factory()->create([
            'domains' => ['test1.example.com', 'test2.example.com'],
            'settings' => [
                'features' => ['test_feature' => true],
                'capabilities' => ['max_users' => 5],
            ],
        ]);

        $this->assertDatabaseHas('tenants', ['id' => $tenant->id]);
        $this->assertIsArray($tenant->refresh()->domains);
        $this->assertIsArray($tenant->refresh()->settings);
    }

    public function test_migration_rollback(): void
    {
        // Roll back the migration
        $this->artisan('migrate:rollback');

        // Assert tables don't exist
        $this->assertTableDoesNotExist('tenants');
        $this->assertColumnDoesNotExist('users', 'tenant_id');
        $this->assertColumnDoesNotExist('activity_log', 'tenant_id');
        $this->assertColumnDoesNotExist('personal_access_tokens', 'tenant_id');
        $this->assertColumnDoesNotExist('model_has_roles', 'tenant_id');
        $this->assertColumnDoesNotExist('model_has_permissions', 'tenant_id');
        $this->assertColumnDoesNotExist('jobs', 'tenant_id');
        $this->assertColumnDoesNotExist('failed_jobs', 'tenant_id');
        $this->assertColumnDoesNotExist('notifications', 'tenant_id');
        $this->assertColumnDoesNotExist('cache', 'tenant_id');
        $this->assertColumnDoesNotExist('sessions', 'tenant_id');
    }
}
