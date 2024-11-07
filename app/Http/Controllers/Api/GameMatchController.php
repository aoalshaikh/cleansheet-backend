<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GameMatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * @OA\Tag(
 *     name="Matches",
 *     description="Match management endpoints"
 * )
 */
class GameMatchController extends Controller
{
    /**
     * List matches.
     * 
     * @OA\Get(
     *     path="/matches",
     *     summary="List matches",
     *     description="Get a paginated list of matches",
     *     operationId="listMatches",
     *     tags={"Matches"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="team_id",
     *         in="query",
     *         description="Filter by team ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by match status",
     *         required=false,
     *         @OA\Schema(type="string", enum={"scheduled", "in_progress", "completed", "cancelled"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Matches retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="matches",
     *                 type="object",
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer"),
     *                         @OA\Property(property="home_team_id", type="integer"),
     *                         @OA\Property(property="away_team_id", type="integer"),
     *                         @OA\Property(property="scheduled_at", type="string", format="date-time"),
     *                         @OA\Property(property="venue", type="string"),
     *                         @OA\Property(property="description", type="string", nullable=true),
     *                         @OA\Property(property="status", type="string", enum={"scheduled", "in_progress", "completed", "cancelled"}),
     *                         @OA\Property(property="started_at", type="string", format="date-time", nullable=true),
     *                         @OA\Property(property="completed_at", type="string", format="date-time", nullable=true),
     *                         @OA\Property(property="cancelled_at", type="string", format="date-time", nullable=true),
     *                         @OA\Property(property="cancellation_reason", type="string", nullable=true),
     *                         @OA\Property(
     *                             property="score",
     *                             type="object",
     *                             @OA\Property(property="home", type="integer"),
     *                             @OA\Property(property="away", type="integer")
     *                         ),
     *                         @OA\Property(property="home_team", ref="#/components/schemas/Team"),
     *                         @OA\Property(property="away_team", ref="#/components/schemas/Team"),
     *                         @OA\Property(
     *                             property="lineup",
     *                             type="array",
     *                             @OA\Items(ref="#/components/schemas/MatchLineup")
     *                         ),
     *                         @OA\Property(
     *                             property="events",
     *                             type="array",
     *                             @OA\Items(ref="#/components/schemas/MatchEvent")
     *                         ),
     *                         @OA\Property(property="settings", type="object", nullable=true),
     *                         @OA\Property(property="created_at", type="string", format="date-time"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time")
     *                     )
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
                'status' => ['sometimes', 'string', 'in:scheduled,in_progress,completed,cancelled'],
            ]);

            $query = GameMatch::query()->with(['homeTeam', 'awayTeam', 'events']);

            if (isset($validated['team_id'])) {
                $query->where(function ($q) use ($validated) {
                    $q->where('home_team_id', $validated['team_id'])
                      ->orWhere('away_team_id', $validated['team_id']);
                });
            }

            if (isset($validated['status'])) {
                $query->where('status', $validated['status']);
            }

            $matches = $query->paginate();

            return response()->json([
                'matches' => $matches,
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Create a new match.
     * 
     * @OA\Post(
     *     path="/matches",
     *     summary="Create new match",
     *     description="Create a new match between two teams",
     *     operationId="createMatch",
     *     tags={"Matches"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"home_team_id", "away_team_id", "scheduled_at", "venue"},
     *             @OA\Property(property="home_team_id", type="integer"),
     *             @OA\Property(property="away_team_id", type="integer"),
     *             @OA\Property(property="scheduled_at", type="string", format="date-time"),
     *             @OA\Property(property="venue", type="string"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="settings", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Match created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Match created successfully"),
     *             @OA\Property(property="match", ref="#/components/schemas/GameMatch")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'home_team_id' => ['required', 'integer', 'exists:teams,id'],
                'away_team_id' => ['required', 'integer', 'exists:teams,id', 'different:home_team_id'],
                'scheduled_at' => ['required', 'date'],
                'venue' => ['required', 'string'],
                'description' => ['nullable', 'string'],
                'settings' => ['nullable', 'array'],
            ]);

            $match = GameMatch::create($validated);

            return response()->json([
                'message' => 'Match created successfully',
                'match' => $match->load(['homeTeam', 'awayTeam']),
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Get match details.
     * 
     * @OA\Get(
     *     path="/matches/{match}",
     *     summary="Get match details",
     *     description="Get detailed information about a specific match",
     *     operationId="getMatch",
     *     tags={"Matches"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="match",
     *         in="path",
     *         description="Match ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Match details retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="match", ref="#/components/schemas/GameMatch")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Match not found")
     * )
     */
    public function show(GameMatch $match): JsonResponse
    {
        return response()->json([
            'match' => $match->load([
                'homeTeam',
                'awayTeam',
                'events',
                'lineup',
            ]),
        ]);
    }

    /**
     * Add match event.
     * 
     * @OA\Post(
     *     path="/matches/{match}/events",
     *     summary="Add match event",
     *     description="Add a new event to a match (goal, card, substitution, etc.)",
     *     operationId="addMatchEvent",
     *     tags={"Matches"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="match",
     *         in="path",
     *         description="Match ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"type", "minute"},
     *             @OA\Property(property="type", type="string", enum={"goal", "yellow_card", "red_card", "substitution"}),
     *             @OA\Property(property="minute", type="integer", minimum=0),
     *             @OA\Property(property="player_id", type="integer"),
     *             @OA\Property(property="details", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Event added successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Event added successfully"),
     *             @OA\Property(property="event", ref="#/components/schemas/MatchEvent")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function addEvent(Request $request, GameMatch $match): JsonResponse
    {
        try {
            $validated = $request->validate([
                'type' => ['required', 'string', 'in:goal,yellow_card,red_card,substitution'],
                'minute' => ['required', 'integer', 'min:0'],
                'player_id' => ['required', 'integer', 'exists:users,id'],
                'details' => ['sometimes', 'array'],
            ]);

            $event = $match->events()->create($validated);

            return response()->json([
                'message' => 'Event added successfully',
                'event' => $event,
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Set match lineup.
     * 
     * @OA\Post(
     *     path="/matches/{match}/lineup",
     *     summary="Set match lineup",
     *     description="Set the lineup for a match",
     *     operationId="setMatchLineup",
     *     tags={"Matches"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="match",
     *         in="path",
     *         description="Match ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"team_id", "players"},
     *             @OA\Property(property="team_id", type="integer"),
     *             @OA\Property(
     *                 property="players",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     required={"player_id", "position", "is_starter"},
     *                     @OA\Property(property="player_id", type="integer"),
     *                     @OA\Property(property="position", type="string"),
     *                     @OA\Property(property="is_starter", type="boolean")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lineup set successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Lineup set successfully"),
     *             @OA\Property(
     *                 property="lineup",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/MatchLineup")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function setLineup(Request $request, GameMatch $match): JsonResponse
    {
        try {
            $validated = $request->validate([
                'team_id' => ['required', 'integer', 'exists:teams,id'],
                'players' => ['required', 'array', 'min:11', 'max:18'],
                'players.*.player_id' => ['required', 'integer', 'exists:users,id'],
                'players.*.position' => ['required', 'string'],
                'players.*.is_starter' => ['required', 'boolean'],
            ]);

            // Clear existing lineup for the team
            $match->lineup()->where('team_id', $validated['team_id'])->delete();

            // Create new lineup entries
            $lineup = collect($validated['players'])->map(function ($player) use ($match, $validated) {
                return $match->lineup()->create([
                    'team_id' => $validated['team_id'],
                    'player_id' => $player['player_id'],
                    'position' => $player['position'],
                    'is_starter' => $player['is_starter'],
                ]);
            });

            return response()->json([
                'message' => 'Lineup set successfully',
                'lineup' => $lineup,
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Start match.
     * 
     * @OA\Post(
     *     path="/matches/{match}/start",
     *     summary="Start match",
     *     description="Start a scheduled match",
     *     operationId="startMatch",
     *     tags={"Matches"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="match",
     *         in="path",
     *         description="Match ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Match started successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Match started successfully"),
     *             @OA\Property(property="match", ref="#/components/schemas/GameMatch")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Invalid match status")
     * )
     */
    public function startMatch(GameMatch $match): JsonResponse
    {
        if ($match->status !== 'scheduled') {
            return response()->json([
                'message' => 'Match cannot be started',
            ], 422);
        }

        $match->update([
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        return response()->json([
            'message' => 'Match started successfully',
            'match' => $match->fresh(),
        ]);
    }

    /**
     * Complete match.
     * 
     * @OA\Post(
     *     path="/matches/{match}/complete",
     *     summary="Complete match",
     *     description="Mark a match as completed",
     *     operationId="completeMatch",
     *     tags={"Matches"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="match",
     *         in="path",
     *         description="Match ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Match completed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Match completed successfully"),
     *             @OA\Property(property="match", ref="#/components/schemas/GameMatch")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Invalid match status")
     * )
     */
    public function completeMatch(GameMatch $match): JsonResponse
    {
        if ($match->status !== 'in_progress') {
            return response()->json([
                'message' => 'Match cannot be completed',
            ], 422);
        }

        $match->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        return response()->json([
            'message' => 'Match completed successfully',
            'match' => $match->fresh(),
        ]);
    }

    /**
     * Cancel match.
     * 
     * @OA\Post(
     *     path="/matches/{match}/cancel",
     *     summary="Cancel match",
     *     description="Cancel a scheduled match",
     *     operationId="cancelMatch",
     *     tags={"Matches"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="match",
     *         in="path",
     *         description="Match ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"reason"},
     *             @OA\Property(property="reason", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Match cancelled successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Match cancelled successfully"),
     *             @OA\Property(property="match", ref="#/components/schemas/GameMatch")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Invalid match status or validation error")
     * )
     */
    public function cancelMatch(Request $request, GameMatch $match): JsonResponse
    {
        try {
            if ($match->status !== 'scheduled') {
                return response()->json([
                    'message' => 'Match cannot be cancelled',
                ], 422);
            }

            $validated = $request->validate([
                'reason' => ['required', 'string'],
            ]);

            $match->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancellation_reason' => $validated['reason'],
            ]);

            return response()->json([
                'message' => 'Match cancelled successfully',
                'match' => $match->fresh(),
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }
    }
}
