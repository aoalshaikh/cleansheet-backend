<?php

namespace Tests\Unit\Console;

use App\Console\Kernel;
use App\Jobs\CleanupExpiredOtps;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionClass;
use Tests\TestCase;

class KernelTest extends TestCase
{
    use RefreshDatabase;

    private Schedule $schedule;
    private array $events;

    protected function setUp(): void
    {
        parent::setUp();

        $this->schedule = $this->app->make(Schedule::class);
        
        // Get protected schedule method using reflection
        $kernel = $this->app->make(Kernel::class);
        $reflection = new ReflectionClass(get_class($kernel));
        $method = $reflection->getMethod('schedule');
        $method->setAccessible(true);
        $method->invoke($kernel, $this->schedule);

        $this->events = $this->schedule->events();
    }

    public function test_cleanup_expired_otps_job_is_scheduled(): void
    {
        $event = $this->findScheduledEvent('cleanup-expired-otps');

        $this->assertNotNull($event, 'CleanupExpiredOtps job should be scheduled');
        $this->assertEquals('0 * * * *', $event->expression); // Hourly
        $this->assertTrue($event->withoutOverlapping);
        $this->assertTrue($event->runInBackground);
        $this->assertEquals('Clean up expired OTP codes', $event->description);
    }

    public function test_activity_log_cleanup_is_scheduled(): void
    {
        $event = $this->findScheduledEvent('cleanup-activity-log');

        $this->assertNotNull($event, 'Activity log cleanup should be scheduled');
        $this->assertEquals('0 0 * * *', $event->expression); // Daily
        $this->assertTrue($event->withoutOverlapping);
        $this->assertEquals('Clean up old activity log entries', $event->description);
    }

    public function test_backup_commands_are_scheduled_when_package_exists(): void
    {
        if (!class_exists('\Spatie\Backup\Commands\BackupCommand')) {
            $this->markTestSkipped('Backup package is not installed');
        }

        // Check backup:clean command
        $cleanEvent = $this->findScheduledEvent('backup-clean');
        $this->assertNotNull($cleanEvent, 'Backup clean command should be scheduled');
        $this->assertEquals('0 1 * * *', $cleanEvent->expression); // 01:00 daily
        $this->assertTrue($cleanEvent->withoutOverlapping);
        $this->assertEquals('Clean up old backups', $cleanEvent->description);

        // Check backup:run command
        $runEvent = $this->findScheduledEvent('backup-run');
        $this->assertNotNull($runEvent, 'Backup run command should be scheduled');
        $this->assertEquals('0 2 * * *', $runEvent->expression); // 02:00 daily
        $this->assertTrue($runEvent->withoutOverlapping);
        $this->assertEquals('Create new backup', $runEvent->description);
    }

    public function test_telescope_pruning_is_scheduled_when_package_exists(): void
    {
        if (!class_exists('\Laravel\Telescope\TelescopeServiceProvider')) {
            $this->markTestSkipped('Telescope package is not installed');
        }

        $event = $this->findScheduledEvent('telescope-prune');
        $this->assertNotNull($event, 'Telescope prune command should be scheduled');
        $this->assertEquals('0 0 * * *', $event->expression); // Daily
        $this->assertTrue($event->withoutOverlapping);
        $this->assertEquals('Prune old telescope entries', $event->description);
    }

    public function test_horizon_snapshot_is_scheduled_when_package_exists(): void
    {
        if (!class_exists('\Laravel\Horizon\HorizonServiceProvider')) {
            $this->markTestSkipped('Horizon package is not installed');
        }

        $event = $this->findScheduledEvent('horizon-snapshot');
        $this->assertNotNull($event, 'Horizon snapshot command should be scheduled');
        $this->assertEquals('*/5 * * * *', $event->expression); // Every 5 minutes
        $this->assertTrue($event->withoutOverlapping);
        $this->assertEquals('Take horizon metrics snapshot', $event->description);
    }

    public function test_queue_monitoring_is_scheduled(): void
    {
        $event = $this->findScheduledEvent('queue-monitor');
        $this->assertNotNull($event, 'Queue monitoring should be scheduled');
        $this->assertEquals('*/5 * * * *', $event->expression); // Every 5 minutes
        $this->assertTrue($event->withoutOverlapping);
        $this->assertEquals('Monitor queue health', $event->description);
    }

    public function test_queue_cleanup_is_scheduled(): void
    {
        $event = $this->findScheduledEvent('queue-prune-failed');
        $this->assertNotNull($event, 'Queue cleanup should be scheduled');
        $this->assertEquals('0 0 * * *', $event->expression); // Daily
        $this->assertTrue($event->withoutOverlapping);
        $this->assertEquals('Clean up old failed jobs', $event->description);
    }

    public function test_cache_cleanup_is_scheduled(): void
    {
        $event = $this->findScheduledEvent('cache-prune-tags');
        $this->assertNotNull($event, 'Cache cleanup should be scheduled');
        $this->assertEquals('0 * * * *', $event->expression); // Hourly
        $this->assertTrue($event->withoutOverlapping);
        $this->assertEquals('Clean up stale cache tags', $event->description);
    }

    public function test_all_scheduled_tasks_run_on_one_server(): void
    {
        foreach ($this->events as $event) {
            $this->assertTrue(
                $event->onOneServer ?? false,
                sprintf(
                    "Task '%s' should be configured to run on one server only",
                    $event->description ?? $event->command ?? 'Unknown task'
                )
            );
        }
    }

    public function test_all_scheduled_tasks_prevent_overlapping(): void
    {
        foreach ($this->events as $event) {
            $this->assertTrue(
                $event->withoutOverlapping ?? false,
                sprintf(
                    "Task '%s' should be configured to prevent overlapping",
                    $event->description ?? $event->command ?? 'Unknown task'
                )
            );
        }
    }

    public function test_all_scheduled_tasks_have_names(): void
    {
        foreach ($this->events as $event) {
            $this->assertNotEmpty(
                $event->name ?? '',
                sprintf(
                    "Task '%s' should have a name",
                    $event->description ?? $event->command ?? 'Unknown task'
                )
            );
        }
    }

    public function test_all_scheduled_tasks_have_descriptions(): void
    {
        foreach ($this->events as $event) {
            $this->assertNotEmpty(
                $event->description ?? '',
                sprintf(
                    "Task '%s' should have a description",
                    $event->name ?? $event->command ?? 'Unknown task'
                )
            );
        }
    }

    private function findScheduledEvent(string $name): ?Event
    {
        foreach ($this->events as $event) {
            if (($event->name ?? '') === $name) {
                return $event;
            }
        }

        return null;
    }
}
