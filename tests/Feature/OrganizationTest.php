<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OrganizationTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $admin;
    private Tenant $tenant;
    private array $organizationData;

    protected function setUp(): void
    {
        parent::setUp();
        
        Storage::fake('public');
        
        $this->tenant = Tenant::factory()->create();
        /** @var User $admin */
        $admin = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $admin->assignRole('admin');
        $this->admin = $admin;

        $this->organizationData = [
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Organization',
            'description' => 'A test organization',
            'settings' => [
                'features' => [
                    'teams' => true,
                    'player_evaluations' => true,
                ],
                'limits' => [
                    'max_teams' => 5,
                    'max_players_per_team' => 25,
                ],
                'notifications' => [
                    'email' => true,
                    'sms' => true,
                ]
            ],
            'is_active' => true
        ];
    }

    public function test_can_create_organization(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/organizations', $this->organizationData);

        $response->assertCreated();
        $this->assertDatabaseHas('organizations', [
            'name' => 'Test Organization',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_can_update_organization(): void
    {
        $organization = Organization::factory()->create([
            'tenant_id' => $this->tenant->id
        ]);

        $updateData = array_merge($this->organizationData, [
            'name' => 'Updated Organization',
        ]);

        $response = $this->actingAs($this->admin)
            ->putJson("/api/organizations/{$organization->id}", $updateData);

        $response->assertOk();
        $this->assertDatabaseHas('organizations', [
            'id' => $organization->id,
            'name' => 'Updated Organization',
        ]);
    }

    public function test_can_upload_organization_logo(): void
    {
        $organization = Organization::factory()->create([
            'tenant_id' => $this->tenant->id
        ]);

        $file = UploadedFile::fake()->image('logo.jpg');

        $response = $this->actingAs($this->admin)
            ->postJson("/api/organizations/{$organization->id}/logo", [
                'logo' => $file
            ]);

        $response->assertOk();
        $organization->refresh();
        $this->assertNotNull($organization->logo_path);
        $this->assertTrue(Storage::disk('public')->exists($organization->logo_path));
    }

    public function test_can_manage_organization_features(): void
    {
        $organization = Organization::factory()->create([
            'tenant_id' => $this->tenant->id,
            'settings' => $this->organizationData['settings']
        ]);

        $this->assertTrue($organization->hasFeature('teams'));
        $this->assertTrue($organization->hasFeature('player_evaluations'));
        $this->assertEquals(5, $organization->getLimit('max_teams'));
        $this->assertTrue($organization->hasNotificationChannel('email'));
    }

    public function test_organization_subscription_status(): void
    {
        $organization = Organization::factory()->create([
            'tenant_id' => $this->tenant->id,
            'subscription_ends_at' => now()->addDays(30)
        ]);

        $this->assertTrue($organization->hasActiveSubscription());
        $this->assertTrue($organization->hasAccess());

        $organization->subscription_ends_at = now()->subDay();
        $organization->save();

        $this->assertFalse($organization->hasActiveSubscription());
        $this->assertTrue($organization->isInTrial()); // Should be in trial as it's a new organization
    }

    public function test_can_manage_organization_relationships(): void
    {
        $organization = Organization::factory()->create([
            'tenant_id' => $this->tenant->id
        ]);

        /** @var User $manager */
        $manager = User::factory()->create(['tenant_id' => $this->tenant->id]);
        /** @var User $coach */
        $coach = User::factory()->create(['tenant_id' => $this->tenant->id]);
        /** @var User $player */
        $player = User::factory()->create(['tenant_id' => $this->tenant->id]);

        $manager->assignRole('manager');
        $coach->assignRole('coach');
        $player->assignRole('player');

        // Save relationships
        $organization->managers()->save($manager);
        $organization->coaches()->save($coach);
        $organization->players()->save($player);

        $this->assertEquals(1, $organization->managers()->count());
        $this->assertEquals(1, $organization->coaches()->count());
        $this->assertEquals(1, $organization->players()->count());
    }

    public function test_cannot_access_organization_from_different_tenant(): void
    {
        $organization = Organization::factory()->create([
            'tenant_id' => $this->tenant->id
        ]);

        $otherTenant = Tenant::factory()->create();
        /** @var User $otherAdmin */
        $otherAdmin = User::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherAdmin->assignRole('admin');

        $response = $this->actingAs($otherAdmin)
            ->getJson("/api/organizations/{$organization->id}");

        $response->assertForbidden();
    }

    public function test_can_soft_delete_organization(): void
    {
        $organization = Organization::factory()->create([
            'tenant_id' => $this->tenant->id
        ]);

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/organizations/{$organization->id}");

        $response->assertOk();
        $this->assertSoftDeleted($organization);
    }

    public function test_organization_scopes(): void
    {
        // Create test organizations
        Organization::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
            'subscription_ends_at' => now()->addMonth()
        ]);

        Organization::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => false
        ]);

        Organization::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
            'subscription_ends_at' => now()->subMonth()
        ]);

        // Test active scope
        $activeOrgs = Organization::query()->active()->get();
        $this->assertEquals(1, $activeOrgs->count());

        // Test hasAccess scope
        $accessibleOrgs = Organization::query()->hasAccess()->get();
        $this->assertEquals(2, $accessibleOrgs->count()); // Includes trial period
    }
}
