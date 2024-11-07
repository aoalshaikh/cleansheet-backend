<?php

namespace Tests\Unit\Models;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Tests\Traits\AssertsTenantContext;
use Tests\Traits\InteractsWithAuthentication;
use Tests\Traits\InteractsWithRoles;

class UserTest extends TestCase
{
    use RefreshDatabase, InteractsWithRoles, InteractsWithAuthentication, AssertsTenantContext;

    private User $user;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create([
            'is_active' => true,
            'settings' => [
                'features' => ['feature1' => true],
                'capabilities' => ['capability1' => true],
                'subscription' => [
                    'plan' => 'premium',
                    'status' => 'active',
                ],
            ],
        ]);

        $this->user = User::factory()
            ->forTenant($this->tenant)
            ->create();

        $this->setupRolesAndPermissions();
    }

    public function test_belongs_to_tenant(): void
    {
        $this->assertBelongsToTenant($this->user, $this->tenant);
        $this->assertTrue($this->user->hasTenant());
    }

    public function test_super_admin_check(): void
    {
        $this->assertFalse($this->user->isSuperAdmin());

        $this->createRole('super-admin');
        $this->user->assignRole('super-admin');

        $this->assertTrue($this->user->isSuperAdmin());
    }

    public function test_tenant_authorization(): void
    {
        $permission = Permission::create(['name' => 'test-permission']);
        $role = Role::create(['name' => 'test-role', 'is_tenant_role' => true]);
        $role->givePermissionTo($permission);

        // Test direct permission
        $this->user->givePermissionTo($permission);
        $this->assertTrue($this->user->hasPermissionTo('test-permission'));

        // Test role-based permission
        $this->user->removeRole('test-role');
        $this->user->assignRole('test-role');
        $this->assertTrue($this->user->hasRole('test-role'));
        $this->assertTrue($this->user->hasPermissionTo('test-permission'));
    }

    public function test_tenant_scoped_permissions(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()
            ->forTenant($otherTenant)
            ->create();

        $permission = Permission::create(['name' => 'test-permission']);

        // Give both users the same permission
        $this->user->givePermissionTo($permission);
        $otherUser->givePermissionTo($permission);

        // Each user should have their own tenant-scoped permission
        $this->assertNotEquals(
            $this->user->permissions->first()->id,
            $otherUser->permissions->first()->id
        );
    }

    public function test_tenant_scoped_roles(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()
            ->forTenant($otherTenant)
            ->create();

        $role = Role::create(['name' => 'test-role', 'is_tenant_role' => true]);

        // Assign same role to both users
        $this->user->assignRole($role);
        $otherUser->assignRole($role);

        // Each user should have their own tenant-scoped role
        $this->assertNotEquals(
            $this->user->roles->first()->id,
            $otherUser->roles->first()->id
        );
    }

    public function test_jwt_claims(): void
    {
        $role = Role::create(['name' => 'test-role', 'is_tenant_role' => true]);
        $this->user->assignRole($role);

        $claims = $this->user->getJWTCustomClaims();

        $this->assertEquals($this->tenant->id, $claims['tenant_id']);
        $this->assertEquals($this->user->email, $claims['email']);
        $this->assertEquals(['test-role'], $claims['roles']);
    }

    public function test_user_preferences(): void
    {
        $this->user->setPreference('theme', 'dark');
        $this->assertEquals('dark', $this->user->getPreference('theme'));

        $this->user->removePreference('theme');
        $this->assertNull($this->user->getPreference('theme'));
    }

    public function test_user_settings(): void
    {
        $this->user->setSetting('notifications', true);
        $this->assertTrue($this->user->getSetting('notifications'));

        $this->user->removeSetting('notifications');
        $this->assertNull($this->user->getSetting('notifications'));
    }

    public function test_email_verification(): void
    {
        $this->assertFalse($this->user->hasVerifiedEmail());

        $this->user->markEmailAsVerified();
        $this->assertTrue($this->user->hasVerifiedEmail());
    }

    public function test_phone_verification(): void
    {
        $this->assertFalse($this->user->hasVerifiedPhone());

        $this->user->markPhoneAsVerified();
        $this->assertTrue($this->user->hasVerifiedPhone());
    }

    public function test_display_name(): void
    {
        $this->assertEquals($this->user->name, $this->user->display_name);

        $userWithoutName = User::factory()->create([
            'name' => null,
            'email' => 'test@example.com',
        ]);
        $this->assertEquals('test', $userWithoutName->display_name);
    }

    public function test_avatar_url(): void
    {
        $this->assertNull($this->user->avatar_url);

        $this->user->avatar_path = 'avatars/test.jpg';
        $this->user->save();

        $this->assertEquals(
            url('storage/avatars/test.jpg'),
            $this->user->avatar_url
        );
    }

    public function test_activity_logging(): void
    {
        $this->user->name = 'Updated Name';
        $this->user->save();

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => User::class,
            'subject_id' => $this->user->id,
            'description' => 'User profile updated',
        ]);
    }

    public function test_soft_deletes(): void
    {
        $this->user->delete();

        $this->assertSoftDeleted($this->user);
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => User::class,
            'subject_id' => $this->user->id,
            'description' => 'User account deleted',
        ]);
    }
}
