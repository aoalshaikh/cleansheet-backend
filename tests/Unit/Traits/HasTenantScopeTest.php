<?php

namespace Tests\Unit\Traits;

use App\Models\Tenant;
use App\Models\User;
use App\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;
use Tests\Traits\InteractsWithAuthentication;
use Tests\Traits\InteractsWithRoles;

class TestModel extends Model
{
    use HasTenantScope;

    protected $fillable = ['name', 'tenant_id'];
    protected $table = 'test_models';
}

class HasTenantScopeTest extends TestCase
{
    use RefreshDatabase, InteractsWithRoles, InteractsWithAuthentication;

    private User $user;
    private Tenant $tenant;
    private TestModel $model;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create([
            'is_active' => true,
            'settings' => [
                'features' => [
                    'feature1' => true,
                    'feature2' => false,
                ],
                'capabilities' => [
                    'capability1' => true,
                    'capability2' => false,
                ],
                'subscription' => [
                    'plan' => 'premium',
                    'status' => 'active',
                ],
            ],
            'domains' => ['test.example.com'],
        ]);

        $this->user = User::factory()
            ->forTenant($this->tenant)
            ->create();

        $this->model = new TestModel();
        $this->model->forceFill([
            'name' => 'Test Model',
            'tenant_id' => $this->tenant->id,
        ])->save();

        $this->setupRolesAndPermissions();
    }

    public function test_automatically_scopes_to_tenant(): void
    {
        $this->actingAsUser($this->user);

        $otherTenant = Tenant::factory()->create();
        TestModel::create([
            'name' => 'Other Model',
            'tenant_id' => $otherTenant->id,
        ]);

        $models = TestModel::all();

        $this->assertCount(1, $models);
        $this->assertEquals($this->tenant->id, $models->first()->tenant_id);
    }

    public function test_super_admin_sees_all_records(): void
    {
        $this->createRole('super-admin');
        $this->user->assignRole('super-admin');
        $this->actingAsUser($this->user);

        $otherTenant = Tenant::factory()->create();
        TestModel::create([
            'name' => 'Other Model',
            'tenant_id' => $otherTenant->id,
        ]);

        $models = TestModel::all();

        $this->assertCount(2, $models);
    }

    public function test_automatically_sets_tenant_id_on_create(): void
    {
        $this->actingAsUser($this->user);

        $model = TestModel::create(['name' => 'New Model']);

        $this->assertEquals($this->tenant->id, $model->tenant_id);
    }

    public function test_tenant_scope_methods(): void
    {
        $otherTenant = Tenant::factory()->create();
        TestModel::create([
            'name' => 'Other Model',
            'tenant_id' => $otherTenant->id,
        ]);

        // Test tenant() scope
        $models = TestModel::tenant($this->tenant)->get();
        $this->assertCount(1, $models);
        $this->assertEquals($this->tenant->id, $models->first()->tenant_id);

        // Test currentTenant() scope
        $this->actingAsUser($this->user);
        $models = TestModel::currentTenant()->get();
        $this->assertCount(1, $models);
        $this->assertEquals($this->tenant->id, $models->first()->tenant_id);

        // Test allTenants() scope
        $models = TestModel::allTenants()->get();
        $this->assertCount(2, $models);
    }

    public function test_tenant_feature_scopes(): void
    {
        $this->actingAsUser($this->user);

        $models = TestModel::tenantFeatures(['feature1'])->get();
        $this->assertCount(1, $models);

        $models = TestModel::tenantFeatures(['feature2'])->get();
        $this->assertCount(0, $models);
    }

    public function test_tenant_capability_scopes(): void
    {
        $this->actingAsUser($this->user);

        $models = TestModel::tenantCapabilities(['capability1'])->get();
        $this->assertCount(1, $models);

        $models = TestModel::tenantCapabilities(['capability2'])->get();
        $this->assertCount(0, $models);
    }

    public function test_tenant_subscription_scopes(): void
    {
        $this->actingAsUser($this->user);

        $models = TestModel::tenantPlan('premium')->get();
        $this->assertCount(1, $models);

        $models = TestModel::tenantSubscriptionStatus('active')->get();
        $this->assertCount(1, $models);
    }

    public function test_tenant_domain_scopes(): void
    {
        $this->actingAsUser($this->user);

        $models = TestModel::tenantDomains(['test.example.com'])->get();
        $this->assertCount(1, $models);

        $models = TestModel::tenantDomains(['other.example.com'])->get();
        $this->assertCount(0, $models);
    }

    public function test_tenant_active_status_scopes(): void
    {
        $this->actingAsUser($this->user);

        $models = TestModel::activeTenants()->get();
        $this->assertCount(1, $models);

        $this->tenant->update(['is_active' => false]);

        $models = TestModel::activeTenants()->get();
        $this->assertCount(0, $models);

        $models = TestModel::inactiveTenants()->get();
        $this->assertCount(1, $models);
    }

    public function test_belongs_to_tenant_check(): void
    {
        $otherTenant = Tenant::factory()->create();

        $this->assertTrue($this->model->belongsToTenant($this->tenant));
        $this->assertFalse($this->model->belongsToTenant($otherTenant));
    }

    public function test_belongs_to_current_tenant_check(): void
    {
        $this->actingAsUser($this->user);

        $this->assertTrue($this->model->belongsToCurrentTenant());

        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->forTenant($otherTenant)->create();
        $this->actingAsUser($otherUser);

        $this->assertFalse($this->model->belongsToCurrentTenant());
    }

    public function test_force_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $this->model->forceTenant($otherTenant);

        $this->assertEquals($otherTenant->id, $this->model->tenant_id);
    }

    public function test_remove_tenant(): void
    {
        $this->model->removeTenant();

        $this->assertNull($this->model->tenant_id);
    }
}
