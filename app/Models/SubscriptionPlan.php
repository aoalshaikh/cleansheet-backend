<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class SubscriptionPlan extends Model
{
    use HasFactory,
        SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'currency',
        'duration_in_days',
        'features',
        'metadata',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'duration_in_days' => 'integer',
        'features' => 'array',
        'metadata' => 'array',
        'is_active' => 'boolean',
    ];

    public function organizationSubscriptions()
    {
        return $this->hasMany(OrganizationSubscription::class, 'plan_id');
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($plan) {
            if (empty($plan->slug)) {
                $plan->slug = Str::slug($plan->name);
            }
        });
    }

    public function hasFeature($feature)
    {
        return isset($this->features[$feature]) && $this->features[$feature];
    }

    public function getFeatureLimit($feature)
    {
        return $this->features[$feature] ?? null;
    }

    public function getDurationInMonths()
    {
        return round($this->duration_in_days / 30);
    }

    public function getMonthlyPrice()
    {
        $months = $this->getDurationInMonths();
        return $months > 0 ? $this->price / $months : $this->price;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByPrice($query, $maxPrice)
    {
        return $query->where('price', '<=', $maxPrice);
    }

    public function scopeByDuration($query, $days)
    {
        return $query->where('duration_in_days', $days);
    }

    public function scopeWithFeature($query, $feature)
    {
        return $query->where("features->{$feature}", true);
    }
}
