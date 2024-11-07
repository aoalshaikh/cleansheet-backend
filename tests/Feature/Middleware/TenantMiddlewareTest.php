<?php

namespace Tests\Feature\Middleware;

use App\Http\Middleware\TenantMiddleware;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Tests\TestCase;
use Tests\Traits\AssertsTenantContext;
use Tests\Traits\InteractsWithAuthentication;
use Tests\Traits\InteractsWithRoles;

class TenantMiddlewareTest extends TestCase
{
    use RefreshDatabase, 
        InteractsWithRoles, 
        InteractsWithAuthentication,
        AssertsTenantContext;

    private TenantMiddleware $middleware;
    private User $user;
    private Tenant $tenant;
    private Request $request;

    protected function setUp(): void
    {
        parent::setUp();

        $this->middleware = new TenantMiddleware();
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
        ]);
        $this->user = User::factory()
            ->forTenant($this->tenant)
            ->create();
        $this->request = Request::create('/test', 'GET');

        $this->setupRolesAndPermissions();
    }

    public function test_allows_super_admin_without_tenant(): void
    {
        $userWithoutTenant = User::factory()->create(['tenant_id' => null]);
        $this->actingAsUser($userWithoutTenant);
        $this->createRole('super-admin');
        $userWithoutTenant->assignRole('super-admin');

        $response = $this->middleware->handle(
            $this->request,
            fn() => new Response()
        );

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNoTenantContext();
    }

    public function test_allows_user_with_active_tenant(): void
    {
        $this->actingAsUser($this->user);

        $response = $this->middleware->handle(
            $this->request,
            fn() => new Response()
        );

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTenantContext($this->tenant);
        $this->assertBelongsToTenant($this->user, $this->tenant);
        $this->assertHasTenantAccess($this->user, $this->tenant);
        $this->assertTenantActive($this->tenant);
    }

    public function test_denies_user_without_tenant(): void
    {
        $userWithoutTenant = User::factory()->create(['tenant_id' => null]);
        $this->actingAsUser($userWithoutTenant);

        $this->expectException(UnauthorizedException::class);

        $this->middleware->handle(
            $this->request,
            fn() => new Response()
        );
    }

    public function test_denies_user_with_inactive_tenant(): void
    {
        $inactiveTenant = Tenant::factory()->create(['is_active' => false]);
        $userWithInactiveTenant = User::factory()
            ->forTenant($inactiveTenant)
            ->create();
        $this->actingAsUser($userWithInactiveTenant);

        $this->expectException(UnauthorizedException::class);
        $this->assertTenantInactive($inactiveTenant);

        $this->middleware->handle(
            $this->request,
            fn() => new Response()
        );
    }

    public function test_denies_guest(): void
    {
        $this->actingAsGuest();

        $this->expectException(UnauthorizedException::class);

        $this->middleware->handle(
            $this->request,
            fn() => new Response()
        );
    }

    public function test_sets_and_clears_tenant_context(): void
    {
        $this->actingAsUser($this->user);

        $this->middleware->handle(
            $this->request,
            fn() => new Response()
        );

        // Context should be set during request
        $this->assertTenantContext($this->tenant);
        $this->assertTenantHasFeature($this->tenant, 'feature1');
        $this->assertTenantDoesNotHaveFeature($this->tenant, 'feature2');
        $this->assertTenantHasCapability($this->tenant, 'capability1');
        $this->assertTenantDoesNotHaveCapability($this->tenant, 'capability2');
        $this->assertTenantHasPlan($this->tenant, 'premium');
        $this->assertTenantHasSubscriptionStatus($this->tenant, 'active');

        // Context should be cleared after request
        $this->assertNoTenantContext();
    }

    public function test_adds_tenant_headers_to_response(): void
    {
        $this->actingAsUser($this->user);

        $response = $this->middleware->handle(
            $this->request,
            fn() => new Response()
        );

        $this->assertEquals(
            $this->tenant->id,
            $response->headers->get('X-Tenant-ID')
        );
    }

    public function test_fires_tenant_switched_event(): void
    {
        Event::fake();
        $this->actingAsUser($this->user);

        $this->middleware->handle(
            $this->request,
            fn() => new Response()
        );

        Event::assertDispatched('tenant.switched');
    }

    public function test_helper_methods_generate_correct_middleware(): void
    {
        $this->assertEquals(
            TenantMiddleware::class,
            TenantMiddleware::tenant()
        );

        $this->assertEquals(
            TenantMiddleware::class . ':active',
            TenantMiddleware::activeTenant()
        );

        $this->assertEquals(
            TenantMiddleware::class . ':settings,feature1,feature2',
            TenantMiddleware::tenantWithSettings(['feature1', 'feature2'])
        );

        $this->assertEquals(
            TenantMiddleware::class . ':features,feature1,feature2',
            TenantMiddleware::tenantWithFeatures(['feature1', 'feature2'])
        );

        $this->assertEquals(
            TenantMiddleware::class . ':domains,example.com,test.com',
            TenantMiddleware::tenantWithDomains(['example.com', 'test.com'])
        );

        $this->assertEquals(
            TenantMiddleware::class . ':capabilities,cap1,cap2',
            TenantMiddleware::tenantWithCapabilities(['cap1', 'cap2'])
        );

        $this->assertEquals(
            TenantMiddleware::class . ':plan,premium',
            TenantMiddleware::tenantWithPlan('premium')
        );

        $this->assertEquals(
            TenantMiddleware::class . ':subscription,active',
            TenantMiddleware::tenantWithSubscriptionStatus('active')
        );
    }

    public function test_tenant_settings_validation(): void
    {
        $this->actingAsUser($this->user);

        // Test feature checks
        $this->assertTenantHasFeature($this->tenant, 'feature1');
        $this->assertTenantDoesNotHaveFeature($this->tenant, 'feature2');

        // Test capability checks
        $this->assertTenantHasCapability($this->tenant, 'capability1');
        $this->assertTenantDoesNotHaveCapability($this->tenant, 'capability2');

        // Test subscription checks
        $this->assertTenantHasPlan($this->tenant, 'premium');
        $this->assertTenantHasSubscriptionStatus($this->tenant, 'active');
    }

    public function test_tenant_domain_validation(): void
    {
        $this->actingAsUser($this->user);
        $domain = 'test.example.com';
        
        // Add domain to tenant
        $this->tenant->domains = array_merge($this->tenant->domains, [$domain]);
        $this->tenant->save();

        $this->assertTenantHasDomain($this->tenant, $domain);
        $this->assertTenantDoesNotHaveDomain($this->tenant, 'invalid-domain.com');
    }
}
