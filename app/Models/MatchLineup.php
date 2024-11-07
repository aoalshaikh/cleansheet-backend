<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use InvalidArgumentException;

class MatchLineup extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'match_id',
        'player_id',
        'status',
        'position',
        'jersey_number',
        'statistics',
    ];

    protected $casts = [
        'statistics' => 'array',
        'jersey_number' => 'integer',
    ];

    const STATUS_STARTING = 'starting';
    const STATUS_SUBSTITUTE = 'substitute';
    const STATUS_NOT_SELECTED = 'not_selected';

    protected static array $validStatuses = [
        self::STATUS_STARTING,
        self::STATUS_SUBSTITUTE,
        self::STATUS_NOT_SELECTED,
    ];

    protected static array $validPositions = [
        'goalkeeper',
        'defender',
        'midfielder',
        'forward',
    ];

    protected static array $defaultStatistics = [
        'minutes_played' => 0,
        'goals' => 0,
        'assists' => 0,
        'yellow_cards' => 0,
        'red_cards' => 0,
        'saves' => 0,
        'tackles' => 0,
        'interceptions' => 0,
        'fouls_committed' => 0,
        'fouls_suffered' => 0,
        'shots_on_target' => 0,
        'shots_off_target' => 0,
        'passes_completed' => 0,
        'passes_attempted' => 0,
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $lineup) {
            // Validate status
            if (!in_array($lineup->status, static::$validStatuses)) {
                throw new InvalidArgumentException("Invalid status: {$lineup->status}");
            }

            // Validate position if provided
            if ($lineup->position && !in_array($lineup->position, static::$validPositions)) {
                throw new InvalidArgumentException("Invalid position: {$lineup->position}");
            }

            // Initialize statistics
            if (empty($lineup->statistics)) {
                $lineup->statistics = static::$defaultStatistics;
            }

            // Validate match status
            if ($lineup->match && ($lineup->match->isCompleted() || $lineup->match->isCancelled())) {
                throw new InvalidArgumentException("Cannot modify lineup of a {$lineup->match->status} match");
            }
        });

        static::updating(function (self $lineup) {
            // Prevent modifications to completed matches
            if ($lineup->match && ($lineup->match->isCompleted() || $lineup->match->isCancelled())) {
                throw new InvalidArgumentException("Cannot modify lineup of a {$lineup->match->status} match");
            }
        });
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(GameMatch::class, 'match_id');
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(User::class, 'player_id');
    }

    public function isStarting(): bool
    {
        return $this->status === self::STATUS_STARTING;
    }

    public function isSubstitute(): bool
    {
        return $this->status === self::STATUS_SUBSTITUTE;
    }

    public function isNotSelected(): bool
    {
        return $this->status === self::STATUS_NOT_SELECTED;
    }

    public function updateStatistics(array $statistics): void
    {
        if (!$this->match->isInProgress() && !$this->match->isCompleted()) {
            throw new InvalidArgumentException("Cannot update statistics for a {$this->match->status} match");
        }

        $this->statistics = array_merge($this->statistics ?? [], $statistics);
        $this->save();

        event('match.lineup.statistics_updated', $this);
    }

    public function incrementStatistic(string $key, int $value = 1): void
    {
        if (!array_key_exists($key, static::$defaultStatistics)) {
            throw new InvalidArgumentException("Invalid statistic key: {$key}");
        }

        $statistics = $this->statistics;
        $statistics[$key] = ($statistics[$key] ?? 0) + $value;
        $this->statistics = $statistics;
        $this->save();

        event('match.lineup.statistic_incremented', [$this, $key, $value]);
    }

    public function getPassingAccuracy(): ?float
    {
        $attempted = $this->statistics['passes_attempted'] ?? 0;
        if ($attempted === 0) {
            return null;
        }

        $completed = $this->statistics['passes_completed'] ?? 0;
        return round(($completed / $attempted) * 100, 2);
    }

    public function getShotsAccuracy(): ?float
    {
        $total = ($this->statistics['shots_on_target'] ?? 0) + ($this->statistics['shots_off_target'] ?? 0);
        if ($total === 0) {
            return null;
        }

        $onTarget = $this->statistics['shots_on_target'] ?? 0;
        return round(($onTarget / $total) * 100, 2);
    }

    public function getStatistic(string $key): int
    {
        if (!array_key_exists($key, static::$defaultStatistics)) {
            throw new InvalidArgumentException("Invalid statistic key: {$key}");
        }

        return $this->statistics[$key] ?? 0;
    }

    public function scopeStarting($query)
    {
        return $query->where('status', self::STATUS_STARTING);
    }

    public function scopeSubstitutes($query)
    {
        return $query->where('status', self::STATUS_SUBSTITUTE);
    }

    public function scopeByPosition($query, string $position)
    {
        if (!in_array($position, static::$validPositions)) {
            throw new InvalidArgumentException("Invalid position: {$position}");
        }
        return $query->where('position', $position);
    }

    public function scopeForPlayer($query, int $playerId)
    {
        return $query->where('player_id', $playerId);
    }

    public function scopeWithMatchAndPlayer($query)
    {
        return $query->with(['match', 'player']);
    }

    public function scopeOrderByJerseyNumber($query)
    {
        return $query->orderBy('jersey_number');
    }

    public function scopeWithMinimumMinutes($query, int $minutes)
    {
        return $query->where('statistics->minutes_played', '>=', $minutes);
    }
}
