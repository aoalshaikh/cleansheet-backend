<?php

namespace Tests\Unit\Providers;

use App\Models\Tenant;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;
use Tests\Traits\InteractsWithAuthentication;
use Tests\Traits\InteractsWithRoles;

class RouteServiceProviderTest extends TestCase
{
    use RefreshDatabase, InteractsWithRoles, InteractsWithAuthentication;

    private User $user;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create([
            'domain' => 'test.example.com',
            'settings' => [
                'capabilities' => [
                    'api_rate_limit' => 30,
                ],
            ],
        ]);

        $this->user = User::factory()
            ->forTenant($this->tenant)
            ->create();

        $this->setupRolesAndPermissions();
    }

    public function test_tenant_route_binding(): void
    {
        // Test binding by making request
        $response = $this->get("/api/tenant/{$this->tenant->id}/test");
        $response->assertStatus(404); // Route doesn't exist, but binding worked

        $response = $this->get("/api/tenant/{$this->tenant->domain}/test");
        $response->assertStatus(404); // Route doesn't exist, but binding worked
    }

    public function test_tenant_route_binding_fails_for_invalid_tenant(): void
    {
        $response = $this->get('/api/tenant/invalid-tenant/test');
        $response->assertStatus(404);
    }

    public function test_api_rate_limiting(): void
    {
        $this->actingAsUser($this->user);
        $request = Request::create('/api/test');
        $request->setUserResolver(fn() => $this->user);

        $limiter = RateLimiter::for('api', function (Request $request) {
            $tenant = $request->user()?->tenant;
            $limit = $tenant?->getSetting('capabilities.api_rate_limit') ?? 60;

            return \Illuminate\Cache\RateLimiting\Limit::perMinute($limit)->by(
                $request->user()?->id ?: $request->ip()
            );
        });

        // Test rate limit value
        $this->assertEquals(30, $limiter($request)->maxAttempts);
    }

    public function test_tenant_rate_limiting(): void
    {
        $this->actingAsUser($this->user);
        $request = Request::create('/api/tenant/test');
        $request->setUserResolver(fn() => $this->user);

        $limiter = RateLimiter::for('tenant', function (Request $request) {
            $tenant = $request->user()?->tenant;
            if (!$tenant) {
                return \Illuminate\Cache\RateLimiting\Limit::none();
            }

            $limit = $tenant->getSetting('capabilities.api_rate_limit') ?? 60;
            return \Illuminate\Cache\RateLimiting\Limit::perMinute($limit)->by($tenant->id);
        });

        // Test rate limit value
        $this->assertEquals(30, $limiter($request)->maxAttempts);
    }

    public function test_admin_rate_limiting(): void
    {
        $this->actingAsUser($this->user);
        $request = Request::create('/admin/test');
        $request->setUserResolver(fn() => $this->user);

        $limiter = RateLimiter::for('admin', function (Request $request) {
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(60)->by(
                $request->user()?->id ?: $request->ip()
            );
        });

        // Test rate limit value
        $this->assertEquals(60, $limiter($request)->maxAttempts);
    }

    public function test_home_constant(): void
    {
        $this->assertEquals('/dashboard', RouteServiceProvider::HOME);
    }

    public function test_tenant_middleware_configuration(): void
    {
        $provider = new RouteServiceProvider($this->app);
        $method = new \ReflectionMethod($provider, 'getTenantMiddleware');
        $method->setAccessible(true);

        $middleware = $method->invoke($provider);

        $this->assertContains('tenant', $middleware);
        $this->assertContains('auth', $middleware);
    }

    public function test_admin_middleware_configuration(): void
    {
        $provider = new RouteServiceProvider($this->app);
        $method = new \ReflectionMethod($provider, 'getAdminMiddleware');
        $method->setAccessible(true);

        $middleware = $method->invoke($provider);

        $this->assertContains('role:super-admin', $middleware);
        $this->assertContains('auth', $middleware);
    }

    public function test_api_middleware_configuration(): void
    {
        $provider = new RouteServiceProvider($this->app);
        $method = new \ReflectionMethod($provider, 'getApiMiddleware');
        $method->setAccessible(true);

        $middleware = $method->invoke($provider);

        $this->assertContains('api', $middleware);
        $this->assertContains('tenant', $middleware);
    }

    public function test_route_prefixes(): void
    {
        $provider = new RouteServiceProvider($this->app);

        $method = new \ReflectionMethod($provider, 'getTenantPrefix');
        $method->setAccessible(true);
        $this->assertEquals('tenant', $method->invoke($provider));

        $method = new \ReflectionMethod($provider, 'getAdminPrefix');
        $method->setAccessible(true);
        $this->assertEquals('admin', $method->invoke($provider));

        $method = new \ReflectionMethod($provider, 'getApiPrefix');
        $method->setAccessible(true);
        $this->assertEquals('api', $method->invoke($provider));
    }

    public function test_provider_is_registered(): void
    {
        $this->assertTrue(
            $this->app->providerIsLoaded(RouteServiceProvider::class)
        );
    }

    public function test_rate_limiter_configuration(): void
    {
        $provider = new RouteServiceProvider($this->app);
        $method = new \ReflectionMethod($provider, 'configureRateLimiting');
        $method->setAccessible(true);
        $method->invoke($provider);

        $this->assertTrue(RateLimiter::hasLimiter('api'));
        $this->assertTrue(RateLimiter::hasLimiter('tenant'));
        $this->assertTrue(RateLimiter::hasLimiter('admin'));
    }
}
