<?php

namespace App\Traits;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

trait HasTenantScope
{
    /**
     * Boot the trait.
     */
    protected static function bootHasTenantScope(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            if (Auth::check()) {
                /** @var User $user */
                $user = Auth::user();
                if (!$user->hasRole(config('permission.super_admin_role'))) {
                    $builder->where('tenant_id', $user->tenant_id);
                }
            }
        });

        static::creating(function (Model $model) {
            if (Auth::check() && !$model->tenant_id) {
                $model->tenant_id = Auth::user()->tenant_id;
            }
        });
    }

    /**
     * Get the tenant that owns the model.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Scope a query to only include records for a specific tenant.
     */
    public function scopeTenant(Builder $query, ?Tenant $tenant = null): Builder
    {
        $tenantId = $tenant ? $tenant->id : (Auth::check() ? Auth::user()->tenant_id : null);

        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope a query to only include records for the current tenant.
     */
    public function scopeCurrentTenant(Builder $query): Builder
    {
        return $query->where('tenant_id', Auth::check() ? Auth::user()->tenant_id : null);
    }

    /**
     * Scope a query to include records for all tenants.
     */
    public function scopeAllTenants(Builder $query): Builder
    {
        return $query->withoutGlobalScope('tenant');
    }

    /**
     * Scope a query to only include records for active tenants.
     */
    public function scopeActiveTenants(Builder $query): Builder
    {
        return $query->whereHas('tenant', function ($query) {
            $query->where('is_active', true);
        });
    }

    /**
     * Scope a query to only include records for inactive tenants.
     */
    public function scopeInactiveTenants(Builder $query): Builder
    {
        return $query->whereHas('tenant', function ($query) {
            $query->where('is_active', false);
        });
    }

    /**
     * Scope a query to only include records for tenants with specific features.
     */
    public function scopeTenantFeatures(Builder $query, array $features): Builder
    {
        return $query->whereHas('tenant', function ($query) use ($features) {
            foreach ($features as $feature) {
                $query->whereJsonContains('settings->features->' . $feature, true);
            }
        });
    }

    /**
     * Scope a query to only include records for tenants with specific capabilities.
     */
    public function scopeTenantCapabilities(Builder $query, array $capabilities): Builder
    {
        return $query->whereHas('tenant', function ($query) use ($capabilities) {
            foreach ($capabilities as $capability) {
                $query->whereJsonContains('settings->capabilities->' . $capability, true);
            }
        });
    }

    /**
     * Scope a query to only include records for tenants with specific subscription plan.
     */
    public function scopeTenantPlan(Builder $query, string $plan): Builder
    {
        return $query->whereHas('tenant', function ($query) use ($plan) {
            $query->whereJsonContains('settings->subscription->plan', $plan);
        });
    }

    /**
     * Scope a query to only include records for tenants with specific subscription status.
     */
    public function scopeTenantSubscriptionStatus(Builder $query, string $status): Builder
    {
        return $query->whereHas('tenant', function ($query) use ($status) {
            $query->whereJsonContains('settings->subscription->status', $status);
        });
    }

    /**
     * Scope a query to only include records for tenants with specific domains.
     */
    public function scopeTenantDomains(Builder $query, array $domains): Builder
    {
        return $query->whereHas('tenant', function ($query) use ($domains) {
            foreach ($domains as $domain) {
                $query->whereJsonContains('domains', $domain);
            }
        });
    }

    /**
     * Get the current tenant ID from the authenticated user.
     */
    protected function getCurrentTenantId(): ?int
    {
        return Auth::check() ? Auth::user()->tenant_id : null;
    }

    /**
     * Determine if the model belongs to the given tenant.
     */
    public function belongsToTenant(Tenant $tenant): bool
    {
        return $this->tenant_id === $tenant->id;
    }

    /**
     * Determine if the model belongs to the current tenant.
     */
    public function belongsToCurrentTenant(): bool
    {
        return $this->tenant_id === $this->getCurrentTenantId();
    }

    /**
     * Force the model to belong to a specific tenant.
     */
    public function forceTenant(Tenant $tenant): self
    {
        $this->tenant_id = $tenant->id;
        return $this;
    }

    /**
     * Remove the tenant association from the model.
     */
    public function removeTenant(): self
    {
        $this->tenant_id = null;
        return $this;
    }

    /**
     * Determine if the model should be scoped to tenant.
     */
    protected function shouldScopeToTenant(): bool
    {
        if (!Auth::check()) {
            return false;
        }

        /** @var User $user */
        $user = Auth::user();
        return !$user->hasRole(config('permission.super_admin_role'));
    }
}
