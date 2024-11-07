<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PlayerSkill extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'skill_id',
        'current_level',
        'target_level',
        'target_date',
        'progress_history',
        'metadata',
    ];

    protected $casts = [
        'current_level' => 'integer',
        'target_level' => 'integer',
        'target_date' => 'date',
        'progress_history' => 'array',
        'metadata' => 'array',
    ];

    public function player()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function skill()
    {
        return $this->belongsTo(Skill::class);
    }

    public function updateLevel($newLevel, $notes = null)
    {
        $oldLevel = $this->current_level;
        $this->progress_history = array_merge($this->progress_history ?? [], [
            [
                'date' => now()->toDateString(),
                'from' => $oldLevel,
                'to' => $newLevel,
                'notes' => $notes,
            ]
        ]);

        $this->current_level = $newLevel;
        $this->save();
    }

    public function setTarget($level, $date = null)
    {
        $this->update([
            'target_level' => $level,
            'target_date' => $date,
        ]);
    }

    public function clearTarget()
    {
        $this->update([
            'target_level' => null,
            'target_date' => null,
        ]);
    }

    public function getProgress()
    {
        if (!$this->target_level) {
            return null;
        }

        $totalLevels = $this->target_level - $this->progress_history[0]['from'];
        $achievedLevels = $this->current_level - $this->progress_history[0]['from'];

        return ($totalLevels > 0) ? ($achievedLevels / $totalLevels) * 100 : 0;
    }

    public function isOnTrack()
    {
        if (!$this->target_date || !$this->target_level) {
            return null;
        }

        $totalDays = now()->diffInDays($this->created_at);
        $daysUntilTarget = now()->diffInDays($this->target_date);
        $expectedProgress = ($totalDays / ($totalDays + $daysUntilTarget)) * 100;

        return $this->getProgress() >= $expectedProgress;
    }

    public function scopeForPlayer($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForSkill($query, $skillId)
    {
        return $query->where('skill_id', $skillId);
    }

    public function scopeWithTargets($query)
    {
        return $query->whereNotNull('target_level');
    }

    public function scopeByLevel($query, $level)
    {
        return $query->where('current_level', $level);
    }

    public function scopeAboveLevel($query, $level)
    {
        return $query->where('current_level', '>', $level);
    }

    public function scopeBelowLevel($query, $level)
    {
        return $query->where('current_level', '<', $level);
    }
}
