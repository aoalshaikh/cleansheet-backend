<?php

namespace App\Services\Auth;

use App\Models\Otp;
use App\Models\Organization;
use App\Models\User;
use App\Services\Organization\OrganizationService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthService
{
    public function __construct(
        private readonly OrganizationService $organizationService
    ) {}

    /**
     * Register a new user.
     */
    public function register(array $data): User
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'],
            'password' => isset($data['password']) ? Hash::make($data['password']) : null,
            'tenant_id' => $data['tenant_id'] ?? null,
        ]);

        // Assign role
        if (isset($data['role'])) {
            $user->assignRole($data['role']);
        }

        // Create guardian account if user is a player and age < 18
        if (isset($data['role']) && $data['role'] === 'player' && isset($data['date_of_birth'])) {
            $age = now()->diffInYears($data['date_of_birth']);
            if ($age < 18 && isset($data['guardian'])) {
                $this->createGuardianAccount($user, $data['guardian']);
            }
        }

        // Generate OTP for phone verification
        if ($user->phone) {
            $this->generateOtp($user);
        }

        return $user;
    }

    /**
     * Register a new organization with its manager.
     */
    public function registerOrganization(array $data, User $owner): Organization
    {
        return $this->organizationService->signup($data, $owner);
    }

    /**
     * Login a user.
     *
     * @return array{user: User, token?: string, requires_otp: bool}|null
     */
    public function login(array $credentials): ?array
    {
        $field = filter_var($credentials['username'], FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
        $user = User::where($field, $credentials['username'])->first();

        if (!$user) {
            return null;
        }

        // For phone login, generate OTP
        if ($field === 'phone') {
            $this->generateOtp($user);
            return ['user' => $user, 'requires_otp' => true];
        }

        // For email login, verify password
        if (!Hash::check($credentials['password'], $user->password)) {
            return null;
        }

        $token = JWTAuth::fromUser($user);
        return [
            'user' => $user,
            'token' => $token,
            'requires_otp' => false,
        ];
    }

    /**
     * Verify OTP.
     *
     * @return array{user: User, token: string}|null
     */
    public function verifyOtp(string $phone, string $code): ?array
    {
        $user = User::where('phone', $phone)->first();
        if (!$user) {
            return null;
        }

        $otp = $user->otps()
            ->where('code', $code)
            ->where('expires_at', '>', now())
            ->first();

        if (!$otp) {
            return null;
        }

        $otp->delete();
        $token = JWTAuth::fromUser($user);

        if (!$user->phone_verified_at) {
            $user->markPhoneAsVerified();
        }

        return [
            'user' => $user,
            'token' => $token,
        ];
    }

    /**
     * Generate new OTP for user.
     */
    public function generateOtp(User $user): Otp
    {
        // Delete any existing OTPs
        $user->otps()->delete();

        return $user->otps()->create([
            'code' => str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT),
            'expires_at' => now()->addMinutes(10),
        ]);
    }

    /**
     * Create a guardian account for a minor player.
     */
    protected function createGuardianAccount(User $player, array $guardianData): User
    {
        $guardian = User::create([
            'name' => $guardianData['name'],
            'email' => $guardianData['email'] ?? null,
            'phone' => $guardianData['phone'],
            'password' => isset($guardianData['password']) ? Hash::make($guardianData['password']) : null,
            'tenant_id' => $player->tenant_id,
            'metadata' => [
                'is_guardian' => true,
                'player_id' => $player->id,
                'relationship' => $guardianData['relationship'] ?? 'parent',
            ],
        ]);

        $guardian->assignRole('guardian');

        // Update player's metadata with guardian info
        $player->update([
            'metadata' => array_merge($player->metadata ?? [], [
                'guardian_id' => $guardian->id,
            ]),
        ]);

        return $guardian;
    }
}
