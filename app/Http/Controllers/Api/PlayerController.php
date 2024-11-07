<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Player\PlayerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * @OA\Tag(
 *     name="Players",
 *     description="Player profile and management endpoints"
 * )
 */
class PlayerController extends Controller
{
    public function __construct(
        private readonly PlayerService $playerService
    ) {}

    /**
     * Get user profile.
     * 
     * @OA\Get(
     *     path="/profile",
     *     summary="Get user profile",
     *     description="Get authenticated user's profile information",
     *     operationId="getUserProfile",
     *     tags={"Players"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Profile retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="user", ref="#/components/schemas/PlayerProfile")
     *         )
     *     )
     * )
     */
    public function profile(Request $request): JsonResponse
    {
        $user = $request->user()->load([
            'roles',
            'teams',
            'teams.tiers' => fn($query) => $query->where('team_tier_players.left_at', null),
        ]);

        return response()->json([
            'user' => $user,
        ]);
    }

    /**
     * Update user profile.
     * 
     * @OA\Put(
     *     path="/profile",
     *     summary="Update user profile",
     *     description="Update authenticated user's profile information",
     *     operationId="updateUserProfile",
     *     tags={"Players"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", maxLength=255),
     *             @OA\Property(property="email", type="string", format="email", maxLength=255),
     *             @OA\Property(property="phone", type="string"),
     *             @OA\Property(
     *                 property="password",
     *                 type="string",
     *                 format="password",
     *                 description="Must be at least 8 characters with mixed case, numbers, and symbols"
     *             ),
     *             @OA\Property(property="preferences", type="object"),
     *             @OA\Property(property="settings", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Profile updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Profile updated successfully"),
     *             @OA\Property(property="user", ref="#/components/schemas/PlayerProfile")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     * @throws ValidationException
     */
    public function updateProfile(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $validated = $request->validate([
                'name' => ['sometimes', 'string', 'max:255'],
                'email' => [
                    'sometimes',
                    'string',
                    'email',
                    'max:255',
                    "unique:users,email,{$user->id}",
                ],
                'phone' => [
                    'sometimes',
                    'string',
                    "unique:users,phone,{$user->id}",
                ],
                'password' => [
                    'sometimes',
                    Password::min(8)
                        ->letters()
                        ->mixedCase()
                        ->numbers()
                        ->symbols()
                ],
                'preferences' => ['sometimes', 'array'],
                'settings' => ['sometimes', 'array'],
            ]);

            $user->update($validated);

            return response()->json([
                'message' => 'Profile updated successfully',
                'user' => $user->fresh(['roles']),
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update profile',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update user avatar.
     * 
     * @OA\Post(
     *     path="/profile/avatar",
     *     summary="Update user avatar",
     *     description="Upload and update user's profile avatar",
     *     operationId="updateUserAvatar",
     *     tags={"Players"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(
     *                     property="avatar",
     *                     type="string",
     *                     format="binary",
     *                     description="Image file (max 2MB)"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Avatar updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Avatar updated successfully"),
     *             @OA\Property(property="avatar_url", type="string")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     * @throws ValidationException
     */
    public function updateAvatar(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'avatar' => ['required', 'image', 'max:2048'], // 2MB max
            ]);

            $user = $request->user();

            // Delete old avatar if exists
            if ($user->avatar_path) {
                Storage::disk('public')->delete($user->avatar_path);
            }

            // Store new avatar
            $path = $request->file('avatar')->store('avatars', 'public');
            $user->update(['avatar_path' => $path]);

            return response()->json([
                'message' => 'Avatar updated successfully',
                'avatar_url' => $user->avatar_url,
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update avatar',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get player statistics.
     * 
     * @OA\Get(
     *     path="/players/{player}/stats",
     *     summary="Get player statistics",
     *     description="Get statistical data for a specific player",
     *     operationId="getPlayerStats",
     *     tags={"Players"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="player",
     *         in="path",
     *         description="Player ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Statistics retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="stats", ref="#/components/schemas/PlayerStats")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Unauthorized access")
     * )
     */
    public function stats(User $player): JsonResponse
    {
        $this->authorize('view', $player);

        try {
            $stats = $this->playerService->getStats($player);

            return response()->json([
                'stats' => $stats,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to get player stats',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get player attendance history.
     * 
     * @OA\Get(
     *     path="/players/{player}/attendance",
     *     summary="Get player attendance history",
     *     description="Get attendance history for a specific player",
     *     operationId="getPlayerAttendance",
     *     tags={"Players"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="player",
     *         in="path",
     *         description="Player ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         description="Start date for attendance history",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         description="End date for attendance history",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Attendance history retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="attendance",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/PlayerAttendance")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=403, description="Unauthorized access"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function attendance(Request $request, User $player): JsonResponse
    {
        $this->authorize('view', $player);

        try {
            $validated = $request->validate([
                'start_date' => ['sometimes', 'date'],
                'end_date' => ['sometimes', 'date', 'after:start_date'],
            ]);

            $attendance = $this->playerService->getAttendanceHistory(
                $player,
                $validated['start_date'] ?? null,
                $validated['end_date'] ?? null
            );

            return response()->json([
                'attendance' => $attendance,
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to get attendance history',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get player skill progress.
     * 
     * @OA\Get(
     *     path="/players/{player}/skills",
     *     summary="Get player skill progress",
     *     description="Get skill progress data for a specific player",
     *     operationId="getPlayerSkillProgress",
     *     tags={"Players"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="player",
     *         in="path",
     *         description="Player ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Skill progress retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="skills",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/PlayerSkill")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=403, description="Unauthorized access")
     * )
     */
    public function skills(User $player): JsonResponse
    {
        $this->authorize('view', $player);

        try {
            $skills = $this->playerService->getSkillProgress($player);

            return response()->json([
                'skills' => $skills,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to get skill progress',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update player skill target.
     * 
     * @OA\Put(
     *     path="/players/{player}/skills/{skill}",
     *     summary="Update player skill target",
     *     description="Update skill target level and date for a specific player",
     *     operationId="updatePlayerSkillTarget",
     *     tags={"Players"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="player",
     *         in="path",
     *         description="Player ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="skill",
     *         in="path",
     *         description="Skill ID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"target_level"},
     *             @OA\Property(property="target_level", type="integer", minimum=0, maximum=100),
     *             @OA\Property(property="target_date", type="string", format="date")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Skill target updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Skill target updated successfully"),
     *             @OA\Property(property="skill", ref="#/components/schemas/PlayerSkill")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Unauthorized access"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     * @throws ValidationException
     */
    public function updateSkill(Request $request, User $player, string $skillId): JsonResponse
    {
        $this->authorize('update', $player);

        try {
            $validated = $request->validate([
                'target_level' => ['required', 'integer', 'min:0', 'max:100'],
                'target_date' => ['sometimes', 'date', 'after:today'],
            ]);

            $skill = \App\Models\Skill::findOrFail($skillId);
            
            $playerSkill = $this->playerService->updateSkillTarget(
                $player,
                $skill,
                $validated['target_level'],
                $validated['target_date'] ?? null
            );

            return response()->json([
                'message' => 'Skill target updated successfully',
                'skill' => $playerSkill,
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update skill target',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
