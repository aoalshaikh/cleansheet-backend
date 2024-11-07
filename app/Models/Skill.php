<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Skill extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'description',
        'max_points',
        'criteria',
        'metadata',
    ];

    protected $casts = [
        'max_points' => 'integer',
        'criteria' => 'array',
        'metadata' => 'array',
    ];

    public function category()
    {
        return $this->belongsTo(SkillCategory::class, 'category_id');
    }

    public function playerSkills()
    {
        return $this->hasMany(PlayerSkill::class);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($skill) {
            if (empty($skill->slug)) {
                $skill->slug = Str::slug($skill->name);
            }
        });
    }

    public function getAverageLevel()
    {
        return $this->playerSkills()->avg('current_level') ?? 0;
    }

    public function getPlayerLevel($userId)
    {
        return $this->playerSkills()
            ->where('user_id', $userId)
            ->value('current_level') ?? 0;
    }

    public function scopeInCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopeWithPlayerSkills($query, $userId = null)
    {
        return $query->with(['playerSkills' => function ($query) use ($userId) {
            if ($userId) {
                $query->where('user_id', $userId);
            }
        }]);
    }
}
