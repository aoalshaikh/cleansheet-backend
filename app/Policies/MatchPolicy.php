<?php

namespace App\Policies;

use App\Models\GameMatch;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class MatchPolicy
{
    use HandlesAuthorization;

    /**
     * Determine if the user can view any matches.
     */
    public function viewAny(User $user): bool
    {
        return true; // All authenticated users can view matches
    }

    /**
     * Determine if the user can view the match.
     */
    public function view(User $user, GameMatch $match): bool
    {
        // Users can view matches in their organization
        return $user->tenant_id === $match->team->organization->tenant_id;
    }

    /**
     * Determine if the user can create matches.
     */
    public function create(User $user): bool
    {
        // Organization managers and coaches can create matches
        return $user->hasRole('manager') || $user->hasRole('coach');
    }

    /**
     * Determine if the user can update the match.
     */
    public function update(User $user, GameMatch $match): bool
    {
        // Organization managers can update any match
        if ($user->hasRole('manager') && 
            $user->tenant_id === $match->team->organization->tenant_id) {
            return true;
        }

        // Team coach can update their team's matches
        return $match->team->coach_id === $user->id;
    }

    /**
     * Determine if the user can delete the match.
     */
    public function delete(User $user, GameMatch $match): bool
    {
        // Organization managers can delete any match
        if ($user->hasRole('manager') && 
            $user->tenant_id === $match->team->organization->tenant_id) {
            return true;
        }

        // Team coach can delete their team's matches
        return $match->team->coach_id === $user->id;
    }

    /**
     * Determine if the user can restore the match.
     */
    public function restore(User $user, GameMatch $match): bool
    {
        // Organization managers can restore any match
        if ($user->hasRole('manager') && 
            $user->tenant_id === $match->team->organization->tenant_id) {
            return true;
        }

        // Team coach can restore their team's matches
        return $match->team->coach_id === $user->id;
    }

    /**
     * Determine if the user can permanently delete the match.
     */
    public function forceDelete(User $user, GameMatch $match): bool
    {
        // Only organization managers can force delete matches
        return $user->hasRole('manager') && 
            $user->tenant_id === $match->team->organization->tenant_id;
    }

    /**
     * Determine if the user can manage match events.
     */
    public function manageEvents(User $user, GameMatch $match): bool
    {
        // Organization managers can manage any match events
        if ($user->hasRole('manager') && 
            $user->tenant_id === $match->team->organization->tenant_id) {
            return true;
        }

        // Team coach can manage their team's match events
        return $match->team->coach_id === $user->id;
    }

    /**
     * Determine if the user can manage match lineups.
     */
    public function manageLineups(User $user, GameMatch $match): bool
    {
        // Organization managers can manage any match lineups
        if ($user->hasRole('manager') && 
            $user->tenant_id === $match->team->organization->tenant_id) {
            return true;
        }

        // Team coach can manage their team's match lineups
        return $match->team->coach_id === $user->id;
    }

    /**
     * Determine if the user can start the match.
     */
    public function start(User $user, GameMatch $match): bool
    {
        // Organization managers can start any match
        if ($user->hasRole('manager') && 
            $user->tenant_id === $match->team->organization->tenant_id) {
            return true;
        }

        // Team coach can start their team's matches
        return $match->team->coach_id === $user->id;
    }

    /**
     * Determine if the user can complete the match.
     */
    public function complete(User $user, GameMatch $match): bool
    {
        // Organization managers can complete any match
        if ($user->hasRole('manager') && 
            $user->tenant_id === $match->team->organization->tenant_id) {
            return true;
        }

        // Team coach can complete their team's matches
        return $match->team->coach_id === $user->id;
    }

    /**
     * Determine if the user can cancel the match.
     */
    public function cancel(User $user, GameMatch $match): bool
    {
        // Organization managers can cancel any match
        if ($user->hasRole('manager') && 
            $user->tenant_id === $match->team->organization->tenant_id) {
            return true;
        }

        // Team coach can cancel their team's matches
        return $match->team->coach_id === $user->id;
    }

    /**
     * Determine if the user can view match statistics.
     */
    public function viewStats(User $user, GameMatch $match): bool
    {
        // All users in the organization can view match stats
        return $user->tenant_id === $match->team->organization->tenant_id;
    }
}
