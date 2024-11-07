<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PlayerEvaluation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * @OA\Tag(
 *     name="Player Evaluations",
 *     description="Player evaluation management endpoints"
 * )
 */
class PlayerEvaluationController extends Controller
{
    /**
     * List evaluations.
     * 
     * @OA\Get(
     *     path="/evaluations",
     *     summary="List evaluations",
     *     description="Get a paginated list of player evaluations",
     *     operationId="listEvaluations",
     *     tags={"Player Evaluations"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="team_id",
     *         in="query",
     *         description="Filter by team ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="player_id",
     *         in="query",
     *         description="Filter by player ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Evaluations retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="evaluations",
     *                 type="object",
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(ref="#/components/schemas/PlayerEvaluation")
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
                'player_id' => ['sometimes', 'integer', 'exists:users,id'],
            ]);

            $query = PlayerEvaluation::query()
                ->with(['player', 'evaluator', 'team']);

            if (isset($validated['team_id'])) {
                $query->where('team_id', $validated['team_id']);
            }

            if (isset($validated['player_id'])) {
                $query->where('player_id', $validated['player_id']);
            }

            $evaluations = $query->paginate();

            return response()->json([
                'evaluations' => $evaluations,
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Create evaluation.
     * 
     * @OA\Post(
     *     path="/evaluations",
     *     summary="Create evaluation",
     *     description="Create a new player evaluation",
     *     operationId="createEvaluation",
     *     tags={"Player Evaluations"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"player_id", "team_id", "evaluation_date", "ratings"},
     *             @OA\Property(property="player_id", type="integer"),
     *             @OA\Property(property="team_id", type="integer"),
     *             @OA\Property(property="evaluation_date", type="string", format="date"),
     *             @OA\Property(
     *                 property="ratings",
     *                 type="object",
     *                 required={"technical", "tactical", "physical", "mental"},
     *                 @OA\Property(property="technical", type="integer", minimum=1, maximum=10),
     *                 @OA\Property(property="tactical", type="integer", minimum=1, maximum=10),
     *                 @OA\Property(property="physical", type="integer", minimum=1, maximum=10),
     *                 @OA\Property(property="mental", type="integer", minimum=1, maximum=10)
     *             ),
     *             @OA\Property(property="notes", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Evaluation created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Evaluation created successfully"),
     *             @OA\Property(property="evaluation", ref="#/components/schemas/PlayerEvaluation")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'player_id' => ['required', 'integer', 'exists:users,id'],
                'team_id' => ['required', 'integer', 'exists:teams,id'],
                'evaluation_date' => ['required', 'date'],
                'ratings' => ['required', 'array'],
                'ratings.technical' => ['required', 'integer', 'min:1', 'max:10'],
                'ratings.tactical' => ['required', 'integer', 'min:1', 'max:10'],
                'ratings.physical' => ['required', 'integer', 'min:1', 'max:10'],
                'ratings.mental' => ['required', 'integer', 'min:1', 'max:10'],
                'notes' => ['nullable', 'string'],
            ]);

            $evaluation = PlayerEvaluation::create([
                ...$validated,
                'evaluator_id' => $request->user()->id,
            ]);

            return response()->json([
                'message' => 'Evaluation created successfully',
                'evaluation' => $evaluation->load(['player', 'evaluator', 'team']),
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Get evaluation details.
     * 
     * @OA\Get(
     *     path="/evaluations/{evaluation}",
     *     summary="Get evaluation details",
     *     description="Get detailed information about a specific evaluation",
     *     operationId="getEvaluation",
     *     tags={"Player Evaluations"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="evaluation",
     *         in="path",
     *         description="Evaluation ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Evaluation details retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="evaluation", ref="#/components/schemas/PlayerEvaluation")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Evaluation not found")
     * )
     */
    public function show(PlayerEvaluation $evaluation): JsonResponse
    {
        return response()->json([
            'evaluation' => $evaluation->load(['player', 'evaluator', 'team']),
        ]);
    }

    /**
     * Update evaluation.
     * 
     * @OA\Put(
     *     path="/evaluations/{evaluation}",
     *     summary="Update evaluation",
     *     description="Update an existing player evaluation",
     *     operationId="updateEvaluation",
     *     tags={"Player Evaluations"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="evaluation",
     *         in="path",
     *         description="Evaluation ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="evaluation_date", type="string", format="date"),
     *             @OA\Property(
     *                 property="ratings",
     *                 type="object",
     *                 @OA\Property(property="technical", type="integer", minimum=1, maximum=10),
     *                 @OA\Property(property="tactical", type="integer", minimum=1, maximum=10),
     *                 @OA\Property(property="physical", type="integer", minimum=1, maximum=10),
     *                 @OA\Property(property="mental", type="integer", minimum=1, maximum=10)
     *             ),
     *             @OA\Property(property="notes", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Evaluation updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Evaluation updated successfully"),
     *             @OA\Property(property="evaluation", ref="#/components/schemas/PlayerEvaluation")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(Request $request, PlayerEvaluation $evaluation): JsonResponse
    {
        try {
            $validated = $request->validate([
                'evaluation_date' => ['sometimes', 'date'],
                'ratings' => ['sometimes', 'array'],
                'ratings.technical' => ['required_with:ratings', 'integer', 'min:1', 'max:10'],
                'ratings.tactical' => ['required_with:ratings', 'integer', 'min:1', 'max:10'],
                'ratings.physical' => ['required_with:ratings', 'integer', 'min:1', 'max:10'],
                'ratings.mental' => ['required_with:ratings', 'integer', 'min:1', 'max:10'],
                'notes' => ['sometimes', 'nullable', 'string'],
            ]);

            $evaluation->update($validated);

            return response()->json([
                'message' => 'Evaluation updated successfully',
                'evaluation' => $evaluation->fresh(['player', 'evaluator', 'team']),
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Delete evaluation.
     * 
     * @OA\Delete(
     *     path="/evaluations/{evaluation}",
     *     summary="Delete evaluation",
     *     description="Delete an existing player evaluation",
     *     operationId="deleteEvaluation",
     *     tags={"Player Evaluations"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="evaluation",
     *         in="path",
     *         description="Evaluation ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Evaluation deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Evaluation deleted successfully")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Evaluation not found")
     * )
     */
    public function destroy(PlayerEvaluation $evaluation): JsonResponse
    {
        $evaluation->delete();

        return response()->json([
            'message' => 'Evaluation deleted successfully',
        ]);
    }

    /**
     * Get player evaluations.
     * 
     * @OA\Get(
     *     path="/players/{player}/evaluations",
     *     summary="Get player evaluations",
     *     description="Get all evaluations for a specific player",
     *     operationId="getPlayerEvaluations",
     *     tags={"Player Evaluations"},
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
     *         description="Player evaluations retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="evaluations",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/PlayerEvaluation")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Player not found")
     * )
     */
    public function playerEvaluations(User $player): JsonResponse
    {
        $evaluations = $player->evaluations()
            ->with(['evaluator', 'team'])
            ->get();

        return response()->json([
            'evaluations' => $evaluations,
        ]);
    }
}
