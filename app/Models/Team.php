<?php

namespace App\Models;

use App\Traits\HasSettings;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Team extends Model
{
    use HasFactory,
        HasSettings,
        LogsActivity,
        SoftDeletes;

    protected $fillable = [
        'organization_id',
        'coach_id',
        'name',
        'slug',
        'description',
        'logo_path',
        'settings',
        'metadata',
        'is_active',
    ];

    protected $casts = [
        'settings' => 'array',
        'metadata' => 'array',
        'is_active' => 'boolean',
    ];

    protected $with = ['coach'];

    protected $defaultSettings = [
        'practice' => [
            'days' => ['monday', 'wednesday', 'friday'],
            'time' => '16:00',
            'duration' => 120,
        ],
        'notifications' => [
            'practice_reminder' => true,
            'match_reminder' => true,
            'evaluation_results' => true,
        ],
        'evaluation' => [
            'frequency' => 'weekly',
            'metrics' => [
                'technique' => true,
                'tactical' => true,
                'physical' => true,
                'mental' => true,
            ],
        ],
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (self $team) {
            if (empty($team->slug)) {
                $team->slug = Str::slug($team->name);
            }

            if (empty($team->settings)) {
                $team->settings = $team->defaultSettings;
            } else {
                $team->settings = array_merge($team->defaultSettings, $team->settings);
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_id');
    }

    public function players(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_player')
            ->withTimestamps();
    }

    public function matches(): HasMany
    {
        return $this->hasMany(GameMatch::class);
    }

    public function homeMatches(): HasMany
    {
        return $this->hasMany(GameMatch::class, 'team_id');
    }

    public function awayMatches(): HasMany
    {
        return $this->hasMany(GameMatch::class, 'opponent_team_id');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(TeamSchedule::class);
    }

    public function tiers(): HasMany
    {
        return $this->hasMany(TeamTier::class);
    }

    public function activePlayers(): BelongsToMany
    {
        return $this->players()
            ->wherePivotNull('left_at');
    }

    public function getPracticeSchedule(): array
    {
        return $this->getSetting('practice', [
            'days' => [],
            'time' => '00:00',
            'duration' => 0,
        ]);
    }

    public function setPracticeSchedule(array $schedule): void
    {
        $this->setSetting('practice', $schedule);
    }

    public function hasNotification(string $type): bool
    {
        return $this->getSetting("notifications.{$type}") === true;
    }

    public function getLogoUrlAttribute(): ?string
    {
        if (!$this->logo_path) {
            return null;
        }

        return url(sprintf('storage/%s', $this->logo_path));
    }

    public function getActivePlayersCountAttribute(): int
    {
        return $this->activePlayers()->count();
    }

    public function getUpcomingMatchesAttribute()
    {
        return $this->matches()
            ->where('status', GameMatch::STATUS_SCHEDULED)
            ->where('scheduled_at', '>', now())
            ->orderBy('scheduled_at')
            ->get();
    }

    public function getUpcomingSchedulesAttribute()
    {
        return $this->schedules()
            ->where('starts_at', '>', now())
            ->where('is_cancelled', false)
            ->orderBy('starts_at')
            ->get();
    }

    public function addPlayer(User $player): void
    {
        if (!$this->players()->where('users.id', $player->id)->exists()) {
            $this->players()->attach($player->id, [
                'joined_at' => now()
            ]);
        }
    }

    public function removePlayer(User $player): void
    {
        $this->players()
            ->where('users.id', $player->id)
            ->wherePivotNull('left_at')
            ->update(['left_at' => now()]);
    }

    public function hasPlayer(User $player): bool
    {
        return $this->activePlayers()
            ->where('users.id', $player->id)
            ->exists();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForOrganization($query, Organization $organization)
    {
        return $query->where('organization_id', $organization->id);
    }

    public function scopeWithActivePlayersCount($query)
    {
        return $query->withCount(['players' => function ($query) {
            $query->wherePivotNull('left_at');
        }]);
    }

    public function scopeWithUpcomingMatches($query)
    {
        return $query->with(['matches' => function ($query) {
            $query->where('status', GameMatch::STATUS_SCHEDULED)
                ->where('scheduled_at', '>', now())
                ->orderBy('scheduled_at');
        }]);
    }

    public function scopeWithUpcomingSchedules($query)
    {
        return $query->with(['schedules' => function ($query) {
            $query->where('starts_at', '>', now())
                ->where('is_cancelled', false)
                ->orderBy('starts_at');
        }]);
    }
}
