<?php

namespace App\Policies;

use App\Models\PlayerEvaluation;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PlayerEvaluationPolicy
{
    use HandlesAuthorization;

    /**
     * Determine if the user can view any evaluations.
     */
    public function viewAny(User $user): bool
    {
        // Managers and coaches can view evaluations
        return $user->hasRole('manager') || $user->hasRole('coach');
    }

    /**
     * Determine if the user can view the evaluation.
     */
    public function view(User $user, PlayerEvaluation $evaluation): bool
    {
        // Organization managers can view all evaluations
        if ($user->hasRole('manager') && $user->tenant_id === $evaluation->player->tenant_id) {
            return true;
        }

        // Coaches can view evaluations they created
        if ($evaluation->evaluated_by === $user->id) {
            return true;
        }

        // Players can view their own evaluations
        if ($evaluation->user_id === $user->id) {
            return true;
        }

        // Guardians can view their player's evaluations
        if ($user->hasRole('guardian') && 
            $user->metadata['player_id'] === $evaluation->user_id) {
            return true;
        }

        return false;
    }

    /**
     * Determine if the user can create evaluations.
     */
    public function create(User $user): bool
    {
        // Only coaches can create evaluations
        return $user->hasRole('coach');
    }

    /**
     * Determine if the user can update the evaluation.
     */
    public function update(User $user, PlayerEvaluation $evaluation): bool
    {
        // Only the evaluator can update their evaluations
        // And only within 24 hours of creation
        return $evaluation->evaluated_by === $user->id && 
            $evaluation->created_at->diffInHours(now()) <= 24;
    }

    /**
     * Determine if the user can delete the evaluation.
     */
    public function delete(User $user, PlayerEvaluation $evaluation): bool
    {
        // Only the evaluator can delete their evaluations
        // And only within 24 hours of creation
        return $evaluation->evaluated_by === $user->id && 
            $evaluation->created_at->diffInHours(now()) <= 24;
    }

    /**
     * Determine if the user can evaluate a specific player.
     */
    public function evaluate(User $user, User $player): bool
    {
        // Must be a coach
        if (!$user->hasRole('coach')) {
            return false;
        }

        // Player must be in one of the coach's teams
        return $user->teams()
            ->whereHas('players', function ($query) use ($player) {
                $query->where('users.id', $player->id);
            })
            ->exists();
    }

    /**
     * Determine if the user can view evaluation statistics.
     */
    public function viewStats(User $user, User $player): bool
    {
        // Organization managers can view all stats
        if ($user->hasRole('manager') && $user->tenant_id === $player->tenant_id) {
            return true;
        }

        // Coaches can view stats for their team players
        if ($user->hasRole('coach')) {
            return $user->teams()
                ->whereHas('players', function ($query) use ($player) {
                    $query->where('users.id', $player->id);
                })
                ->exists();
        }

        // Players can view their own stats
        if ($user->id === $player->id) {
            return true;
        }

        // Guardians can view their player's stats
        if ($user->hasRole('guardian') && 
            $user->metadata['player_id'] === $player->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine if the user can view evaluation history.
     */
    public function viewHistory(User $user, User $player): bool
    {
        // Same rules as viewStats
        return $this->viewStats($user, $player);
    }

    /**
     * Determine if the user can export evaluations.
     */
    public function export(User $user, User $player): bool
    {
        // Organization managers can export all evaluations
        if ($user->hasRole('manager') && $user->tenant_id === $player->tenant_id) {
            return true;
        }

        // Players can export their own evaluations
        if ($user->id === $player->id) {
            return true;
        }

        // Guardians can export their player's evaluations
        if ($user->hasRole('guardian') && 
            $user->metadata['player_id'] === $player->id) {
            return true;
        }

        return false;
    }
}
