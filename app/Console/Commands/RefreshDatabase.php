<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RefreshDatabase extends Command
{
    protected $signature = 'db:refresh {--fresh : Wipe the database and run all migrations}';
    protected $description = 'Refresh the database with fresh migrations and seed data';

    public function handle(): void
    {
        if (!app()->environment('local', 'testing')) {
            if (!$this->confirm('You are not in local/testing environment. Do you wish to continue?')) {
                return;
            }
        }

        $this->info('Starting database refresh...');

        // Disable foreign key checks
        Schema::disableForeignKeyConstraints();

        // Drop all tables if --fresh option is used
        if ($this->option('fresh')) {
            $this->info('Dropping all tables...');
            foreach(DB::select('SHOW TABLES') as $table) {
                $table_array = get_object_vars($table);
                Schema::drop($table_array[key($table_array)]);
            }
        }

        // Re-enable foreign key checks
        Schema::enableForeignKeyConstraints();

        // Run migrations
        $this->info('Running migrations...');
        Artisan::call('migrate', ['--force' => true]);
        $this->info(Artisan::output());

        // Clear cache
        $this->info('Clearing cache...');
        Artisan::call('cache:clear');
        Artisan::call('config:clear');
        Artisan::call('route:clear');
        Artisan::call('view:clear');

        // Run seeders
        $this->info('Running seeders...');
        Artisan::call('db:seed', ['--force' => true]);
        $this->info(Artisan::output());

        // Generate JWT secret if needed
        if (!config('jwt.secret')) {
            $this->info('Generating JWT secret...');
            Artisan::call('jwt:secret', ['--force' => true]);
            $this->info(Artisan::output());
        }

        // Generate IDE helper files in local environment
        if (app()->environment('local')) {
            $this->info('Generating IDE helper files...');
            if (class_exists('\Barryvdh\LaravelIdeHelper\IdeHelperServiceProvider')) {
                Artisan::call('ide-helper:generate');
                Artisan::call('ide-helper:meta');
                Artisan::call('ide-helper:models', ['--nowrite' => true]);
            }
        }

        $this->info('Database refresh completed successfully!');

        // Show some useful information
        $this->info('Default users created:');
        $this->table(
            ['Email', 'Password', 'Role'],
            [
                ['admin@example.com', 'password', 'super-admin'],
                ['user@example.com', 'password', 'user'],
            ]
        );

        if (app()->environment('local', 'testing')) {
            $this->info('Additional test users:');
            $this->table(
                ['Email', 'Password', 'Role'],
                [
                    ['manager@example.com', 'password', 'manager'],
                    ['admin@example.com', 'password', 'admin'],
                ]
            );
        }
    }
}
