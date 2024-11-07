<?php

namespace Tests\Unit\Traits;

use App\Models\Tenant;
use App\Models\User;
use Database\Factories\TestModelFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class LogsActivityTest extends TestCase
{
    use RefreshDatabase;

    private TestModel $model;
    private User $user;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $this->model = TestModelFactory::new()
            ->forTenant($this->tenant)
            ->create();

        Auth::login($this->user);
    }

    public function test_logs_model_creation(): void
    {
        $activity = Activity::latest()->first();

        $this->assertNotNull($activity);
        $this->assertEquals('created TestModel', $activity->description);
        $this->assertEquals(TestModel::class, $activity->subject_type);
        $this->assertEquals($this->model->id, $activity->subject_id);
    }

    public function test_logs_model_update(): void
    {
        $this->model->update(['name' => 'Updated Name']);

        $activity = Activity::latest()->first();

        $this->assertNotNull($activity);
        $this->assertEquals('updated TestModel', $activity->description);
        $this->assertEquals($this->model->getOriginal('name'), $activity->properties['old']['name']);
        $this->assertEquals('Updated Name', $activity->properties['attributes']['name']);
    }

    public function test_logs_model_deletion(): void
    {
        $this->model->delete();

        $activity = Activity::latest()->first();

        $this->assertNotNull($activity);
        $this->assertEquals('deleted TestModel', $activity->description);
    }

    public function test_includes_tenant_context(): void
    {
        $activity = Activity::latest()->first();

        $this->assertNotNull($activity);
        $this->assertEquals($this->tenant->id, $activity->properties['tenant_id']);
    }

    public function test_respects_ignored_attributes(): void
    {
        $this->model->update([
            'name' => 'Updated Name',
            'secret' => 'Updated Secret',
        ]);

        $activity = Activity::latest()->first();

        $this->assertArrayHasKey('name', $activity->properties['attributes']);
        $this->assertArrayNotHasKey('secret', $activity->properties['attributes']);
    }

    public function test_uses_custom_descriptions(): void
    {
        $model = TestModelFactory::new()
            ->asCustomDescriptions()
            ->forTenant($this->tenant)
            ->create();

        $activity = Activity::latest()->first();

        $this->assertEquals('A new test model was created', $activity->description);
    }

    public function test_uses_custom_log_name(): void
    {
        $model = TestModelFactory::new()
            ->asCustomLogName()
            ->forTenant($this->tenant)
            ->create();

        $activity = Activity::latest()->first();

        $this->assertEquals('custom_log', $activity->log_name);
    }

    public function test_can_disable_logging(): void
    {
        $initialCount = Activity::count();

        $model = TestModelFactory::new()
            ->asDisabledLogging()
            ->forTenant($this->tenant)
            ->create();

        $this->assertEquals($initialCount, Activity::count());
    }

    public function test_includes_custom_properties(): void
    {
        $model = TestModelFactory::new()
            ->asCustomProperties()
            ->forTenant($this->tenant)
            ->create();

        $activity = Activity::latest()->first();

        $this->assertEquals('custom value', $activity->properties['custom_property']);
    }

    public function test_handles_soft_deletes(): void
    {
        $model = TestModelFactory::new()
            ->asSoftDeletes()
            ->forTenant($this->tenant)
            ->create();

        $model->delete();

        $activities = Activity::latest()->get();
        
        $this->assertEquals('deleted TestModelWithSoftDeletes', $activities[0]->description);
        $this->assertNotNull($model->deleted_at);
    }

    public function test_logs_model_restoration(): void
    {
        $model = TestModelFactory::new()
            ->asSoftDeletes()
            ->forTenant($this->tenant)
            ->create();

        $model->delete();
        $model->restore();

        $activity = Activity::latest()->first();

        $this->assertEquals('restored TestModelWithSoftDeletes', $activity->description);
    }

    public function test_handles_multiple_updates(): void
    {
        $this->model->update(['name' => 'First Update']);
        $this->model->update(['name' => 'Second Update']);

        $activities = Activity::latest()
            ->where('subject_type', TestModel::class)
            ->where('description', 'like', '%updated%')
            ->get();

        $this->assertCount(2, $activities);
        $this->assertEquals('Second Update', $activities[0]->properties['attributes']['name']);
        $this->assertEquals('First Update', $activities[1]->properties['attributes']['name']);
    }

    public function test_logs_causer(): void
    {
        $this->model->update(['name' => 'Updated Name']);

        $activity = Activity::latest()->first();

        $this->assertEquals(User::class, $activity->causer_type);
        $this->assertEquals($this->user->id, $activity->causer_id);
    }

    public function test_handles_no_changes(): void
    {
        $initialCount = Activity::count();
        
        $this->model->update(['name' => $this->model->name]);

        $this->assertEquals($initialCount, Activity::count());
    }
}
