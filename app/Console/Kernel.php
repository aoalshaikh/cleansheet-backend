<?php

namespace App\Console;

use App\Console\Commands\CleanActivityLogs;
use App\Console\Commands\RefreshDatabase;
use App\Jobs\CleanupExpiredOtps;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        RefreshDatabase::class,
        CleanActivityLogs::class,
    ];

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Run OTP cleanup every hour
        $schedule->job(new CleanupExpiredOtps)
            ->hourly()
            ->name('cleanup-expired-otps')
            ->withoutOverlapping()
            ->onOneServer()
            ->runInBackground();

        // Run activity log cleanup daily
        $schedule->command('activitylog:clean')
            ->daily()
            ->at('01:00')
            ->name('cleanup-activity-log')
            ->withoutOverlapping()
            ->onOneServer();

        // Run database backup daily (if backup package is installed)
        if (class_exists('\Spatie\Backup\Commands\BackupCommand')) {
            $schedule->command('backup:clean')
                ->daily()
                ->at('02:00')
                ->name('backup-clean')
                ->withoutOverlapping()
                ->onOneServer();

            $schedule->command('backup:run')
                ->daily()
                ->at('03:00')
                ->name('backup-run')
                ->withoutOverlapping()
                ->onOneServer();
        }

        // Run telescope pruning daily (if telescope is installed)
        if (class_exists('\Laravel\Telescope\TelescopeServiceProvider')) {
            $schedule->command('telescope:prune')
                ->daily()
                ->at('04:00')
                ->name('telescope-prune')
                ->withoutOverlapping()
                ->onOneServer();
        }

        // Run horizon snapshot hourly (if horizon is installed)
        if (class_exists('\Laravel\Horizon\HorizonServiceProvider')) {
            $schedule->command('horizon:snapshot')
                ->everyFiveMinutes()
                ->name('horizon-snapshot')
                ->withoutOverlapping()
                ->onOneServer();
        }

        // Run queue monitoring
        $schedule->command('queue:monitor')
            ->everyFiveMinutes()
            ->name('queue-monitor')
            ->withoutOverlapping()
            ->onOneServer();

        // Run queue cleanup
        $schedule->command('queue:prune-failed')
            ->daily()
            ->at('05:00')
            ->name('queue-prune-failed')
            ->withoutOverlapping()
            ->onOneServer();

        // Run cache cleanup
        $schedule->command('cache:prune-stale-tags')
            ->hourly()
            ->name('cache-prune-tags')
            ->withoutOverlapping()
            ->onOneServer();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
