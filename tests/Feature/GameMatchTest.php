<?php

namespace Tests\Feature;

use App\Models\GameMatch;
use App\Models\MatchEvent;
use App\Models\MatchLineup;
use App\Models\Organization;
use App\Models\Team;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use InvalidArgumentException;
use Tests\TestCase;

class GameMatchTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $admin;
    private User $coach;
    private Team $team;
    private Team $opponentTeam;
    private GameMatch $match;
    private array $players = [];

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

        $organization = Organization::factory()->create([
            'tenant_id' => $tenant->id
        ]);

        $this->team = Team::factory()->create([
            'organization_id' => $organization->id,
            'coach_id' => $this->coach->id
        ]);

        $this->opponentTeam = Team::factory()->create([
            'organization_id' => $organization->id,
            'coach_id' => $this->coach->id
        ]);

        // Create test players
        for ($i = 0; $i < 5; $i++) {
            /** @var User $player */
            $player = User::factory()->create(['tenant_id' => $tenant->id]);
            $player->assignRole('player');
            $this->players[] = $player;
            $this->team->addPlayer($player);
        }

        // Create a test match
        $this->match = GameMatch::create([
            'team_id' => $this->team->id,
            'opponent_team_id' => $this->opponentTeam->id,
            'venue' => 'Test Stadium',
            'scheduled_at' => now()->addDays(1),
            'type' => GameMatch::TYPE_LEAGUE,
            'status' => GameMatch::STATUS_SCHEDULED
        ]);
    }

    public function test_match_status_transitions(): void
    {
        // Test valid transitions
        $this->assertTrue($this->match->isScheduled());
        $this->assertTrue($this->match->start());
        $this->assertTrue($this->match->isInProgress());
        $this->assertTrue($this->match->complete());
        $this->assertTrue($this->match->isCompleted());

        // Test invalid transitions
        $newMatch = GameMatch::create([
            'team_id' => $this->team->id,
            'scheduled_at' => now()->addDays(1),
            'venue' => 'Test Venue',
            'type' => GameMatch::TYPE_FRIENDLY
        ]);

        $this->expectException(InvalidArgumentException::class);
        $newMatch->complete();
    }

    public function test_match_cancellation(): void
    {
        // Can cancel scheduled match
        $this->assertTrue($this->match->cancel());
        $this->assertTrue($this->match->isCancelled());

        // Cannot cancel completed match
        $match = GameMatch::create([
            'team_id' => $this->team->id,
            'scheduled_at' => now()->addDays(1),
            'venue' => 'Test Venue',
            'type' => GameMatch::TYPE_FRIENDLY,
            'status' => GameMatch::STATUS_COMPLETED
        ]);

        $this->assertFalse($match->cancel());
    }

    public function test_match_score_management(): void
    {
        $this->match->start();

        // Update score during match
        $this->match->updateScore(2, 1);
        $this->assertEquals(2, $this->match->home_score);
        $this->assertEquals(1, $this->match->away_score);

        $this->match->complete();

        // Cannot update score after completion
        $this->expectException(InvalidArgumentException::class);
        $this->match->updateScore(3, 1);
    }

    public function test_match_events_management(): void
    {
        $this->match->start();
        $player = $this->players[0];

        // Add goal event
        $goalEvent = $this->match->addEvent(
            MatchEvent::TYPE_GOAL,
            $player->id,
            15,
            ['assisted_by' => $this->players[1]->id]
        );

        $this->assertTrue($goalEvent->isGoal());
        $this->assertEquals(15, $goalEvent->minute);
        $this->assertEquals($player->id, $goalEvent->player_id);

        // Add card event
        $cardEvent = $this->match->addEvent(
            MatchEvent::TYPE_YELLOW_CARD,
            $player->id,
            30,
            null,
            'Rough tackle'
        );

        $this->assertTrue($cardEvent->isCard());
        $this->assertEquals('Rough tackle', $cardEvent->notes);

        // Cannot add events to completed match
        $this->match->complete();

        $this->expectException(InvalidArgumentException::class);
        $this->match->addEvent(MatchEvent::TYPE_GOAL, $player->id, 80);
    }

    public function test_match_lineup_management(): void
    {
        // Set starting lineup
        $startingLineup = $this->match->setLineup(
            $this->players[0]->id,
            MatchLineup::STATUS_STARTING,
            'forward',
            10
        );

        $this->assertTrue($startingLineup->isStarting());
        $this->assertEquals('forward', $startingLineup->position);
        $this->assertEquals(10, $startingLineup->jersey_number);

        // Set substitute
        $substituteLineup = $this->match->setLineup(
            $this->players[1]->id,
            MatchLineup::STATUS_SUBSTITUTE,
            'midfielder',
            14
        );

        $this->assertTrue($substituteLineup->isSubstitute());

        // Start match and update statistics
        $this->match->start();

        $startingLineup->updateStatistics([
            'minutes_played' => 90,
            'goals' => 1,
            'assists' => 1,
            'shots_on_target' => 3,
            'shots_off_target' => 2,
            'passes_completed' => 45,
            'passes_attempted' => 50
        ]);

        $this->assertEquals(90, $startingLineup->getStatistic('minutes_played'));
        $this->assertEquals(90, $startingLineup->getShotsAccuracy());
        $this->assertEquals(90, $startingLineup->getPassingAccuracy());

        // Cannot modify lineup after match completion
        $this->match->complete();

        $this->expectException(InvalidArgumentException::class);
        $this->match->setLineup($this->players[2]->id, MatchLineup::STATUS_SUBSTITUTE);
    }

    public function test_match_scopes(): void
    {
        // Create matches with different statuses and dates
        GameMatch::create([
            'team_id' => $this->team->id,
            'scheduled_at' => now()->addDays(7),
            'venue' => 'Future Venue',
            'type' => GameMatch::TYPE_LEAGUE,
            'status' => GameMatch::STATUS_SCHEDULED
        ]);

        GameMatch::create([
            'team_id' => $this->team->id,
            'scheduled_at' => now()->subDays(7),
            'venue' => 'Past Venue',
            'type' => GameMatch::TYPE_CUP,
            'status' => GameMatch::STATUS_COMPLETED
        ]);

        // Test scopes
        $this->assertEquals(2, GameMatch::upcoming()->count());
        $this->assertEquals(1, GameMatch::past()->count());
        $this->assertEquals(1, GameMatch::byType(GameMatch::TYPE_CUP)->count());
        $this->assertEquals(2, GameMatch::byType(GameMatch::TYPE_LEAGUE)->count());
        $this->assertEquals(3, GameMatch::forTeam($this->team->id)->count());
    }

    public function test_match_result_calculation(): void
    {
        $this->match->start();
        
        // Home win
        $this->match->updateScore(2, 1);
        $this->match->complete();
        $this->assertEquals('win', $this->match->getResult());

        // Away win
        $match2 = GameMatch::create([
            'team_id' => $this->team->id,
            'scheduled_at' => now(),
            'venue' => 'Test Venue',
            'status' => GameMatch::STATUS_IN_PROGRESS
        ]);
        $match2->updateScore(1, 3);
        $match2->complete();
        $this->assertEquals('loss', $match2->getResult());

        // Draw
        $match3 = GameMatch::create([
            'team_id' => $this->team->id,
            'scheduled_at' => now(),
            'venue' => 'Test Venue',
            'status' => GameMatch::STATUS_IN_PROGRESS
        ]);
        $match3->updateScore(2, 2);
        $match3->complete();
        $this->assertEquals('draw', $match3->getResult());
    }

    public function test_match_statistics_tracking(): void
    {
        $this->match->start();
        $player = $this->players[0];

        // Set lineup
        $lineup = $this->match->setLineup($player->id, MatchLineup::STATUS_STARTING, 'forward', 9);

        // Add events and track statistics
        $this->match->addEvent(MatchEvent::TYPE_GOAL, $player->id, 15);
        $this->match->addEvent(MatchEvent::TYPE_ASSIST, $player->id, 30);
        $this->match->addEvent(MatchEvent::TYPE_YELLOW_CARD, $player->id, 45);

        // Verify statistics were updated
        $this->assertEquals(1, $lineup->fresh()->getStatistic('goals'));
        $this->assertEquals(1, $lineup->fresh()->getStatistic('assists'));
        $this->assertEquals(1, $lineup->fresh()->getStatistic('yellow_cards'));

        // Update detailed statistics
        $lineup->updateStatistics([
            'minutes_played' => 90,
            'shots_on_target' => 4,
            'shots_off_target' => 2,
            'passes_completed' => 40,
            'passes_attempted' => 50
        ]);

        // Verify calculations
        $this->assertEquals(66.67, $lineup->fresh()->getShotsAccuracy());
        $this->assertEquals(80, $lineup->fresh()->getPassingAccuracy());
    }
}
