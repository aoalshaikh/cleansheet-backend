<?php

namespace App\Models;

use App\Traits\HasSettings;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Tenant extends Model
{
    use HasFactory,
        HasSettings,
        LogsActivity,
        SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'domain',
        'settings',
        'is_active',
        'domains',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'settings' => 'array',
        'domains' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * The default settings for a new tenant.
     *
     * @var array<string, mixed>
     */
    protected $defaultSettings = [
        'features' => [
            'dashboard' => true,
            'api_access' => true,
            'file_uploads' => true,
        ],
        'capabilities' => [
            'max_users' => 5,
            'max_storage' => '1GB',
            'max_projects' => 10,
            'api_rate_limit' => 1000,
        ],
        'subscription' => [
            'plan' => 'basic',
            'status' => 'active',
        ],
    ];

    /**
     * Get the activity log options for the model.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'domain', 'settings', 'is_active', 'domains'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Get the users for the tenant.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get the activities for the tenant.
     */
    public function activities(): HasMany
    {
        return $this->hasMany(\Spatie\Activitylog\Models\Activity::class, 'tenant_id');
    }

    /**
     * Check if the tenant has a specific feature enabled.
     */
    public function hasFeature(string $feature): bool
    {
        return $this->getSetting("features.{$feature}") === true;
    }

    /**
     * Get a tenant capability.
     */
    public function getCapability(string $capability): mixed
    {
        return $this->getSetting("capabilities.{$capability}");
    }

    /**
     * Check if the tenant is on a specific plan.
     */
    public function hasPlan(string $plan): bool
    {
        return $this->getSetting('subscription.plan') === $plan;
    }

    /**
     * Check if the tenant has a specific subscription status.
     */
    public function hasSubscriptionStatus(string $status): bool
    {
        return $this->getSetting('subscription.status') === $status;
    }

    /**
     * Add a domain to the tenant.
     */
    public function addDomain(string $domain): void
    {
        $domains = $this->domains ?? [];
        if (!in_array($domain, $domains)) {
            $domains[] = $domain;
            $this->domains = $domains;
            $this->save();
        }
    }

    /**
     * Remove a domain from the tenant.
     */
    public function removeDomain(string $domain): void
    {
        $domains = $this->domains ?? [];
        if (($key = array_search($domain, $domains)) !== false) {
            unset($domains[$key]);
            $this->domains = array_values($domains);
            $this->save();
        }
    }

    /**
     * Check if the tenant has a specific domain.
     */
    public function hasDomain(string $domain): bool
    {
        return in_array($domain, $this->domains ?? []);
    }

    /**
     * Get the tenant's domains.
     *
     * @return array<string>
     */
    public function getDomains(): array
    {
        return $this->domains ?? [];
    }

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $tenant) {
            // Merge default settings with provided settings
            $tenant->settings = array_merge(
                $tenant->defaultSettings,
                $tenant->settings ?? []
            );
        });

        static::deleting(function (self $tenant) {
            if ($tenant->isForceDeleting()) {
                // Force delete related models when tenant is force deleted
                $tenant->users()->forceDelete();
                $tenant->activities()->delete();
            }
        });

        static::deleted(function (self $tenant) {
            // Clear tenant cache
            app('cache.tenant')->forTenant($tenant)->flush();
        });

        static::restored(function (self $tenant) {
            // Restore related models when tenant is restored
            $tenant->users()->restore();
        });
    }

    /**
     * Scope a query to only include active tenants.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get the tenant's display name.
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->name ?? $this->domain ?? "Tenant #{$this->id}";
    }

    /**
     * Get the tenant's primary domain.
     */
    public function getPrimaryDomainAttribute(): ?string
    {
        return $this->domain ?? Arr::first($this->domains);
    }
}
