<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\PlayerEvaluation;
use App\Models\PlayerSkill;
use App\Models\Skill;
use App\Models\SkillCategory;
use App\Models\Team;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class PlayerSkillTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $admin;
    private User $coach;
    private User $player;
    private Team $team;
    private SkillCategory $category;
    private Skill $skill;

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
        $player = User::factory()->create(['tenant_id' => $tenant->id]);
        $player->assignRole('player');
        $this->player = $player;

        $organization = Organization::factory()->create([
            'tenant_id' => $tenant->id
        ]);

        $this->team = Team::factory()->create([
            'organization_id' => $organization->id,
            'coach_id' => $this->coach->id
        ]);

        // Create skill category and skill
        $this->category = SkillCategory::create([
            'name' => 'Technical Skills',
            'slug' => 'technical-skills',
            'description' => 'Technical football skills',
            'max_points' => 100
        ]);

        $this->skill = Skill::create([
            'category_id' => $this->category->id,
            'name' => 'Ball Control',
            'slug' => 'ball-control',
            'description' => 'Ability to control the ball',
            'max_points' => 100,
            'criteria' => [
                'first_touch' => 'Clean first touch',
                'close_control' => 'Maintains close control while dribbling',
                'ball_protection' => 'Effectively shields the ball'
            ]
        ]);
    }

    public function test_can_create_skill_category(): void
    {
        $categoryData = [
            'name' => 'Physical Skills',
            'slug' => 'physical-skills',
            'description' => 'Physical attributes and abilities',
            'max_points' => 100
        ];

        $response = $this->actingAs($this->admin)
            ->postJson('/api/skill-categories', $categoryData);

        $response->assertCreated();
        $this->assertDatabaseHas('skill_categories', [
            'name' => 'Physical Skills',
            'slug' => 'physical-skills'
        ]);
    }

    public function test_can_create_skill(): void
    {
        $skillData = [
            'category_id' => $this->category->id,
            'name' => 'Passing',
            'slug' => 'passing',
            'description' => 'Passing ability',
            'max_points' => 100,
            'criteria' => [
                'accuracy' => 'Pass reaches intended target',
                'power' => 'Appropriate power on passes',
                'vision' => 'Ability to spot passing opportunities'
            ]
        ];

        $response = $this->actingAs($this->admin)
            ->postJson('/api/skills', $skillData);

        $response->assertCreated();
        $this->assertDatabaseHas('skills', [
            'name' => 'Passing',
            'category_id' => $this->category->id
        ]);
    }

    public function test_can_track_player_skill(): void
    {
        $playerSkillData = [
            'user_id' => $this->player->id,
            'skill_id' => $this->skill->id,
            'current_level' => 60,
            'target_level' => 80,
            'target_date' => now()->addMonths(3)->format('Y-m-d')
        ];

        $response = $this->actingAs($this->coach)
            ->postJson('/api/player-skills', $playerSkillData);

        $response->assertCreated();
        $this->assertDatabaseHas('player_skills', [
            'user_id' => $this->player->id,
            'skill_id' => $this->skill->id,
            'current_level' => 60
        ]);
    }

    public function test_can_update_skill_level(): void
    {
        $playerSkill = PlayerSkill::create([
            'user_id' => $this->player->id,
            'skill_id' => $this->skill->id,
            'current_level' => 60
        ]);

        $response = $this->actingAs($this->coach)
            ->putJson("/api/player-skills/{$playerSkill->id}/level", [
                'level' => 70,
                'notes' => 'Improved ball control in training'
            ]);

        $response->assertOk();
        $playerSkill->refresh();
        
        $this->assertEquals(70, $playerSkill->current_level);
        $this->assertNotEmpty($playerSkill->progress_history);
        $this->assertEquals(60, $playerSkill->progress_history[0]['from']);
        $this->assertEquals(70, $playerSkill->progress_history[0]['to']);
    }

    public function test_can_set_skill_target(): void
    {
        $playerSkill = PlayerSkill::create([
            'user_id' => $this->player->id,
            'skill_id' => $this->skill->id,
            'current_level' => 60
        ]);

        $targetDate = now()->addMonths(3)->format('Y-m-d');
        
        $response = $this->actingAs($this->coach)
            ->putJson("/api/player-skills/{$playerSkill->id}/target", [
                'target_level' => 80,
                'target_date' => $targetDate
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('player_skills', [
            'id' => $playerSkill->id,
            'target_level' => 80,
            'target_date' => $targetDate
        ]);
    }

    public function test_can_create_player_evaluation(): void
    {
        $evaluationData = [
            'user_id' => $this->player->id,
            'evaluated_by' => $this->coach->id,
            'team_id' => $this->team->id,
            'evaluation_date' => now()->format('Y-m-d'),
            'skill_scores' => [
                $this->skill->id => 75
            ],
            'notes' => 'Good performance in training session'
        ];

        $response = $this->actingAs($this->coach)
            ->postJson('/api/player-evaluations', $evaluationData);

        $response->assertCreated();
        $this->assertDatabaseHas('player_evaluations', [
            'user_id' => $this->player->id,
            'evaluated_by' => $this->coach->id,
            'team_id' => $this->team->id,
            'total_points' => 75
        ]);
    }

    public function test_can_track_evaluation_progress(): void
    {
        // Create initial evaluation
        $initialEvaluation = PlayerEvaluation::create([
            'user_id' => $this->player->id,
            'evaluated_by' => $this->coach->id,
            'team_id' => $this->team->id,
            'evaluation_date' => now()->subDays(7),
            'skill_scores' => [$this->skill->id => 60],
            'total_points' => 60
        ]);

        // Create new evaluation
        $newEvaluation = PlayerEvaluation::create([
            'user_id' => $this->player->id,
            'evaluated_by' => $this->coach->id,
            'team_id' => $this->team->id,
            'evaluation_date' => now(),
            'skill_scores' => [$this->skill->id => 70],
            'total_points' => 70
        ]);

        $progress = $newEvaluation->getProgressSinceLastEvaluation();

        $this->assertEquals(60, $progress['previous_total']);
        $this->assertEquals(70, $progress['current_total']);
        $this->assertEquals(10, $progress['total_change']);
        $this->assertEquals(10, $progress['skills'][$this->skill->id]['change']);
    }

    public function test_player_skill_scopes(): void
    {
        // Create multiple player skills
        PlayerSkill::create([
            'user_id' => $this->player->id,
            'skill_id' => $this->skill->id,
            'current_level' => 60,
            'target_level' => 80
        ]);

        $skill2 = Skill::create([
            'category_id' => $this->category->id,
            'name' => 'Shooting',
            'slug' => 'shooting',
            'max_points' => 100
        ]);

        PlayerSkill::create([
            'user_id' => $this->player->id,
            'skill_id' => $skill2->id,
            'current_level' => 70
        ]);

        $this->assertEquals(2, PlayerSkill::forPlayer($this->player->id)->count());
        $this->assertEquals(1, PlayerSkill::withTargets()->count());
        $this->assertEquals(1, PlayerSkill::aboveLevel(65)->count());
        $this->assertEquals(1, PlayerSkill::belowLevel(65)->count());
    }

    public function test_evaluation_scopes(): void
    {
        // Create multiple evaluations
        PlayerEvaluation::create([
            'user_id' => $this->player->id,
            'evaluated_by' => $this->coach->id,
            'team_id' => $this->team->id,
            'evaluation_date' => now()->subDays(7),
            'skill_scores' => [$this->skill->id => 60],
            'total_points' => 60
        ]);

        PlayerEvaluation::create([
            'user_id' => $this->player->id,
            'evaluated_by' => $this->coach->id,
            'team_id' => $this->team->id,
            'evaluation_date' => now(),
            'skill_scores' => [$this->skill->id => 70],
            'total_points' => 70
        ]);

        $this->assertEquals(2, PlayerEvaluation::forPlayer($this->player->id)->count());
        $this->assertEquals(2, PlayerEvaluation::forTeam($this->team->id)->count());
        $this->assertEquals(2, PlayerEvaluation::byEvaluator($this->coach->id)->count());
        $this->assertEquals(1, PlayerEvaluation::withMinPoints(65)->count());
        $this->assertEquals(1, PlayerEvaluation::withMaxPoints(65)->count());
    }
}
