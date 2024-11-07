<?php

namespace App\Http\Controllers;

use App\Http\Requests\Profile\UpdateAvatarRequest;
use App\Http\Requests\Profile\UpdatePasswordRequest;
use App\Http\Requests\Profile\UpdatePreferencesRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        $this->middleware(['auth', 'verified', 'tenant']);
    }

    /**
     * Show the user's profile.
     */
    public function show(Request $request): View
    {
        return view('profile.show', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->fill($request->validated());
        $user->save();

        return redirect()
            ->route('profile.show')
            ->with('status', 'Profile updated successfully.');
    }

    /**
     * Update the user's password.
     */
    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->password = Hash::make($request->input('password'));
        $user->save();

        return redirect()
            ->route('profile.show')
            ->with('status', 'Password updated successfully.');
    }

    /**
     * Update the user's avatar.
     */
    public function updateAvatar(UpdateAvatarRequest $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->avatar_path) {
            Storage::delete($user->avatar_path);
        }

        $path = $request->file('avatar')->store('avatars', 'public');
        $user->avatar_path = $path;
        $user->save();

        return redirect()
            ->route('profile.show')
            ->with('status', 'Avatar updated successfully.');
    }

    /**
     * Update the user's preferences.
     */
    public function updatePreferences(UpdatePreferencesRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->preferences = array_merge(
            $user->preferences ?? [],
            $request->validated()['preferences'] ?? []
        );
        $user->save();

        return redirect()
            ->route('profile.show')
            ->with('status', 'Preferences updated successfully.');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        // Delete avatar if exists
        if ($user->avatar_path) {
            Storage::delete($user->avatar_path);
        }

        // Log the account deletion
        activity()
            ->causedBy($user)
            ->forTenant($user->tenant)
            ->log('Account deleted');

        // Delete the user
        $user->delete();

        // Logout and invalidate session
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('welcome')
            ->with('status', 'Account deleted successfully.');
    }
}
