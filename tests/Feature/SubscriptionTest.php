<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Models\PlayerSubscription;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class SubscriptionTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $admin;
    private Organization $organization;
    private User $player;
    private SubscriptionPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();
        
        $tenant = Tenant::factory()->create();
        
        /** @var User $admin */
        $admin = User::factory()->create(['tenant_id' => $tenant->id]);
        $admin->assignRole('admin');
        $this->admin = $admin;

        $this->organization = Organization::factory()->create([
            'tenant_id' => $tenant->id
        ]);

        /** @var User $player */
        $player = User::factory()->create(['tenant_id' => $tenant->id]);
        $player->assignRole('player');
        $this->player = $player;

        // Create a subscription plan
        $this->plan = SubscriptionPlan::create([
            'name' => 'Pro Plan',
            'description' => 'Professional team plan',
            'price' => 99.99,
            'currency' => 'USD',
            'duration_in_days' => 30,
            'features' => [
                'max_teams' => 10,
                'max_players_per_team' => 30,
                'player_evaluations' => true,
                'advanced_analytics' => true
            ],
            'is_active' => true
        ]);
    }

    public function test_can_create_subscription_plan(): void
    {
        $planData = [
            'name' => 'Enterprise Plan',
            'description' => 'Enterprise level features',
            'price' => 299.99,
            'currency' => 'USD',
            'duration_in_days' => 365,
            'features' => [
                'max_teams' => -1, // unlimited
                'max_players_per_team' => -1,
                'player_evaluations' => true,
                'advanced_analytics' => true,
                'api_access' => true
            ]
        ];

        $response = $this->actingAs($this->admin)
            ->postJson('/api/subscription-plans', $planData);

        $response->assertCreated();
        $this->assertDatabaseHas('subscription_plans', [
            'name' => 'Enterprise Plan',
            'price' => 299.99
        ]);
    }

    public function test_can_subscribe_organization(): void
    {
        $subscriptionData = [
            'organization_id' => $this->organization->id,
            'plan_id' => $this->plan->id,
            'payment_method' => 'stripe',
            'payment_id' => 'pm_test_123',
            'auto_renew' => true
        ];

        $response = $this->actingAs($this->admin)
            ->postJson('/api/organization-subscriptions', $subscriptionData);

        $response->assertCreated();
        $this->assertDatabaseHas('organization_subscriptions', [
            'organization_id' => $this->organization->id,
            'plan_id' => $this->plan->id,
            'status' => 'active'
        ]);

        $this->organization->refresh();
        $this->assertTrue($this->organization->hasActiveSubscription());
    }

    public function test_can_subscribe_player(): void
    {
        $subscriptionData = [
            'user_id' => $this->player->id,
            'organization_id' => $this->organization->id,
            'price_paid' => 9.99,
            'currency' => 'USD',
            'payment_method' => 'stripe',
            'payment_id' => 'pm_test_123',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'auto_renew' => true
        ];

        $response = $this->actingAs($this->admin)
            ->postJson('/api/player-subscriptions', $subscriptionData);

        $response->assertCreated();
        $this->assertDatabaseHas('player_subscriptions', [
            'user_id' => $this->player->id,
            'organization_id' => $this->organization->id,
            'status' => 'active'
        ]);
    }

    public function test_can_cancel_organization_subscription(): void
    {
        $subscription = OrganizationSubscription::create([
            'organization_id' => $this->organization->id,
            'plan_id' => $this->plan->id,
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'price_paid' => $this->plan->price,
            'currency' => $this->plan->currency,
            'features_snapshot' => $this->plan->features,
            'status' => 'active',
            'auto_renew' => true
        ]);

        $response = $this->actingAs($this->admin)
            ->putJson("/api/organization-subscriptions/{$subscription->id}/cancel", [
                'cancellation_reason' => 'Switching to different service'
            ]);

        $response->assertOk();
        $subscription->refresh();
        
        $this->assertEquals('cancelled', $subscription->status);
        $this->assertNotNull($subscription->cancelled_at);
        $this->assertFalse($subscription->auto_renew);
    }

    public function test_can_cancel_player_subscription(): void
    {
        $subscription = PlayerSubscription::create([
            'user_id' => $this->player->id,
            'organization_id' => $this->organization->id,
            'price_paid' => 9.99,
            'currency' => 'USD',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'status' => 'active',
            'auto_renew' => true
        ]);

        $response = $this->actingAs($this->player)
            ->putJson("/api/player-subscriptions/{$subscription->id}/cancel", [
                'cancellation_reason' => 'No longer need the service'
            ]);

        $response->assertOk();
        $subscription->refresh();
        
        $this->assertEquals('cancelled', $subscription->status);
        $this->assertNotNull($subscription->cancelled_at);
        $this->assertFalse($subscription->auto_renew);
    }

    public function test_subscription_plan_features(): void
    {
        $this->assertTrue($this->plan->hasFeature('player_evaluations'));
        $this->assertEquals(10, $this->plan->getFeatureLimit('max_teams'));
        $this->assertEquals(30, $this->plan->getFeatureLimit('max_players_per_team'));
        $this->assertEquals(1, $this->plan->getDurationInMonths());
        $this->assertEquals(99.99, $this->plan->getMonthlyPrice());
    }

    public function test_subscription_plan_scopes(): void
    {
        // Create additional plans
        SubscriptionPlan::create([
            'name' => 'Basic Plan',
            'price' => 49.99,
            'currency' => 'USD',
            'duration_in_days' => 30,
            'features' => ['max_teams' => 3],
            'is_active' => true
        ]);

        SubscriptionPlan::create([
            'name' => 'Inactive Plan',
            'price' => 149.99,
            'currency' => 'USD',
            'duration_in_days' => 30,
            'features' => ['max_teams' => 20],
            'is_active' => false
        ]);

        $this->assertEquals(2, SubscriptionPlan::active()->count());
        $this->assertEquals(1, SubscriptionPlan::byPrice(50)->count());
        $this->assertEquals(3, SubscriptionPlan::byDuration(30)->count());
        $this->assertEquals(1, SubscriptionPlan::withFeature('advanced_analytics')->count());
    }

    public function test_organization_subscription_renewal(): void
    {
        $subscription = OrganizationSubscription::create([
            'organization_id' => $this->organization->id,
            'plan_id' => $this->plan->id,
            'starts_at' => now(),
            'ends_at' => now()->addDays(5),
            'price_paid' => $this->plan->price,
            'currency' => $this->plan->currency,
            'features_snapshot' => $this->plan->features,
            'status' => 'active',
            'auto_renew' => true
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/organization-subscriptions/{$subscription->id}/renew", [
                'payment_method' => 'stripe',
                'payment_id' => 'pm_test_456'
            ]);

        $response->assertOk();
        $subscription->refresh();
        
        $this->assertTrue($subscription->ends_at->isAfter(now()->addDays(30)));
        $this->assertEquals('active', $subscription->status);
    }

    public function test_expired_subscriptions_handling(): void
    {
        // Create expired organization subscription
        $expiredSubscription = OrganizationSubscription::create([
            'organization_id' => $this->organization->id,
            'plan_id' => $this->plan->id,
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->subDay(),
            'price_paid' => $this->plan->price,
            'currency' => $this->plan->currency,
            'features_snapshot' => $this->plan->features,
            'status' => 'active',
            'auto_renew' => false
        ]);

        // Run expiration check
        $response = $this->actingAs($this->admin)
            ->postJson('/api/subscriptions/check-expirations');

        $response->assertOk();
        $expiredSubscription->refresh();
        
        $this->assertEquals('expired', $expiredSubscription->status);
        $this->organization->refresh();
        $this->assertFalse($this->organization->hasActiveSubscription());
    }
}
