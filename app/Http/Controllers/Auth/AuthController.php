<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Api\ApiController;
use App\Models\User;
use App\Services\Auth\OtpService;
use App\Services\Logging\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends ApiController
{
    protected $otpService;
    protected $activityLogger;

    public function __construct(OtpService $otpService, ActivityLogger $activityLogger)
    {
        $this->otpService = $otpService;
        $this->activityLogger = $activityLogger;
        $this->middleware('auth:api', ['except' => ['login', 'register', 'requestOtp', 'verifyOtp']]);
    }

    /**
     * @OA\Post(
     *     path="/auth/login",
     *     tags={"Authentication"},
     *     summary="Login with email and password",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email","password"},
     *             @OA\Property(property="email", type="string", format="email"),
     *             @OA\Property(property="password", type="string", format="password")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Login successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="access_token", type="string"),
     *             @OA\Property(property="token_type", type="string"),
     *             @OA\Property(property="expires_in", type="integer"),
     *             @OA\Property(property="user", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Invalid credentials")
     * )
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation error', 422, $validator->errors());
        }

        $credentials = $validator->validated();
        $user = User::where('email', $credentials['email'])->first();

        if (!$user || !$token = JWTAuth::attempt($credentials)) {
            if ($user) {
                $this->activityLogger->logAuth('login', $user, false);
            }
            return $this->errorResponse('Unauthorized', 401);
        }

        $this->activityLogger->logAuth('login', $user, true);
        return $this->respondWithToken($token, $user);
    }

    /**
     * @OA\Post(
     *     path="/auth/register",
     *     tags={"Authentication"},
     *     summary="Register a new user",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","email","password","tenant_id"},
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="email", type="string", format="email"),
     *             @OA\Property(property="phone", type="string"),
     *             @OA\Property(property="password", type="string", format="password"),
     *             @OA\Property(property="tenant_id", type="integer")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="User registered successfully"
     *     ),
     *     @OA\Response(response=400, description="Invalid input")
     * )
     */
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|between:2,100',
            'email' => 'required|string|email|max:100|unique:users',
            'phone' => 'nullable|string|unique:users',
            'password' => 'required|string|min:6',
            'tenant_id' => 'required|exists:tenants,id',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation error', 400, $validator->errors());
        }

        $user = User::create(array_merge(
            $validator->validated(),
            ['password' => Hash::make($request->password)]
        ));

        $this->activityLogger->log('registered', $user);

        return $this->successResponse(
            ['user' => $user],
            'User successfully registered',
            201
        );
    }

    /**
     * @OA\Post(
     *     path="/auth/otp/request",
     *     tags={"Authentication"},
     *     summary="Request OTP for authentication",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"type","identifier"},
     *             @OA\Property(property="type", type="string", enum={"email","phone"}),
     *             @OA\Property(property="identifier", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="OTP sent successfully"
     *     ),
     *     @OA\Response(response=404, description="User not found")
     * )
     */
    public function requestOtp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:email,phone',
            'identifier' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation error', 422, $validator->errors());
        }

        $field = $request->type === 'email' ? 'email' : 'phone';
        $user = User::where($field, $request->identifier)->first();

        if (!$user) {
            return $this->errorResponse('User not found', 404);
        }

        $otp = $this->otpService->generate($user, $request->type, $request->identifier);

        if (!$otp) {
            $this->activityLogger->log('OTP generation failed', $user);
            return $this->errorResponse('Failed to generate OTP', 500);
        }

        $this->activityLogger->log('OTP requested', $user);
        return $this->successResponse([], 'OTP sent successfully');
    }

    /**
     * @OA\Post(
     *     path="/auth/otp/verify",
     *     tags={"Authentication"},
     *     summary="Verify OTP and login",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"type","identifier","code"},
     *             @OA\Property(property="type", type="string", enum={"email","phone"}),
     *             @OA\Property(property="identifier", type="string"),
     *             @OA\Property(property="code", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="OTP verified successfully"
     *     ),
     *     @OA\Response(response=400, description="Invalid OTP")
     * )
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:email,phone',
            'identifier' => 'required|string',
            'code' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation error', 422, $validator->errors());
        }

        $field = $request->type === 'email' ? 'email' : 'phone';
        $user = User::where($field, $request->identifier)->first();

        if (!$user) {
            return $this->errorResponse('User not found', 404);
        }

        if (!$this->otpService->verify($user, $request->type, $request->identifier, $request->code)) {
            $this->activityLogger->logOtpVerification($user, $request->type, false);
            return $this->errorResponse('Invalid or expired OTP', 400);
        }

        $token = JWTAuth::fromUser($user);
        $this->activityLogger->logOtpVerification($user, $request->type, true);
        
        return $this->respondWithToken($token, $user);
    }

    /**
     * @OA\Post(
     *     path="/auth/logout",
     *     tags={"Authentication"},
     *     summary="Logout user",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successfully logged out"
     *     )
     * )
     */
    public function logout(): JsonResponse
    {
        $user = Auth::user();
        $this->activityLogger->logAuth('logout', $user);
        JWTAuth::invalidate(JWTAuth::getToken());
        return $this->successResponse([], 'Successfully logged out');
    }

    /**
     * @OA\Post(
     *     path="/auth/refresh",
     *     tags={"Authentication"},
     *     summary="Refresh JWT token",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Token refreshed successfully"
     *     )
     * )
     */
    public function refresh(): JsonResponse
    {
        $user = Auth::user();
        $token = JWTAuth::refresh();
        $this->activityLogger->log('refreshed token', $user);
        return $this->respondWithToken($token, $user);
    }

    /**
     * @OA\Get(
     *     path="/auth/me",
     *     tags={"Authentication"},
     *     summary="Get authenticated user info",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="User information retrieved successfully"
     *     )
     * )
     */
    public function me(): JsonResponse
    {
        return $this->successResponse(Auth::user());
    }
}
