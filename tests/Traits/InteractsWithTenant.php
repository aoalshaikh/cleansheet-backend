<?php

namespace Tests\Traits;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

trait InteractsWithTenant
{
    protected ?Tenant $currentTenant = null;
    protected ?User $currentUser = null;

    /**
     * Set up a tenant context for testing.
     */
    protected function setUpTenant(array $attributes = []): Tenant
    {
        $this->currentTenant = Tenant::factory()->create(array_merge([
            'is_active' => true,
            'settings' => [
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
            ],
        ], $attributes));

        return $this->currentTenant;
    }

    /**
     * Create a user for the current tenant.
     */
    protected function createTenantUser(array $attributes = []): User
    {
        if (!$this->currentTenant) {
            $this->setUpTenant();
        }

        $this->currentUser = User::factory()
            ->forTenant($this->currentTenant)
            ->create($attributes);

        return $this->currentUser;
    }

    /**
     * Act as a tenant user.
     */
    protected function actingAsTenantUser(?User $user = null): static
    {
        $this->currentUser = $user ?? $this->createTenantUser();
        Auth::login($this->currentUser);

        return $this;
    }

    /**
     * Create multiple users for the current tenant.
     *
     * @return Collection<int, User>
     */
    protected function createTenantUsers(int $count, array $attributes = []): Collection
    {
        if (!$this->currentTenant) {
            $this->setUpTenant();
        }

        return User::factory()
            ->count($count)
            ->forTenant($this->currentTenant)
            ->create($attributes);
    }

    /**
     * Assert that a model belongs to the current tenant.
     */
    protected function assertBelongsToCurrentTenant(Model $model): void
    {
        $this->assertEquals(
            $this->currentTenant->id,
            $model->tenant_id,
            get_class($model) . " does not belong to the current tenant."
        );
    }

    /**
     * Assert that a model does not belong to the current tenant.
     */
    protected function assertDoesNotBelongToCurrentTenant(Model $model): void
    {
        $this->assertNotEquals(
            $this->currentTenant->id,
            $model->tenant_id,
            get_class($model) . " belongs to the current tenant."
        );
    }

    /**
     * Assert that the current tenant has a specific feature enabled.
     */
    protected function assertTenantHasFeature(string $feature): void
    {
        $this->assertTrue(
            $this->currentTenant->getSetting("features.{$feature}") === true,
            "Feature '{$feature}' is not enabled for the current tenant."
        );
    }

    /**
     * Assert that the current tenant has a specific capability.
     */
    protected function assertTenantHasCapability(string $capability, mixed $value): void
    {
        $this->assertEquals(
            $value,
            $this->currentTenant->getSetting("capabilities.{$capability}"),
            "Capability '{$capability}' does not match expected value."
        );
    }

    /**
     * Assert that the current tenant is on a specific plan.
     */
    protected function assertTenantOnPlan(string $plan): void
    {
        $this->assertEquals(
            $plan,
            $this->currentTenant->getSetting('subscription.plan'),
            "Tenant is not on the '{$plan}' plan."
        );
    }

    /**
     * Assert that the current tenant has a specific subscription status.
     */
    protected function assertTenantSubscriptionStatus(string $status): void
    {
        $this->assertEquals(
            $status,
            $this->currentTenant->getSetting('subscription.status'),
            "Tenant subscription status is not '{$status}'."
        );
    }

    /**
     * Assert that the current tenant is active.
     */
    protected function assertTenantActive(): void
    {
        $this->assertTrue(
            $this->currentTenant->is_active,
            'Tenant is not active.'
        );
    }

    /**
     * Assert that the current tenant is inactive.
     */
    protected function assertTenantInactive(): void
    {
        $this->assertFalse(
            $this->currentTenant->is_active,
            'Tenant is active.'
        );
    }

    /**
     * Get the current tenant.
     */
    protected function getCurrentTenant(): Tenant
    {
        if (!$this->currentTenant) {
            $this->setUpTenant();
        }

        return $this->currentTenant;
    }

    /**
     * Get the current tenant user.
     */
    protected function getCurrentTenantUser(): User
    {
        if (!$this->currentUser) {
            $this->createTenantUser();
        }

        return $this->currentUser;
    }

    /**
     * Clean up tenant context after test.
     */
    protected function tearDownTenant(): void
    {
        $this->currentTenant = null;
        $this->currentUser = null;
        Auth::logout();
    }
}
