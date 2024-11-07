<?php

namespace App\Services\Player;

use App\Models\PlayerEvaluation;
use App\Models\PlayerSkill;
use App\Models\Skill;
use App\Models\User;
use App\Services\BaseService;
use Illuminate\Support\Facades\DB;

class PlayerService extends BaseService
{
    /**
     * Create a new player evaluation.
     */
    public function createEvaluation(User $player, array $data): PlayerEvaluation
    {
        return DB::transaction(function () use ($player, $data) {
            $evaluation = PlayerEvaluation::create([
                'user_id' => $player->id,
                'evaluated_by' => request()->user()->id,
                'team_id' => $data['team_id'],
                'evaluation_date' => $data['date'] ?? now(),
                'skill_scores' => $data['scores'],
                'total_points' => array_sum($data['scores']),
                'notes' => $data['notes'] ?? null,
            ]);

            // Update player skills based on evaluation
            foreach ($data['scores'] as $skillId => $score) {
                $skill = Skill::find($skillId);
                if (!$skill) continue;

                $playerSkill = PlayerSkill::firstOrCreate(
                    ['user_id' => $player->id, 'skill_id' => $skillId],
                    ['current_level' => 0]
                );

                // Calculate new level (normalized to 0-100)
                $normalizedScore = round(($score / $skill->max_points) * 100);
                $playerSkill->updateLevel($normalizedScore, "Evaluation score: {$score}");
            }

            return $evaluation;
        });
    }

    /**
     * Get player statistics.
     */
    public function getStats(User $player): array
    {
        $attendance = DB::table('team_schedule_attendances')
            ->where('user_id', $player->id)
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN status = "present" THEN 1 ELSE 0 END) as present,
                SUM(CASE WHEN status = "absent" THEN 1 ELSE 0 END) as absent,
                SUM(CASE WHEN status = "late" THEN 1 ELSE 0 END) as late
            ')
            ->first();

        $matchStats = DB::table('match_lineups')
            ->join('matches', 'matches.id', '=', 'match_lineups.match_id')
            ->where('match_lineups.player_id', $player->id)
            ->where('matches.status', 'completed')
            ->selectRaw('
                COUNT(*) as total_matches,
                SUM(CASE WHEN match_lineups.status = "starting" THEN 1 ELSE 0 END) as starting_xi,
                SUM(CASE WHEN match_lineups.status = "substitute" THEN 1 ELSE 0 END) as substitute
            ')
            ->first();

        $events = DB::table('match_events')
            ->where('player_id', $player->id)
            ->selectRaw('
                type,
                COUNT(*) as count
            ')
            ->groupBy('type')
            ->get()
            ->pluck('count', 'type');

        $skillProgress = PlayerSkill::where('user_id', $player->id)
            ->with('skill.category')
            ->get()
            ->groupBy('skill.category.name')
            ->map(function ($skills) {
                return round($skills->avg('current_level'));
            });

        $evaluations = PlayerEvaluation::where('user_id', $player->id)
            ->orderBy('evaluation_date', 'desc')
            ->take(5)
            ->get()
            ->map(function ($evaluation) {
                return [
                    'date' => $evaluation->evaluation_date,
                    'total_points' => $evaluation->total_points,
                    'evaluator' => $evaluation->evaluator->name,
                ];
            });

        return [
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
            'matches' => [
                'total' => $matchStats->total_matches,
                'starting_xi' => $matchStats->starting_xi,
                'substitute' => $matchStats->substitute,
            ],
            'performance' => [
                'goals' => $events['goal'] ?? 0,
                'assists' => $events['assist'] ?? 0,
                'yellow_cards' => $events['yellow_card'] ?? 0,
                'red_cards' => $events['red_card'] ?? 0,
            ],
            'skills' => $skillProgress,
            'recent_evaluations' => $evaluations,
        ];
    }

    /**
     * Get player's attendance history.
     */
    public function getAttendanceHistory(User $player, ?string $startDate = null, ?string $endDate = null): array
    {
        $query = DB::table('team_schedule_attendances')
            ->join('team_schedules', 'team_schedules.id', '=', 'team_schedule_attendances.team_schedule_id')
            ->join('teams', 'teams.id', '=', 'team_schedules.team_id')
            ->where('team_schedule_attendances.user_id', $player->id)
            ->select([
                'team_schedules.starts_at',
                'team_schedules.title',
                'team_schedules.type',
                'teams.name as team_name',
                'team_schedule_attendances.status',
                'team_schedule_attendances.notes',
            ])
            ->orderBy('team_schedules.starts_at', 'desc');

        if ($startDate) {
            $query->where('team_schedules.starts_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('team_schedules.starts_at', '<=', $endDate);
        }

        return $query->get()->map(function ($record) {
            return [
                'date' => $record->starts_at,
                'event' => $record->title,
                'type' => $record->type,
                'team' => $record->team_name,
                'status' => $record->status,
                'notes' => $record->notes,
            ];
        })->toArray();
    }

    /**
     * Get player's skill progress.
     */
    public function getSkillProgress(User $player): array
    {
        return PlayerSkill::where('user_id', $player->id)
            ->with(['skill.category'])
            ->get()
            ->groupBy('skill.category.name')
            ->map(function ($skills) {
                return $skills->map(function ($skill) {
                    return [
                        'name' => $skill->skill->name,
                        'current_level' => $skill->current_level,
                        'target_level' => $skill->target_level,
                        'progress' => $skill->getProgress(),
                        'on_track' => $skill->isOnTrack(),
                        'history' => $skill->progress_history ?? [],
                    ];
                });
            })
            ->toArray();
    }

    /**
     * Update player's skill target.
     */
    public function updateSkillTarget(User $player, Skill $skill, int $targetLevel, ?string $targetDate = null): PlayerSkill
    {
        $playerSkill = PlayerSkill::firstOrCreate(
            ['user_id' => $player->id, 'skill_id' => $skill->id],
            ['current_level' => 0]
        );

        $playerSkill->setTarget($targetLevel, $targetDate);
        return $playerSkill;
    }
}
