<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PlayerEvaluation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'evaluated_by',
        'team_id',
        'evaluation_date',
        'skill_scores',
        'total_points',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'evaluation_date' => 'date',
        'skill_scores' => 'array',
        'total_points' => 'integer',
        'metadata' => 'array',
    ];

    public function player()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function evaluator()
    {
        return $this->belongsTo(User::class, 'evaluated_by');
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function updateSkillScores(array $scores)
    {
        $total = array_sum($scores);
        $this->update([
            'skill_scores' => $scores,
            'total_points' => $total,
        ]);

        // Update individual skill levels based on scores
        foreach ($scores as $skillId => $score) {
            $playerSkill = PlayerSkill::firstOrCreate([
                'user_id' => $this->user_id,
                'skill_id' => $skillId,
            ]);

            $skill = Skill::find($skillId);
            if ($skill) {
                $normalizedLevel = round(($score / $skill->max_points) * 100);
                $playerSkill->updateLevel($normalizedLevel, "Evaluation score: {$score}");
            }
        }
    }

    public function getSkillScore($skillId)
    {
        return $this->skill_scores[$skillId] ?? 0;
    }

    public function getAverageScore()
    {
        if (empty($this->skill_scores)) {
            return 0;
        }

        return round($this->total_points / count($this->skill_scores));
    }

    public function scopeForPlayer($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForTeam($query, $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    public function scopeByEvaluator($query, $evaluatorId)
    {
        return $query->where('evaluated_by', $evaluatorId);
    }

    public function scopeInDateRange($query, $start, $end)
    {
        return $query->whereBetween('evaluation_date', [$start, $end]);
    }

    public function scopeLatest($query)
    {
        return $query->orderBy('evaluation_date', 'desc');
    }

    public function scopeWithMinPoints($query, $points)
    {
        return $query->where('total_points', '>=', $points);
    }

    public function scopeWithMaxPoints($query, $points)
    {
        return $query->where('total_points', '<=', $points);
    }

    public function scopeWithSkillScore($query, $skillId, $minScore)
    {
        return $query->where("skill_scores->{$skillId}", '>=', $minScore);
    }

    public function getProgressSinceLastEvaluation()
    {
        $lastEvaluation = self::where('user_id', $this->user_id)
            ->where('evaluation_date', '<', $this->evaluation_date)
            ->latest('evaluation_date')
            ->first();

        if (!$lastEvaluation) {
            return null;
        }

        $progress = [];
        foreach ($this->skill_scores as $skillId => $score) {
            $lastScore = $lastEvaluation->getSkillScore($skillId);
            $progress[$skillId] = [
                'previous' => $lastScore,
                'current' => $score,
                'change' => $score - $lastScore,
            ];
        }

        return [
            'previous_total' => $lastEvaluation->total_points,
            'current_total' => $this->total_points,
            'total_change' => $this->total_points - $lastEvaluation->total_points,
            'skills' => $progress,
        ];
    }
}
