<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = User::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->unique()->e164PhoneNumber(),
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
            'password' => Hash::make('password'),
            'remember_token' => Str::random(10),
            'preferences' => [
                'theme' => $this->faker->randomElement(['light', 'dark', 'system']),
                'notifications' => [
                    'email' => $this->faker->boolean(),
                    'push' => $this->faker->boolean(),
                    'sms' => $this->faker->boolean(),
                ],
                'language' => $this->faker->languageCode(),
                'timezone' => $this->faker->timezone(),
            ],
            'settings' => [
                'date_format' => 'Y-m-d',
                'time_format' => $this->faker->randomElement(['12', '24']),
                'first_day_of_week' => $this->faker->numberBetween(0, 6),
                'currency' => $this->faker->currencyCode(),
            ],
        ];
    }

    /**
     * Indicate that the user's email is unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
            'phone_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the user's email is verified.
     */
    public function emailVerified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => now(),
        ]);
    }

    /**
     * Indicate that the user's phone is verified.
     */
    public function phoneVerified(): static
    {
        return $this->state(fn (array $attributes) => [
            'phone_verified_at' => now(),
        ]);
    }

    /**
     * Indicate that the user has an avatar.
     */
    public function withAvatar(): static
    {
        return $this->state(fn (array $attributes) => [
            'avatar_path' => 'avatars/' . $this->faker->image('storage/app/public/avatars', 400, 400, null, false),
        ]);
    }

    /**
     * Indicate that the user belongs to a specific tenant.
     */
    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $tenant->id,
        ]);
    }

    /**
     * Indicate that the user has no tenant.
     */
    public function withoutTenant(): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => null,
        ]);
    }

    /**
     * Indicate that the user has specific preferences.
     */
    public function withPreferences(array $preferences): static
    {
        return $this->state(fn (array $attributes) => [
            'preferences' => array_merge($attributes['preferences'] ?? [], $preferences),
        ]);
    }

    /**
     * Indicate that the user has specific settings.
     */
    public function withSettings(array $settings): static
    {
        return $this->state(fn (array $attributes) => [
            'settings' => array_merge($attributes['settings'] ?? [], $settings),
        ]);
    }

    /**
     * Indicate that the user has a specific password.
     */
    public function withPassword(string $password): static
    {
        return $this->state(fn (array $attributes) => [
            'password' => Hash::make($password),
        ]);
    }

    /**
     * Indicate that the user is an admin.
     */
    public function asAdmin(): static
    {
        return $this->afterCreating(function (User $user) {
            $user->assignRole('admin');
        });
    }

    /**
     * Indicate that the user has specific roles.
     */
    public function withRoles(array|string $roles): static
    {
        return $this->afterCreating(function (User $user) use ($roles) {
            $user->assignRole($roles);
        });
    }

    /**
     * Indicate that the user has specific permissions.
     */
    public function withPermissions(array|string $permissions): static
    {
        return $this->afterCreating(function (User $user) use ($permissions) {
            $user->givePermissionTo($permissions);
        });
    }
}
