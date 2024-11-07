<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class OrganizationPolicy
{
    use HandlesAuthorization;

    /**
     * Determine if the user can view any organizations.
     */
    public function viewAny(User $user): bool
    {
        return true; // All authenticated users can view organizations
    }

    /**
     * Determine if the user can view the organization.
     */
    public function view(User $user, Organization $organization): bool
    {
        // Users can view organizations they belong to
        return $user->tenant_id === $organization->tenant_id;
    }

    /**
     * Determine if the user can create organizations.
     */
    public function create(User $user): bool
    {
        // Only superadmins can create organizations
        return $user->isSuperAdmin();
    }

    /**
     * Determine if the user can update the organization.
     */
    public function update(User $user, Organization $organization): bool
    {
        // Organization owners and managers can update
        if ($organization->metadata['owner_id'] === $user->id) {
            return true;
        }

        return $user->hasRole('manager') && 
            $user->tenant_id === $organization->tenant_id;
    }

    /**
     * Determine if the user can delete the organization.
     */
    public function delete(User $user, Organization $organization): bool
    {
        // Only organization owners and superadmins can delete
        return $organization->metadata['owner_id'] === $user->id || 
            $user->isSuperAdmin();
    }

    /**
     * Determine if the user can restore the organization.
     */
    public function restore(User $user, Organization $organization): bool
    {
        // Only superadmins can restore
        return $user->isSuperAdmin();
    }

    /**
     * Determine if the user can permanently delete the organization.
     */
    public function forceDelete(User $user, Organization $organization): bool
    {
        // Only superadmins can force delete
        return $user->isSuperAdmin();
    }

    /**
     * Determine if the user can manage organization members.
     */
    public function manageMembers(User $user, Organization $organization): bool
    {
        // Organization owners and managers can manage members
        if ($organization->metadata['owner_id'] === $user->id) {
            return true;
        }

        return $user->hasRole('manager') && 
            $user->tenant_id === $organization->tenant_id;
    }

    /**
     * Determine if the user can manage organization subscriptions.
     */
    public function manageSubscriptions(User $user, Organization $organization): bool
    {
        // Only organization owners can manage subscriptions
        return $organization->metadata['owner_id'] === $user->id;
    }

    /**
     * Determine if the user can view organization statistics.
     */
    public function viewStats(User $user, Organization $organization): bool
    {
        // Organization owners, managers, and coaches can view stats
        if ($organization->metadata['owner_id'] === $user->id) {
            return true;
        }

        return ($user->hasRole('manager') || $user->hasRole('coach')) && 
            $user->tenant_id === $organization->tenant_id;
    }

    /**
     * Determine if the user can manage organization settings.
     */
    public function manageSettings(User $user, Organization $organization): bool
    {
        // Only organization owners can manage settings
        return $organization->metadata['owner_id'] === $user->id;
    }

    /**
     * Determine if the user can view organization financial information.
     */
    public function viewFinancials(User $user, Organization $organization): bool
    {
        // Only organization owners can view financials
        return $organization->metadata['owner_id'] === $user->id;
    }
}
