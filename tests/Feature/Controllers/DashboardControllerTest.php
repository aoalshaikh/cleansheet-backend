<?php

namespace Tests\Feature\Controllers;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Tests\Traits\InteractsWithRoles;
use Tests\Traits\InteractsWithTenant;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase, InteractsWithRoles, InteractsWithTenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenant([
            'settings' => [
                'features' => [
                    'dashboard' => true,
                    'api_access' => true,
                    'file_uploads' => false,
                ],
                'capabilities' => [
                    'max_users' => 5,
                    'max_storage' => '1GB',
                ],
            ],
        ]);

        $this->setupRolesAndPermissions();
    }

    public function test_dashboard_displays_for_authenticated_user(): void
    {
        $this->actingAsTenantUser();

        $response = $this->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertViewIs('dashboard');
        $response->assertViewHas('user', $this->getCurrentTenantUser());
        $response->assertViewHas('tenant', $this->getCurrentTenant());
    }

    public function test_dashboard_shows_tenant_stats(): void
    {
        $this->actingAsTenantUser();

        // Create additional users and roles
        $this->createTenantUsers(2);
        Role::create(['name' => 'test-role', 'guard_name' => 'web']);

        // Create some activity
        activity()
            ->causedBy($this->getCurrentTenantUser())
            ->forTenant($this->getCurrentTenant())
            ->log('Test activity');

        $response = $this->get(route('dashboard'));

        $response->assertViewHas('stats', function ($stats) {
            return $stats['users'] === 3 && // Including the main user
                   $stats['roles'] === 1 &&
                   $stats['activities'] === 1;
        });
    }

    public function test_dashboard_shows_recent_activities(): void
    {
        $this->actingAsTenantUser();

        // Create multiple activities
        for ($i = 1; $i <= 10; $i++) {
            activity()
                ->causedBy($this->getCurrentTenantUser())
                ->forTenant($this->getCurrentTenant())
                ->log("Activity {$i}");
        }

        $response = $this->get(route('dashboard'));

        $response->assertViewHas('recentActivities', function ($activities) {
            return $activities->count() === 5 && // Only last 5 activities
                   $activities->first()->description === 'Activity 10';
        });
    }

    public function test_dashboard_shows_enabled_features(): void
    {
        $this->actingAsTenantUser();

        $response = $this->get(route('dashboard'));

        $response->assertViewHas('features', function ($features) {
            return $features->contains('dashboard') &&
                   $features->contains('api_access') &&
                   !$features->contains('file_uploads');
        });

        $this->assertTenantHasFeature('dashboard');
        $this->assertTenantHasFeature('api_access');
    }

    public function test_dashboard_shows_tenant_capabilities(): void
    {
        $this->actingAsTenantUser();

        $response = $this->get(route('dashboard'));

        $response->assertViewHas('capabilities', function ($capabilities) {
            return $capabilities['max_users'] === 5 &&
                   $capabilities['max_storage'] === '1GB';
        });

        $this->assertTenantHasCapability('max_users', 5);
        $this->assertTenantHasCapability('max_storage', '1GB');
    }

    public function test_guest_cannot_access_dashboard(): void
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_unverified_user_cannot_access_dashboard(): void
    {
        $unverifiedUser = $this->createTenantUser(['email_verified_at' => null]);
        $this->actingAsTenantUser($unverifiedUser);

        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('verification.notice'));
    }

    public function test_user_without_tenant_cannot_access_dashboard(): void
    {
        /** @var User */
        $userWithoutTenant = User::factory()
            ->create(['tenant_id' => null]);

        $this->actingAs($userWithoutTenant);

        $response = $this->get(route('dashboard'));
        $response->assertForbidden();
    }

    public function test_dashboard_with_super_admin(): void
    {
        $this->createRole('super-admin');
        $user = $this->createTenantUser();
        $user->assignRole('super-admin');
        $this->actingAsTenantUser($user);

        $response = $this->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertViewHas('stats');
        $response->assertViewHas('recentActivities');
    }

    public function test_dashboard_with_inactive_tenant(): void
    {
        $this->getCurrentTenant()->update(['is_active' => false]);
        $this->actingAsTenantUser();

        $response = $this->get(route('dashboard'));
        $response->assertForbidden();

        $this->assertTenantInactive();
    }

    public function test_dashboard_respects_tenant_isolation(): void
    {
        $this->actingAsTenantUser();

        // Create another tenant with its own data
        $otherTenant = $this->setUpTenant();
        $otherUser = $this->createTenantUser();
        
        activity()
            ->causedBy($otherUser)
            ->forTenant($otherTenant)
            ->log('Other tenant activity');

        $response = $this->get(route('dashboard'));

        $response->assertViewHas('stats', function ($stats) {
            return $stats['users'] === 1 && // Only users from current tenant
                   $stats['activities'] === 0; // No activities from other tenant
        });
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }
}
