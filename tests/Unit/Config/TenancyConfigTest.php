<?php

namespace Tests\Unit\Config;

use App\Models\Tenant;
use App\Models\User;
use Tests\TestCase;

class TenancyConfigTest extends TestCase
{
    public function test_models_are_configured(): void
    {
        $this->assertEquals(
            Tenant::class,
            config('tenancy.tenant_model')
        );

        $this->assertEquals(
            User::class,
            config('tenancy.user_model')
        );
    }

    public function test_default_settings_are_configured(): void
    {
        $settings = config('tenancy.default_settings');

        $this->assertIsArray($settings);
        $this->assertArrayHasKey('features', $settings);
        $this->assertArrayHasKey('capabilities', $settings);
        $this->assertArrayHasKey('subscription', $settings);
        $this->assertArrayHasKey('branding', $settings);
        $this->assertArrayHasKey('notifications', $settings);
        $this->assertArrayHasKey('security', $settings);
    }

    public function test_plans_are_configured(): void
    {
        $plans = config('tenancy.plans');

        $this->assertIsArray($plans);
        $this->assertArrayHasKey('basic', $plans);
        $this->assertArrayHasKey('premium', $plans);
        $this->assertArrayHasKey('enterprise', $plans);

        foreach ($plans as $plan) {
            $this->assertArrayHasKey('features', $plan);
            $this->assertArrayHasKey('capabilities', $plan);
        }
    }

    public function test_domain_configuration(): void
    {
        $domain = config('tenancy.domain');

        $this->assertIsArray($domain);
        $this->assertArrayHasKey('subdomain', $domain);
        $this->assertArrayHasKey('custom_domains', $domain);

        $this->assertArrayHasKey('enabled', $domain['subdomain']);
        $this->assertArrayHasKey('suffix', $domain['subdomain']);

        $this->assertArrayHasKey('enabled', $domain['custom_domains']);
        $this->assertArrayHasKey('verify_ssl', $domain['custom_domains']);
    }

    public function test_database_configuration(): void
    {
        $database = config('tenancy.database');

        $this->assertIsArray($database);
        $this->assertArrayHasKey('tenant_aware_models', $database);
        $this->assertArrayHasKey('activity_logging', $database);

        $this->assertContains(
            User::class,
            $database['tenant_aware_models']
        );

        $this->assertTrue($database['activity_logging']['enabled']);
        $this->assertEquals('tenant_id', $database['activity_logging']['tenant_column']);
    }

    public function test_cache_configuration(): void
    {
        $cache = config('tenancy.cache');

        $this->assertIsArray($cache);
        $this->assertArrayHasKey('prefix', $cache);
        $this->assertArrayHasKey('ttl', $cache);

        $this->assertEquals('tenant', $cache['prefix']);
        $this->assertEquals(3600, $cache['ttl']);
    }

    public function test_route_configuration(): void
    {
        $routes = config('tenancy.routes');

        $this->assertIsArray($routes);
        $this->assertArrayHasKey('prefix', $routes);
        $this->assertArrayHasKey('middleware', $routes);

        $this->assertEquals('tenant', $routes['prefix']);
        $this->assertEquals(['web', 'auth', 'tenant'], $routes['middleware']);
    }

    public function test_security_configuration(): void
    {
        $security = config('tenancy.security');

        $this->assertIsArray($security);
        $this->assertArrayHasKey('password_policies', $security);

        $policies = $security['password_policies'];
        $this->assertArrayHasKey('default', $policies);
        $this->assertArrayHasKey('strict', $policies);

        foreach (['default', 'strict'] as $policy) {
            $this->assertArrayHasKey('min_length', $policies[$policy]);
            $this->assertArrayHasKey('require_uppercase', $policies[$policy]);
            $this->assertArrayHasKey('require_numeric', $policies[$policy]);
            $this->assertArrayHasKey('require_special_char', $policies[$policy]);
        }
    }

    public function test_feature_flags(): void
    {
        $features = config('tenancy.features');

        $this->assertIsArray($features);
        $this->assertArrayHasKey('tenant_impersonation', $features);
        $this->assertArrayHasKey('tenant_switching', $features);
        $this->assertArrayHasKey('tenant_deletion', $features);
        $this->assertArrayHasKey('tenant_backup', $features);

        $this->assertFalse($features['tenant_impersonation']);
        $this->assertTrue($features['tenant_switching']);
        $this->assertTrue($features['tenant_deletion']);
        $this->assertTrue($features['tenant_backup']);
    }

    public function test_plan_features_are_properly_nested(): void
    {
        $plans = config('tenancy.plans');

        foreach ($plans as $planName => $plan) {
            $this->assertArrayHasKey('features', $plan, "Plan {$planName} should have features");
            $this->assertArrayHasKey('capabilities', $plan, "Plan {$planName} should have capabilities");

            // Basic plan checks
            if ($planName === 'basic') {
                $this->assertFalse($plan['features']['team_management']);
                $this->assertEquals(5, $plan['capabilities']['max_users']);
            }

            // Premium plan checks
            if ($planName === 'premium') {
                $this->assertTrue($plan['features']['team_management']);
                $this->assertEquals(25, $plan['capabilities']['max_users']);
            }

            // Enterprise plan checks
            if ($planName === 'enterprise') {
                $this->assertTrue($plan['features']['sso']);
                $this->assertNull($plan['capabilities']['max_users']);
            }
        }
    }

    public function test_all_required_config_keys_exist(): void
    {
        $requiredKeys = [
            'tenant_model',
            'user_model',
            'default_settings',
            'plans',
            'domain',
            'database',
            'cache',
            'routes',
            'security',
            'features',
        ];

        foreach ($requiredKeys as $key) {
            $this->assertNotNull(
                config("tenancy.{$key}"),
                "Missing required config key: {$key}"
            );
        }
    }
}
