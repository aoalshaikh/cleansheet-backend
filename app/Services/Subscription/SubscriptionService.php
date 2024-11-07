<?php

namespace App\Services\Subscription;

use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Models\PlayerSubscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\BaseService;
use Illuminate\Support\Facades\DB;

class SubscriptionService extends BaseService
{
    /**
     * Subscribe organization to a plan.
     */
    public function subscribeOrganization(Organization $organization, SubscriptionPlan $plan, array $paymentData): OrganizationSubscription
    {
        return DB::transaction(function () use ($organization, $plan, $paymentData) {
            // Cancel any active subscription
            $activeSubscription = $organization->subscriptions()
                ->where('status', 'active')
                ->first();

            if ($activeSubscription) {
                $activeSubscription->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'auto_renew' => false,
                ]);
            }

            // Create new subscription
            return $organization->subscriptions()->create([
                'plan_id' => $plan->id,
                'starts_at' => now(),
                'ends_at' => now()->addDays($plan->duration_in_days),
                'price_paid' => $plan->price,
                'currency' => $plan->currency,
                'payment_method' => $paymentData['method'],
                'payment_id' => $paymentData['id'],
                'features_snapshot' => $plan->features,
                'status' => 'active',
                'auto_renew' => $paymentData['auto_renew'] ?? true,
            ]);
        });
    }

    /**
     * Subscribe player to an organization.
     */
    public function subscribePlayer(User $player, Organization $organization, array $paymentData): PlayerSubscription
    {
        if (!$player->hasRole('player')) {
            throw new \InvalidArgumentException('User must have player role');
        }

        return DB::transaction(function () use ($player, $organization, $paymentData) {
            // Cancel any active subscription for this organization
            $activeSubscription = $player->subscriptions()
                ->where('organization_id', $organization->id)
                ->where('status', 'active')
                ->first();

            if ($activeSubscription) {
                $activeSubscription->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'auto_renew' => false,
                ]);
            }

            // Get subscription price from organization settings
            $price = $organization->getSetting('subscription.player_price', 0);
            $duration = $organization->getSetting('subscription.player_duration', 30);

            // Create new subscription
            return PlayerSubscription::create([
                'user_id' => $player->id,
                'organization_id' => $organization->id,
                'price_paid' => $price,
                'currency' => $organization->getSetting('subscription.currency', 'USD'),
                'payment_method' => $paymentData['method'],
                'payment_id' => $paymentData['id'],
                'starts_at' => now(),
                'ends_at' => now()->addDays($duration),
                'status' => 'active',
                'auto_renew' => $paymentData['auto_renew'] ?? true,
            ]);
        });
    }

    /**
     * Cancel a subscription.
     */
    public function cancelSubscription(string $type, int $subscriptionId, string $reason = null): bool
    {
        $model = $type === 'organization' 
            ? OrganizationSubscription::findOrFail($subscriptionId)
            : PlayerSubscription::findOrFail($subscriptionId);

        return $model->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
            'auto_renew' => false,
        ]);
    }

    /**
     * Process subscription renewals.
     */
    public function processRenewals(): array
    {
        $results = [
            'organization' => ['success' => 0, 'failed' => 0],
            'player' => ['success' => 0, 'failed' => 0],
        ];

        // Process organization subscriptions
        OrganizationSubscription::where('status', 'active')
            ->where('auto_renew', true)
            ->where('ends_at', '<=', now()->addDays(3))
            ->chunk(100, function ($subscriptions) use (&$results) {
                foreach ($subscriptions as $subscription) {
                    try {
                        $this->renewOrganizationSubscription($subscription);
                        $results['organization']['success']++;
                    } catch (\Exception $e) {
                        $results['organization']['failed']++;
                    }
                }
            });

        // Process player subscriptions
        PlayerSubscription::where('status', 'active')
            ->where('auto_renew', true)
            ->where('ends_at', '<=', now()->addDays(3))
            ->chunk(100, function ($subscriptions) use (&$results) {
                foreach ($subscriptions as $subscription) {
                    try {
                        $this->renewPlayerSubscription($subscription);
                        $results['player']['success']++;
                    } catch (\Exception $e) {
                        $results['player']['failed']++;
                    }
                }
            });

        return $results;
    }

    /**
     * Renew an organization subscription.
     */
    protected function renewOrganizationSubscription(OrganizationSubscription $subscription): OrganizationSubscription
    {
        return DB::transaction(function () use ($subscription) {
            // Mark current subscription as completed
            $subscription->update([
                'status' => 'completed',
                'auto_renew' => false,
            ]);

            // Create new subscription
            return $subscription->organization->subscriptions()->create([
                'plan_id' => $subscription->plan_id,
                'starts_at' => $subscription->ends_at,
                'ends_at' => $subscription->ends_at->addDays($subscription->plan->duration_in_days),
                'price_paid' => $subscription->plan->price,
                'currency' => $subscription->currency,
                'payment_method' => $subscription->payment_method,
                'features_snapshot' => $subscription->plan->features,
                'status' => 'active',
                'auto_renew' => true,
                'metadata' => [
                    'renewed_from' => $subscription->id,
                ],
            ]);
        });
    }

    /**
     * Renew a player subscription.
     */
    protected function renewPlayerSubscription(PlayerSubscription $subscription): PlayerSubscription
    {
        return DB::transaction(function () use ($subscription) {
            // Mark current subscription as completed
            $subscription->update([
                'status' => 'completed',
                'auto_renew' => false,
            ]);

            // Get current subscription settings from organization
            $organization = $subscription->organization;
            $price = $organization->getSetting('subscription.player_price', $subscription->price_paid);
            $duration = $organization->getSetting('subscription.player_duration', 30);

            // Create new subscription
            return PlayerSubscription::create([
                'user_id' => $subscription->user_id,
                'organization_id' => $subscription->organization_id,
                'price_paid' => $price,
                'currency' => $subscription->currency,
                'payment_method' => $subscription->payment_method,
                'starts_at' => $subscription->ends_at,
                'ends_at' => $subscription->ends_at->addDays($duration),
                'status' => 'active',
                'auto_renew' => true,
                'metadata' => [
                    'renewed_from' => $subscription->id,
                ],
            ]);
        });
    }

    /**
     * Get subscription plans.
     */
    public function getPlans(array $filters = []): mixed
    {
        $query = SubscriptionPlan::where('is_active', true);

        if (isset($filters['max_price'])) {
            $query->where('price', '<=', $filters['max_price']);
        }

        if (isset($filters['duration'])) {
            $query->where('duration_in_days', $filters['duration']);
        }

        if (isset($filters['feature'])) {
            $query->whereJsonContains('features', [$filters['feature'] => true]);
        }

        return $query->orderBy('price')->get();
    }
}
