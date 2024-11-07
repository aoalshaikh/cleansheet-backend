<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\Console\Input\InputOption;

class CleanActivityLogs extends Command
{
    protected $signature = 'activitylog:clean 
        {--days= : Optional. Number of days to keep. Defaults to config value.}
        {--tenant= : Optional. Clean logs for specific tenant ID.}
        {--type= : Optional. Clean logs of specific type (e.g., auth, profile).}
        {--dry-run : Run without actually deleting records}';

    protected $description = 'Clean up old activity logs';

    public function handle(): int
    {
        $days = $this->option('days') ?? config('activitylog.delete_records_older_than_days', 60);
        $tenantId = $this->option('tenant');
        $type = $this->option('type');
        $isDryRun = $this->option('dry-run');

        $this->info("Starting activity log cleanup...");
        $this->info("Keeping records from the last {$days} days");

        if ($isDryRun) {
            $this->warn('DRY RUN - No records will be deleted');
        }

        try {
            $query = Activity::where('created_at', '<', now()->subDays($days));

            // Add tenant filter if specified
            if ($tenantId) {
                $query->whereJsonContains('properties->tenant_id', (int) $tenantId);
                $this->info("Filtering for tenant ID: {$tenantId}");
            }

            // Add type filter if specified
            if ($type) {
                $query->where('log_name', $type);
                $this->info("Filtering for log type: {$type}");
            }

            // Get count before deletion
            $count = $query->count();

            if ($count === 0) {
                $this->info('No records found to delete');
                return Command::SUCCESS;
            }

            $this->info("Found {$count} records to delete");

            if ($isDryRun) {
                $this->table(
                    ['Date', 'Description', 'Tenant ID', 'Type'],
                    $query->get()->map(function ($activity) {
                        return [
                            $activity->created_at->format('Y-m-d H:i:s'),
                            $activity->description,
                            $activity->properties['tenant_id'] ?? 'N/A',
                            $activity->log_name,
                        ];
                    })
                );
            } else {
                // Use chunking for better performance with large datasets
                $deleted = 0;
                $query->chunkById(1000, function ($records) use (&$deleted) {
                    foreach ($records as $record) {
                        $record->delete();
                        $deleted++;
                    }

                    if ($deleted % 5000 === 0) {
                        $this->info("Deleted {$deleted} records...");
                    }
                });

                $this->info("Successfully deleted {$deleted} records");

                // Optimize table after large deletion
                if ($deleted > 1000) {
                    $this->info('Optimizing activity_log table...');
                    DB::statement('OPTIMIZE TABLE activity_log');
                }
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Error during cleanup: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
            return Command::FAILURE;
        }
    }

    protected function getOptions(): array
    {
        return [
            ['days', null, InputOption::VALUE_OPTIONAL, 'Number of days to keep'],
            ['tenant', null, InputOption::VALUE_OPTIONAL, 'Clean logs for specific tenant ID'],
            ['type', null, InputOption::VALUE_OPTIONAL, 'Clean logs of specific type'],
            ['dry-run', null, InputOption::VALUE_NONE, 'Run without actually deleting records'],
        ];
    }
}
