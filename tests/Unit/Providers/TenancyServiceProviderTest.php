<?php

namespace Tests\Unit\Providers;

use App\Models\Tenant;
use App\Models\User;
use App\Providers\TenancyServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\InteractsWithAuthentication;
use Tests\Traits\InteractsWithRoles;

class TenancyServiceProviderTest extends TestCase
{
    use RefreshDatabase, InteractsWithRoles, InteractsWithAuthentication;

    private User $user;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create([
            'settings' => [
                'features' => ['test_feature' => true],
                'capabilities' => ['test_capability' => true],
                'subscription' => ['plan' => 'premium'],
            ],
        ]);

        $this->user = User::factory()
            ->forTenant($this->tenant)
            ->create();

        $this->setupRolesAndPermissions();
    }

    public function test_provider_is_registered(): void
    {
        $this->assertTrue(
            $this->app->providerIsLoaded(TenancyServiceProvider::class)
        );
    }

    public function test_config_is_loaded(): void
    {
        $this->assertNotNull(config('tenancy'));
        $this->assertEquals(
            Tenant::class,
            config('tenancy.tenant_model')
        );
    }

    public function test_tenant_singleton_registration(): void
    {
        $this->actingAsUser($this->user);

        $tenant = $this->app->make('tenant');
        $this->assertInstanceOf(Tenant::class, $tenant);
        $this->assertEquals($this->tenant->id, $tenant->id);
    }

    public function test_blade_directives_registration(): void
    {
        $this->actingAsUser($this->user);

        $directives = [
            'tenant',
            'tenantFeature',
            'tenantPlan',
            'tenantCapability',
        ];

        foreach ($directives as $directive) {
            $this->assertTrue(
                Blade::getCustomDirectives()[$directive] instanceof \Closure,
                "Blade directive '{$directive}' is not registered"
            );
        }
    }

    public function test_blade_directives_functionality(): void
    {
        $this->actingAsUser($this->user);

        // @tenant directive
        $this->assertTrue(
            $this->evaluateDirective('@tenant')
        );

        // @tenantFeature directive
        $this->assertTrue(
            $this->evaluateDirective("@tenantFeature('test_feature')")
        );

        // @tenantPlan directive
        $this->assertTrue(
            $this->evaluateDirective("@tenantPlan('premium')")
        );

        // @tenantCapability directive
        $this->assertTrue(
            $this->evaluateDirective("@tenantCapability('test_capability')")
        );
    }

    public function test_model_events_registration(): void
    {
        $this->actingAsUser($this->user);

        $model = new class extends \Illuminate\Database\Eloquent\Model {
            protected $table = 'test_models';
            protected $fillable = ['name', 'tenant_id'];
        };

        $model->name = 'Test';
        $model->save();

        $this->assertEquals($this->tenant->id, $model->tenant_id);
    }

    public function test_query_builder_tenant_macro(): void
    {
        $this->actingAsUser($this->user);

        $query = \Illuminate\Database\Eloquent\Model::query()->tenant();
        $this->assertStringContains(
            'where `tenant_id` = ?',
            $query->toSql()
        );
    }

    public function test_str_tenant_domain_macro(): void
    {
        $domain = Str::tenantDomain('test');
        $this->assertEquals(
            'test.' . config('tenancy.domain.subdomain.suffix'),
            $domain
        );
    }

    public function test_gates_registration(): void
    {
        $this->createRole('super-admin');
        $this->user->assignRole('super-admin');
        $this->actingAsUser($this->user);

        $gates = [
            'impersonate-tenant',
            'switch-tenant',
            'delete-tenant',
            'backup-tenant',
        ];

        foreach ($gates as $gate) {
            $this->assertTrue(
                Gate::has($gate),
                "Gate '{$gate}' is not registered"
            );
        }

        // Test super admin permissions
        $this->assertTrue(Gate::allows('impersonate-tenant'));
        $this->assertTrue(Gate::allows('switch-tenant'));
        $this->assertTrue(Gate::allows('delete-tenant', $this->tenant));
        $this->assertTrue(Gate::allows('backup-tenant', $this->tenant));
    }

    public function test_cache_tags_functionality(): void
    {
        $this->actingAsUser($this->user);

        Cache::tags(['tenant:' . $this->tenant->id])->put('test_key', 'test_value');
        $this->assertEquals(
            'test_value',
            Cache::tags(['tenant:' . $this->tenant->id])->get('test_key')
        );

        // Test cache clearing on tenant update
        $this->tenant->name = 'Updated Name';
        $this->tenant->save();

        $this->assertNull(
            Cache::tags(['tenant:' . $this->tenant->id])->get('test_key')
        );
    }

    public function test_activity_log_tenant_scope(): void
    {
        $this->actingAsUser($this->user);

        activity()
            ->forTenant($this->tenant)
            ->log('Test activity');

        $this->assertDatabaseHas('activity_log', [
            'tenant_id' => $this->tenant->id,
            'description' => 'Test activity',
        ]);
    }

    /**
     * Helper method to evaluate blade directives.
     */
    private function evaluateDirective(string $directive): bool
    {
        $compiled = Blade::compileString($directive);
        return eval('return ' . trim($compiled, ';') . ';');
    }

    /**
     * Helper method to check if a string contains another string.
     */
    private function assertStringContains(string $needle, string $haystack): void
    {
        $this->assertTrue(
            str_contains($haystack, $needle),
            "Failed asserting that '{$haystack}' contains '{$needle}'"
        );
    }
}
