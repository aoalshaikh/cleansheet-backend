<?php

namespace Tests\Feature;

use App\Models\GameMatch;
use App\Models\Organization;
use App\Models\Team;
use App\Models\TeamSchedule;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TeamTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $admin;
    private User $coach;
    private Organization $organization;
    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();
        
        Storage::fake('public');
        
        $tenant = Tenant::factory()->create();
        
        /** @var User $admin */
        $admin = User::factory()->create(['tenant_id' => $tenant->id]);
        $admin->assignRole('admin');
        $this->admin = $admin;

        /** @var User $coach */
        $coach = User::factory()->create(['tenant_id' => $tenant->id]);
        $coach->assignRole('coach');
        $this->coach = $coach;

        $this->organization = Organization::factory()->create([
            'tenant_id' => $tenant->id
        ]);

        $this->team = Team::factory()->create([
            'organization_id' => $this->organization->id,
            'coach_id' => $this->coach->id,
            'name' => 'Test Team',
            'description' => 'A test team',
            'settings' => [
                'practice' => [
                    'days' => ['monday', 'wednesday'],
                    'time' => '17:00',
                    'duration' => 90
                ]
            ]
        ]);
    }

    public function test_can_create_team(): void
    {
        $teamData = [
            'organization_id' => $this->organization->id,
            'coach_id' => $this->coach->id,
            'name' => 'New Team',
            'description' => 'A new team',
            'settings' => [
                'practice' => [
                    'days' => ['tuesday', 'thursday'],
                    'time' => '16:00',
                    'duration' => 120
                ]
            ]
        ];

        $response = $this->actingAs($this->admin)
            ->postJson('/api/teams', $teamData);

        $response->assertCreated();
        $this->assertDatabaseHas('teams', [
            'name' => 'New Team',
            'organization_id' => $this->organization->id
        ]);
    }

    public function test_can_update_team(): void
    {
        $updateData = [
            'name' => 'Updated Team',
            'description' => 'Updated description',
            'settings' => [
                'practice' => [
                    'days' => ['friday'],
                    'time' => '18:00',
                    'duration' => 60
                ]
            ]
        ];

        $response = $this->actingAs($this->admin)
            ->putJson("/api/teams/{$this->team->id}", $updateData);

        $response->assertOk();
        $this->assertDatabaseHas('teams', [
            'id' => $this->team->id,
            'name' => 'Updated Team'
        ]);
    }

    public function test_can_upload_team_logo(): void
    {
        $file = UploadedFile::fake()->image('team-logo.jpg');

        $response = $this->actingAs($this->admin)
            ->postJson("/api/teams/{$this->team->id}/logo", [
                'logo' => $file
            ]);

        $response->assertOk();
        $this->team->refresh();
        $this->assertNotNull($this->team->logo_path);
        $this->assertTrue(Storage::disk('public')->exists($this->team->logo_path));
    }

    public function test_can_manage_team_players(): void
    {
        /** @var User $player1 */
        $player1 = User::factory()->create(['tenant_id' => $this->organization->tenant_id]);
        /** @var User $player2 */
        $player2 = User::factory()->create(['tenant_id' => $this->organization->tenant_id]);
        
        $player1->assignRole('player');
        $player2->assignRole('player');

        // Add players
        $this->team->addPlayer($player1);
        $this->team->addPlayer($player2);

        $this->assertEquals(2, $this->team->activePlayers()->count());
        $this->assertTrue($this->team->hasPlayer($player1));

        // Remove a player
        $this->team->removePlayer($player1);

        $this->assertEquals(1, $this->team->activePlayers()->count());
        $this->assertFalse($this->team->hasPlayer($player1));
        $this->assertTrue($this->team->hasPlayer($player2));
    }

    public function test_can_manage_team_matches(): void
    {
        // Create home match
        $homeMatch = GameMatch::factory()->create([
            'team_id' => $this->team->id,
            'scheduled_at' => now()->addDays(7)
        ]);

        // Create away match
        $awayMatch = GameMatch::factory()->create([
            'opponent_team_id' => $this->team->id,
            'scheduled_at' => now()->addDays(14)
        ]);

        $this->assertEquals(1, $this->team->homeMatches()->count());
        $this->assertEquals(1, $this->team->awayMatches()->count());
        $this->assertEquals(2, $this->team->matches()->count());
    }

    public function test_can_manage_team_schedules(): void
    {
        // Create active schedule
        TeamSchedule::factory()->create([
            'team_id' => $this->team->id,
            'starts_at' => now()->addDays(1),
            'is_cancelled' => false
        ]);

        // Create cancelled schedule
        TeamSchedule::factory()->create([
            'team_id' => $this->team->id,
            'starts_at' => now()->addDays(2),
            'is_cancelled' => true
        ]);

        $upcomingSchedules = $this->team->upcomingSchedules;
        $this->assertEquals(1, $upcomingSchedules->count());
    }

    public function test_team_settings_management(): void
    {
        // Test default settings
        $newTeam = Team::factory()->create([
            'organization_id' => $this->organization->id,
            'coach_id' => $this->coach->id
        ]);

        $this->assertNotNull($newTeam->settings);
        $this->assertTrue($newTeam->hasNotification('practice_reminder'));

        // Test practice schedule
        $schedule = $this->team->getPracticeSchedule();
        $this->assertEquals(['monday', 'wednesday'], $schedule['days']);
        $this->assertEquals('17:00', $schedule['time']);

        // Update practice schedule
        $newSchedule = [
            'days' => ['tuesday', 'thursday'],
            'time' => '18:00',
            'duration' => 120
        ];
        $this->team->setPracticeSchedule($newSchedule);
        $this->team->refresh();

        $updatedSchedule = $this->team->getPracticeSchedule();
        $this->assertEquals(['tuesday', 'thursday'], $updatedSchedule['days']);
        $this->assertEquals('18:00', $updatedSchedule['time']);
    }

    public function test_team_scopes(): void
    {
        // Create inactive team
        Team::factory()->create([
            'organization_id' => $this->organization->id,
            'is_active' => false
        ]);

        // Create team for different organization
        $otherOrg = Organization::factory()->create([
            'tenant_id' => $this->organization->tenant_id
        ]);
        Team::factory()->create([
            'organization_id' => $otherOrg->id,
            'is_active' => true
        ]);

        $this->assertEquals(1, Team::active()->forOrganization($this->organization)->count());
    }

    public function test_team_computed_attributes(): void
    {
        /** @var User $player */
        $player = User::factory()->create(['tenant_id' => $this->organization->tenant_id]);
        $player->assignRole('player');
        $this->team->addPlayer($player);

        // Test active players count
        $this->assertEquals(1, $this->team->active_players_count);

        // Create upcoming match
        GameMatch::factory()->create([
            'team_id' => $this->team->id,
            'status' => GameMatch::STATUS_SCHEDULED,
            'scheduled_at' => now()->addDays(7)
        ]);

        // Create past match
        GameMatch::factory()->create([
            'team_id' => $this->team->id,
            'status' => GameMatch::STATUS_COMPLETED,
            'scheduled_at' => now()->subDays(7)
        ]);

        // Test upcoming matches
        $this->assertEquals(1, $this->team->upcoming_matches->count());
    }

    public function test_team_eager_loading(): void
    {
        $team = Team::with([
            'organization',
            'coach',
            'activePlayers',
            'matches' => function ($query) {
                $query->upcoming();
            },
            'schedules' => function ($query) {
                $query->upcoming();
            }
        ])->find($this->team->id);

        $this->assertTrue($team->relationLoaded('organization'));
        $this->assertTrue($team->relationLoaded('coach'));
        $this->assertTrue($team->relationLoaded('activePlayers'));
        $this->assertTrue($team->relationLoaded('matches'));
        $this->assertTrue($team->relationLoaded('schedules'));
    }

    public function test_cannot_access_team_from_different_organization(): void
    {
        $otherOrg = Organization::factory()->create([
            'tenant_id' => $this->organization->tenant_id
        ]);

        /** @var User $otherAdmin */
        $otherAdmin = User::factory()->create(['tenant_id' => $this->organization->tenant_id]);
        $otherAdmin->assignRole('admin');

        $response = $this->actingAs($otherAdmin)
            ->getJson("/api/teams/{$this->team->id}");

        $response->assertForbidden();
    }

    public function test_can_soft_delete_team(): void
    {
        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/teams/{$this->team->id}");

        $response->assertOk();
        $this->assertSoftDeleted($this->team);
        
        // Verify relationships are maintained
        $this->assertDatabaseHas('team_player', [
            'team_id' => $this->team->id
        ]);
    }
}
