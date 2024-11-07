<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * @OA\Tag(
 *     name="Subscriptions",
 *     description="Subscription management endpoints"
 * )
 */
class SubscriptionController extends Controller
{
    /**
     * List subscription plans.
     * 
     * @OA\Get(
     *     path="/subscriptions/plans",
     *     summary="List subscription plans",
     *     description="Get a list of available subscription plans",
     *     operationId="listSubscriptionPlans",
     *     tags={"Subscriptions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Plans retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="plans",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/SubscriptionPlan")
     *             )
     *         )
     *     )
     * )
     */
    public function plans(): JsonResponse
    {
        $plans = SubscriptionPlan::all();

        return response()->json([
            'plans' => $plans,
        ]);
    }

    /**
     * Subscribe to a plan.
     * 
     * @OA\Post(
     *     path="/subscriptions/subscribe",
     *     summary="Subscribe to plan",
     *     description="Subscribe to a subscription plan",
     *     operationId="subscribe",
     *     tags={"Subscriptions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"plan_id", "payment_method", "billing_details"},
     *             @OA\Property(property="plan_id", type="string"),
     *             @OA\Property(property="payment_method", type="string"),
     *             @OA\Property(
     *                 property="billing_details",
     *                 type="object",
     *                 required={"name", "email", "address"},
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="email", type="string", format="email"),
     *                 @OA\Property(
     *                     property="address",
     *                     type="object",
     *                     @OA\Property(property="line1", type="string"),
     *                     @OA\Property(property="line2", type="string", nullable=true),
     *                     @OA\Property(property="city", type="string"),
     *                     @OA\Property(property="state", type="string"),
     *                     @OA\Property(property="postal_code", type="string"),
     *                     @OA\Property(property="country", type="string")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Subscription created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Subscription created successfully"),
     *             @OA\Property(property="subscription", ref="#/components/schemas/OrganizationSubscription")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function subscribe(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'plan_id' => ['required', 'string', 'exists:subscription_plans,id'],
                'payment_method' => ['required', 'string'],
                'billing_details' => ['required', 'array'],
                'billing_details.name' => ['required', 'string'],
                'billing_details.email' => ['required', 'email'],
                'billing_details.address' => ['required', 'array'],
            ]);

            $user = $request->user();
            $plan = SubscriptionPlan::findOrFail($validated['plan_id']);

            // Create subscription logic here
            // This would typically involve payment processing and subscription creation

            return response()->json([
                'message' => 'Subscription created successfully',
                'subscription' => [
                    'plan' => $plan,
                    'status' => 'active',
                    'current_period_end' => now()->addMonth(),
                ],
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create subscription',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cancel subscription.
     * 
     * @OA\Post(
     *     path="/subscriptions/cancel",
     *     summary="Cancel subscription",
     *     description="Cancel current subscription",
     *     operationId="cancelSubscription",
     *     tags={"Subscriptions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"reason"},
     *             @OA\Property(property="reason", type="string", description="Reason for cancellation"),
     *             @OA\Property(property="feedback", type="string", description="Additional feedback", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Subscription cancelled successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Subscription cancelled successfully"),
     *             @OA\Property(property="subscription", ref="#/components/schemas/OrganizationSubscription"),
     *             @OA\Property(property="end_date", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function cancel(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'reason' => ['required', 'string'],
                'feedback' => ['nullable', 'string'],
            ]);

            $user = $request->user();

            // Cancel subscription logic here
            // This would typically involve updating subscription status and handling any necessary cleanup

            return response()->json([
                'message' => 'Subscription cancelled successfully',
                'end_date' => now()->endOfMonth(),
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to cancel subscription',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get subscription status.
     * 
     * @OA\Get(
     *     path="/subscriptions/status",
     *     summary="Get subscription status",
     *     description="Get current subscription status and details",
     *     operationId="getSubscriptionStatus",
     *     tags={"Subscriptions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Subscription status retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="subscription",
     *                 type="object",
     *                 oneOf={
     *                     @OA\Schema(type="null"),
     *                     @OA\Schema(ref="#/components/schemas/OrganizationSubscription")
     *                 }
     *             )
     *         )
     *     )
     * )
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        $subscription = $user->subscription;

        if (!$subscription) {
            return response()->json([
                'subscription' => null,
            ]);
        }

        return response()->json([
            'subscription' => [
                'status' => $subscription->status,
                'plan' => $subscription->plan,
                'current_period_start' => $subscription->current_period_start,
                'current_period_end' => $subscription->current_period_end,
                'cancel_at_period_end' => $subscription->cancel_at_period_end,
            ],
        ]);
    }
}
