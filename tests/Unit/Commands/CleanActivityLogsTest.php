<?php

namespace Tests\Unit\Commands;

use App\Console\Commands\CleanActivityLogs;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class CleanActivityLogsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        // Create test activities
        $this->createTestActivities();
    }

    private function createTestActivities(): void
    {
        // Create old activities (90 days old)
        Activity::factory()
            ->count(5)
            ->old(90)
            ->auth()
            ->forTenant($this->tenant->id)
            ->forSubject(User::class, $this->user->id)
            ->create();

        // Create recent activities
        Activity::factory()
            ->count(3)
            ->recent(30)
            ->auth()
            ->forTenant($this->tenant->id)
            ->forSubject(User::class, $this->user->id)
            ->create();
    }

    public function test_command_cleans_old_records(): void
    {
        $this->artisan('activitylog:clean --days=60')
            ->expectsOutput('Starting activity log cleanup...')
            ->expectsOutput('Keeping records from the last 60 days')
            ->expectsOutput('Found 5 records to delete')
            ->expectsOutput('Successfully deleted 5 records')
            ->assertSuccessful();

        $this->assertEquals(3, Activity::count());
        $this->assertEquals(
            0,
            Activity::where('created_at', '<', now()->subDays(60))->count()
        );
    }

    public function test_command_respects_tenant_filter(): void
    {
        // Create another tenant with activities
        $otherTenant = Tenant::factory()->create();
        Activity::factory()
            ->old(90)
            ->auth()
            ->forTenant($otherTenant->id)
            ->create();

        $this->artisan('activitylog:clean --days=60 --tenant=' . $this->tenant->id)
            ->expectsOutput("Filtering for tenant ID: {$this->tenant->id}")
            ->assertSuccessful();

        $this->assertEquals(4, Activity::count());
        $this->assertEquals(
            1,
            Activity::whereJsonContains('properties->tenant_id', $otherTenant->id)->count()
        );
    }

    public function test_command_respects_type_filter(): void
    {
        // Create activities with different types
        Activity::factory()
            ->old(90)
            ->profile()
            ->forTenant($this->tenant->id)
            ->forSubject(User::class, $this->user->id)
            ->create();

        $this->artisan('activitylog:clean --days=60 --type=auth')
            ->expectsOutput('Filtering for log type: auth')
            ->assertSuccessful();

        $this->assertEquals(4, Activity::count());
        $this->assertEquals(1, Activity::where('log_name', 'profile')->count());
    }

    public function test_dry_run_option(): void
    {
        $this->artisan('activitylog:clean --days=60 --dry-run')
            ->expectsOutput('DRY RUN - No records will be deleted')
            ->expectsOutput('Found 5 records to delete')
            ->assertSuccessful();

        $this->assertEquals(8, Activity::count());
    }

    public function test_command_handles_no_records_to_delete(): void
    {
        Activity::query()->delete();

        $this->artisan('activitylog:clean --days=60')
            ->expectsOutput('No records found to delete')
            ->assertSuccessful();
    }

    public function test_command_uses_config_default_days(): void
    {
        config(['activitylog.delete_records_older_than_days' => 30]);

        $this->artisan('activitylog:clean')
            ->expectsOutput('Keeping records from the last 30 days')
            ->assertSuccessful();
    }

    public function test_command_handles_invalid_tenant_id(): void
    {
        $this->artisan('activitylog:clean --tenant=999999')
            ->expectsOutput('No records found to delete')
            ->assertSuccessful();
    }

    public function test_command_handles_invalid_type(): void
    {
        $this->artisan('activitylog:clean --type=invalid_type')
            ->expectsOutput('No records found to delete')
            ->assertSuccessful();
    }

    public function test_command_optimizes_table_after_large_deletion(): void
    {
        // Create 2000 old records
        Activity::factory()
            ->count(2000)
            ->old(90)
            ->auth()
            ->forTenant($this->tenant->id)
            ->create();

        $this->artisan('activitylog:clean --days=60')
            ->expectsOutput('Optimizing activity_log table...')
            ->assertSuccessful();
    }

    public function test_command_handles_different_activity_types(): void
    {
        Activity::factory()
            ->count(3)
            ->old(90)
            ->auth()
            ->forTenant($this->tenant->id)
            ->create();

        Activity::factory()
            ->count(3)
            ->old(90)
            ->profile()
            ->forTenant($this->tenant->id)
            ->create();

        Activity::factory()
            ->count(3)
            ->old(90)
            ->system()
            ->forTenant($this->tenant->id)
            ->create();

        $this->artisan('activitylog:clean --days=60')
            ->expectsOutput('Found 9 records to delete')
            ->assertSuccessful();

        $this->assertEquals(3, Activity::count()); // Only recent activities remain
    }

    public function test_command_preserves_activity_properties(): void
    {
        $oldActivity = Activity::factory()
            ->old(90)
            ->auth()
            ->forTenant($this->tenant->id)
            ->withProperties(['custom' => 'value'])
            ->create();

        $recentActivity = Activity::factory()
            ->recent()
            ->auth()
            ->forTenant($this->tenant->id)
            ->withProperties(['custom' => 'value'])
            ->create();

        $this->artisan('activitylog:clean --days=60')->assertSuccessful();

        $this->assertNull(Activity::find($oldActivity->id));
        $this->assertNotNull(Activity::find($recentActivity->id));
        $this->assertEquals(
            'value',
            Activity::find($recentActivity->id)->properties['custom']
        );
    }
}
