<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Team;
use App\Models\TeamSchedule;
use App\Models\TeamScheduleAttendance;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class TeamScheduleTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $admin;
    private User $coach;
    private User $player;
    private Team $team;
    private TeamSchedule $schedule;

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

        // Add player to team
        $this->team->players()->attach($this->player->id);

        // Create a schedule
        $this->schedule = TeamSchedule::create([
            'team_id' => $this->team->id,
            'type' => 'practice',
            'title' => 'Regular Practice',
            'description' => 'Weekly practice session',
            'location' => 'Training Ground',
            'starts_at' => now()->addDay()->setHour(16),
            'ends_at' => now()->addDay()->setHour(18),
            'metadata' => [
                'equipment_needed' => ['boots', 'training kit'],
                'focus_areas' => ['passing', 'shooting']
            ]
        ]);
    }

    public function test_can_create_team_schedule(): void
    {
        $scheduleData = [
            'team_id' => $this->team->id,
            'type' => 'fitness',
            'title' => 'Fitness Training',
            'description' => 'Strength and conditioning',
            'location' => 'Gym',
            'starts_at' => now()->addDays(2)->setHour(15)->format('Y-m-d H:i:s'),
            'ends_at' => now()->addDays(2)->setHour(16)->format('Y-m-d H:i:s'),
            'metadata' => [
                'equipment_needed' => ['gym shoes', 'workout clothes'],
                'focus_areas' => ['strength', 'endurance']
            ]
        ];

        $response = $this->actingAs($this->coach)
            ->postJson('/api/schedules', $scheduleData);

        $response->assertCreated();
        $this->assertDatabaseHas('team_schedules', [
            'team_id' => $this->team->id,
            'type' => 'fitness',
            'title' => 'Fitness Training'
        ]);
    }

    public function test_can_update_team_schedule(): void
    {
        $updateData = [
            'title' => 'Updated Practice Session',
            'location' => 'Main Stadium',
            'starts_at' => now()->addDay()->setHour(17)->format('Y-m-d H:i:s'),
            'ends_at' => now()->addDay()->setHour(19)->format('Y-m-d H:i:s')
        ];

        $response = $this->actingAs($this->coach)
            ->putJson("/api/schedules/{$this->schedule->id}", $updateData);

        $response->assertOk();
        $this->assertDatabaseHas('team_schedules', [
            'id' => $this->schedule->id,
            'title' => 'Updated Practice Session',
            'location' => 'Main Stadium'
        ]);
    }

    public function test_can_mark_attendance(): void
    {
        $attendanceData = [
            'user_id' => $this->player->id,
            'status' => 'present',
            'notes' => 'Arrived on time'
        ];

        $response = $this->actingAs($this->coach)
            ->postJson("/api/schedules/{$this->schedule->id}/attendance", $attendanceData);

        $response->assertOk();
        $this->assertDatabaseHas('team_schedule_attendances', [
            'schedule_id' => $this->schedule->id,
            'user_id' => $this->player->id,
            'status' => 'present'
        ]);
    }

    public function test_can_update_attendance(): void
    {
        // First mark attendance
        TeamScheduleAttendance::create([
            'schedule_id' => $this->schedule->id,
            'user_id' => $this->player->id,
            'status' => 'present',
            'notes' => 'On time'
        ]);

        // Update to absent
        $updateData = [
            'user_id' => $this->player->id,
            'status' => 'absent',
            'notes' => 'Called in sick'
        ];

        $response = $this->actingAs($this->coach)
            ->postJson("/api/schedules/{$this->schedule->id}/attendance", $updateData);

        $response->assertOk();
        $this->assertDatabaseHas('team_schedule_attendances', [
            'schedule_id' => $this->schedule->id,
            'user_id' => $this->player->id,
            'status' => 'absent',
            'notes' => 'Called in sick'
        ]);
    }

    public function test_can_get_schedule_attendance(): void
    {
        // Create multiple attendance records
        TeamScheduleAttendance::create([
            'schedule_id' => $this->schedule->id,
            'user_id' => $this->player->id,
            'status' => 'present'
        ]);

        $otherPlayer = User::factory()->create(['tenant_id' => $this->team->organization->tenant_id]);
        $otherPlayer->assignRole('player');
        $this->team->players()->attach($otherPlayer->id);

        TeamScheduleAttendance::create([
            'schedule_id' => $this->schedule->id,
            'user_id' => $otherPlayer->id,
            'status' => 'absent'
        ]);

        $response = $this->actingAs($this->coach)
            ->getJson("/api/schedules/{$this->schedule->id}/attendance");

        $response->assertOk();
        $this->assertEquals(2, count($response->json('data')));
    }

    public function test_can_get_player_attendance_history(): void
    {
        // Create multiple schedules and attendance records
        TeamScheduleAttendance::create([
            'schedule_id' => $this->schedule->id,
            'user_id' => $this->player->id,
            'status' => 'present'
        ]);

        $newSchedule = TeamSchedule::create([
            'team_id' => $this->team->id,
            'type' => 'practice',
            'title' => 'Another Practice',
            'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(2)->addHours(2)
        ]);

        TeamScheduleAttendance::create([
            'schedule_id' => $newSchedule->id,
            'user_id' => $this->player->id,
            'status' => 'absent'
        ]);

        $response = $this->actingAs($this->coach)
            ->getJson("/api/players/{$this->player->id}/attendance");

        $response->assertOk();
        $this->assertEquals(2, count($response->json('data')));
    }

    public function test_can_get_team_schedule(): void
    {
        // Create multiple schedules
        TeamSchedule::create([
            'team_id' => $this->team->id,
            'type' => 'match',
            'title' => 'Friendly Match',
            'starts_at' => now()->addDays(3),
            'ends_at' => now()->addDays(3)->addHours(2)
        ]);

        TeamSchedule::create([
            'team_id' => $this->team->id,
            'type' => 'practice',
            'title' => 'Extra Training',
            'starts_at' => now()->addDays(4),
            'ends_at' => now()->addDays(4)->addHours(2)
        ]);

        $response = $this->actingAs($this->coach)
            ->getJson("/api/teams/{$this->team->id}/schedule");

        $response->assertOk();
        $this->assertEquals(3, count($response->json('data'))); // Including the one from setUp
    }

    public function test_cannot_access_other_team_schedule(): void
    {
        $otherTeam = Team::factory()->create([
            'organization_id' => $this->team->organization_id
        ]);

        $otherSchedule = TeamSchedule::create([
            'team_id' => $otherTeam->id,
            'type' => 'practice',
            'title' => 'Other Team Practice',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHours(2)
        ]);

        $response = $this->actingAs($this->coach)
            ->getJson("/api/schedules/{$otherSchedule->id}");

        $response->assertForbidden();
    }

    public function test_can_delete_schedule(): void
    {
        $response = $this->actingAs($this->coach)
            ->deleteJson("/api/schedules/{$this->schedule->id}");

        $response->assertOk();
        $this->assertSoftDeleted($this->schedule);
        
        // Verify attendance records are kept
        $this->assertDatabaseHas('team_schedule_attendances', [
            'schedule_id' => $this->schedule->id
        ]);
    }
}
