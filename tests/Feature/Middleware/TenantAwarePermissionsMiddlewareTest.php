<?php

namespace Tests\Feature\Middleware;

use App\Http\Middleware\TenantAwarePermissions;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Tests\TestCase;
use Tests\Traits\InteractsWithAuthentication;
use Tests\Traits\InteractsWithRoles;

class TenantAwarePermissionsMiddlewareTest extends TestCase
{
    use RefreshDatabase, InteractsWithRoles, InteractsWithAuthentication;

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

    public function test_super_admin_bypasses_permission_check(): void
    {
        $this->actingAsUser($this->user);
        Role::create(['name' => 'super-admin', 'guard_name' => 'web']);
        $this->user->assignRole('super-admin');

        $response = $this->middleware->handle(
            $this->request,
            fn() => new Response(),
            'test-permission'
        );

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_allows_user_with_permission(): void
    {
        $this->actingAsUser($this->user);
        Permission::create(['name' => 'test-permission', 'guard_name' => 'web']);
        $this->user->givePermissionTo('test-permission');

        $response = $this->middleware->handle(
            $this->request,
            fn() => new Response(),
            'test-permission'
        );

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_denies_user_without_permission(): void
    {
        $this->actingAsUser($this->user);

        $this->expectException(UnauthorizedException::class);

        $this->middleware->handle(
            $this->request,
            fn() => new Response(),
            'test-permission'
        );
    }

    public function test_denies_user_without_tenant(): void
    {
        $userWithoutTenant = User::factory()->create(['tenant_id' => null]);
        $this->actingAsUser($userWithoutTenant);

        $this->expectException(UnauthorizedException::class);

        $this->middleware->handle(
            $this->request,
            fn() => new Response(),
            'test-permission'
        );
    }

    public function test_denies_guest(): void
    {
        $this->actingAsGuest();

        $this->expectException(UnauthorizedException::class);

        $this->middleware->handle(
            $this->request,
            fn() => new Response(),
            'test-permission'
        );
    }

    public function test_handles_multiple_permissions_or(): void
    {
        $this->actingAsUser($this->user);
        Permission::create(['name' => 'permission1', 'guard_name' => 'web']);
        $this->user->givePermissionTo('permission1');

        $response = $this->middleware->handle(
            $this->request,
            fn() => new Response(),
            'permission1|permission2'
        );

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_handles_multiple_permissions_and(): void
    {
        $this->actingAsUser($this->user);
        Permission::create(['name' => 'permission1', 'guard_name' => 'web']);
        Permission::create(['name' => 'permission2', 'guard_name' => 'web']);
        $this->user->syncPermissions(['permission1', 'permission2']);

        $response = $this->middleware->handle(
            $this->request,
            fn() => new Response(),
            'permission1|permission2'
        );

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_respects_tenant_boundaries(): void
    {
        // Create another tenant and user
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()
            ->forTenant($otherTenant)
            ->create();

        // Create permission
        Permission::create(['name' => 'test-permission', 'guard_name' => 'web']);

        // Give both users the same permission
        $this->user->givePermissionTo('test-permission');
        $otherUser->givePermissionTo('test-permission');

        // Test first user
        $this->actingAsUser($this->user);
        $response = $this->middleware->handle(
            $this->request,
            fn() => new Response(),
            'test-permission'
        );
        $this->assertEquals(200, $response->getStatusCode());

        // Test second user
        $this->actingAsUser($otherUser);
        $response = $this->middleware->handle(
            $this->request,
            fn() => new Response(),
            'test-permission'
        );
        $this->assertEquals(200, $response->getStatusCode());

        // Verify permissions are tenant-scoped
        $this->assertNotEquals(
            $this->user->permissions->first()->id,
            $otherUser->permissions->first()->id
        );
    }

    public function test_handles_role_check(): void
    {
        $this->actingAsUser($this->user);
        Role::create(['name' => 'test-role', 'guard_name' => 'web', 'is_tenant_role' => true]);
        $this->user->assignRole('test-role');

        $response = $this->middleware->handle(
            $this->request,
            fn() => new Response(),
            'role:test-role'
        );

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_handles_role_or_permission(): void
    {
        $this->actingAsUser($this->user);
        Permission::create(['name' => 'test-permission', 'guard_name' => 'web']);
        $this->user->givePermissionTo('test-permission');

        $response = $this->middleware->handle(
            $this->request,
            fn() => new Response(),
            'role_or_permission:test-role|test-permission'
        );

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_handles_guard_specification(): void
    {
        $this->actingAsUser($this->user);
        Permission::create(['name' => 'test-permission', 'guard_name' => 'web']);
        $this->user->givePermissionTo('test-permission');

        $response = $this->middleware->handle(
            $this->request,
            fn() => new Response(),
            'test-permission,web'
        );

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_helper_methods_generate_correct_middleware(): void
    {
        $this->assertEquals(
            TenantAwarePermissions::class . ':test-permission',
            TenantAwarePermissions::permission('test-permission')
        );

        $this->assertEquals(
            TenantAwarePermissions::class . ':test-role',
            TenantAwarePermissions::role('test-role')
        );

        $this->assertEquals(
            TenantAwarePermissions::class . ':test-role|test-permission',
            TenantAwarePermissions::anyOf(['test-role', 'test-permission'])
        );
    }
}
