<?php

namespace App\Models;

use App\Traits\HasSettings;
use App\Traits\HasTenantAuthorization;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Organization extends Model
{
    use HasFactory,
        HasSettings,
        HasTenantAuthorization,
        LogsActivity,
        SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'logo_path',
        'description',
        'settings',
        'metadata',
        'is_active',
        'subscription_ends_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'settings' => 'array',
        'metadata' => 'array',
        'is_active' => 'boolean',
        'subscription_ends_at' => 'datetime',
    ];

    /**
     * The default settings for a new organization.
     *
     * @var array<string, mixed>
     */
    protected $defaultSettings = [
        'features' => [
            'teams' => true,
            'player_evaluations' => true,
            'attendance_tracking' => true,
            'match_management' => true,
            'notifications' => true,
        ],
        'limits' => [
            'max_teams' => 5,
            'max_players_per_team' => 25,
            'max_coaches' => 10,
            'evaluation_points_per_session' => 500,
        ],
        'notifications' => [
            'email' => true,
            'sms' => true,
            'push' => true,
        ],
    ];

    /**
     * The activity log attributes to track.
     *
     * @var array<int, string>
     */
    protected $activityLogAttributes = [
        'name',
        'description',
        'settings',
        'metadata',
        'is_active',
        'subscription_ends_at',
    ];

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $organization) {
            // Generate slug if not provided
            if (empty($organization->slug)) {
                $organization->slug = Str::slug($organization->name);
            }

            // Merge default settings
            $organization->settings = array_merge(
                $organization->defaultSettings,
                $organization->settings ?? []
            );
        });
    }

    /**
     * Get the tenant that owns the organization.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the teams for the organization.
     */
    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    /**
     * Get the managers for the organization.
     */
    public function managers(): HasMany
    {
        return $this->hasMany(User::class)->role('manager');
    }

    /**
     * Get the coaches for the organization.
     */
    public function coaches(): HasMany
    {
        return $this->hasMany(User::class)->role('coach');
    }

    /**
     * Get the players for the organization.
     */
    public function players(): HasMany
    {
        return $this->hasMany(User::class)->role('player');
    }

    /**
     * Check if the organization has an active subscription.
     */
    public function hasActiveSubscription(): bool
    {
        if (!$this->subscription_ends_at) {
            return false;
        }

        return $this->subscription_ends_at->isFuture();
    }

    /**
     * Check if the organization is in trial period.
     */
    public function isInTrial(): bool
    {
        if (!$this->created_at) {
            return false;
        }

        return $this->created_at->addMonths(2)->isFuture();
    }

    /**
     * Check if the organization has access to features.
     */
    public function hasAccess(): bool
    {
        return $this->is_active && ($this->hasActiveSubscription() || $this->isInTrial());
    }

    /**
     * Check if the organization has a specific feature enabled.
     */
    public function hasFeature(string $feature): bool
    {
        return $this->getSetting("features.{$feature}") === true;
    }

    /**
     * Get an organization limit.
     */
    public function getLimit(string $limit): mixed
    {
        return $this->getSetting("limits.{$limit}");
    }

    /**
     * Check if a notification channel is enabled.
     */
    public function hasNotificationChannel(string $channel): bool
    {
        return $this->getSetting("notifications.{$channel}") === true;
    }

    /**
     * Get the organization's logo URL.
     */
    public function getLogoUrlAttribute(): ?string
    {
        if (!$this->logo_path) {
            return null;
        }

        return url(sprintf('storage/%s', $this->logo_path));
    }

    /**
     * Scope a query to only include active organizations.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include organizations with active subscriptions or in trial.
     */
    public function scopeHasAccess($query)
    {
        return $query->where(function ($query) {
            $query->where('is_active', true)
                ->where(function ($query) {
                    $query->whereNull('subscription_ends_at')
                        ->orWhere('subscription_ends_at', '>', now());
                })
                ->orWhere('created_at', '>', now()->subMonths(2));
        });
    }
}
