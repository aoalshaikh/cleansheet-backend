<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TeamSchedule extends Model
{
    use HasFactory,
        LogsActivity,
        SoftDeletes;

    protected $fillable = [
        'team_id',
        'title',
        'description',
        'location',
        'type',
        'starts_at',
        'ends_at',
        'is_recurring',
        'recurrence_pattern',
        'metadata',
        'notify_team',
        'is_cancelled',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_recurring' => 'boolean',
        'recurrence_pattern' => 'array',
        'metadata' => 'array',
        'notify_team' => 'boolean',
        'is_cancelled' => 'boolean',
    ];

    const TYPE_PRACTICE = 'practice';
    const TYPE_MEETING = 'meeting';
    const TYPE_FITNESS = 'fitness';
    const TYPE_OTHER = 'other';

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function attendances()
    {
        return $this->hasMany(TeamScheduleAttendance::class);
    }

    public function presentAttendances()
    {
        return $this->attendances()->where('status', TeamScheduleAttendance::STATUS_PRESENT);
    }

    public function absentAttendances()
    {
        return $this->attendances()->where('status', TeamScheduleAttendance::STATUS_ABSENT);
    }

    public function lateAttendances()
    {
        return $this->attendances()->where('status', TeamScheduleAttendance::STATUS_LATE);
    }

    public function excusedAttendances()
    {
        return $this->attendances()->where('status', TeamScheduleAttendance::STATUS_EXCUSED);
    }

    public function markAttendance($userId, $status, $notes = null, $metadata = null)
    {
        return $this->attendances()->updateOrCreate(
            ['user_id' => $userId],
            [
                'status' => $status,
                'notes' => $notes,
                'metadata' => $metadata,
            ]
        );
    }

    public function cancel()
    {
        $this->update(['is_cancelled' => true]);
    }

    public function restore()
    {
        $this->update(['is_cancelled' => false]);
    }

    public function getDurationInMinutes()
    {
        return $this->starts_at->diffInMinutes($this->ends_at);
    }

    public function getAttendanceRate()
    {
        $total = $this->attendances()->count();
        if ($total === 0) {
            return 0;
        }

        $present = $this->presentAttendances()->count();
        return ($present / $total) * 100;
    }

    public function scopeUpcoming($query)
    {
        return $query->where('starts_at', '>', now())
            ->where('is_cancelled', false)
            ->orderBy('starts_at');
    }

    public function scopePast($query)
    {
        return $query->where('ends_at', '<', now())
            ->orderByDesc('starts_at');
    }

    public function scopeActive($query)
    {
        return $query->where('is_cancelled', false);
    }

    public function scopeCancelled($query)
    {
        return $query->where('is_cancelled', true);
    }

    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeForTeam($query, $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    public function scopeInDateRange($query, $start, $end)
    {
        return $query->whereBetween('starts_at', [$start, $end]);
    }
}
