<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    /**
     * Determine if the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Managers can view all users in their organization
        return $user->hasRole('manager');
    }

    /**
     * Determine if the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        // Users can view their own profile
        if ($user->id === $model->id) {
            return true;
        }

        // Managers can view users in their organization
        if ($user->hasRole('manager') && $user->tenant_id === $model->tenant_id) {
            return true;
        }

        // Coaches can view their team players
        if ($user->hasRole('coach')) {
            return $user->teams()
                ->whereHas('players', function ($query) use ($model) {
                    $query->where('users.id', $model->id);
                })
                ->exists();
        }

        // Guardians can view their player's profile
        if ($user->hasRole('guardian') && 
            $user->metadata['player_id'] === $model->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine if the user can create models.
     */
    public function create(User $user): bool
    {
        // Only managers can create new users
        return $user->hasRole('manager');
    }

    /**
     * Determine if the user can update the model.
     */
    public function update(User $user, User $model): bool
    {
        // Users can update their own profile
        if ($user->id === $model->id) {
            return true;
        }

        // Managers can update users in their organization
        if ($user->hasRole('manager') && $user->tenant_id === $model->tenant_id) {
            return true;
        }

        // Guardians can update their player's profile
        if ($user->hasRole('guardian') && 
            $user->metadata['player_id'] === $model->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine if the user can delete the model.
     */
    public function delete(User $user, User $model): bool
    {
        // Only managers can delete users
        return $user->hasRole('manager') && 
            $user->tenant_id === $model->tenant_id;
    }

    /**
     * Determine if the user can restore the model.
     */
    public function restore(User $user, User $model): bool
    {
        // Only managers can restore users
        return $user->hasRole('manager') && 
            $user->tenant_id === $model->tenant_id;
    }

    /**
     * Determine if the user can permanently delete the model.
     */
    public function forceDelete(User $user, User $model): bool
    {
        // Only managers can force delete users
        return $user->hasRole('manager') && 
            $user->tenant_id === $model->tenant_id;
    }

    /**
     * Determine if the user can update notification preferences.
     */
    public function updateNotificationPreferences(User $user, User $model): bool
    {
        // Users can update their own notification preferences
        if ($user->id === $model->id) {
            return true;
        }

        // Guardians can update their player's notification preferences
        if ($user->hasRole('guardian') && 
            $user->metadata['player_id'] === $model->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine if the user can manage subscriptions.
     */
    public function manageSubscriptions(User $user, User $model): bool
    {
        // Users can manage their own subscriptions
        if ($user->id === $model->id) {
            return true;
        }

        // Guardians can manage their player's subscriptions
        if ($user->hasRole('guardian') && 
            $user->metadata['player_id'] === $model->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine if the user can view attendance history.
     */
    public function viewAttendance(User $user, User $model): bool
    {
        // Users can view their own attendance
        if ($user->id === $model->id) {
            return true;
        }

        // Managers can view attendance for users in their organization
        if ($user->hasRole('manager') && $user->tenant_id === $model->tenant_id) {
            return true;
        }

        // Coaches can view attendance for their team players
        if ($user->hasRole('coach')) {
            return $user->teams()
                ->whereHas('players', function ($query) use ($model) {
                    $query->where('users.id', $model->id);
                })
                ->exists();
        }

        // Guardians can view their player's attendance
        if ($user->hasRole('guardian') && 
            $user->metadata['player_id'] === $model->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine if the user can evaluate another user.
     */
    public function evaluate(User $user, User $model): bool
    {
        // Only coaches can evaluate players
        if (!$user->hasRole('coach')) {
            return false;
        }

        // Player must be in one of the coach's teams
        return $user->teams()
            ->whereHas('players', function ($query) use ($model) {
                $query->where('users.id', $model->id);
            })
            ->exists();
    }
}
