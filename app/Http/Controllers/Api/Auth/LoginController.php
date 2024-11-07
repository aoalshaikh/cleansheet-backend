<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\OtpVerificationRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Facades\JWTAuth;

class LoginController extends Controller
{
    /**
     * @OA\Post(
     *     path="/login",
     *     summary="Authenticate user",
     *     description="Login using email/phone and password",
     *     operationId="login",
     *     tags={"Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"password"},
     *             @OA\Property(property="email", type="string", format="email", example="user@example.com"),
     *             @OA\Property(property="phone", type="string", example="+1234567890"),
     *             @OA\Property(property="password", type="string", format="password", example="password123"),
     *             @OA\Property(property="remember", type="boolean", example=false)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Login successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="token", type="string", example="eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."),
     *             @OA\Property(
     *                 property="user",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="John Doe"),
     *                 @OA\Property(property="email", type="string", format="email", example="user@example.com"),
     *                 @OA\Property(property="phone", type="string", example="+1234567890"),
     *                 @OA\Property(property="roles", type="array", @OA\Items(type="string", example="player"))
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Invalid credentials",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Invalid credentials")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="email",
     *                     type="array",
     *                     @OA\Items(type="string", example="The email field is required when phone is not present.")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();
        
        if (!$token = Auth::guard('api')->attempt($credentials)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        /** @var User $user */
        $user = Auth::guard('api')->user();

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'roles' => $user->getRoleNames(),
            ]
        ]);
    }

    /**
     * @OA\Post(
     *     path="/verify-otp",
     *     summary="Verify OTP code",
     *     description="Verify one-time password for two-factor authentication",
     *     operationId="verifyOtp",
     *     tags={"Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"type", "identifier", "code"},
     *             @OA\Property(property="type", type="string", enum={"email", "phone"}, example="email"),
     *             @OA\Property(property="identifier", type="string", example="user@example.com"),
     *             @OA\Property(property="code", type="string", example="123456")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="OTP verified successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="OTP verified successfully"),
     *             @OA\Property(property="token", type="string", example="eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Invalid OTP",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Invalid OTP")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="code",
     *                     type="array",
     *                     @OA\Items(type="string", example="The code field is required.")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function verifyOtp(OtpVerificationRequest $request): JsonResponse
    {
        $validated = $request->validated();

        /** @var User $user */
        $user = Auth::guard('api')->user();
        
        $otp = $user->otps()
            ->where('type', $validated['type'])
            ->where('identifier', $validated['identifier'])
            ->where('code', $validated['code'])
            ->first();

        if (!$otp || !$otp->verify()) {
            return response()->json(['message' => 'Invalid OTP'], 401);
        }

        // Invalidate current token and generate a new one
        JWTAuth::invalidate(JWTAuth::getToken());
        $token = JWTAuth::fromUser($user);

        return response()->json([
            'message' => 'OTP verified successfully',
            'token' => $token
        ]);
    }

    /**
     * @OA\Post(
     *     path="/resend-otp",
     *     summary="Resend OTP",
     *     description="Request a new OTP code",
     *     operationId="resendOtp",
     *     tags={"Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"type", "identifier"},
     *             @OA\Property(property="type", type="string", enum={"email", "phone"}, example="email"),
     *             @OA\Property(property="identifier", type="string", example="user@example.com")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="OTP sent successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="OTP sent successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="type",
     *                     type="array",
     *                     @OA\Items(type="string", example="The type field is required.")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function resendOtp(OtpVerificationRequest $request): JsonResponse
    {
        $validated = $request->validated();

        /** @var User $user */
        $user = Auth::guard('api')->user();

        // Invalidate existing OTPs
        $user->otps()
            ->where('type', $validated['type'])
            ->where('identifier', $validated['identifier'])
            ->update(['verified_at' => now()]);

        // Generate new OTP
        $otp = $user->otps()->create([
            'type' => $validated['type'],
            'identifier' => $validated['identifier'],
            'code' => str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT),
            'expires_at' => now()->addMinutes(10)
        ]);

        // Send OTP via appropriate channel
        if ($otp->type === 'email') {
            // Send email
        } else {
            // Send SMS
        }

        return response()->json(['message' => 'OTP sent successfully']);
    }
}
