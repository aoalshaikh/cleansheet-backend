<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class TeamTier extends Model
{
    use HasFactory,
        LogsActivity,
        SoftDeletes;

    protected $fillable = [
        'team_id',
        'parent_tier_id',
        'name',
        'slug',
        'description',
        'level',
        'min_age',
        'max_age',
        'requirements',
        'metadata',
        'is_active',
    ];

    protected $casts = [
        'level' => 'integer',
        'min_age' => 'integer',
        'max_age' => 'integer',
        'requirements' => 'array',
        'metadata' => 'array',
        'is_active' => 'boolean',
    ];

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function parentTier()
    {
        return $this->belongsTo(TeamTier::class, 'parent_tier_id');
    }

    public function childTiers()
    {
        return $this->hasMany(TeamTier::class, 'parent_tier_id');
    }

    public function players()
    {
        return $this->belongsToMany(User::class, 'team_tier_players')
            ->withPivot('evaluation', 'promoted_at', 'demoted_at')
            ->withTimestamps();
    }

    public function activePlayers()
    {
        return $this->players()
            ->wherePivotNull('demoted_at');
    }

    public function assignPlayer($userId, $evaluation = null)
    {
        return $this->players()->attach($userId, [
            'evaluation' => $evaluation,
            'promoted_at' => now(),
        ]);
    }

    public function removePlayer($userId)
    {
        return $this->players()
            ->wherePivot('user_id', $userId)
            ->wherePivotNull('demoted_at')
            ->update(['demoted_at' => now()]);
    }

    public function updatePlayerEvaluation($userId, array $evaluation)
    {
        return $this->players()
            ->wherePivot('user_id', $userId)
            ->wherePivotNull('demoted_at')
            ->update(['evaluation' => $evaluation]);
    }

    public function promotePlayer($userId, TeamTier $toTier, $evaluation = null)
    {
        $this->removePlayer($userId);
        $toTier->assignPlayer($userId, $evaluation);
    }

    public function demotePlayer($userId, TeamTier $toTier, $evaluation = null)
    {
        $this->removePlayer($userId);
        $toTier->assignPlayer($userId, $evaluation);
    }

    public function hasPlayer($userId)
    {
        return $this->activePlayers()
            ->where('users.id', $userId)
            ->exists();
    }

    public function isTopLevel()
    {
        return is_null($this->parent_tier_id);
    }

    public function hasChildTiers()
    {
        return $this->childTiers()->exists();
    }

    public function getAncestors()
    {
        $ancestors = collect();
        $tier = $this;

        while ($tier->parentTier) {
            $ancestors->push($tier->parentTier);
            $tier = $tier->parentTier;
        }

        return $ancestors;
    }

    public function getDescendants()
    {
        $descendants = collect();
        $this->childTiers->each(function ($tier) use ($descendants) {
            $descendants->push($tier);
            $descendants = $descendants->merge($tier->getDescendants());
        });

        return $descendants;
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($tier) {
            if (empty($tier->slug)) {
                $tier->slug = Str::slug($tier->name);
            }
        });
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeTopLevel($query)
    {
        return $query->whereNull('parent_tier_id');
    }

    public function scopeForTeam($query, $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    public function scopeByLevel($query, $level)
    {
        return $query->where('level', $level);
    }

    public function scopeInAgeRange($query, $age)
    {
        return $query->where(function ($query) use ($age) {
            $query->where(function ($query) use ($age) {
                $query->whereNull('min_age')
                    ->whereNull('max_age');
            })->orWhere(function ($query) use ($age) {
                $query->where('min_age', '<=', $age)
                    ->where('max_age', '>=', $age);
            });
        });
    }
}
