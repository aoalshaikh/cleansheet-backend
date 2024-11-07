<?php

namespace App\Policies;

use App\Models\Team;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class TeamPolicy
{
    use HandlesAuthorization;

    /**
     * Determine if the user can view any teams.
     */
    public function viewAny(User $user): bool
    {
        return true; // All authenticated users can view teams
    }

    /**
     * Determine if the user can view the team.
     */
    public function view(User $user, Team $team): bool
    {
        // Users can view teams in their organization
        return $user->tenant_id === $team->organization->tenant_id;
    }

    /**
     * Determine if the user can create teams.
     */
    public function create(User $user): bool
    {
        // Organization managers can create teams
        return $user->hasRole('manager');
    }

    /**
     * Determine if the user can update the team.
     */
    public function update(User $user, Team $team): bool
    {
        // Organization managers and team coaches can update
        if ($user->hasRole('manager') && $user->tenant_id === $team->organization->tenant_id) {
            return true;
        }

        return $team->coach_id === $user->id;
    }

    /**
     * Determine if the user can delete the team.
     */
    public function delete(User $user, Team $team): bool
    {
        // Only organization managers can delete teams
        return $user->hasRole('manager') && 
            $user->tenant_id === $team->organization->tenant_id;
    }

    /**
     * Determine if the user can restore the team.
     */
    public function restore(User $user, Team $team): bool
    {
        // Only organization managers can restore teams
        return $user->hasRole('manager') && 
            $user->tenant_id === $team->organization->tenant_id;
    }

    /**
     * Determine if the user can permanently delete the team.
     */
    public function forceDelete(User $user, Team $team): bool
    {
        // Only organization managers can force delete teams
        return $user->hasRole('manager') && 
            $user->tenant_id === $team->organization->tenant_id;
    }

    /**
     * Determine if the user can manage team members.
     */
    public function manageMembers(User $user, Team $team): bool
    {
        // Organization managers and team coaches can manage members
        if ($user->hasRole('manager') && $user->tenant_id === $team->organization->tenant_id) {
            return true;
        }

        return $team->coach_id === $user->id;
    }

    /**
     * Determine if the user can manage team schedule.
     */
    public function manageSchedule(User $user, Team $team): bool
    {
        // Organization managers and team coaches can manage schedule
        if ($user->hasRole('manager') && $user->tenant_id === $team->organization->tenant_id) {
            return true;
        }

        return $team->coach_id === $user->id;
    }

    /**
     * Determine if the user can manage team tiers.
     */
    public function manageTiers(User $user, Team $team): bool
    {
        // Organization managers and team coaches can manage tiers
        if ($user->hasRole('manager') && $user->tenant_id === $team->organization->tenant_id) {
            return true;
        }

        return $team->coach_id === $user->id;
    }

    /**
     * Determine if the user can view team statistics.
     */
    public function viewStats(User $user, Team $team): bool
    {
        // Organization managers, team coaches, and team players can view stats
        if ($user->hasRole('manager') && $user->tenant_id === $team->organization->tenant_id) {
            return true;
        }

        if ($team->coach_id === $user->id) {
            return true;
        }

        return $user->hasRole('player') && 
            $team->players()->where('users.id', $user->id)->exists();
    }

    /**
     * Determine if the user can manage team matches.
     */
    public function manageMatches(User $user, Team $team): bool
    {
        // Organization managers and team coaches can manage matches
        if ($user->hasRole('manager') && $user->tenant_id === $team->organization->tenant_id) {
            return true;
        }

        return $team->coach_id === $user->id;
    }

    /**
     * Determine if the user can evaluate players.
     */
    public function evaluatePlayers(User $user, Team $team): bool
    {
        // Only team coaches can evaluate players
        return $team->coach_id === $user->id;
    }

    /**
     * Determine if the user can view team evaluations.
     */
    public function viewEvaluations(User $user, Team $team): bool
    {
        // Organization managers, team coaches, and evaluated players can view evaluations
        if ($user->hasRole('manager') && $user->tenant_id === $team->organization->tenant_id) {
            return true;
        }

        if ($team->coach_id === $user->id) {
            return true;
        }

        return $user->hasRole('player') && 
            $team->players()->where('users.id', $user->id)->exists();
    }
}
