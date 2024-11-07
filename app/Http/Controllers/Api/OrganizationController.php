<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Services\Organization\OrganizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * @OA\Tag(
 *     name="Organizations",
 *     description="Organization management endpoints"
 * )
 */
class OrganizationController extends Controller
{
    public function __construct(
        private readonly OrganizationService $organizationService
    ) {}

    /**
     * Get organization details.
     * 
     * @OA\Get(
     *     path="/organizations/{organization}",
     *     summary="Get organization details",
     *     description="Get details of a specific organization including teams and subscriptions",
     *     operationId="getOrganization",
     *     tags={"Organizations"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="organization",
     *         in="path",
     *         description="Organization ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Organization details retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="organization", ref="#/components/schemas/Organization")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized access",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     )
     * )
     */
    public function show(Organization $organization): JsonResponse
    {
        $this->authorize('view', $organization);

        return response()->json([
            'organization' => $organization->load(['teams', 'subscriptions']),
        ]);
    }

    /**
     * Update organization details.
     * 
     * @OA\Put(
     *     path="/organizations/{organization}",
     *     summary="Update organization details",
     *     description="Update details of a specific organization",
     *     operationId="updateOrganization",
     *     tags={"Organizations"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="organization",
     *         in="path",
     *         description="Organization ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="Updated Soccer Academy"),
     *             @OA\Property(property="description", type="string", example="Premier soccer training academy in the region"),
     *             @OA\Property(
     *                 property="settings",
     *                 type="object",
     *                 @OA\Property(
     *                     property="notifications",
     *                     type="object",
     *                     additionalProperties={"type": "boolean"}
     *                 ),
     *                 @OA\Property(
     *                     property="features",
     *                     type="object",
     *                     additionalProperties={"type": "boolean"}
     *                 ),
     *                 @OA\Property(
     *                     property="subscription",
     *                     type="object",
     *                     @OA\Property(property="player_price", type="number", minimum=0),
     *                     @OA\Property(property="player_duration", type="integer", minimum=1),
     *                     @OA\Property(property="currency", type="string", minLength=3, maxLength=3)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Organization updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Organization updated successfully"),
     *             @OA\Property(property="organization", ref="#/components/schemas/Organization")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Unauthorized access"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     * @throws ValidationException
     */
    public function update(Request $request, Organization $organization): JsonResponse
    {
        $this->authorize('update', $organization);

        try {
            $validated = $request->validate([
                'name' => ['sometimes', 'string', 'max:255'],
                'description' => ['sometimes', 'nullable', 'string'],
                'settings' => ['sometimes', 'array'],
                'settings.notifications' => ['sometimes', 'array'],
                'settings.notifications.*' => ['boolean'],
                'settings.features' => ['sometimes', 'array'],
                'settings.features.*' => ['boolean'],
                'settings.subscription' => ['sometimes', 'array'],
                'settings.subscription.player_price' => ['numeric', 'min:0'],
                'settings.subscription.player_duration' => ['integer', 'min:1'],
                'settings.subscription.currency' => ['string', 'size:3'],
            ]);

            $organization = $this->organizationService->update($organization, $validated);

            return response()->json([
                'message' => 'Organization updated successfully',
                'organization' => $organization,
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update organization',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get organization statistics.
     * 
     * @OA\Get(
     *     path="/organizations/{organization}/stats",
     *     summary="Get organization statistics",
     *     description="Get statistical data for a specific organization",
     *     operationId="getOrganizationStats",
     *     tags={"Organizations"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="organization",
     *         in="path",
     *         description="Organization ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Statistics retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="stats",
     *                 type="object",
     *                 @OA\Property(property="total_players", type="integer"),
     *                 @OA\Property(property="total_coaches", type="integer"),
     *                 @OA\Property(property="total_teams", type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=403, description="Unauthorized access")
     * )
     */
    public function stats(Organization $organization): JsonResponse
    {
        $this->authorize('view', $organization);

        try {
            $stats = $this->organizationService->getStats($organization);

            return response()->json([
                'stats' => $stats,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to get organization stats',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get organization players.
     * 
     * @OA\Get(
     *     path="/organizations/{organization}/players",
     *     summary="Get organization players",
     *     description="Get list of players in the organization",
     *     operationId="getOrganizationPlayers",
     *     tags={"Organizations"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="organization",
     *         in="path",
     *         description="Organization ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Players list retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="players",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/User")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=403, description="Unauthorized access")
     * )
     */
    public function players(Request $request, Organization $organization): JsonResponse
    {
        $this->authorize('view', $organization);

        try {
            $players = $this->organizationService->getMembersByRole(
                $organization,
                'player',
            );

            return response()->json([
                'players' => $players,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to get organization players',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get organization coaches.
     * 
     * @OA\Get(
     *     path="/organizations/{organization}/coaches",
     *     summary="Get organization coaches",
     *     description="Get list of coaches in the organization",
     *     operationId="getOrganizationCoaches",
     *     tags={"Organizations"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="organization",
     *         in="path",
     *         description="Organization ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Coaches list retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="coaches",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/User")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=403, description="Unauthorized access")
     * )
     */
    public function coaches(Request $request, Organization $organization): JsonResponse
    {
        $this->authorize('view', $organization);

        try {
            $coaches = $this->organizationService->getMembersByRole(
                $organization,
                'coach',
            );

            return response()->json([
                'coaches' => $coaches,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to get organization coaches',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Invite a user to the organization.
     * 
     * @OA\Post(
     *     path="/organizations/{organization}/invite",
     *     summary="Invite user to organization",
     *     description="Send invitation to join the organization",
     *     operationId="inviteToOrganization",
     *     tags={"Organizations"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="organization",
     *         in="path",
     *         description="Organization ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "phone", "role"},
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="email", type="string", format="email", example="john.doe@example.com"),
     *             @OA\Property(property="phone", type="string", example="+1234567890"),
     *             @OA\Property(property="role", type="string", enum={"player", "coach"}, example="player")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Invitation sent successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Invitation sent successfully"),
     *             @OA\Property(property="user", ref="#/components/schemas/User")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Unauthorized access"),
     *     @OA\Response(response=422, description="Validation error or member limit reached")
     * )
     * @throws ValidationException
     */
    public function invite(Request $request, Organization $organization): JsonResponse
    {
        $this->authorize('update', $organization);

        try {
            $validated = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'email' => ['nullable', 'email', 'unique:users'],
                'phone' => ['required', 'string', 'unique:users'],
                'role' => ['required', 'string', 'in:player,coach'],
            ]);

            if ($this->organizationService->hasReachedMemberLimit($organization, $validated['role'])) {
                return response()->json([
                    'message' => 'Organization has reached its member limit for this role',
                ], 422);
            }

            $user = $this->organizationService->invite($organization, $validated);

            return response()->json([
                'message' => 'Invitation sent successfully',
                'user' => $user,
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to send invitation',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
