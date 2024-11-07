<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TeamTier;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * @OA\Tag(
 *     name="Team Tiers",
 *     description="Team tier management endpoints"
 * )
 */
class TeamTierController extends Controller
{
    /**
     * List tiers.
     * 
     * @OA\Get(
     *     path="/tiers",
     *     summary="List tiers",
     *     description="Get a paginated list of team tiers",
     *     operationId="listTiers",
     *     tags={"Team Tiers"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="team_id",
     *         in="query",
     *         description="Filter by team ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Tiers retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="tiers",
     *                 type="object",
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(ref="#/components/schemas/TeamTier")
     *                 ),
     *                 @OA\Property(property="current_page", type="integer"),
     *                 @OA\Property(property="last_page", type="integer"),
     *                 @OA\Property(property="per_page", type="integer"),
     *                 @OA\Property(property="total", type="integer")
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'team_id' => ['sometimes', 'integer', 'exists:teams,id'],
            ]);

            $query = TeamTier::query()->with(['team', 'players']);

            if (isset($validated['team_id'])) {
                $query->where('team_id', $validated['team_id']);
            }

            $tiers = $query->paginate();

            return response()->json([
                'tiers' => $tiers,
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Create tier.
     * 
     * @OA\Post(
     *     path="/tiers",
     *     summary="Create tier",
     *     description="Create a new team tier",
     *     operationId="createTier",
     *     tags={"Team Tiers"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"team_id", "name", "level"},
     *             @OA\Property(property="team_id", type="integer"),
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="level", type="integer", minimum=1),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(
     *                 property="requirements",
     *                 type="object",
     *                 @OA\Property(property="min_age", type="integer"),
     *                 @OA\Property(property="max_age", type="integer"),
     *                 @OA\Property(
     *                     property="skill_levels",
     *                     type="object",
     *                     additionalProperties={"type": "integer", "minimum": 0, "maximum": 100}
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Tier created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Tier created successfully"),
     *             @OA\Property(property="tier", ref="#/components/schemas/TeamTier")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'team_id' => ['required', 'integer', 'exists:teams,id'],
                'name' => ['required', 'string', 'max:255'],
                'level' => ['required', 'integer', 'min:1'],
                'description' => ['nullable', 'string'],
                'requirements' => ['nullable', 'array'],
            ]);

            $tier = TeamTier::create($validated);

            return response()->json([
                'message' => 'Tier created successfully',
                'tier' => $tier->load('team'),
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Get tier details.
     * 
     * @OA\Get(
     *     path="/tiers/{tier}",
     *     summary="Get tier details",
     *     description="Get detailed information about a specific tier",
     *     operationId="getTier",
     *     tags={"Team Tiers"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="tier",
     *         in="path",
     *         description="Tier ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Tier details retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="tier", ref="#/components/schemas/TeamTier")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Tier not found")
     * )
     */
    public function show(TeamTier $tier): JsonResponse
    {
        return response()->json([
            'tier' => $tier->load(['team', 'players']),
        ]);
    }

    /**
     * Update tier.
     * 
     * @OA\Put(
     *     path="/tiers/{tier}",
     *     summary="Update tier",
     *     description="Update an existing team tier",
     *     operationId="updateTier",
     *     tags={"Team Tiers"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="tier",
     *         in="path",
     *         description="Tier ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="level", type="integer", minimum=1),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(
     *                 property="requirements",
     *                 type="object",
     *                 @OA\Property(property="min_age", type="integer"),
     *                 @OA\Property(property="max_age", type="integer"),
     *                 @OA\Property(
     *                     property="skill_levels",
     *                     type="object",
     *                     additionalProperties={"type": "integer", "minimum": 0, "maximum": 100}
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Tier updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Tier updated successfully"),
     *             @OA\Property(property="tier", ref="#/components/schemas/TeamTier")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(Request $request, TeamTier $tier): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => ['sometimes', 'string', 'max:255'],
                'level' => ['sometimes', 'integer', 'min:1'],
                'description' => ['sometimes', 'nullable', 'string'],
                'requirements' => ['sometimes', 'nullable', 'array'],
            ]);

            $tier->update($validated);

            return response()->json([
                'message' => 'Tier updated successfully',
                'tier' => $tier->fresh(['team', 'players']),
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Delete tier.
     * 
     * @OA\Delete(
     *     path="/tiers/{tier}",
     *     summary="Delete tier",
     *     description="Delete an existing team tier",
     *     operationId="deleteTier",
     *     tags={"Team Tiers"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="tier",
     *         in="path",
     *         description="Tier ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Tier deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Tier deleted successfully")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Tier not found")
     * )
     */
    public function destroy(TeamTier $tier): JsonResponse
    {
        $tier->delete();

        return response()->json([
            'message' => 'Tier deleted successfully',
        ]);
    }

    /**
     * Assign player to tier.
     * 
     * @OA\Post(
     *     path="/tiers/{tier}/players/{player}",
     *     summary="Assign player to tier",
     *     description="Assign a player to a specific tier",
     *     operationId="assignPlayerToTier",
     *     tags={"Team Tiers"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="tier",
     *         in="path",
     *         description="Tier ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="player",
     *         in="path",
     *         description="Player ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Player assigned successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Player assigned successfully"),
     *             @OA\Property(property="tier", ref="#/components/schemas/TeamTier")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Player already in tier")
     * )
     */
    public function assignPlayer(TeamTier $tier, User $player): JsonResponse
    {
        if ($tier->players()->where('user_id', $player->id)->exists()) {
            return response()->json([
                'message' => 'Player already in tier',
            ], 422);
        }

        $tier->players()->attach($player->id);

        return response()->json([
            'message' => 'Player assigned successfully',
            'tier' => $tier->fresh(['team', 'players']),
        ]);
    }

    /**
     * Remove player from tier.
     * 
     * @OA\Delete(
     *     path="/tiers/{tier}/players/{player}",
     *     summary="Remove player from tier",
     *     description="Remove a player from a specific tier",
     *     operationId="removePlayerFromTier",
     *     tags={"Team Tiers"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="tier",
     *         in="path",
     *         description="Tier ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="player",
     *         in="path",
     *         description="Player ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Player removed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Player removed successfully"),
     *             @OA\Property(property="tier", ref="#/components/schemas/TeamTier")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Player not in tier")
     * )
     */
    public function removePlayer(TeamTier $tier, User $player): JsonResponse
    {
        $tier->players()->detach($player->id);

        return response()->json([
            'message' => 'Player removed successfully',
            'tier' => $tier->fresh(['team', 'players']),
        ]);
    }

    /**
     * Promote player.
     * 
     * @OA\Post(
     *     path="/tiers/{tier}/players/{player}/promote",
     *     summary="Promote player",
     *     description="Promote a player to the next tier",
     *     operationId="promotePlayer",
     *     tags={"Team Tiers"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="tier",
     *         in="path",
     *         description="Current Tier ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="player",
     *         in="path",
     *         description="Player ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Player promoted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Player promoted successfully"),
     *             @OA\Property(property="new_tier", ref="#/components/schemas/TeamTier")
     *         )
     *     ),
     *     @OA\Response(response=422, description="No higher tier available")
     * )
     */
    public function promotePlayer(TeamTier $tier, User $player): JsonResponse
    {
        $nextTier = TeamTier::where('team_id', $tier->team_id)
            ->where('level', '>', $tier->level)
            ->orderBy('level')
            ->first();

        if (!$nextTier) {
            return response()->json([
                'message' => 'No higher tier available',
            ], 422);
        }

        $tier->players()->detach($player->id);
        $nextTier->players()->attach($player->id);

        return response()->json([
            'message' => 'Player promoted successfully',
            'new_tier' => $nextTier->load(['team', 'players']),
        ]);
    }

    /**
     * Demote player.
     * 
     * @OA\Post(
     *     path="/tiers/{tier}/players/{player}/demote",
     *     summary="Demote player",
     *     description="Demote a player to the previous tier",
     *     operationId="demotePlayer",
     *     tags={"Team Tiers"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="tier",
     *         in="path",
     *         description="Current Tier ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="player",
     *         in="path",
     *         description="Player ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Player demoted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Player demoted successfully"),
     *             @OA\Property(property="new_tier", ref="#/components/schemas/TeamTier")
     *         )
     *     ),
     *     @OA\Response(response=422, description="No lower tier available")
     * )
     */
    public function demotePlayer(TeamTier $tier, User $player): JsonResponse
    {
        $previousTier = TeamTier::where('team_id', $tier->team_id)
            ->where('level', '<', $tier->level)
            ->orderByDesc('level')
            ->first();

        if (!$previousTier) {
            return response()->json([
                'message' => 'No lower tier available',
            ], 422);
        }

        $tier->players()->detach($player->id);
        $previousTier->players()->attach($player->id);

        return response()->json([
            'message' => 'Player demoted successfully',
            'new_tier' => $previousTier->load(['team', 'players']),
        ]);
    }
}
