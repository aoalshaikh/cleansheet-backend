<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrganizationSubscription extends Model
{
    use HasFactory,
        SoftDeletes;

    protected $fillable = [
        'organization_id',
        'plan_id',
        'starts_at',
        'ends_at',
        'price_paid',
        'currency',
        'payment_method',
        'payment_id',
        'features_snapshot',
        'metadata',
        'status',
        'cancelled_at',
        'cancellation_reason',
        'auto_renew',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'price_paid' => 'decimal:2',
        'features_snapshot' => 'array',
        'metadata' => 'array',
        'cancelled_at' => 'datetime',
        'auto_renew' => 'boolean',
    ];

    const STATUS_ACTIVE = 'active';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_EXPIRED = 'expired';

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function plan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    public function isActive()
    {
        return $this->status === self::STATUS_ACTIVE && now()->between($this->starts_at, $this->ends_at);
    }

    public function isCancelled()
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isExpired()
    {
        return $this->status === self::STATUS_EXPIRED || now()->isAfter($this->ends_at);
    }

    public function cancel($reason = null)
    {
        $this->update([
            'status' => self::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
            'auto_renew' => false,
        ]);
    }

    public function markAsExpired()
    {
        $this->update([
            'status' => self::STATUS_EXPIRED,
            'auto_renew' => false,
        ]);
    }

    public function hasFeature($feature)
    {
        return isset($this->features_snapshot[$feature]) && $this->features_snapshot[$feature];
    }

    public function getFeatureLimit($feature)
    {
        return $this->features_snapshot[$feature] ?? null;
    }

    public function getDaysRemaining()
    {
        if ($this->isExpired() || $this->isCancelled()) {
            return 0;
        }

        return now()->diffInDays($this->ends_at, false);
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>', now());
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', self::STATUS_CANCELLED);
    }

    public function scopeExpired($query)
    {
        return $query->where(function ($query) {
            $query->where('status', self::STATUS_EXPIRED)
                ->orWhere('ends_at', '<=', now());
        });
    }

    public function scopeAutoRenewing($query)
    {
        return $query->where('auto_renew', true);
    }

    public function scopeForOrganization($query, $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeEndingSoon($query, $days = 7)
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->whereBetween('ends_at', [now(), now()->addDays($days)]);
    }
}
