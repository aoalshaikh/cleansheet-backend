<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Spatie\Activitylog\Models\Activity;

class ActivityFactory extends Factory
{
    protected $model = Activity::class;

    public function definition(): array
    {
        $user = User::factory()->create();

        return [
            'log_name' => $this->faker->randomElement(['auth', 'profile', 'system']),
            'description' => $this->faker->sentence(),
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'causer_type' => User::class,
            'causer_id' => $user->id,
            'properties' => [
                'tenant_id' => $user->tenant_id,
                'ip_address' => $this->faker->ipv4(),
                'user_agent' => $this->faker->userAgent(),
                'old' => null,
                'attributes' => null,
            ],
            'created_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'updated_at' => function (array $attributes) {
                return $attributes['created_at'];
            },
        ];
    }

    public function old(int $days = 90): self
    {
        return $this->state(function () use ($days) {
            return [
                'created_at' => now()->subDays($days),
                'updated_at' => now()->subDays($days),
            ];
        });
    }

    public function recent(int $days = 30): self
    {
        return $this->state(function () use ($days) {
            return [
                'created_at' => now()->subDays($days),
                'updated_at' => now()->subDays($days),
            ];
        });
    }

    public function forTenant(int $tenantId): self
    {
        return $this->state(function (array $attributes) use ($tenantId) {
            $properties = $attributes['properties'] ?? [];
            $properties['tenant_id'] = $tenantId;

            return [
                'properties' => $properties,
            ];
        });
    }

    public function withType(string $type): self
    {
        return $this->state(function () use ($type) {
            return [
                'log_name' => $type,
            ];
        });
    }

    public function withProperties(array $properties): self
    {
        return $this->state(function (array $attributes) use ($properties) {
            return [
                'properties' => array_merge(
                    $attributes['properties'] ?? [],
                    $properties
                ),
            ];
        });
    }

    public function forSubject(string $type, int $id): self
    {
        return $this->state(function () use ($type, $id) {
            return [
                'subject_type' => $type,
                'subject_id' => $id,
            ];
        });
    }

    public function causedBy(string $type, int $id): self
    {
        return $this->state(function () use ($type, $id) {
            return [
                'causer_type' => $type,
                'causer_id' => $id,
            ];
        });
    }

    public function withChanges(array $old, array $new): self
    {
        return $this->state(function (array $attributes) use ($old, $new) {
            $properties = $attributes['properties'] ?? [];
            $properties['old'] = $old;
            $properties['attributes'] = $new;

            return [
                'properties' => $properties,
            ];
        });
    }

    public function auth(): self
    {
        return $this->state(function () {
            return [
                'log_name' => 'auth',
                'description' => $this->faker->randomElement([
                    'login',
                    'logout',
                    'failed login',
                    'password reset',
                    'verified email',
                ]),
            ];
        });
    }

    public function profile(): self
    {
        return $this->state(function () {
            return [
                'log_name' => 'profile',
                'description' => 'updated profile',
            ];
        });
    }

    public function system(): self
    {
        return $this->state(function () {
            return [
                'log_name' => 'system',
                'description' => $this->faker->randomElement([
                    'backup completed',
                    'cache cleared',
                    'queue restarted',
                    'maintenance mode enabled',
                    'maintenance mode disabled',
                ]),
            ];
        });
    }
}
