<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use InvalidArgumentException;

class MatchEvent extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'match_id',
        'player_id',
        'type',
        'minute',
        'metadata',
        'notes',
    ];

    protected $casts = [
        'metadata' => 'array',
        'minute' => 'integer',
    ];

    const TYPE_GOAL = 'goal';
    const TYPE_ASSIST = 'assist';
    const TYPE_YELLOW_CARD = 'yellow_card';
    const TYPE_RED_CARD = 'red_card';
    const TYPE_SUBSTITUTION = 'substitution';
    const TYPE_INJURY = 'injury';
    const TYPE_OTHER = 'other';

    protected static array $validTypes = [
        self::TYPE_GOAL,
        self::TYPE_ASSIST,
        self::TYPE_YELLOW_CARD,
        self::TYPE_RED_CARD,
        self::TYPE_SUBSTITUTION,
        self::TYPE_INJURY,
        self::TYPE_OTHER,
    ];

    protected static array $statisticsMapping = [
        self::TYPE_GOAL => 'goals',
        self::TYPE_ASSIST => 'assists',
        self::TYPE_YELLOW_CARD => 'yellow_cards',
        self::TYPE_RED_CARD => 'red_cards',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $event) {
            if (!in_array($event->type, static::$validTypes)) {
                throw new InvalidArgumentException("Invalid event type: {$event->type}");
            }

            if ($event->minute < 0 || $event->minute > 120) {
                throw new InvalidArgumentException("Invalid minute: {$event->minute}");
            }

            // Validate match status
            if (!$event->match?->isInProgress()) {
                throw new InvalidArgumentException("Cannot add events to a {$event->match?->status} match");
            }
        });

        static::created(function (self $event) {
            // Update player statistics
            if (isset(static::$statisticsMapping[$event->type])) {
                $statistic = static::$statisticsMapping[$event->type];
                $lineup = $event->match->lineups()
                    ->where('player_id', $event->player_id)
                    ->first();

                if ($lineup) {
                    $statistics = $lineup->statistics ?? [];
                    $statistics[$statistic] = ($statistics[$statistic] ?? 0) + 1;
                    $lineup->update(['statistics' => $statistics]);
                }
            }

            event('match.event.created', $event);
        });

        static::deleted(function (self $event) {
            // Revert player statistics
            if (isset(static::$statisticsMapping[$event->type])) {
                $statistic = static::$statisticsMapping[$event->type];
                $lineup = $event->match->lineups()
                    ->where('player_id', $event->player_id)
                    ->first();

                if ($lineup) {
                    $statistics = $lineup->statistics ?? [];
                    $statistics[$statistic] = max(0, ($statistics[$statistic] ?? 0) - 1);
                    $lineup->update(['statistics' => $statistics]);
                }
            }

            event('match.event.deleted', $event);
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

    public function isGoal(): bool
    {
        return $this->type === self::TYPE_GOAL;
    }

    public function isAssist(): bool
    {
        return $this->type === self::TYPE_ASSIST;
    }

    public function isCard(): bool
    {
        return in_array($this->type, [self::TYPE_YELLOW_CARD, self::TYPE_RED_CARD]);
    }

    public function isYellowCard(): bool
    {
        return $this->type === self::TYPE_YELLOW_CARD;
    }

    public function isRedCard(): bool
    {
        return $this->type === self::TYPE_RED_CARD;
    }

    public function isSubstitution(): bool
    {
        return $this->type === self::TYPE_SUBSTITUTION;
    }

    public function isInjury(): bool
    {
        return $this->type === self::TYPE_INJURY;
    }

    public function getMetadataValue(string $key, mixed $default = null): mixed
    {
        return data_get($this->metadata, $key, $default);
    }

    public function setMetadataValue(string $key, mixed $value): void
    {
        $metadata = $this->metadata ?? [];
        data_set($metadata, $key, $value);
        $this->metadata = $metadata;
        $this->save();
    }

    public function scopeOfType($query, string $type)
    {
        if (!in_array($type, static::$validTypes)) {
            throw new InvalidArgumentException("Invalid event type: {$type}");
        }
        return $query->where('type', $type);
    }

    public function scopeForPlayer($query, int $playerId)
    {
        return $query->where('player_id', $playerId);
    }

    public function scopeInMinuteRange($query, int $start, int $end)
    {
        return $query->whereBetween('minute', [$start, $end]);
    }

    public function scopeWithPlayerAndMatch($query)
    {
        return $query->with(['player', 'match']);
    }

    public function scopeOrderByMinute($query, string $direction = 'asc')
    {
        return $query->orderBy('minute', $direction);
    }
}
