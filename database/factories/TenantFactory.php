<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Tenant>
 */
class TenantFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Tenant::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->company();
        $domain = Str::slug($name) . '.' . $this->faker->domainName();

        return [
            'name' => $name,
            'domain' => $domain,
            'is_active' => true,
            'domains' => [$domain],
            'settings' => [
                'features' => [
                    'dashboard' => true,
                    'api_access' => true,
                    'file_uploads' => true,
                    'team_management' => false,
                    'advanced_reporting' => false,
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
                    'trial_ends_at' => now()->addDays(14),
                ],
                'branding' => [
                    'primary_color' => '#007bff',
                    'secondary_color' => '#6c757d',
                    'logo_url' => null,
                ],
                'notifications' => [
                    'email' => true,
                    'slack' => false,
                    'webhook' => false,
                ],
                'security' => [
                    'two_factor' => false,
                    'ip_whitelist' => [],
                    'password_policy' => 'default',
                ],
            ],
        ];
    }

    /**
     * Indicate that the tenant is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the tenant is on a premium plan.
     */
    public function premium(): static
    {
        return $this->state(fn (array $attributes) => [
            'settings' => array_merge($attributes['settings'] ?? [], [
                'features' => [
                    'dashboard' => true,
                    'api_access' => true,
                    'file_uploads' => true,
                    'team_management' => true,
                    'advanced_reporting' => true,
                ],
                'capabilities' => [
                    'max_users' => 25,
                    'max_storage' => '10GB',
                    'max_projects' => 50,
                    'api_rate_limit' => 5000,
                ],
                'subscription' => [
                    'plan' => 'premium',
                    'status' => 'active',
                ],
            ]),
        ]);
    }

    /**
     * Indicate that the tenant is on an enterprise plan.
     */
    public function enterprise(): static
    {
        return $this->state(fn (array $attributes) => [
            'settings' => array_merge($attributes['settings'] ?? [], [
                'features' => [
                    'dashboard' => true,
                    'api_access' => true,
                    'file_uploads' => true,
                    'team_management' => true,
                    'advanced_reporting' => true,
                    'custom_domain' => true,
                    'sso' => true,
                ],
                'capabilities' => [
                    'max_users' => null, // unlimited
                    'max_storage' => null, // unlimited
                    'max_projects' => null, // unlimited
                    'api_rate_limit' => null, // unlimited
                ],
                'subscription' => [
                    'plan' => 'enterprise',
                    'status' => 'active',
                ],
            ]),
        ]);
    }

    /**
     * Indicate that the tenant's subscription has expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'settings' => array_merge($attributes['settings'] ?? [], [
                'subscription' => [
                    'status' => 'expired',
                    'expired_at' => now()->subDay(),
                ],
            ]),
        ]);
    }

    /**
     * Indicate that the tenant is in trial period.
     */
    public function trial(): static
    {
        return $this->state(fn (array $attributes) => [
            'settings' => array_merge($attributes['settings'] ?? [], [
                'subscription' => [
                    'status' => 'trial',
                    'trial_ends_at' => now()->addDays(14),
                ],
            ]),
        ]);
    }

    /**
     * Indicate that the tenant requires strict security settings.
     */
    public function strictSecurity(): static
    {
        return $this->state(fn (array $attributes) => [
            'settings' => array_merge($attributes['settings'] ?? [], [
                'security' => [
                    'two_factor' => true,
                    'password_policy' => 'strict',
                    'session_lifetime' => 60, // minutes
                    'ip_whitelist' => ['192.168.1.0/24'],
                    'max_login_attempts' => 3,
                    'lockout_duration' => 30, // minutes
                ],
            ]),
        ]);
    }

    /**
     * Configure custom branding for the tenant.
     */
    public function withBranding(array $branding): static
    {
        return $this->state(fn (array $attributes) => [
            'settings' => array_merge($attributes['settings'] ?? [], [
                'branding' => array_merge(
                    $attributes['settings']['branding'] ?? [],
                    $branding
                ),
            ]),
        ]);
    }

    /**
     * Configure custom domains for the tenant.
     */
    public function withDomains(array $domains): static
    {
        return $this->state(fn (array $attributes) => [
            'domains' => array_unique(array_merge(
                $attributes['domains'] ?? [],
                $domains
            )),
        ]);
    }
}
