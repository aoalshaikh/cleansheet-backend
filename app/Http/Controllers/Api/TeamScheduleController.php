<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TeamSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * @OA\Tag(
 *     name="Team Schedules",
 *     description="Team schedule and attendance management endpoints"
 * )
 */
class TeamScheduleController extends Controller
{
    /**
     * List schedules.
     * 
     * @OA\Get(
     *     path="/schedules",
     *     summary="List schedules",
     *     description="Get a paginated list of team schedules",
     *     operationId="listSchedules",
     *     tags={"Team Schedules"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="team_id",
     *         in="query",
     *         description="Filter by team ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         description="Filter by start date",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         description="Filter by end date",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Schedules retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="schedules",
     *                 type="object",
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(ref="#/components/schemas/TeamSchedule")
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
                'start_date' => ['sometimes', 'date'],
                'end_date' => ['sometimes', 'date', 'after:start_date'],
            ]);

            $query = TeamSchedule::query()->with(['team', 'attendance']);

            if (isset($validated['team_id'])) {
                $query->where('team_id', $validated['team_id']);
            }

            if (isset($validated['start_date'])) {
                $query->where('scheduled_at', '>=', $validated['start_date']);
            }

            if (isset($validated['end_date'])) {
                $query->where('scheduled_at', '<=', $validated['end_date']);
            }

            $schedules = $query->paginate();

            return response()->json([
                'schedules' => $schedules,
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Create schedule.
     * 
     * @OA\Post(
     *     path="/schedules",
     *     summary="Create schedule",
     *     description="Create a new team schedule",
     *     operationId="createSchedule",
     *     tags={"Team Schedules"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"team_id", "type", "scheduled_at", "duration"},
     *             @OA\Property(property="team_id", type="integer"),
     *             @OA\Property(property="type", type="string", enum={"practice", "match", "event"}),
     *             @OA\Property(property="title", type="string"),
     *             @OA\Property(property="scheduled_at", type="string", format="date-time"),
     *             @OA\Property(property="duration", type="integer", minimum=30, maximum=240),
     *             @OA\Property(property="location", type="string"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="is_mandatory", type="boolean")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Schedule created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Schedule created successfully"),
     *             @OA\Property(property="schedule", ref="#/components/schemas/TeamSchedule")
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
                'type' => ['required', 'string', 'in:practice,match,event'],
                'title' => ['required', 'string'],
                'scheduled_at' => ['required', 'date'],
                'duration' => ['required', 'integer', 'min:30', 'max:240'],
                'location' => ['nullable', 'string'],
                'description' => ['nullable', 'string'],
                'is_mandatory' => ['sometimes', 'boolean'],
            ]);

            $schedule = TeamSchedule::create($validated);

            return response()->json([
                'message' => 'Schedule created successfully',
                'schedule' => $schedule->load('team'),
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Get schedule details.
     * 
     * @OA\Get(
     *     path="/schedules/{schedule}",
     *     summary="Get schedule details",
     *     description="Get detailed information about a specific schedule",
     *     operationId="getSchedule",
     *     tags={"Team Schedules"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="schedule",
     *         in="path",
     *         description="Schedule ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Schedule details retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="schedule", ref="#/components/schemas/TeamSchedule")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Schedule not found")
     * )
     */
    public function show(TeamSchedule $schedule): JsonResponse
    {
        return response()->json([
            'schedule' => $schedule->load(['team', 'attendance']),
        ]);
    }

    /**
     * Update schedule.
     * 
     * @OA\Put(
     *     path="/schedules/{schedule}",
     *     summary="Update schedule",
     *     description="Update an existing team schedule",
     *     operationId="updateSchedule",
     *     tags={"Team Schedules"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="schedule",
     *         in="path",
     *         description="Schedule ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="type", type="string", enum={"practice", "match", "event"}),
     *             @OA\Property(property="title", type="string"),
     *             @OA\Property(property="scheduled_at", type="string", format="date-time"),
     *             @OA\Property(property="duration", type="integer", minimum=30, maximum=240),
     *             @OA\Property(property="location", type="string"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="is_mandatory", type="boolean")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Schedule updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Schedule updated successfully"),
     *             @OA\Property(property="schedule", ref="#/components/schemas/TeamSchedule")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(Request $request, TeamSchedule $schedule): JsonResponse
    {
        try {
            $validated = $request->validate([
                'type' => ['sometimes', 'string', 'in:practice,match,event'],
                'title' => ['sometimes', 'string'],
                'scheduled_at' => ['sometimes', 'date'],
                'duration' => ['sometimes', 'integer', 'min:30', 'max:240'],
                'location' => ['sometimes', 'nullable', 'string'],
                'description' => ['sometimes', 'nullable', 'string'],
                'is_mandatory' => ['sometimes', 'boolean'],
            ]);

            $schedule->update($validated);

            return response()->json([
                'message' => 'Schedule updated successfully',
                'schedule' => $schedule->fresh(['team', 'attendance']),
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Delete schedule.
     * 
     * @OA\Delete(
     *     path="/schedules/{schedule}",
     *     summary="Delete schedule",
     *     description="Delete an existing team schedule",
     *     operationId="deleteSchedule",
     *     tags={"Team Schedules"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="schedule",
     *         in="path",
     *         description="Schedule ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Schedule deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Schedule deleted successfully")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Schedule not found")
     * )
     */
    public function destroy(TeamSchedule $schedule): JsonResponse
    {
        $schedule->delete();

        return response()->json([
            'message' => 'Schedule deleted successfully',
        ]);
    }

    /**
     * Mark attendance.
     * 
     * @OA\Post(
     *     path="/schedules/{schedule}/attendance",
     *     summary="Mark attendance",
     *     description="Mark attendance for players in a schedule",
     *     operationId="markAttendance",
     *     tags={"Team Schedules"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="schedule",
     *         in="path",
     *         description="Schedule ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"attendance"},
     *             @OA\Property(
     *                 property="attendance",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     required={"player_id", "status"},
     *                     @OA\Property(property="player_id", type="integer"),
     *                     @OA\Property(property="status", type="string", enum={"present", "absent", "late", "excused"}),
     *                     @OA\Property(property="notes", type="string")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Attendance marked successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Attendance marked successfully"),
     *             @OA\Property(
     *                 property="attendance",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/TeamScheduleAttendance")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function markAttendance(Request $request, TeamSchedule $schedule): JsonResponse
    {
        try {
            $validated = $request->validate([
                'attendance' => ['required', 'array'],
                'attendance.*.player_id' => ['required', 'integer', 'exists:users,id'],
                'attendance.*.status' => ['required', 'string', 'in:present,absent,late,excused'],
                'attendance.*.notes' => ['nullable', 'string'],
            ]);

            // Clear existing attendance
            $schedule->attendance()->delete();

            // Create new attendance records
            $attendance = collect($validated['attendance'])->map(function ($record) use ($schedule) {
                return $schedule->attendance()->create([
                    'player_id' => $record['player_id'],
                    'status' => $record['status'],
                    'notes' => $record['notes'] ?? null,
                ]);
            });

            return response()->json([
                'message' => 'Attendance marked successfully',
                'attendance' => $attendance,
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Get attendance.
     * 
     * @OA\Get(
     *     path="/schedules/{schedule}/attendance",
     *     summary="Get attendance",
     *     description="Get attendance records for a schedule",
     *     operationId="getAttendance",
     *     tags={"Team Schedules"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="schedule",
     *         in="path",
     *         description="Schedule ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Attendance retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="attendance",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/TeamScheduleAttendance")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Schedule not found")
     * )
     */
    public function getAttendance(TeamSchedule $schedule): JsonResponse
    {
        $attendance = $schedule->attendance()->with('player')->get();

        return response()->json([
            'attendance' => $attendance,
        ]);
    }
}
