<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Event;
use Tests\Traits\InteractsWithAuthentication;
use Tests\Traits\InteractsWithRoles;
use Tests\Traits\InteractsWithTenant;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * Indicates whether the default seeder should run before each test.
     *
     * @var bool
     */
    protected $seed = false;

    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Disable event listeners during testing for better performance
        Event::fake([
            'eloquent.*',
            'auth.*',
            'tenant.*',
        ]);

        // Prevent maintenance mode from affecting tests
        $this->preventMaintenanceMode();
    }

    /**
     * Prevent maintenance mode from affecting tests.
     */
    protected function preventMaintenanceMode(): void
    {
        $this->app->maintenance_mode = false;
    }

    /**
     * Reset the application state between tests.
     */
    protected function tearDown(): void
    {
        // Clear any uploaded files
        if (isset($this->app['files'])) {
            array_map(
                fn($file) => @unlink($file),
                glob(storage_path('framework/testing/*'))
            );
        }

        parent::tearDown();
    }

    /**
     * Boot the testing helper traits.
     *
     * @return array<class-string, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            \Spatie\Permission\PermissionServiceProvider::class,
            \Spatie\Activitylog\ActivitylogServiceProvider::class,
        ];
    }

    /**
     * Define environment setup.
     *
     * @param \Illuminate\Foundation\Application $app
     */
    protected function getEnvironmentSetUp($app): void
    {
        // Use in-memory SQLite database for testing
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Configure queue to sync for testing
        $app['config']->set('queue.default', 'sync');

        // Configure mail to array for testing
        $app['config']->set('mail.default', 'array');

        // Configure filesystem to use local driver for testing
        $app['config']->set('filesystems.default', 'local');

        // Configure cache to array driver for testing
        $app['config']->set('cache.default', 'array');

        // Configure session to array driver for testing
        $app['config']->set('session.driver', 'array');

        // Configure permission defaults for testing
        $app['config']->set('permission.super_admin_role', 'super-admin');
        $app['config']->set('permission.register_permission_check_method', true);

        // Configure activity log for testing
        $app['config']->set('activitylog.enabled', true);
        $app['config']->set('activitylog.delete_records_older_than_days', 365);
    }

    /**
     * Get package aliases.
     *
     * @param \Illuminate\Foundation\Application $app
     * @return array<string, class-string>
     */
    protected function getPackageAliases($app): array
    {
        return [
            'Activity' => \Spatie\Activitylog\Facades\CauserResolver::class,
        ];
    }

    /**
     * Assert that a string contains another string.
     */
    public function assertStringContains(string $needle, string $haystack, string $message = ''): void
    {
        $this->assertStringContainsString($needle, $haystack, $message);
    }

    /**
     * Assert that a string does not contain another string.
     */
    public function assertStringNotContains(string $needle, string $haystack, string $message = ''): void
    {
        $this->assertStringNotContainsString($needle, $haystack, $message);
    }

    /**
     * Run the database migrations for the application.
     */
    protected function runDatabaseMigrations(): void
    {
        $this->artisan('migrate:fresh');

        $this->beforeApplicationDestroyed(function () {
            $this->artisan('migrate:rollback');
        });
    }
}
