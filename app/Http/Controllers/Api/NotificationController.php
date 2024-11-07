<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Notification\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * @OA\Tag(
 *     name="Notifications",
 *     description="Notification management endpoints"
 * )
 */
class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationService $notificationService
    ) {}

    /**
     * List user notifications.
     * 
     * @OA\Get(
     *     path="/notifications",
     *     summary="List notifications",
     *     description="Get paginated list of user's notifications with optional filters",
     *     operationId="listNotifications",
     *     tags={"Notifications"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="channel",
     *         in="query",
     *         description="Filter by notification channel",
     *         required=false,
     *         @OA\Schema(type="string", enum={"email", "sms", "push"})
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by notification status",
     *         required=false,
     *         @OA\Schema(type="string", enum={"read", "unread"})
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
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, maximum=100)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Notifications retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="notifications",
     *                 type="object",
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(ref="#/components/schemas/Notification")
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
                'channel' => ['sometimes', 'string', 'in:email,sms,push'],
                'status' => ['sometimes', 'string', 'in:read,unread'],
                'start_date' => ['sometimes', 'date'],
                'end_date' => ['sometimes', 'date', 'after:start_date'],
                'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            ]);

            $notifications = $this->notificationService->getUserNotifications(
                $request->user(),
                $validated
            );

            return response()->json([
                'notifications' => $notifications,
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Mark notifications as read.
     * 
     * @OA\Post(
     *     path="/notifications/mark-read",
     *     summary="Mark notifications as read",
     *     description="Mark specific notifications as read",
     *     operationId="markNotificationsAsRead",
     *     tags={"Notifications"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"notification_ids"},
     *             @OA\Property(
     *                 property="notification_ids",
     *                 type="array",
     *                 @OA\Items(type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Notifications marked as read",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="5 notifications marked as read")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function markAsRead(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'notification_ids' => ['required', 'array'],
                'notification_ids.*' => ['required', 'integer', 'exists:notification_logs,id'],
            ]);

            $count = $this->notificationService->markAsRead(
                $request->user(),
                $validated['notification_ids']
            );

            return response()->json([
                'message' => "{$count} notifications marked as read",
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Mark all notifications as read.
     * 
     * @OA\Post(
     *     path="/notifications/mark-all-read",
     *     summary="Mark all notifications as read",
     *     description="Mark all user's notifications as read",
     *     operationId="markAllNotificationsAsRead",
     *     tags={"Notifications"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="All notifications marked as read",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="10 notifications marked as read")
     *         )
     *     )
     * )
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        try {
            $count = $this->notificationService->markAsRead($request->user());

            return response()->json([
                'message' => "{$count} notifications marked as read",
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to mark notifications as read',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Clear notifications.
     * 
     * @OA\Delete(
     *     path="/notifications/clear",
     *     summary="Clear notifications",
     *     description="Clear user's notifications based on status and date",
     *     operationId="clearNotifications",
     *     tags={"Notifications"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Status of notifications to clear",
     *         required=false,
     *         @OA\Schema(type="string", enum={"read", "all"}, default="read")
     *     ),
     *     @OA\Parameter(
     *         name="before_date",
     *         in="query",
     *         description="Clear notifications before this date",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Notifications cleared successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="5 notifications cleared")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function clear(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'status' => ['sometimes', 'string', 'in:read,all'],
                'before_date' => ['sometimes', 'date'],
            ]);

            $status = match ($validated['status'] ?? 'read') {
                'all' => null,
                default => 'read',
            };

            $count = $this->notificationService->clearNotifications(
                $request->user(),
                $status,
                $validated['before_date'] ?? null
            );

            return response()->json([
                'message' => "{$count} notifications cleared",
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Update notification preferences.
     * 
     * @OA\Put(
     *     path="/notifications/preferences",
     *     summary="Update notification preferences",
     *     description="Update user's notification preferences for different channels and types",
     *     operationId="updateNotificationPreferences",
     *     tags={"Notifications"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/NotificationPreferences")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Preferences updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Notification preferences updated"),
     *             @OA\Property(property="preferences", ref="#/components/schemas/NotificationPreferences")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function updatePreferences(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'email' => ['sometimes', 'boolean'],
                'sms' => ['sometimes', 'boolean'],
                'push' => ['sometimes', 'boolean'],
                'types' => ['sometimes', 'array'],
                'types.*' => ['string', 'in:practice_reminder,match_reminder,evaluation_results'],
            ]);

            $user = $request->user();
            $preferences = $user->getPreference('notifications', []);

            // Update channel preferences
            foreach (['email', 'sms', 'push'] as $channel) {
                if (isset($validated[$channel])) {
                    $preferences['channels'][$channel] = $validated[$channel];
                }
            }

            // Update notification type preferences
            if (isset($validated['types'])) {
                $preferences['types'] = array_fill_keys($validated['types'], true);
            }

            $user->setPreference('notifications', $preferences);

            return response()->json([
                'message' => 'Notification preferences updated',
                'preferences' => $preferences,
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }
    }
}
