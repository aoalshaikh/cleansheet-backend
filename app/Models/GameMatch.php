<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use InvalidArgumentException;

class GameMatch extends Model
{
    use HasFactory,
        LogsActivity,
        SoftDeletes;

    protected $table = 'matches';

    protected $fillable = [
        'team_id',
        'opponent_team_id',
        'opponent_name',
        'venue',
        'scheduled_at',
        'type',
        'status',
        'home_score',
        'away_score',
        'metadata',
        'notes',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'metadata' => 'array',
        'home_score' => 'integer',
        'away_score' => 'integer',
    ];

    const TYPE_FRIENDLY = 'friendly';
    const TYPE_LEAGUE = 'league';
    const TYPE_CUP = 'cup';
    const TYPE_TOURNAMENT = 'tournament';

    const STATUS_SCHEDULED = 'scheduled';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';

    protected static array $validStatusTransitions = [
        self::STATUS_SCHEDULED => [self::STATUS_IN_PROGRESS, self::STATUS_CANCELLED],
        self::STATUS_IN_PROGRESS => [self::STATUS_COMPLETED, self::STATUS_CANCELLED],
        self::STATUS_COMPLETED => [],
        self::STATUS_CANCELLED => [],
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $match) {
            if (empty($match->status)) {
                $match->status = self::STATUS_SCHEDULED;
            }
        });
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function opponentTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'opponent_team_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(MatchEvent::class, 'match_id');
    }

    public function lineups(): HasMany
    {
        return $this->hasMany(MatchLineup::class, 'match_id');
    }

    public function startingPlayers(): HasMany
    {
        return $this->lineups()->where('status', MatchLineup::STATUS_STARTING);
    }

    public function substitutePlayers(): HasMany
    {
        return $this->lineups()->where('status', MatchLineup::STATUS_SUBSTITUTE);
    }

    public function goals(): HasMany
    {
        return $this->events()->where('type', MatchEvent::TYPE_GOAL);
    }

    public function cards(): HasMany
    {
        return $this->events()->whereIn('type', [
            MatchEvent::TYPE_YELLOW_CARD,
            MatchEvent::TYPE_RED_CARD
        ]);
    }

    public function isScheduled(): bool
    {
        return $this->status === self::STATUS_SCHEDULED;
    }

    public function isInProgress(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function canTransitionTo(string $newStatus): bool
    {
        return in_array($newStatus, static::$validStatusTransitions[$this->status] ?? []);
    }

    protected function validateStatusTransition(string $newStatus): void
    {
        if (!$this->canTransitionTo($newStatus)) {
            throw new InvalidArgumentException(
                "Invalid status transition from {$this->status} to {$newStatus}"
            );
        }
    }

    public function start(): bool
    {
        if (!$this->isScheduled()) {
            return false;
        }

        $this->validateStatusTransition(self::STATUS_IN_PROGRESS);
        
        $this->update(['status' => self::STATUS_IN_PROGRESS]);
        event('match.started', $this);
        
        return true;
    }

    public function complete(): bool
    {
        if (!$this->isInProgress()) {
            return false;
        }

        $this->validateStatusTransition(self::STATUS_COMPLETED);
        
        $this->update(['status' => self::STATUS_COMPLETED]);
        event('match.completed', $this);
        
        return true;
    }

    public function cancel(): bool
    {
        if ($this->isCompleted()) {
            return false;
        }

        $this->validateStatusTransition(self::STATUS_CANCELLED);
        
        $this->update(['status' => self::STATUS_CANCELLED]);
        event('match.cancelled', $this);
        
        return true;
    }

    public function updateScore(int $homeScore, int $awayScore): void
    {
        if ($this->isCompleted() || $this->isCancelled()) {
            throw new InvalidArgumentException("Cannot update score of a {$this->status} match");
        }

        $this->update([
            'home_score' => $homeScore,
            'away_score' => $awayScore,
        ]);

        event('match.score_updated', $this);
    }

    public function addEvent(string $type, int $playerId, int $minute, ?array $metadata = null, ?string $notes = null): MatchEvent
    {
        if (!$this->isInProgress()) {
            throw new InvalidArgumentException("Cannot add events to a {$this->status} match");
        }

        $event = $this->events()->create([
            'type' => $type,
            'player_id' => $playerId,
            'minute' => $minute,
            'metadata' => $metadata,
            'notes' => $notes,
        ]);

        event('match.event_added', [$this, $event]);

        return $event;
    }

    public function setLineup(int $playerId, string $status, ?string $position = null, ?int $jerseyNumber = null): MatchLineup
    {
        if ($this->isCompleted() || $this->isCancelled()) {
            throw new InvalidArgumentException("Cannot modify lineup of a {$this->status} match");
        }

        $lineup = $this->lineups()->updateOrCreate(
            ['player_id' => $playerId],
            [
                'status' => $status,
                'position' => $position,
                'jersey_number' => $jerseyNumber,
            ]
        );

        event('match.lineup_updated', [$this, $lineup]);

        return $lineup;
    }

    public function updatePlayerStatistics(int $playerId, array $statistics): void
    {
        if (!$this->isCompleted()) {
            throw new InvalidArgumentException('Can only update statistics for completed matches');
        }

        $lineup = $this->lineups()->where('player_id', $playerId)->first();
        if ($lineup) {
            $lineup->update(['statistics' => $statistics]);
            event('match.statistics_updated', [$this, $lineup]);
        }
    }

    public function getResult(): ?string
    {
        if (!$this->isCompleted() || is_null($this->home_score) || is_null($this->away_score)) {
            return null;
        }

        if ($this->home_score > $this->away_score) {
            return 'win';
        } elseif ($this->home_score < $this->away_score) {
            return 'loss';
        } else {
            return 'draw';
        }
    }

    public function scopeUpcoming($query)
    {
        return $query->where('status', self::STATUS_SCHEDULED)
            ->where('scheduled_at', '>', now())
            ->orderBy('scheduled_at');
    }

    public function scopePast($query)
    {
        return $query->whereIn('status', [self::STATUS_COMPLETED, self::STATUS_CANCELLED])
            ->orderByDesc('scheduled_at');
    }

    public function scopeForTeam($query, $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeInDateRange($query, $start, $end)
    {
        return $query->whereBetween('scheduled_at', [$start, $end]);
    }

    public function scopeWithFullLineup($query)
    {
        return $query->with(['lineups.player', 'events.player']);
    }
}
