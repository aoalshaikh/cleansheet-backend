<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\User;
use App\Services\Team\TeamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * @OA\Tag(
 *     name="Teams",
 *     description="Team management endpoints"
 * )
 */
class TeamController extends Controller
{
    public function __construct(
        private readonly TeamService $teamService
    ) {}

    /**
     * List teams.
     * 
     * @OA\Get(
     *     path="/teams",
     *     summary="List teams",
     *     description="Get a paginated list of teams for an organization",
     *     operationId="listTeams",
     *     tags={"Teams"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="organization_id",
     *         in="query",
     *         description="Organization ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="active",
     *         in="query",
     *         description="Filter by active status",
     *         required=false,
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Teams retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="teams",
     *                 type="object",
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(ref="#/components/schemas/Team")
     *                 ),
     *                 @OA\Property(property="current_page", type="integer"),
     *                 @OA\Property(property="last_page", type="integer"),
     *                 @OA\Property(property="per_page", type="integer"),
     *                 @OA\Property(property="total", type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'organization_id' => ['required', 'exists:organizations,id'],
                'active' => ['sometimes', 'boolean'],
            ]);

            $query = Team::query()
                ->where('organization_id', $validated['organization_id'])
                ->with(['coach', 'tiers']);

            if (isset($validated['active'])) {
                $query->where('is_active', $validated['active']);
            }

            $teams = $query->paginate();

            return response()->json([
                'teams' => $teams,
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to list teams',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a new team.
     * 
     * @OA\Post(
     *     path="/teams",
     *     summary="Create new team",
     *     description="Create a new team in an organization",
     *     operationId="createTeam",
     *     tags={"Teams"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"organization_id", "name", "practice_days", "practice_time", "practice_duration"},
     *             @OA\Property(property="organization_id", type="integer"),
     *             @OA\Property(property="coach_id", type="integer", nullable=true),
     *             @OA\Property(property="name", type="string", maxLength=255),
     *             @OA\Property(property="description", type="string", nullable=true),
     *             @OA\Property(property="settings", type="object", nullable=true),
     *             @OA\Property(
     *                 property="practice_days",
     *                 type="array",
     *                 @OA\Items(type="string", enum={"monday","tuesday","wednesday","thursday","friday","saturday","sunday"})
     *             ),
     *             @OA\Property(property="practice_time", type="string", format="HH:mm"),
     *             @OA\Property(property="practice_duration", type="integer", minimum=30, maximum=240),
     *             @OA\Property(property="create_default_tier", type="boolean")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Team created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Team created successfully"),
     *             @OA\Property(property="team", ref="#/components/schemas/Team")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     * @throws ValidationException
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'organization_id' => ['required', 'exists:organizations,id'],
                'coach_id' => ['nullable', 'exists:users,id'],
                'name' => ['required', 'string', 'max:255'],
                'description' => ['nullable', 'string'],
                'settings' => ['nullable', 'array'],
                'practice_days' => ['array', 'min:1'],
                'practice_days.*' => ['string', 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday'],
                'practice_time' => ['string', 'date_format:H:i'],
                'practice_duration' => ['integer', 'min:30', 'max:240'],
                'create_default_tier' => ['sometimes', 'boolean'],
            ]);

            $team = $this->teamService->createTeam($validated);

            return response()->json([
                'message' => 'Team created successfully',
                'team' => $team->load(['coach', 'tiers']),
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create team',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get team details.
     * 
     * @OA\Get(
     *     path="/teams/{team}",
     *     summary="Get team details",
     *     description="Get detailed information about a specific team",
     *     operationId="getTeamDetails",
     *     tags={"Teams"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="team",
     *         in="path",
     *         description="Team ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Team details retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="team", ref="#/components/schemas/Team")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Unauthorized access")
     * )
     */
    public function show(Team $team): JsonResponse
    {
        $this->authorize('view', $team);

        return response()->json([
            'team' => $team->load([
                'coach',
                'tiers',
                'players',
                'schedules' => fn($query) => $query->upcoming(),
                'matches' => fn($query) => $query->upcoming(),
            ]),
        ]);
    }

    /**
     * Update team details.
     * 
     * @OA\Put(
     *     path="/teams/{team}",
     *     summary="Update team details",
     *     description="Update details of a specific team",
     *     operationId="updateTeamDetails",
     *     tags={"Teams"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="team",
     *         in="path",
     *         description="Team ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="coach_id", type="integer", nullable=true),
     *             @OA\Property(property="name", type="string", maxLength=255),
     *             @OA\Property(property="description", type="string", nullable=true),
     *             @OA\Property(property="settings", type="object"),
     *             @OA\Property(
     *                 property="practice_days",
     *                 type="array",
     *                 @OA\Items(type="string", enum={"monday","tuesday","wednesday","thursday","friday","saturday","sunday"})
     *             ),
     *             @OA\Property(property="practice_time", type="string", format="HH:mm"),
     *             @OA\Property(property="practice_duration", type="integer", minimum=30, maximum=240),
     *             @OA\Property(property="is_active", type="boolean")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Team updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Team updated successfully"),
     *             @OA\Property(property="team", ref="#/components/schemas/Team")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Unauthorized access"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     * @throws ValidationException
     */
    public function update(Request $request, Team $team): JsonResponse
    {
        $this->authorize('update', $team);

        try {
            $validated = $request->validate([
                'coach_id' => ['sometimes', 'nullable', 'exists:users,id'],
                'name' => ['sometimes', 'string', 'max:255'],
                'description' => ['sometimes', 'nullable', 'string'],
                'settings' => ['sometimes', 'array'],
                'practice_days' => ['sometimes', 'array', 'min:1'],
                'practice_days.*' => ['string', 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday'],
                'practice_time' => ['sometimes', 'string', 'date_format:H:i'],
                'practice_duration' => ['sometimes', 'integer', 'min:30', 'max:240'],
                'is_active' => ['sometimes', 'boolean'],
            ]);

            if (isset($validated['practice_days']) || isset($validated['practice_time']) || isset($validated['practice_duration'])) {
                $this->teamService->updateSchedule($team, [
                    'days' => $validated['practice_days'] ?? $team->getPracticeSchedule()['days'],
                    'time' => $validated['practice_time'] ?? $team->getPracticeSchedule()['time'],
                    'duration' => $validated['practice_duration'] ?? $team->getPracticeSchedule()['duration'],
                ]);
            }

            unset($validated['practice_days'], $validated['practice_time'], $validated['practice_duration']);
            
            $team->update($validated);

            return response()->json([
                'message' => 'Team updated successfully',
                'team' => $team->fresh(['coach', 'tiers']),
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update team',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Add a player to the team.
     * 
     * @OA\Post(
     *     path="/teams/{team}/players/{player}",
     *     summary="Add player to team",
     *     description="Add a player to a specific team",
     *     operationId="addPlayerToTeam",
     *     tags={"Teams"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="team",
     *         in="path",
     *         description="Team ID",
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
     *         description="Player added successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Player added successfully")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Unauthorized access"),
     *     @OA\Response(response=422, description="Invalid player role or team limit reached")
     * )
     */
    public function addPlayer(Request $request, Team $team, User $player): JsonResponse
    {
        $this->authorize('update', $team);

        try {
            if (!$player->hasRole('player')) {
                return response()->json([
                    'message' => 'User must be a player',
                ], 422);
            }

            if ($this->teamService->hasReachedPlayerLimit($team)) {
                return response()->json([
                    'message' => 'Team has reached its player limit',
                ], 422);
            }

            $this->teamService->addPlayer($team, $player);

            return response()->json([
                'message' => 'Player added successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to add player',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove a player from the team.
     * 
     * @OA\Delete(
     *     path="/teams/{team}/players/{player}",
     *     summary="Remove player from team",
     *     description="Remove a player from a specific team",
     *     operationId="removePlayerFromTeam",
     *     tags={"Teams"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="team",
     *         in="path",
     *         description="Team ID",
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
     *             @OA\Property(property="message", type="string", example="Player removed successfully")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Unauthorized access")
     * )
     */
    public function removePlayer(Request $request, Team $team, User $player): JsonResponse
    {
        $this->authorize('update', $team);

        try {
            $this->teamService->removePlayer($team, $player);

            return response()->json([
                'message' => 'Player removed successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to remove player',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get team statistics.
     * 
     * @OA\Get(
     *     path="/teams/{team}/stats",
     *     summary="Get team statistics",
     *     description="Get statistical data for a specific team",
     *     operationId="getTeamStats",
     *     tags={"Teams"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="team",
     *         in="path",
     *         description="Team ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Statistics retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="stats", ref="#/components/schemas/TeamStats")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Unauthorized access")
     * )
     */
    public function stats(Team $team): JsonResponse
    {
        $this->authorize('view', $team);

        try {
            $stats = $this->teamService->getStats($team);

            return response()->json([
                'stats' => $stats,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to get team stats',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get team schedule.
     * 
     * @OA\Get(
     *     path="/teams/{team}/schedule",
     *     summary="Get team schedule",
     *     description="Get schedule for a specific team within a date range",
     *     operationId="getTeamSchedule",
     *     tags={"Teams"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="team",
     *         in="path",
     *         description="Team ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         description="Start date (Y-m-d)",
     *         required=true,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         description="End date (Y-m-d)",
     *         required=true,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Schedule retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="schedule",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/TeamSchedule")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=403, description="Unauthorized access"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function schedule(Request $request, Team $team): JsonResponse
    {
        $this->authorize('view', $team);

        try {
            $validated = $request->validate([
                'start_date' => ['required', 'date'],
                'end_date' => ['required', 'date', 'after:start_date'],
            ]);

            $schedule = $this->teamService->getSchedule(
                $team,
                $validated['start_date'],
                $validated['end_date']
            );

            return response()->json([
                'schedule' => $schedule,
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to get team schedule',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
