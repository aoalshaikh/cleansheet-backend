<?php

namespace App\Services\Organization;

use App\Models\Organization;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\BaseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrganizationService extends BaseService
{
    protected $subscriptionPlanModel;

    public function __construct(Organization $model, SubscriptionPlan $subscriptionPlanModel)
    {
        parent::__construct($model);
        $this->subscriptionPlanModel = $subscriptionPlanModel;
    }

    /**
     * Create a new organization with initial setup.
     */
    public function signup(array $data, User $owner): Organization
    {
        return DB::transaction(function () use ($data, $owner) {
            // Create organization
            $organization = $this->create([
                'tenant_id' => $owner->tenant_id,
                'name' => $data['name'],
                'slug' => Str::slug($data['name']),
                'description' => $data['description'] ?? null,
                'settings' => $data['settings'] ?? null,
                'metadata' => array_merge($data['metadata'] ?? [], [
                    'owner_id' => $owner->id,
                ]),
            ]);

            // Set up trial subscription
            if (isset($data['plan_id'])) {
                $plan = $this->subscriptionPlanModel->findOrFail($data['plan_id']);
            } else {
                $plan = $this->subscriptionPlanModel->where('slug', 'basic')->first();
            }

            if ($plan) {
                $organization->subscriptions()->create([
                    'plan_id' => $plan->id,
                    'starts_at' => now(),
                    'ends_at' => now()->addMonths(2), // 2-month trial
                    'price_paid' => 0,
                    'currency' => $plan->currency,
                    'status' => 'active',
                    'features_snapshot' => $plan->features,
                    'metadata' => [
                        'is_trial' => true,
                        'trial_ends_at' => now()->addMonths(2),
                    ],
                ]);
            }

            // Assign owner as manager
            $owner->assignRole('manager');

            return $organization;
        });
    }

    /**
     * Get organization statistics.
     */
    public function getStats(Organization $organization): array
    {
        $teams = $organization->teams()->count();
        $players = $organization->players()->count();
        $coaches = $organization->coaches()->count();
        $activeMatches = $organization->teams()
            ->withCount(['matches' => function ($query) {
                $query->where('status', 'scheduled')
                    ->orWhere('status', 'in_progress');
            }])
            ->get()
            ->sum('matches_count');

        $attendance = DB::table('team_schedule_attendances')
            ->join('team_schedules', 'team_schedules.id', '=', 'team_schedule_attendances.team_schedule_id')
            ->join('teams', 'teams.id', '=', 'team_schedules.team_id')
            ->where('teams.organization_id', $organization->id)
            ->where('team_schedule_attendances.created_at', '>=', now()->subDays(30))
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN status = "present" THEN 1 ELSE 0 END) as present,
                SUM(CASE WHEN status = "absent" THEN 1 ELSE 0 END) as absent,
                SUM(CASE WHEN status = "late" THEN 1 ELSE 0 END) as late
            ')
            ->first();

        return [
            'teams' => $teams,
            'players' => $players,
            'coaches' => $coaches,
            'active_matches' => $activeMatches,
            'attendance_rate' => $attendance->total > 0
                ? round(($attendance->present / $attendance->total) * 100, 2)
                : 0,
            'attendance_stats' => [
                'present' => $attendance->present,
                'absent' => $attendance->absent,
                'late' => $attendance->late,
            ],
            'subscription' => [
                'status' => $organization->hasActiveSubscription(),
                'trial' => $organization->isInTrial(),
                'days_remaining' => $organization->subscriptions()
                    ->where('status', 'active')
                    ->first()
                    ?->getDaysRemaining() ?? 0,
            ],
        ];
    }

    /**
     * Invite a user to the organization.
     */
    public function invite(Organization $organization, array $data): User
    {
        return DB::transaction(function () use ($organization, $data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'],
                'tenant_id' => $organization->tenant_id,
                'metadata' => [
                    'invited_by' => request()->user()->id,
                    'organization_id' => $organization->id,
                ],
            ]);

            $user->assignRole($data['role']);

            // Generate OTP for verification
            $user->otps()->create([
                'code' => str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT),
                'expires_at' => now()->addDays(7),
            ]);

            return $user;
        });
    }

    /**
     * Get organization members by role.
     */
    public function getMembersByRole(Organization $organization, string $role)
    {
        return User::role($role)
            ->where('tenant_id', $organization->tenant_id)
            ->whereJsonContains('metadata->organization_id', $organization->id)
            ->paginate();
    }

    /**
     * Update organization settings.
     */
    public function updateSettings(Organization $organization, array $settings): bool
    {
        return $organization->update([
            'settings' => array_merge($organization->settings ?? [], $settings),
        ]);
    }

    /**
     * Check if organization has reached its member limit.
     */
    public function hasReachedMemberLimit(Organization $organization, string $role): bool
    {
        $subscription = $organization->subscriptions()
            ->where('status', 'active')
            ->first();

        if (!$subscription) {
            return true;
        }

        $limits = $subscription->features_snapshot['limits'] ?? [];
        $currentCount = $this->getMembersByRole($organization, $role)->total();

        switch ($role) {
            case 'player':
                return $currentCount >= ($limits['max_players'] ?? 0);
            case 'coach':
                return $currentCount >= ($limits['max_coaches'] ?? 0);
            default:
                return false;
        }
    }
}
