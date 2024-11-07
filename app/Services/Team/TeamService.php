<?php

namespace App\Services\Team;

use App\Models\Team;
use App\Models\User;
use App\Services\BaseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TeamService extends BaseService
{
    /**
     * Create a new team.
     */
    public function createTeam(array $data): Team
    {
        return DB::transaction(function () use ($data) {
            $team = $this->create([
                'organization_id' => $data['organization_id'],
                'coach_id' => $data['coach_id'] ?? null,
                'name' => $data['name'],
                'slug' => Str::slug($data['name']),
                'description' => $data['description'] ?? null,
                'settings' => array_merge(
                    $data['settings'] ?? [],
                    [
                        'practice' => [
                            'days' => $data['practice_days'] ?? ['monday', 'wednesday', 'friday'],
                            'time' => $data['practice_time'] ?? '16:00',
                            'duration' => $data['practice_duration'] ?? 120,
                        ],
                    ]
                ),
            ]);

            // Create default tier if enabled
            if (!empty($data['create_default_tier'])) {
                $team->tiers()->create([
                    'name' => 'Main Squad',
                    'slug' => 'main-squad',
                    'level' => 0,
                ]);
            }

            return $team;
        });
    }

    /**
     * Add a player to the team.
     */
    public function addPlayer(Team $team, User $player): bool
    {
        if (!$player->hasRole('player')) {
            throw new \InvalidArgumentException('User must have player role');
        }

        if ($team->hasPlayer($player)) {
            throw new \InvalidArgumentException('Player is already in the team');
        }

        $team->addPlayer($player, [
            'joined_at' => now(),
        ]);

        return true;
    }

    /**
     * Remove a player from the team.
     */
    public function removePlayer(Team $team, User $player): bool
    {
        if (!$team->hasPlayer($player)) {
            throw new \InvalidArgumentException('Player is not in the team');
        }

        $team->removePlayer($player);
        return true;
    }

    /**
     * Update team schedule.
     */
    public function updateSchedule(Team $team, array $schedule): bool
    {
        $team->setPracticeSchedule([
            'days' => $schedule['days'],
            'time' => $schedule['time'],
            'duration' => $schedule['duration'],
        ]);

        return true;
    }

    /**
     * Get team statistics.
     */
    public function getStats(Team $team): array
    {
        $players = $team->players()->count();
        $matches = $team->matches()->count();
        $wins = $team->matches()
            ->where('status', 'completed')
            ->where(function ($query) {
                $query->whereColumn('home_score', '>', 'away_score');
            })
            ->count();

        $attendance = DB::table('team_schedule_attendances')
            ->join('team_schedules', 'team_schedules.id', '=', 'team_schedule_attendances.team_schedule_id')
            ->where('team_schedules.team_id', $team->id)
            ->where('team_schedule_attendances.created_at', '>=', now()->subDays(30))
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN status = "present" THEN 1 ELSE 0 END) as present,
                SUM(CASE WHEN status = "absent" THEN 1 ELSE 0 END) as absent,
                SUM(CASE WHEN status = "late" THEN 1 ELSE 0 END) as late
            ')
            ->first();

        $upcomingMatches = $team->matches()
            ->where('status', 'scheduled')
            ->where('scheduled_at', '>', now())
            ->count();

        return [
            'players' => $players,
            'matches' => [
                'total' => $matches,
                'wins' => $wins,
                'win_rate' => $matches > 0 ? round(($wins / $matches) * 100, 2) : 0,
                'upcoming' => $upcomingMatches,
            ],
            'attendance' => [
                'rate' => $attendance->total > 0
                    ? round(($attendance->present / $attendance->total) * 100, 2)
                    : 0,
                'stats' => [
                    'present' => $attendance->present,
                    'absent' => $attendance->absent,
                    'late' => $attendance->late,
                ],
            ],
            'tiers' => [
                'count' => $team->tiers()->count(),
                'players_by_tier' => $team->tiers()
                    ->withCount('activePlayers')
                    ->get()
                    ->pluck('active_players_count', 'name'),
            ],
        ];
    }

    /**
     * Get team schedule for a date range.
     */
    public function getSchedule(Team $team, string $startDate, string $endDate): array
    {
        $schedules = $team->schedules()
            ->whereBetween('starts_at', [$startDate, $endDate])
            ->get();

        $matches = $team->matches()
            ->whereBetween('scheduled_at', [$startDate, $endDate])
            ->get();

        return [
            'schedules' => $schedules->map(function ($schedule) {
                return [
                    'id' => $schedule->id,
                    'type' => $schedule->type,
                    'title' => $schedule->title,
                    'starts_at' => $schedule->starts_at,
                    'ends_at' => $schedule->ends_at,
                    'location' => $schedule->location,
                    'is_cancelled' => $schedule->is_cancelled,
                ];
            }),
            'matches' => $matches->map(function ($match) {
                return [
                    'id' => $match->id,
                    'opponent' => $match->opponent_name ?? $match->opponentTeam?->name,
                    'venue' => $match->venue,
                    'scheduled_at' => $match->scheduled_at,
                    'type' => $match->type,
                    'status' => $match->status,
                ];
            }),
        ];
    }

    /**
     * Check if team has reached its player limit.
     */
    public function hasReachedPlayerLimit(Team $team): bool
    {
        $organization = $team->organization;
        $subscription = $organization->subscriptions()
            ->where('status', 'active')
            ->first();

        if (!$subscription) {
            return true;
        }

        $limits = $subscription->features_snapshot['limits'] ?? [];
        $maxPlayersPerTeam = $limits['max_players_per_team'] ?? 0;

        return $maxPlayersPerTeam > 0 && $team->players()->count() >= $maxPlayersPerTeam;
    }
}
