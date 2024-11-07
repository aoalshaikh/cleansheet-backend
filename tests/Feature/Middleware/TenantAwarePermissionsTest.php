<?php

namespace Tests\Feature\Middleware;

use App\Http\Middleware\TenantAwarePermissions;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Tests\TestCase;
use Tests\Traits\InteractsWithRoles;

class TenantAwarePermissionsTest extends TestCase
{
    use RefreshDatabase, InteractsWithRoles;

    private TenantAwarePermissions $middleware;
    private User $user;
    private Tenant $tenant;
    private Request $request;

    protected function setUp(): void
    {
        parent::setUp();

        $this->middleware = new TenantAwarePermissions();
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()
            ->forTenant($this->tenant)
            ->create();
        $this->request = Request::create('/test', 'GET');

        $this->setupRolesAndPermissions();
    }

    public function test_allows_super_admin_without_permission(): void
    {
        $this->user->assignRole('super-admin');
        Auth::login($this->user);

        $response = $this->middleware->handle(
            $this->request,
            fn() => response('OK'),
            'test-permission'
        );

        $this->assertEquals('OK', $response->getContent());
    }

    public function test_allows_user_with_permission(): void
    {
        $this->user->givePermissionTo('test-permission');
        Auth::login($this->user);

        $response = $this->middleware->handle(
            $this->request,
            fn() => response('OK'),
            'test-permission'
        );

        $this->assertEquals('OK', $response->getContent());
    }

    public function test_denies_user_without_permission(): void
    {
        Auth::login($this->user);

        $this->expectException(UnauthorizedException::class);

        $this->middleware->handle(
            $this->request,
            fn() => response('OK'),
            'test-permission'
        );
    }

    public function test_denies_guest(): void
    {
        $this->expectException(UnauthorizedException::class);

        $this->middleware->handle(
            $this->request,
            fn() => response('OK'),
            'test-permission'
        );
    }

    public function test_denies_user_without_tenant(): void
    {
        $userWithoutTenant = User::factory()
            ->create(['tenant_id' => null]);
        Auth::login($userWithoutTenant);

        $this->expectException(UnauthorizedException::class);

        $this->middleware->handle(
            $this->request,
            fn() => response('OK'),
            'test-permission'
        );
    }

    public function test_allows_user_with_any_permission(): void
    {
        $this->user->givePermissionTo('permission1');
        Auth::login($this->user);

        $response = $this->middleware->handle(
            $this->request,
            fn() => response('OK'),
            'permission1|permission2'
        );

        $this->assertEquals('OK', $response->getContent());
    }

    public function test_denies_user_without_any_permission(): void
    {
        Auth::login($this->user);

        $this->expectException(UnauthorizedException::class);

        $this->middleware->handle(
            $this->request,
            fn() => response('OK'),
            'permission1|permission2'
        );
    }

    public function test_respects_tenant_boundaries(): void
    {
        // Create another tenant and user
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()
            ->forTenant($otherTenant)
            ->create();

        // Give both users the same permission
        $this->user->givePermissionTo('test-permission');
        $otherUser->givePermissionTo('test-permission');

        // Test first user
        Auth::login($this->user);
        $response = $this->middleware->handle(
            $this->request,
            fn() => response('OK'),
            'test-permission'
        );
        $this->assertEquals('OK', $response->getContent());

        // Test second user
        Auth::login($otherUser);
        $response = $this->middleware->handle(
            $this->request,
            fn() => response('OK'),
            'test-permission'
        );
        $this->assertEquals('OK', $response->getContent());

        // Verify permissions are tenant-scoped
        $this->assertNotEquals(
            $this->user->getPermissionNames(),
            $otherUser->getPermissionNames()
        );
    }

    public function test_helper_methods_generate_correct_middleware(): void
    {
        // Test single permission
        $this->assertEquals(
            TenantAwarePermissions::class . ':test-permission',
            TenantAwarePermissions::permission('test-permission')
        );

        // Test multiple permissions (AND)
        $this->assertEquals(
            [
                TenantAwarePermissions::class . ':permission1',
                TenantAwarePermissions::class . ':permission2',
            ],
            TenantAwarePermissions::allOf(['permission1', 'permission2'])
        );

        // Test multiple permissions (OR)
        $this->assertEquals(
            TenantAwarePermissions::class . ':permission1|permission2',
            TenantAwarePermissions::anyOf(['permission1', 'permission2'])
        );

        // Test with guard
        $this->assertEquals(
            TenantAwarePermissions::class . ':test-permission,api',
            TenantAwarePermissions::permission('test-permission', 'api')
        );
    }

    public function test_tenant_specific_helper_methods(): void
    {
        // Test tenant permission
        $this->assertEquals(
            TenantAwarePermissions::class . ':test-permission',
            TenantAwarePermissions::tenantPermission('test-permission')
        );

        // Test tenant role
        $this->assertEquals(
            TenantAwarePermissions::class . ':test-role',
            TenantAwarePermissions::tenantRole('test-role')
        );

        // Test multiple tenant permissions (AND)
        $this->assertEquals(
            [
                TenantAwarePermissions::class . ':permission1',
                TenantAwarePermissions::class . ':permission2',
            ],
            TenantAwarePermissions::allTenantPermissions(['permission1', 'permission2'])
        );

        // Test multiple tenant permissions (OR)
        $this->assertEquals(
            TenantAwarePermissions::class . ':permission1|permission2',
            TenantAwarePermissions::anyTenantPermission(['permission1', 'permission2'])
        );

        // Test multiple tenant roles (AND)
        $this->assertEquals(
            [
                TenantAwarePermissions::class . ':role1',
                TenantAwarePermissions::class . ':role2',
            ],
            TenantAwarePermissions::allTenantRoles(['role1', 'role2'])
        );

        // Test multiple tenant roles (OR)
        $this->assertEquals(
            TenantAwarePermissions::class . ':role1|role2',
            TenantAwarePermissions::anyTenantRole(['role1', 'role2'])
        );
    }
}
