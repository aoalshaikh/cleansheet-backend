<?php

namespace Tests\Unit\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\InteractsWithRoles;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase, InteractsWithRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_creates_system_tenant(): void
    {
        $systemTenant = Tenant::where('name', 'System')->first();

        $this->assertNotNull($systemTenant);
        $this->assertTrue($systemTenant->is_active);
        $this->assertEquals([config('app.domain', 'localhost')], $systemTenant->domains);
        $this->assertNotNull($systemTenant->settings);
        $this->assertEquals('system', $systemTenant->settings['theme']);
    }

    public function test_creates_super_admin_user(): void
    {
        $systemTenant = Tenant::where('name', 'System')->first();
        $superAdmin = User::where('email', 'admin@example.com')->first();

        $this->assertNotNull($superAdmin);
        $this->assertEquals($systemTenant->id, $superAdmin->tenant_id);
        $this->assertEquals('Super Admin', $superAdmin->name);
        $this->assertNotNull($superAdmin->email_verified_at);
        $this->assertTrue($superAdmin->hasRole('super-admin'));
    }

    public function test_creates_test_tenant_in_testing(): void
    {
        $testTenant = Tenant::where('name', 'Test Tenant')->first();

        $this->assertNotNull($testTenant);
        $this->assertTrue($testTenant->is_active);
        $this->assertEquals(['test.'.config('app.domain', 'localhost')], $testTenant->domains);
    }

    public function test_creates_test_admin_in_testing(): void
    {
        $testTenant = Tenant::where('name', 'Test Tenant')->first();
        $testAdmin = User::where('email', 'test@example.com')->first();

        $this->assertNotNull($testAdmin);
        $this->assertEquals($testTenant->id, $testAdmin->tenant_id);
        $this->assertEquals('Test Admin', $testAdmin->name);
        $this->assertNotNull($testAdmin->email_verified_at);
        $this->assertTrue($testAdmin->hasRole('tenant-admin'));
    }

    public function test_creates_test_users_in_testing(): void
    {
        $testTenant = Tenant::where('name', 'Test Tenant')->first();
        
        $users = User::where('tenant_id', $testTenant->id)
            ->whereHas('roles', function ($query) {
                $query->where('name', 'tenant-user');
            })
            ->get();

        $this->assertCount(5, $users);
        foreach ($users as $user) {
            $this->assertTrue($user->hasRole('tenant-user'));
        }
    }

    public function test_creates_test_managers_in_testing(): void
    {
        $testTenant = Tenant::where('name', 'Test Tenant')->first();
        
        $managers = User::where('tenant_id', $testTenant->id)
            ->whereHas('roles', function ($query) {
                $query->where('name', 'tenant-manager');
            })
            ->get();

        $this->assertCount(2, $managers);
        foreach ($managers as $manager) {
            $this->assertTrue($manager->hasRole('tenant-manager'));
        }
    }

    public function test_does_not_create_additional_tenants_in_testing(): void
    {
        // In testing environment, we should only have System and Test Tenant
        $tenantCount = Tenant::count();
        $this->assertEquals(2, $tenantCount);
    }

    public function test_all_users_have_required_attributes(): void
    {
        $users = User::all();

        foreach ($users as $user) {
            $this->assertNotNull($user->tenant_id);
            $this->assertNotNull($user->name);
            $this->assertNotNull($user->email);
            $this->assertNotNull($user->password);
            $this->assertNotNull($user->email_verified_at);
            $this->assertTrue($user->roles->isNotEmpty());
        }
    }

    public function test_all_tenants_have_required_attributes(): void
    {
        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            $this->assertNotNull($tenant->name);
            $this->assertNotNull($tenant->domains);
            $this->assertIsArray($tenant->domains);
            $this->assertNotEmpty($tenant->domains);
            $this->assertNotNull($tenant->is_active);
            $this->assertTrue($tenant->users()->exists());
        }
    }

    public function test_all_users_belong_to_active_tenants(): void
    {
        $users = User::with('tenant')->get();

        foreach ($users as $user) {
            $this->assertTrue($user->tenant->is_active);
        }
    }

    public function test_all_users_have_appropriate_roles(): void
    {
        $systemTenant = Tenant::where('name', 'System')->first();
        $testTenant = Tenant::where('name', 'Test Tenant')->first();

        // System tenant users
        $this->assertTrue(
            User::where('tenant_id', $systemTenant->id)
                ->whereHas('roles', fn($q) => $q->where('name', 'super-admin'))
                ->exists()
        );

        // Test tenant users
        $this->assertTrue(
            User::where('tenant_id', $testTenant->id)
                ->whereHas('roles', fn($q) => $q->where('name', 'tenant-admin'))
                ->exists()
        );

        $this->assertTrue(
            User::where('tenant_id', $testTenant->id)
                ->whereHas('roles', fn($q) => $q->where('name', 'tenant-manager'))
                ->exists()
        );

        $this->assertTrue(
            User::where('tenant_id', $testTenant->id)
                ->whereHas('roles', fn($q) => $q->where('name', 'tenant-user'))
                ->exists()
        );
    }
}
