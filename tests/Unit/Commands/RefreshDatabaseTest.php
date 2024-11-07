<?php

namespace Tests\Unit\Commands;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RefreshDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_refreshes_database_in_local_environment(): void
    {
        // Set environment to local
        $this->app['env'] = 'local';

        // Run the command
        $this->artisan('db:refresh')
            ->expectsOutput('Starting database refresh...')
            ->expectsOutput('Running migrations...')
            ->expectsOutput('Running seeders...')
            ->expectsOutput('Database refresh completed successfully!')
            ->assertSuccessful();

        // Verify migrations ran
        $this->assertTrue(Schema::hasTable('users'));
        $this->assertTrue(Schema::hasTable('tenants'));
        $this->assertTrue(Schema::hasTable('otps'));
        $this->assertTrue(Schema::hasTable('roles'));
        $this->assertTrue(Schema::hasTable('permissions'));

        // Verify seeders ran
        $this->assertDatabaseHas('tenants', [
            'name' => 'Default Tenant',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'admin@example.com',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'user@example.com',
        ]);

        // Verify test data was created in local environment
        $this->assertDatabaseHas('users', [
            'email' => 'manager@example.com',
        ]);
    }

    public function test_command_asks_for_confirmation_in_production(): void
    {
        // Set environment to production
        $this->app['env'] = 'production';

        // Run the command and expect confirmation
        $this->artisan('db:refresh')
            ->expectsQuestion(
                'You are not in local/testing environment. Do you wish to continue?',
                false
            )
            ->assertSuccessful();

        // Verify no changes were made
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('tenants', 0);
    }

    public function test_fresh_option_drops_all_tables(): void
    {
        // Create some data
        Tenant::factory()->create();
        User::factory()->create();

        // Run the command with --fresh option
        $this->artisan('db:refresh --fresh')
            ->expectsOutput('Dropping all tables...')
            ->assertSuccessful();

        // Verify tables were recreated
        $this->assertTrue(Schema::hasTable('users'));
        $this->assertTrue(Schema::hasTable('tenants'));

        // Verify default data was seeded
        $this->assertDatabaseHas('tenants', [
            'name' => 'Default Tenant',
        ]);
    }

    public function test_command_clears_cache(): void
    {
        // Cache some data
        cache()->put('test-key', 'test-value', 60);

        // Run the command
        $this->artisan('db:refresh')
            ->expectsOutput('Clearing cache...')
            ->assertSuccessful();

        // Verify cache was cleared
        $this->assertNull(cache()->get('test-key'));
    }

    public function test_command_generates_jwt_secret_if_missing(): void
    {
        // Remove JWT secret
        config(['jwt.secret' => null]);

        // Run the command
        $this->artisan('db:refresh')
            ->expectsOutput('Generating JWT secret...')
            ->assertSuccessful();
    }

    public function test_command_shows_default_users_info(): void
    {
        $this->artisan('db:refresh')
            ->expectsTable(
                ['Email', 'Password', 'Role'],
                [
                    ['admin@example.com', 'password', 'super-admin'],
                    ['user@example.com', 'password', 'user'],
                ]
            )
            ->assertSuccessful();
    }

    public function test_command_shows_test_users_in_local_environment(): void
    {
        // Set environment to local
        $this->app['env'] = 'local';

        $this->artisan('db:refresh')
            ->expectsTable(
                ['Email', 'Password', 'Role'],
                [
                    ['manager@example.com', 'password', 'manager'],
                    ['admin@example.com', 'password', 'admin'],
                ]
            )
            ->assertSuccessful();
    }

    public function test_command_maintains_database_integrity(): void
    {
        $this->artisan('db:refresh')->assertSuccessful();

        // Verify foreign key constraints are working
        $tenant = Tenant::first();
        $this->assertNotNull($tenant);

        $users = User::where('tenant_id', $tenant->id)->get();
        foreach ($users as $user) {
            $this->assertEquals($tenant->id, $user->tenant_id);
            $this->assertTrue($user->roles->isNotEmpty());
        }
    }
}
