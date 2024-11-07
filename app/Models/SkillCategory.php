<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class SkillCategory extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'max_points',
        'metadata',
    ];

    protected $casts = [
        'max_points' => 'integer',
        'metadata' => 'array',
    ];

    public function skills()
    {
        return $this->hasMany(Skill::class, 'category_id');
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($category) {
            if (empty($category->slug)) {
                $category->slug = Str::slug($category->name);
            }
        });
    }

    public function scopeWithSkills($query)
    {
        return $query->with('skills');
    }

    public function getAverageSkillLevel($userId)
    {
        $skills = $this->skills()
            ->with(['playerSkills' => function ($query) use ($userId) {
                $query->where('user_id', $userId);
            }])
            ->get();

        if ($skills->isEmpty()) {
            return 0;
        }

        $totalLevel = 0;
        $count = 0;

        foreach ($skills as $skill) {
            $playerSkill = $skill->playerSkills->first();
            if ($playerSkill) {
                $totalLevel += $playerSkill->current_level;
                $count++;
            }
        }

        return $count > 0 ? round($totalLevel / $count) : 0;
    }
}
