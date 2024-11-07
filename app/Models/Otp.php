<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Otp extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'code',
        'type',
        'identifier',
        'expires_at',
        'verified_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    protected $activityLogAttributes = [
        'type',
        'identifier',
        'expires_at',
        'verified_at',
    ];

    protected $activityLogName = 'otp';

    protected $activityLogIgnored = [
        'code', // Don't log the actual OTP code for security
    ];

    protected $activityLogDescriptions = [
        'created' => 'OTP generated',
        'updated' => 'OTP status updated',
        'deleted' => 'OTP deleted',
    ];

    public function otpable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    public function verify(): bool
    {
        if ($this->isExpired() || $this->isVerified()) {
            return false;
        }

        $this->update(['verified_at' => Carbon::now()]);
        return true;
    }

    public function scopeUnverified($query)
    {
        return $query->whereNull('verified_at');
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', Carbon::now());
    }

    public function scopeActive($query)
    {
        return $query->whereNull('verified_at')
            ->where('expires_at', '>', Carbon::now());
    }

    public function scopeForType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeForIdentifier($query, string $identifier)
    {
        return $query->where('identifier', $identifier);
    }

    public function scopeWithCode($query, string $code)
    {
        return $query->where('code', $code);
    }

    public function getActivityLogProperties(): array
    {
        return [
            'type' => $this->type,
            'identifier' => $this->identifier,
            'is_expired' => $this->isExpired(),
            'is_verified' => $this->isVerified(),
            'expires_in' => $this->expires_at->diffForHumans(),
            'otpable_type' => $this->otpable_type,
            'otpable_id' => $this->otpable_id,
        ];
    }

    public function shouldLogActivity(): bool
    {
        // Don't log activity for expired OTPs
        return !$this->isExpired();
    }

    protected function getActivityLogEventName(string $event): string
    {
        return "otp.{$event}";
    }

    public function getTenantId(): ?int
    {
        if (method_exists($this->otpable, 'tenant')) {
            return $this->otpable->tenant->id ?? null;
        }

        if (property_exists($this->otpable, 'tenant_id')) {
            return $this->otpable->tenant_id;
        }

        return null;
    }

    public function getActivityLogDescription(string $eventName): string
    {
        return match ($eventName) {
            'created' => "Generated {$this->type} OTP",
            'updated' => $this->verified_at ? "Verified {$this->type} OTP" : "Updated {$this->type} OTP",
            'deleted' => "Deleted {$this->type} OTP",
            default => parent::getActivityLogDescription($eventName),
        };
    }
}
