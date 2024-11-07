<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Profile\UpdateAvatarRequest;
use App\Http\Requests\Profile\UpdatePasswordRequest;
use App\Http\Requests\Profile\UpdatePreferencesRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ProfileController extends ApiController
{
    /**
     * Get the authenticated user's profile.
     */
    public function show(): JsonResponse
    {
        $user = Auth::user();
        $user->load(['roles', 'permissions']);

        return $this->success(new UserResource($user));
    }

    /**
     * Update the authenticated user's profile.
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = Auth::user();
        $validated = $request->validated();

        // Check if email is being changed
        if (isset($validated['email']) && $validated['email'] !== $user->email) {
            $validated['email_verified_at'] = null;
        }

        // Check if phone is being changed
        if (isset($validated['phone']) && $validated['phone'] !== $user->phone) {
            $validated['phone_verified_at'] = null;
        }

        $user->update($validated);
        $user->refresh();
        $user->load(['roles', 'permissions']);

        return $this->success(new UserResource($user));
    }

    /**
     * Update the authenticated user's password.
     */
    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $user = Auth::user();
        $user->update([
            'password' => Hash::make($request->password),
        ]);

        // Log out other devices
        Auth::logoutOtherDevices($request->password);

        return $this->success([
            'message' => 'Password updated successfully',
        ]);
    }

    /**
     * Upload a new avatar for the authenticated user.
     */
    public function uploadAvatar(UpdateAvatarRequest $request): JsonResponse
    {
        $user = Auth::user();

        // Delete old avatar if exists
        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        // Store new avatar using the request's helper method
        $path = $request->file('avatar')->store(
            $request->getStoragePath(),
            'public'
        );

        $user->update(['avatar_path' => $path]);
        $user->refresh();
        $user->load(['roles', 'permissions']);

        return $this->success(new UserResource($user));
    }

    /**
     * Delete the authenticated user's avatar.
     */
    public function deleteAvatar(): JsonResponse
    {
        $user = Auth::user();

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
            $user->update(['avatar_path' => null]);
        }

        return $this->noContent();
    }

    /**
     * Update the authenticated user's preferences.
     */
    public function updatePreferences(UpdatePreferencesRequest $request): JsonResponse
    {
        $user = Auth::user();
        $preferences = $request->getValidatedPreferences();

        $user->update(['preferences' => $preferences]);
        $user->refresh();
        $user->load(['roles', 'permissions']);

        return $this->success(new UserResource($user));
    }

    /**
     * Delete the authenticated user's account.
     */
    public function destroy(): JsonResponse
    {
        $user = Auth::user();

        // Delete avatar if exists
        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        // Soft delete the user
        $user->delete();

        // Log out the user
        Auth::logout();

        return $this->noContent();
    }
}
