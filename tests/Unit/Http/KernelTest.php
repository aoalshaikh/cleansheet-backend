<?php

namespace Tests\Unit\Http;

use App\Http\Kernel;
use App\Http\Middleware\TenantAwarePermissions;
use App\Http\Middleware\TenantMiddleware;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Tests\TestCase;

class KernelTest extends TestCase
{
    private Kernel $kernel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->kernel = $this->app->make(Kernel::class);
    }

    public function test_extends_http_kernel(): void
    {
        $this->assertInstanceOf(HttpKernel::class, $this->kernel);
    }

    public function test_registers_global_middleware(): void
    {
        $middleware = $this->getPrivateProperty($this->kernel, 'middleware');

        $this->assertContains(\App\Http\Middleware\TrustProxies::class, $middleware);
        $this->assertContains(\App\Http\Middleware\PreventRequestsDuringMaintenance::class, $middleware);
        $this->assertContains(\App\Http\Middleware\TrimStrings::class, $middleware);
    }

    public function test_registers_web_middleware_group(): void
    {
        $groups = $this->getPrivateProperty($this->kernel, 'middlewareGroups');

        $this->assertArrayHasKey('web', $groups);
        $this->assertContains(\App\Http\Middleware\EncryptCookies::class, $groups['web']);
        $this->assertContains(\App\Http\Middleware\VerifyCsrfToken::class, $groups['web']);
    }

    public function test_registers_api_middleware_group(): void
    {
        $groups = $this->getPrivateProperty($this->kernel, 'middlewareGroups');

        $this->assertArrayHasKey('api', $groups);
        $this->assertContains(TenantMiddleware::class, $groups['api']);
    }

    public function test_registers_middleware_aliases(): void
    {
        $aliases = $this->getPrivateProperty($this->kernel, 'middlewareAliases');

        $this->assertArrayHasKey('tenant', $aliases);
        $this->assertEquals(TenantMiddleware::class, $aliases['tenant']);

        $this->assertArrayHasKey('role', $aliases);
        $this->assertEquals(TenantAwarePermissions::class, $aliases['role']);

        $this->assertArrayHasKey('permission', $aliases);
        $this->assertEquals(TenantAwarePermissions::class, $aliases['permission']);

        $this->assertArrayHasKey('role_or_permission', $aliases);
        $this->assertEquals(TenantAwarePermissions::class, $aliases['role_or_permission']);
    }

    public function test_sets_middleware_priority(): void
    {
        $priority = $this->getPrivateProperty($this->kernel, 'middlewarePriority');

        // Tenant middleware should come before permission middleware
        $tenantIndex = array_search(TenantMiddleware::class, $priority);
        $permissionIndex = array_search(TenantAwarePermissions::class, $priority);

        $this->assertNotFalse($tenantIndex);
        $this->assertNotFalse($permissionIndex);
        $this->assertLessThan($permissionIndex, $tenantIndex);
    }

    public function test_middleware_groups_have_correct_order(): void
    {
        $groups = $this->getPrivateProperty($this->kernel, 'middlewareGroups');

        // API group should have tenant middleware last
        $apiGroup = $groups['api'];
        $this->assertEquals(TenantMiddleware::class, end($apiGroup));

        // Web group should have correct session handling order
        $webGroup = $groups['web'];
        $sessionStart = array_search(\Illuminate\Session\Middleware\StartSession::class, $webGroup);
        $shareErrors = array_search(\Illuminate\View\Middleware\ShareErrorsFromSession::class, $webGroup);
        $this->assertLessThan($shareErrors, $sessionStart);
    }

    public function test_all_required_middleware_are_registered(): void
    {
        $aliases = $this->getPrivateProperty($this->kernel, 'middlewareAliases');

        $requiredMiddleware = [
            'auth',
            'auth.basic',
            'auth.session',
            'cache.headers',
            'can',
            'guest',
            'password.confirm',
            'signed',
            'throttle',
            'verified',
            'tenant',
            'role',
            'permission',
            'role_or_permission',
        ];

        foreach ($requiredMiddleware as $middleware) {
            $this->assertArrayHasKey(
                $middleware,
                $aliases,
                "Required middleware '{$middleware}' is not registered"
            );
        }
    }

    public function test_middleware_priority_includes_all_critical_middleware(): void
    {
        $priority = $this->getPrivateProperty($this->kernel, 'middlewarePriority');

        $criticalMiddleware = [
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \Illuminate\Auth\Middleware\Authorize::class,
            TenantMiddleware::class,
            TenantAwarePermissions::class,
        ];

        foreach ($criticalMiddleware as $middleware) {
            $this->assertContains(
                $middleware,
                $priority,
                "Critical middleware '{$middleware}' is not in priority list"
            );
        }
    }

    public function test_can_resolve_middleware_from_container(): void
    {
        $middlewareClasses = [
            TenantMiddleware::class,
            TenantAwarePermissions::class,
            \App\Http\Middleware\EncryptCookies::class,
            \App\Http\Middleware\VerifyCsrfToken::class,
            \App\Http\Middleware\TrustProxies::class,
        ];

        foreach ($middlewareClasses as $middlewareClass) {
            $middleware = $this->app->make($middlewareClass);
            $this->assertInstanceOf($middlewareClass, $middleware);
        }
    }

    public function test_middleware_groups_are_properly_configured(): void
    {
        $groups = $this->getPrivateProperty($this->kernel, 'middlewareGroups');

        // Web group configuration
        $this->assertArrayHasKey('web', $groups);
        $webGroup = $groups['web'];
        $this->assertIsArray($webGroup);
        $this->assertNotEmpty($webGroup);

        // API group configuration
        $this->assertArrayHasKey('api', $groups);
        $apiGroup = $groups['api'];
        $this->assertIsArray($apiGroup);
        $this->assertNotEmpty($apiGroup);

        // Specific middleware presence
        $this->assertContains(
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            $apiGroup
        );
        $this->assertContains(
            TenantMiddleware::class,
            $apiGroup
        );
    }

    private function getPrivateProperty(object $object, string $property): mixed
    {
        $reflection = new \ReflectionClass($object);
        $property = $reflection->getProperty($property);
        $property->setAccessible(true);
        return $property->getValue($object);
    }
}
