<?php

namespace Tests\Traits;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;

trait InteractsWithAuthentication
{
    /**
     * The currently authenticated user.
     */
    protected ?Authenticatable $authenticatedUser = null;

    /**
     * Set up authentication mocks.
     */
    protected function setUpAuth(?Authenticatable $user = null): void
    {
        $this->authenticatedUser = $user;

        Auth::shouldReceive('guard')
            ->andReturn(Auth::guard());

        Auth::shouldReceive('user')
            ->andReturn($this->authenticatedUser);

        Auth::shouldReceive('check')
            ->andReturn($this->authenticatedUser !== null);

        Auth::shouldReceive('guest')
            ->andReturn($this->authenticatedUser === null);
    }

    /**
     * Act as a given user.
     */
    protected function actingAsUser(User $user): static
    {
        $this->authenticatedUser = $user;
        $this->setUpAuth($user);
        return $this;
    }

    /**
     * Act as a guest.
     */
    protected function actingAsGuest(): static
    {
        $this->authenticatedUser = null;
        $this->setUpAuth();
        return $this;
    }

    /**
     * Get the currently authenticated user.
     */
    protected function getAuthenticatedUser(): ?Authenticatable
    {
        return $this->authenticatedUser;
    }

    /**
     * Assert that a user is authenticated.
     */
    protected function assertAuthenticated(?string $guard = null): void
    {
        $this->assertNotNull(
            $this->authenticatedUser,
            'Expected to be authenticated, but was not.'
        );
    }

    /**
     * Assert that a user is not authenticated.
     */
    protected function assertGuest(?string $guard = null): void
    {
        $this->assertNull(
            $this->authenticatedUser,
            'Expected to be a guest, but was authenticated.'
        );
    }

    /**
     * Assert that the currently authenticated user is the given user.
     */
    protected function assertAuthenticatedAs(Authenticatable $user, ?string $guard = null): void
    {
        $this->assertTrue(
            $this->authenticatedUser && 
            $this->authenticatedUser->getAuthIdentifier() === $user->getAuthIdentifier(),
            'Expected to be authenticated as the given user, but was not.'
        );
    }

    /**
     * Assert that the given credentials are valid.
     */
    protected function assertCredentials(array $credentials, ?string $guard = null): void
    {
        $this->assertTrue(
            Auth::validate($credentials),
            'The given credentials are invalid.'
        );
    }

    /**
     * Assert that the given credentials are invalid.
     */
    protected function assertInvalidCredentials(array $credentials, ?string $guard = null): void
    {
        $this->assertFalse(
            Auth::validate($credentials),
            'The given credentials are valid.'
        );
    }

    /**
     * Return a user with the given permissions.
     */
    protected function userWithPermissions(array $permissions): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);
        return $user;
    }

    /**
     * Return a user with the given roles.
     */
    protected function userWithRoles(array $roles): User
    {
        $user = User::factory()->create();
        $user->assignRole($roles);
        return $user;
    }

    /**
     * Return a user with the given roles and permissions.
     */
    protected function userWithRolesAndPermissions(array $roles, array $permissions): User
    {
        $user = User::factory()->create();
        $user->assignRole($roles);
        $user->givePermissionTo($permissions);
        return $user;
    }

    /**
     * Return a super admin user.
     */
    protected function superAdminUser(): User
    {
        return $this->userWithRoles([config('permission.super_admin_role')]);
    }

    /**
     * Return a tenant admin user.
     */
    protected function tenantAdminUser(): User
    {
        return $this->userWithRoles(['tenant-admin']);
    }

    /**
     * Return a tenant user.
     */
    protected function tenantUser(): User
    {
        return $this->userWithRoles(['tenant-user']);
    }

    /**
     * Compare two users for equality.
     */
    protected function isSameUser(?Authenticatable $user1, ?Authenticatable $user2): bool
    {
        if ($user1 === null || $user2 === null) {
            return $user1 === $user2;
        }

        return $user1->getAuthIdentifier() === $user2->getAuthIdentifier();
    }
}
