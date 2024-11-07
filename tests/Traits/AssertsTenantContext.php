<?php

namespace Tests\Traits;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;

trait AssertsTenantContext
{
    /**
     * Assert that the tenant context is set correctly.
     */
    protected function assertTenantContext(Tenant $tenant): void
    {
        $this->assertEquals($tenant->id, config('tenant.id'));
        $this->assertEquals($tenant->name, config('tenant.name'));
        $this->assertEquals($tenant->settings, config('tenant.settings'));
        $this->assertEquals($tenant->domains, config('tenant.domains'));
    }

    /**
     * Assert that no tenant context is set.
     */
    protected function assertNoTenantContext(): void
    {
        $this->assertNull(config('tenant.id'));
        $this->assertNull(config('tenant.name'));
        $this->assertNull(config('tenant.settings'));
        $this->assertNull(config('tenant.domains'));
    }

    /**
     * Assert that a model belongs to a tenant.
     */
    protected function assertBelongsToTenant(Model $model, Tenant $tenant): void
    {
        $this->assertEquals(
            $tenant->id,
            $model->tenant_id,
            get_class($model) . " does not belong to tenant {$tenant->id}"
        );
    }

    /**
     * Assert that a model does not belong to a tenant.
     */
    protected function assertDoesNotBelongToTenant(Model $model, Tenant $tenant): void
    {
        $this->assertNotEquals(
            $tenant->id,
            $model->tenant_id,
            get_class($model) . " belongs to tenant {$tenant->id}"
        );
    }

    /**
     * Assert that a user has access to a tenant.
     */
    protected function assertHasTenantAccess(User $user, Tenant $tenant): void
    {
        $this->assertEquals(
            $tenant->id,
            $user->tenant_id,
            "User {$user->id} does not have access to tenant {$tenant->id}"
        );
    }

    /**
     * Assert that a user does not have access to a tenant.
     */
    protected function assertDoesNotHaveTenantAccess(User $user, Tenant $tenant): void
    {
        $this->assertNotEquals(
            $tenant->id,
            $user->tenant_id,
            "User {$user->id} has access to tenant {$tenant->id}"
        );
    }

    /**
     * Assert that a tenant is active.
     */
    protected function assertTenantActive(Tenant $tenant): void
    {
        $this->assertTrue(
            $tenant->is_active,
            "Tenant {$tenant->id} is not active"
        );
    }

    /**
     * Assert that a tenant is inactive.
     */
    protected function assertTenantInactive(Tenant $tenant): void
    {
        $this->assertFalse(
            $tenant->is_active,
            "Tenant {$tenant->id} is active"
        );
    }

    /**
     * Assert that a tenant has a specific setting.
     */
    protected function assertTenantHasSetting(Tenant $tenant, string $key, mixed $value): void
    {
        $this->assertEquals(
            $value,
            data_get($tenant->settings, $key),
            "Tenant {$tenant->id} does not have setting {$key} = {$value}"
        );
    }

    /**
     * Assert that a tenant does not have a specific setting.
     */
    protected function assertTenantDoesNotHaveSetting(Tenant $tenant, string $key): void
    {
        $this->assertNull(
            data_get($tenant->settings, $key),
            "Tenant {$tenant->id} has setting {$key}"
        );
    }

    /**
     * Assert that a tenant has a specific domain.
     */
    protected function assertTenantHasDomain(Tenant $tenant, string $domain): void
    {
        $this->assertContains(
            $domain,
            $tenant->domains,
            "Tenant {$tenant->id} does not have domain {$domain}"
        );
    }

    /**
     * Assert that a tenant does not have a specific domain.
     */
    protected function assertTenantDoesNotHaveDomain(Tenant $tenant, string $domain): void
    {
        $this->assertNotContains(
            $domain,
            $tenant->domains,
            "Tenant {$tenant->id} has domain {$domain}"
        );
    }

    /**
     * Assert that a tenant has a specific feature.
     */
    protected function assertTenantHasFeature(Tenant $tenant, string $feature): void
    {
        $this->assertTrue(
            data_get($tenant->settings, "features.{$feature}", false),
            "Tenant {$tenant->id} does not have feature {$feature}"
        );
    }

    /**
     * Assert that a tenant does not have a specific feature.
     */
    protected function assertTenantDoesNotHaveFeature(Tenant $tenant, string $feature): void
    {
        $this->assertFalse(
            data_get($tenant->settings, "features.{$feature}", false),
            "Tenant {$tenant->id} has feature {$feature}"
        );
    }

    /**
     * Assert that a tenant has a specific capability.
     */
    protected function assertTenantHasCapability(Tenant $tenant, string $capability): void
    {
        $this->assertTrue(
            data_get($tenant->settings, "capabilities.{$capability}", false),
            "Tenant {$tenant->id} does not have capability {$capability}"
        );
    }

    /**
     * Assert that a tenant does not have a specific capability.
     */
    protected function assertTenantDoesNotHaveCapability(Tenant $tenant, string $capability): void
    {
        $this->assertFalse(
            data_get($tenant->settings, "capabilities.{$capability}", false),
            "Tenant {$tenant->id} has capability {$capability}"
        );
    }

    /**
     * Assert that a tenant has a specific subscription plan.
     */
    protected function assertTenantHasPlan(Tenant $tenant, string $plan): void
    {
        $this->assertEquals(
            $plan,
            data_get($tenant->settings, 'subscription.plan'),
            "Tenant {$tenant->id} does not have plan {$plan}"
        );
    }

    /**
     * Assert that a tenant has a specific subscription status.
     */
    protected function assertTenantHasSubscriptionStatus(Tenant $tenant, string $status): void
    {
        $this->assertEquals(
            $status,
            data_get($tenant->settings, 'subscription.status'),
            "Tenant {$tenant->id} does not have subscription status {$status}"
        );
    }
}
