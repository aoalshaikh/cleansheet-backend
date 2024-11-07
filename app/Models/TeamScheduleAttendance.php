<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TeamScheduleAttendance extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'team_schedule_id',
        'user_id',
        'status',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    const STATUS_PRESENT = 'present';
    const STATUS_ABSENT = 'absent';
    const STATUS_LATE = 'late';
    const STATUS_EXCUSED = 'excused';

    public function schedule()
    {
        return $this->belongsTo(TeamSchedule::class, 'team_schedule_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isPresent()
    {
        return $this->status === self::STATUS_PRESENT;
    }

    public function isAbsent()
    {
        return $this->status === self::STATUS_ABSENT;
    }

    public function isLate()
    {
        return $this->status === self::STATUS_LATE;
    }

    public function isExcused()
    {
        return $this->status === self::STATUS_EXCUSED;
    }

    public function markPresent($notes = null)
    {
        $this->update([
            'status' => self::STATUS_PRESENT,
            'notes' => $notes,
        ]);
    }

    public function markAbsent($notes = null)
    {
        $this->update([
            'status' => self::STATUS_ABSENT,
            'notes' => $notes,
        ]);
    }

    public function markLate($notes = null)
    {
        $this->update([
            'status' => self::STATUS_LATE,
            'notes' => $notes,
        ]);
    }

    public function markExcused($notes = null)
    {
        $this->update([
            'status' => self::STATUS_EXCUSED,
            'notes' => $notes,
        ]);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForSchedule($query, $scheduleId)
    {
        return $query->where('team_schedule_id', $scheduleId);
    }
}
