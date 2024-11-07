<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Team;
use App\Models\TeamTier;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class TeamTierTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $admin;
    private User $coach;
    private User $player;
    private Team $team;
    private TeamTier $beginnerTier;
    private TeamTier $intermediateTier;
    private TeamTier $advancedTier;

    protected function setUp(): void
    {
        parent::setUp();
        
        $tenant = Tenant::factory()->create();
        
        /** @var User $admin */
        $admin = User::factory()->create(['tenant_id' => $tenant->id]);
        $admin->assignRole('admin');
        $this->admin = $admin;

        /** @var User $coach */
        $coach = User::factory()->create(['tenant_id' => $tenant->id]);
        $coach->assignRole('coach');
        $this->coach = $coach;

        /** @var User $player */
        $player = User::factory()->create([
            'tenant_id' => $tenant->id,
            'date_of_birth' => now()->subYears(15)
        ]);
        $player->assignRole('player');
        $this->player = $player;

        $organization = Organization::factory()->create([
            'tenant_id' => $tenant->id
        ]);

        $this->team = Team::factory()->create([
            'organization_id' => $organization->id,
            'coach_id' => $this->coach->id
        ]);

        // Create tier hierarchy
        $this->beginnerTier = TeamTier::create([
            'team_id' => $this->team->id,
            'name' => 'Beginner',
            'description' => 'Entry level tier',
            'level' => 0,
            'min_age' => 13,
            'max_age' => 15,
            'requirements' => [
                'min_skill_level' => 0,
                'max_skill_level' => 30
            ]
        ]);

        $this->intermediateTier = TeamTier::create([
            'team_id' => $this->team->id,
            'parent_tier_id' => $this->beginnerTier->id,
            'name' => 'Intermediate',
            'description' => 'Mid level tier',
            'level' => 1,
            'min_age' => 14,
            'max_age' => 16,
            'requirements' => [
                'min_skill_level' => 31,
                'max_skill_level' => 70
            ]
        ]);

        $this->advancedTier = TeamTier::create([
            'team_id' => $this->team->id,
            'parent_tier_id' => $this->intermediateTier->id,
            'name' => 'Advanced',
            'description' => 'Advanced level tier',
            'level' => 2,
            'min_age' => 15,
            'max_age' => 17,
            'requirements' => [
                'min_skill_level' => 71,
                'max_skill_level' => 100
            ]
        ]);
    }

    public function test_can_create_team_tier(): void
    {
        $tierData = [
            'team_id' => $this->team->id,
            'name' => 'Elite',
            'description' => 'Top level tier',
            'level' => 3,
            'min_age' => 16,
            'max_age' => 18,
            'requirements' => [
                'min_skill_level' => 90,
                'max_skill_level' => 100,
                'min_matches_played' => 20
            ]
        ];

        $response = $this->actingAs($this->coach)
            ->postJson('/api/tiers', $tierData);

        $response->assertCreated();
        $this->assertDatabaseHas('team_tiers', [
            'team_id' => $this->team->id,
            'name' => 'Elite',
            'level' => 3
        ]);
    }

    public function test_can_update_team_tier(): void
    {
        $updateData = [
            'name' => 'Advanced Elite',
            'description' => 'Updated description',
            'min_age' => 16,
            'max_age' => 18,
            'requirements' => [
                'min_skill_level' => 80,
                'max_skill_level' => 100
            ]
        ];

        $response = $this->actingAs($this->coach)
            ->putJson("/api/tiers/{$this->advancedTier->id}", $updateData);

        $response->assertOk();
        $this->assertDatabaseHas('team_tiers', [
            'id' => $this->advancedTier->id,
            'name' => 'Advanced Elite',
            'min_age' => 16,
            'max_age' => 18
        ]);
    }

    public function test_can_assign_player_to_tier(): void
    {
        $response = $this->actingAs($this->coach)
            ->postJson("/api/tiers/{$this->beginnerTier->id}/players/{$this->player->id}", [
                'evaluation' => [
                    'technical_skills' => 25,
                    'tactical_understanding' => 20,
                    'physical_fitness' => 30
                ]
            ]);

        $response->assertOk();
        $this->assertTrue($this->beginnerTier->hasPlayer($this->player->id));
        $this->assertDatabaseHas('team_tier_players', [
            'team_tier_id' => $this->beginnerTier->id,
            'user_id' => $this->player->id,
        ]);
    }

    public function test_can_promote_player(): void
    {
        // First assign to beginner tier
        $this->beginnerTier->assignPlayer($this->player->id, [
            'technical_skills' => 25,
            'tactical_understanding' => 20,
            'physical_fitness' => 30
        ]);

        // Promote to intermediate tier
        $response = $this->actingAs($this->coach)
            ->postJson("/api/tiers/{$this->beginnerTier->id}/players/{$this->player->id}/promote", [
                'evaluation' => [
                    'technical_skills' => 35,
                    'tactical_understanding' => 40,
                    'physical_fitness' => 45
                ]
            ]);

        $response->assertOk();
        $this->assertFalse($this->beginnerTier->hasPlayer($this->player->id));
        $this->assertTrue($this->intermediateTier->hasPlayer($this->player->id));
    }

    public function test_can_demote_player(): void
    {
        // First assign to intermediate tier
        $this->intermediateTier->assignPlayer($this->player->id, [
            'technical_skills' => 35,
            'tactical_understanding' => 40,
            'physical_fitness' => 45
        ]);

        // Demote to beginner tier
        $response = $this->actingAs($this->coach)
            ->postJson("/api/tiers/{$this->intermediateTier->id}/players/{$this->player->id}/demote", [
                'evaluation' => [
                    'technical_skills' => 25,
                    'tactical_understanding' => 20,
                    'physical_fitness' => 30
                ]
            ]);

        $response->assertOk();
        $this->assertFalse($this->intermediateTier->hasPlayer($this->player->id));
        $this->assertTrue($this->beginnerTier->hasPlayer($this->player->id));
    }

    public function test_can_update_player_evaluation(): void
    {
        $this->beginnerTier->assignPlayer($this->player->id, [
            'technical_skills' => 25,
            'tactical_understanding' => 20,
            'physical_fitness' => 30
        ]);

        $newEvaluation = [
            'technical_skills' => 28,
            'tactical_understanding' => 25,
            'physical_fitness' => 32
        ];

        $this->beginnerTier->updatePlayerEvaluation($this->player->id, $newEvaluation);

        $this->assertDatabaseHas('team_tier_players', [
            'team_tier_id' => $this->beginnerTier->id,
            'user_id' => $this->player->id,
            'evaluation->technical_skills' => 28
        ]);
    }

    public function test_tier_hierarchy_relationships(): void
    {
        $this->assertTrue($this->beginnerTier->isTopLevel());
        $this->assertTrue($this->beginnerTier->hasChildTiers());
        $this->assertEquals($this->beginnerTier->id, $this->intermediateTier->parentTier->id);
        $this->assertEquals($this->intermediateTier->id, $this->advancedTier->parentTier->id);

        // Test ancestors
        $ancestors = $this->advancedTier->getAncestors();
        $this->assertEquals(2, $ancestors->count());
        $this->assertTrue($ancestors->contains($this->intermediateTier));
        $this->assertTrue($ancestors->contains($this->beginnerTier));

        // Test descendants
        $descendants = $this->beginnerTier->getDescendants();
        $this->assertEquals(2, $descendants->count());
        $this->assertTrue($descendants->contains($this->intermediateTier));
        $this->assertTrue($descendants->contains($this->advancedTier));
    }

    public function test_tier_scopes(): void
    {
        $this->assertEquals(1, TeamTier::topLevel()->count());
        $this->assertEquals(3, TeamTier::active()->count());
        $this->assertEquals(3, TeamTier::forTeam($this->team->id)->count());
        $this->assertEquals(1, TeamTier::byLevel(2)->count());
        $this->assertEquals(2, TeamTier::inAgeRange(15)->count());
    }

    public function test_cannot_assign_player_outside_age_range(): void
    {
        /** @var User $olderPlayer */
        $olderPlayer = User::factory()->create([
            'tenant_id' => $this->team->organization->tenant_id,
            'date_of_birth' => now()->subYears(20)
        ]);
        $olderPlayer->assignRole('player');

        $response = $this->actingAs($this->coach)
            ->postJson("/api/tiers/{$this->beginnerTier->id}/players/{$olderPlayer->id}");

        $response->assertStatus(422);
        $this->assertFalse($this->beginnerTier->hasPlayer($olderPlayer->id));
    }

    public function test_can_remove_player_from_tier(): void
    {
        $this->beginnerTier->assignPlayer($this->player->id);

        $response = $this->actingAs($this->coach)
            ->deleteJson("/api/tiers/{$this->beginnerTier->id}/players/{$this->player->id}");

        $response->assertOk();
        $this->assertFalse($this->beginnerTier->hasPlayer($this->player->id));
        $this->assertNotNull(
            $this->beginnerTier->players()
                ->wherePivot('user_id', $this->player->id)
                ->first()
                ->pivot
                ->demoted_at
        );
    }
}
