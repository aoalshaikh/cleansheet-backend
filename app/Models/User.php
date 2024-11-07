<?php

namespace App\Models;

use App\Traits\HasTenantAuthorization;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory,
        Notifiable,
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
        'first_name',
        'last_name',
        'email',
        'phone',
        'password',
        'avatar_path',
        'date_of_birth',
        'preferences',
        'settings',
        'metadata'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'phone_verified_at' => 'datetime',
        'date_of_birth' => 'date',
        'preferences' => 'array',
        'settings' => 'array',
        'metadata' => 'array',
    ];

    /**
     * The relationships that should be eager loaded.
     *
     * @var array<int, string>
     */
    protected $with = [
        'tenant',
    ];

    /**
     * The activity log attributes to track.
     *
     * @var array<int, string>
     */
    protected $activityLogAttributes = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'tenant_id',
        'avatar_path',
        'date_of_birth',
        'preferences',
        'settings',
        'metadata',
        'email_verified_at',
        'phone_verified_at',
    ];

    /**
     * The activity log name.
     *
     * @var string
     */
    protected $activityLogName = 'user';

    /**
     * The attributes to ignore in activity log.
     *
     * @var array<int, string>
     */
    protected $activityLogIgnored = [
        'password',
        'remember_token',
        'updated_at',
    ];

    /**
     * The activity log descriptions.
     *
     * @var array<string, string>
     */
    protected $activityLogDescriptions = [
        'created' => 'User account created',
        'updated' => 'User profile updated',
        'deleted' => 'User account deleted',
        'restored' => 'User account restored',
    ];

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     */
    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     */
    public function getJWTCustomClaims(): array
    {
        return [
            'tenant_id' => $this->tenant_id,
            'email' => $this->email,
            'roles' => $this->getRoleNames(),
        ];
    }

    /**
     * Get the tenant that owns the user.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the user's OTPs.
     */
    public function otps(): MorphMany
    {
        return $this->morphMany(Otp::class, 'otpable');
    }

    /**
     * Get the activity log properties.
     */
    public function getActivityLogProperties(): array
    {
        return [
            'roles' => $this->getRoleNames(),
            'permissions' => $this->getPermissionNames(),
        ];
    }

    /**
     * Get the user's full name.
     */
    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /**
     * Get the user's display name.
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->full_name ?? explode('@', $this->email)[0];
    }

    /**
     * Get the user's avatar URL.
     */
    public function getAvatarUrlAttribute(): ?string
    {
        if (!$this->avatar_path) {
            return null;
        }

        return url(sprintf('storage/%s', $this->avatar_path));
    }

    /**
     * Get a user preference.
     */
    public function getPreference(string $key, mixed $default = null): mixed
    {
        return data_get($this->preferences, $key, $default);
    }

    /**
     * Set a user preference.
     */
    public function setPreference(string $key, mixed $value): void
    {
        $preferences = $this->preferences ?? [];
        data_set($preferences, $key, $value);
        $this->preferences = $preferences;
        $this->save();
    }

    /**
     * Remove a user preference.
     */
    public function removePreference(string $key): void
    {
        $preferences = $this->preferences ?? [];
        data_forget($preferences, $key);
        $this->preferences = $preferences;
        $this->save();
    }

    /**
     * Get a user setting.
     */
    public function getSetting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    /**
     * Set a user setting.
     */
    public function setSetting(string $key, mixed $value): void
    {
        $settings = $this->settings ?? [];
        data_set($settings, $key, $value);
        $this->settings = $settings;
        $this->save();
    }

    /**
     * Remove a user setting.
     */
    public function removeSetting(string $key): void
    {
        $settings = $this->settings ?? [];
        data_forget($settings, $key);
        $this->settings = $settings;
        $this->save();
    }

    /**
     * Get all permissions for the user.
     */
    public function getPermissionNames(): array
    {
        return $this->getAllPermissions()->pluck('name')->toArray();
    }

    /**
     * Determine if the user is a super admin.
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasRole(config('permission.super_admin_role'));
    }

    /**
     * Determine if the user belongs to a tenant.
     */
    public function hasTenant(): bool
    {
        return !is_null($this->tenant_id);
    }
}
