<?php

namespace Tests\Feature\ActivityLog;

use App\Models\Tenant;
use App\Models\User;
use App\Services\Logging\ActivityLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ActivityLoggingTest extends TestCase
{
    use RefreshDatabase;

    private ActivityLogger $logger;
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

        $this->request = Request::create('/api/test', 'GET');
        $this->request->headers->set('User-Agent', 'Test Browser');
        $this->request->server->set('REMOTE_ADDR', '127.0.0.1');

        $this->logger = new ActivityLogger($this->request);
    }

    public function test_logs_basic_activity(): void
    {
        $this->logger->log('test action', $this->user);

        $activity = Activity::latest()->first();

        $this->assertNotNull($activity);
        $this->assertEquals('test action', $activity->description);
        $this->assertEquals(User::class, $activity->subject_type);
        $this->assertEquals($this->user->id, $activity->subject_id);
    }

    public function test_logs_auth_success(): void
    {
        $this->logger->logAuth('login', $this->user, true);

        $activity = Activity::latest()->first();

        $this->assertNotNull($activity);
        $this->assertEquals('login', $activity->description);
        $this->assertTrue($activity->properties['success']);
        $this->assertEquals($this->user->id, $activity->subject_id);
    }

    public function test_logs_auth_failure(): void
    {
        $this->logger->logAuth('login', $this->user, false);

        $activity = Activity::latest()->first();

        $this->assertNotNull($activity);
        $this->assertEquals('failed login', $activity->description);
        $this->assertFalse($activity->properties['success']);
        $this->assertEquals($this->user->id, $activity->subject_id);
    }

    public function test_logs_otp_verification_success(): void
    {
        $this->logger->logOtpVerification($this->user, 'phone', true);

        $activity = Activity::latest()->first();

        $this->assertNotNull($activity);
        $this->assertEquals('verified OTP', $activity->description);
        $this->assertTrue($activity->properties['success']);
        $this->assertEquals('phone', $activity->properties['type']);
    }

    public function test_logs_otp_verification_failure(): void
    {
        $this->logger->logOtpVerification($this->user, 'phone', false);

        $activity = Activity::latest()->first();

        $this->assertNotNull($activity);
        $this->assertEquals('failed OTP verification', $activity->description);
        $this->assertFalse($activity->properties['success']);
        $this->assertEquals('phone', $activity->properties['type']);
    }

    public function test_logs_profile_update(): void
    {
        $oldValues = ['name' => 'Old Name'];
        $newValues = ['name' => 'New Name'];

        $this->logger->logProfileUpdate($this->user, $oldValues, $newValues);

        $activity = Activity::latest()->first();

        $this->assertNotNull($activity);
        $this->assertEquals('updated profile', $activity->description);
        $this->assertEquals($oldValues, $activity->properties['old']);
        $this->assertEquals($newValues, $activity->properties['attributes']);
    }

    public function test_logs_include_request_metadata(): void
    {
        $this->logger->log('test action', $this->user);

        $activity = Activity::latest()->first();

        $this->assertNotNull($activity);
        $this->assertEquals('127.0.0.1', $activity->properties['ip_address']);
        $this->assertEquals('Test Browser', $activity->properties['user_agent']);
    }

    public function test_logs_include_tenant_context(): void
    {
        $this->logger->log('test action', $this->user);

        $activity = Activity::latest()->first();

        $this->assertNotNull($activity);
        $this->assertEquals($this->tenant->id, $activity->properties['tenant_id']);
    }

    public function test_logs_are_tenant_scoped(): void
    {
        // Create another tenant and user
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
        ]);

        // Create activities using factory
        Activity::factory()
            ->forTenant($this->tenant->id)
            ->forSubject(User::class, $this->user->id)
            ->create();

        Activity::factory()
            ->forTenant($otherTenant->id)
            ->forSubject(User::class, $otherUser->id)
            ->create();

        // Check that activities are properly scoped
        $thisTenantsActivities = Activity::whereJsonContains('properties->tenant_id', $this->tenant->id)->get();
        $otherTenantsActivities = Activity::whereJsonContains('properties->tenant_id', $otherTenant->id)->get();

        $this->assertEquals(1, $thisTenantsActivities->count());
        $this->assertEquals(1, $otherTenantsActivities->count());
        $this->assertEquals($this->user->id, $thisTenantsActivities->first()->subject_id);
        $this->assertEquals($otherUser->id, $otherTenantsActivities->first()->subject_id);
    }

    public function test_logs_handle_deleted_users(): void
    {
        Activity::factory()
            ->forTenant($this->tenant->id)
            ->forSubject(User::class, $this->user->id)
            ->create();

        $this->user->delete();

        $activity = Activity::latest()->first();

        $this->assertNotNull($activity);
        $this->assertEquals($this->user->id, $activity->subject_id);
        $this->assertSoftDeleted($this->user);
    }

    public function test_logs_handle_deleted_tenants(): void
    {
        Activity::factory()
            ->forTenant($this->tenant->id)
            ->forSubject(User::class, $this->user->id)
            ->create();

        $this->tenant->delete();

        $activity = Activity::latest()->first();

        $this->assertNotNull($activity);
        $this->assertEquals($this->tenant->id, $activity->properties['tenant_id']);
        $this->assertSoftDeleted($this->tenant);
    }

    public function test_logs_handle_different_types(): void
    {
        Activity::factory()
            ->auth()
            ->forTenant($this->tenant->id)
            ->forSubject(User::class, $this->user->id)
            ->create();

        Activity::factory()
            ->profile()
            ->forTenant($this->tenant->id)
            ->forSubject(User::class, $this->user->id)
            ->create();

        Activity::factory()
            ->system()
            ->forTenant($this->tenant->id)
            ->forSubject(User::class, $this->user->id)
            ->create();

        $this->assertEquals(1, Activity::where('log_name', 'auth')->count());
        $this->assertEquals(1, Activity::where('log_name', 'profile')->count());
        $this->assertEquals(1, Activity::where('log_name', 'system')->count());
    }
}
