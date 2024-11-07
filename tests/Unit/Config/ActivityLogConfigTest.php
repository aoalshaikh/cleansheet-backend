<?php

namespace Tests\Unit\Config;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ActivityLogConfigTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_activity_log_is_enabled(): void
    {
        $this->assertTrue(config('activitylog.enabled'));
    }

    public function test_default_log_name_is_set(): void
    {
        $this->assertEquals('default', config('activitylog.default_log_name'));
    }

    public function test_soft_delete_models_are_returned(): void
    {
        $this->assertTrue(config('activitylog.subject_returns_soft_deleted_models'));
    }

    public function test_activity_model_is_configured(): void
    {
        $this->assertEquals(
            \Spatie\Activitylog\Models\Activity::class,
            config('activitylog.activity_model')
        );
    }

    public function test_table_name_is_configured(): void
    {
        $this->assertEquals('activity_log', config('activitylog.table_name'));
    }

    public function test_auto_log_events_are_configured(): void
    {
        $events = config('activitylog.auto_log_events');

        $this->assertTrue($events['created']);
        $this->assertTrue($events['updated']);
        $this->assertTrue($events['deleted']);
        $this->assertTrue($events['restored']);
    }

    public function test_ignored_attributes_are_configured(): void
    {
        $ignoredAttributes = config('activitylog.ignored_attributes');

        $this->assertContains('password', $ignoredAttributes);
        $this->assertContains('remember_token', $ignoredAttributes);
    }

    public function test_properties_logging_is_configured(): void
    {
        $properties = config('activitylog.properties');

        $this->assertTrue($properties['ip_address']);
        $this->assertTrue($properties['user_agent']);
        $this->assertTrue($properties['tenant_id']);
    }

    public function test_auto_log_user_is_enabled(): void
    {
        $this->assertTrue(config('activitylog.auto_log_user'));
    }

    public function test_empty_logs_are_not_submitted(): void
    {
        $this->assertFalse(config('activitylog.submit_empty_logs'));
    }

    public function test_activity_log_respects_configuration(): void
    {
        // Test that password changes are not logged
        $this->user->update(['password' => 'new_password']);
        
        $activity = Activity::latest()->first();
        $this->assertNull($activity);

        // Test that name changes are logged
        $this->user->update(['name' => 'New Name']);
        
        $activity = Activity::latest()->first();
        $this->assertNotNull($activity);
        $this->assertArrayNotHasKey('password', $activity->properties['attributes']);
    }

    public function test_activity_log_cleanup_age_is_configured(): void
    {
        $this->assertEquals(
            60,
            config('activitylog.delete_records_older_than_days')
        );
    }

    public function test_date_format_is_configured(): void
    {
        $this->assertEquals(
            'Y-m-d H:i:s',
            config('activitylog.date_format')
        );
    }

    public function test_queue_configuration_is_set(): void
    {
        $this->assertEquals(
            'sync',
            config('activitylog.queue_connection')
        );
    }

    public function test_activity_log_uses_default_connection_when_not_specified(): void
    {
        $this->assertNull(config('activitylog.database_connection'));
        $this->assertEquals(
            config('database.default'),
            Activity::query()->getConnection()->getName()
        );
    }

    public function test_activity_log_configuration_can_be_modified_at_runtime(): void
    {
        // Temporarily disable activity logging
        Config::set('activitylog.enabled', false);
        
        $this->user->update(['name' => 'Another Name']);
        $this->assertNull(Activity::latest()->first());

        // Re-enable activity logging
        Config::set('activitylog.enabled', true);
        
        $this->user->update(['name' => 'Final Name']);
        $this->assertNotNull(Activity::latest()->first());
    }

    public function test_activity_log_queue_events_are_configured(): void
    {
        $queueEvents = config('activitylog.queue_events');

        $this->assertFalse($queueEvents['created']);
        $this->assertFalse($queueEvents['updated']);
        $this->assertFalse($queueEvents['deleted']);
        $this->assertFalse($queueEvents['restored']);
    }

    public function test_activity_log_model_events_are_configured(): void
    {
        $modelEvents = config('activitylog.with_model_events');

        $this->assertFalse($modelEvents['created']);
        $this->assertFalse($modelEvents['updated']);
        $this->assertFalse($modelEvents['deleted']);
        $this->assertFalse($modelEvents['restored']);
    }
}
