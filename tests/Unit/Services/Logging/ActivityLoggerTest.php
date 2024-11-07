<?php

namespace Tests\Unit\Services\Logging;

use App\Models\Tenant;
use App\Models\User;
use App\Services\Logging\ActivityLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ActivityLoggerTest extends TestCase
{
    use RefreshDatabase;

    private ActivityLogger $activityLogger;
    private User $user;
    private Tenant $tenant;
    private Request $request;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $this->request = new Request();
        $this->request->headers->set('User-Agent', 'Test Browser');
        $this->request->server->set('REMOTE_ADDR', '127.0.0.1');

        $this->activityLogger = new ActivityLogger($this->request);
    }

    public function test_log_creates_activity_record(): void
    {
        $this->activityLogger->log('test action', $this->user);

        $this->assertDatabaseHas('activity_log', [
            'description' => 'test action',
            'subject_type' => User::class,
            'subject_id' => $this->user->id,
            'causer_type' => null,
            'causer_id' => null,
        ]);

        $activity = Activity::latest()->first();
        $this->assertEquals('127.0.0.1', $activity->properties['ip_address']);
        $this->assertEquals('Test Browser', $activity->properties['user_agent']);
        $this->assertEquals($this->tenant->id, $activity->properties['tenant_id']);
    }

    public function test_log_with_authenticated_user(): void
    {
        Auth::login($this->user);

        $this->activityLogger->log('test action', $this->user);

        $this->assertDatabaseHas('activity_log', [
            'description' => 'test action',
            'subject_type' => User::class,
            'subject_id' => $this->user->id,
            'causer_type' => User::class,
            'causer_id' => $this->user->id,
        ]);
    }

    public function test_log_auth_success(): void
    {
        $this->activityLogger->logAuth('login', $this->user, true);

        $this->assertDatabaseHas('activity_log', [
            'description' => 'login',
            'subject_type' => User::class,
            'subject_id' => $this->user->id,
        ]);

        $activity = Activity::latest()->first();
        $this->assertTrue($activity->properties['success']);
    }

    public function test_log_auth_failure(): void
    {
        $this->activityLogger->logAuth('login', $this->user, false);

        $this->assertDatabaseHas('activity_log', [
            'description' => 'failed login',
            'subject_type' => User::class,
            'subject_id' => $this->user->id,
        ]);

        $activity = Activity::latest()->first();
        $this->assertFalse($activity->properties['success']);
    }

    public function test_log_otp_verification_success(): void
    {
        $this->activityLogger->logOtpVerification($this->user, 'phone', true);

        $this->assertDatabaseHas('activity_log', [
            'description' => 'verified OTP',
            'subject_type' => User::class,
            'subject_id' => $this->user->id,
        ]);

        $activity = Activity::latest()->first();
        $this->assertTrue($activity->properties['success']);
        $this->assertEquals('phone', $activity->properties['type']);
    }

    public function test_log_otp_verification_failure(): void
    {
        $this->activityLogger->logOtpVerification($this->user, 'phone', false);

        $this->assertDatabaseHas('activity_log', [
            'description' => 'failed OTP verification',
            'subject_type' => User::class,
            'subject_id' => $this->user->id,
        ]);

        $activity = Activity::latest()->first();
        $this->assertFalse($activity->properties['success']);
        $this->assertEquals('phone', $activity->properties['type']);
    }

    public function test_log_profile_update(): void
    {
        $oldValues = ['name' => 'Old Name'];
        $newValues = ['name' => 'New Name'];

        $this->activityLogger->logProfileUpdate($this->user, $oldValues, $newValues);

        $this->assertDatabaseHas('activity_log', [
            'description' => 'updated profile',
            'subject_type' => User::class,
            'subject_id' => $this->user->id,
        ]);

        $activity = Activity::latest()->first();
        $this->assertEquals($oldValues, $activity->properties['old']);
        $this->assertEquals($newValues, $activity->properties['attributes']);
    }

    public function test_log_includes_tenant_context(): void
    {
        $this->activityLogger->log('test action', $this->user);

        $activity = Activity::latest()->first();
        $this->assertEquals($this->tenant->id, $activity->properties['tenant_id']);
    }

    public function test_log_includes_request_metadata(): void
    {
        $this->activityLogger->log('test action', $this->user);

        $activity = Activity::latest()->first();
        $this->assertEquals('127.0.0.1', $activity->properties['ip_address']);
        $this->assertEquals('Test Browser', $activity->properties['user_agent']);
    }
}
