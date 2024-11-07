<?php

namespace App\Providers;

use App\Models\GameMatch;
use App\Models\Organization;
use App\Models\PlayerEvaluation;
use App\Models\Team;
use App\Models\User;
use App\Policies\MatchPolicy;
use App\Policies\OrganizationPolicy;
use App\Policies\PlayerEvaluationPolicy;
use App\Policies\TeamPolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Organization::class => OrganizationPolicy::class,
        Team::class => TeamPolicy::class,
        PlayerEvaluation::class => PlayerEvaluationPolicy::class,
        User::class => UserPolicy::class,
        GameMatch::class => MatchPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        // Register policies
        $this->registerPolicies();

        // Define super admin gate
        Gate::before(function (User $user, string $ability) {
            if ($user->isSuperAdmin()) {
                return true;
            }
        });

        // Organization-specific gates
        Gate::define('manage-organization', function (User $user, Organization $organization) {
            return $organization->metadata['owner_id'] === $user->id || 
                ($user->hasRole('manager') && $user->tenant_id === $organization->tenant_id);
        });

        Gate::define('manage-organization-members', function (User $user, Organization $organization) {
            return $organization->metadata['owner_id'] === $user->id || 
                ($user->hasRole('manager') && $user->tenant_id === $organization->tenant_id);
        });

        // Team-specific gates
        Gate::define('manage-team', function (User $user, Team $team) {
            return $user->hasRole('manager') || $team->coach_id === $user->id;
        });

        Gate::define('manage-team-players', function (User $user, Team $team) {
            return $user->hasRole('manager') || $team->coach_id === $user->id;
        });

        // Player evaluation gates
        Gate::define('evaluate-players', function (User $user, Team $team) {
            return $team->coach_id === $user->id;
        });

        Gate::define('view-evaluations', function (User $user, User $player) {
            // Player can view their own evaluations
            if ($user->id === $player->id) {
                return true;
            }

            // Guardian can view their player's evaluations
            if ($user->hasRole('guardian') && $user->metadata['player_id'] === $player->id) {
                return true;
            }

            // Coach can view their team players' evaluations
            if ($user->hasRole('coach')) {
                return $user->teams()
                    ->whereHas('players', function ($query) use ($player) {
                        $query->where('users.id', $player->id);
                    })
                    ->exists();
            }

            // Manager can view all evaluations in their organization
            return $user->hasRole('manager') && $user->tenant_id === $player->tenant_id;
        });

        // Subscription gates
        Gate::define('manage-subscriptions', function (User $user, Organization $organization) {
            return $organization->metadata['owner_id'] === $user->id;
        });

        // Notification gates
        Gate::define('manage-notifications', function (User $user, Organization $organization) {
            return $organization->metadata['owner_id'] === $user->id || 
                ($user->hasRole('manager') && $user->tenant_id === $organization->tenant_id);
        });

        // Guardian-specific gates
        Gate::define('manage-player', function (User $user, User $player) {
            return $user->hasRole('guardian') && $user->metadata['player_id'] === $player->id;
        });
    }
}
