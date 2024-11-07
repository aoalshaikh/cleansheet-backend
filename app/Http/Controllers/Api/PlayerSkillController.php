<?php

namespace App\Http\Controllers\Api;

use App\Models\Skill;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PlayerSkillController extends Controller
{
    /**
     * List skills.
     * 
     * @OA\Get(
     *     path="/skills",
     *     summary="List skills",
     *     description="Get a list of all available skills",
     *     operationId="listSkills",
     *     tags={"Player Skills"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="category",
     *         in="query",
     *         description="Filter by skill category",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Skills retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="skills",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/Skill")
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'category' => ['sometimes', 'string', 'exists:skill_categories,id'],
            ]);

            $query = Skill::query()->with('category');

            if (isset($validated['category'])) {
                $query->where('category_id', $validated['category']);
            }

            $skills = $query->get();

            return response()->json([
                'skills' => $skills,
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Get skill details.
     * 
     * @OA\Get(
     *     path="/skills/{skill}",
     *     summary="Get skill details",
     *     description="Get detailed information about a specific skill",
     *     operationId="showSkill",
     *     tags={"Player Skills"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="skill",
     *         in="path",
     *         description="Skill ID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Skill details retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="skill", ref="#/components/schemas/Skill")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Skill not found")
     * )
     */
    public function show(Skill $skill): JsonResponse
    {
        return response()->json([
            'skill' => $skill->load('category'),
        ]);
    }

    /**
     * Get player skill progress.
     * 
     * @OA\Get(
     *     path="/skills/players/{player}",
     *     summary="Get player skill progress",
     *     description="Get all skills and their progress for a specific player",
     *     operationId="getPlayerSkillList",
     *     tags={"Player Skills"},
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
     *         description="Player skills retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="skills",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/PlayerSkill")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Player not found")
     * )
     */
    public function playerSkills(User $player): JsonResponse
    {
        $skills = $player->skills()
            ->with('skill.category')
            ->get()
            ->map(function ($playerSkill) {
                return [
                    'skill' => $playerSkill->skill,
                    'current_level' => $playerSkill->current_level,
                    'target_level' => $playerSkill->target_level,
                    'target_date' => $playerSkill->target_date,
                ];
            });

        return response()->json([
            'skills' => $skills,
        ]);
    }

    /**
     * Update player skill.
     * 
     * @OA\Post(
     *     path="/skills/players/{player}/{skill}",
     *     summary="Update player skill",
     *     description="Update skill level and target for a specific player",
     *     operationId="updatePlayerSkillLevel",
     *     tags={"Player Skills"},
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
     *             required={"current_level"},
     *             @OA\Property(property="current_level", type="integer", minimum=0, maximum=100),
     *             @OA\Property(property="target_level", type="integer", minimum=0, maximum=100),
     *             @OA\Property(property="target_date", type="string", format="date")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Skill updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Skill updated successfully"),
     *             @OA\Property(property="skill", ref="#/components/schemas/PlayerSkill")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function updateSkill(Request $request, User $player, Skill $skill): JsonResponse
    {
        try {
            $validated = $request->validate([
                'current_level' => ['required', 'integer', 'min:0', 'max:100'],
                'target_level' => ['sometimes', 'integer', 'min:0', 'max:100'],
                'target_date' => ['sometimes', 'nullable', 'date', 'after:today'],
            ]);

            $playerSkill = $player->skills()
                ->firstOrCreate(
                    ['skill_id' => $skill->id],
                    ['current_level' => 0]
                );

            $playerSkill->update($validated);

            return response()->json([
                'message' => 'Skill updated successfully',
                'skill' => [
                    'skill' => $skill->load('category'),
                    'current_level' => $playerSkill->current_level,
                    'target_level' => $playerSkill->target_level,
                    'target_date' => $playerSkill->target_date,
                ],
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }
    }
}
