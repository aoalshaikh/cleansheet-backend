<?php

use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\GameMatchController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OrganizationController;
use App\Http\Controllers\Api\PlayerController;
use App\Http\Controllers\Api\PlayerEvaluationController;
use App\Http\Controllers\Api\PlayerSkillController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\TeamController;
use App\Http\Controllers\Api\TeamScheduleController;
use App\Http\Controllers\Api\TeamTierController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes
Route::prefix('v1')->group(function () {
    // Auth
    Route::post('login', [LoginController::class, 'login']);
    Route::post('register', [RegisterController::class, 'register']);
    Route::post('verify-otp', [LoginController::class, 'verifyOtp']);
    Route::post('resend-otp', [LoginController::class, 'resendOtp']);

    // Organization signup
    Route::post('organizations/signup', [OrganizationController::class, 'signup']);
});

// Protected routes
Route::prefix('v1')->middleware(['auth:api'])->group(function () {
    // User Profile
    Route::get('profile', [PlayerController::class, 'profile']);
    Route::put('profile', [PlayerController::class, 'updateProfile']);
    Route::post('profile/avatar', [PlayerController::class, 'updateAvatar']);

    // Organizations
    Route::apiResource('organizations', OrganizationController::class);
    Route::prefix('organizations/{organization}')->group(function () {
        Route::get('stats', [OrganizationController::class, 'stats']);
        Route::get('players', [OrganizationController::class, 'players']);
        Route::get('coaches', [OrganizationController::class, 'coaches']);
        Route::post('invite', [OrganizationController::class, 'invite']);
    });

    // Teams
    Route::apiResource('teams', TeamController::class);
    Route::prefix('teams/{team}')->group(function () {
        Route::post('players/{player}', [TeamController::class, 'addPlayer']);
        Route::delete('players/{player}', [TeamController::class, 'removePlayer']);
        Route::get('stats', [TeamController::class, 'stats']);
        Route::get('schedule', [TeamController::class, 'schedule']);
    });

    // Team Schedules
    Route::apiResource('schedules', TeamScheduleController::class);
    Route::prefix('schedules/{schedule}')->group(function () {
        Route::post('attendance', [TeamScheduleController::class, 'markAttendance']);
        Route::get('attendance', [TeamScheduleController::class, 'getAttendance']);
    });

    // Team Tiers
    Route::apiResource('tiers', TeamTierController::class);
    Route::prefix('tiers/{tier}')->group(function () {
        Route::post('players/{player}', [TeamTierController::class, 'assignPlayer']);
        Route::delete('players/{player}', [TeamTierController::class, 'removePlayer']);
        Route::post('players/{player}/promote', [TeamTierController::class, 'promotePlayer']);
        Route::post('players/{player}/demote', [TeamTierController::class, 'demotePlayer']);
    });

    // Matches
    Route::apiResource('matches', GameMatchController::class);
    Route::prefix('matches/{match}')->group(function () {
        Route::post('events', [GameMatchController::class, 'addEvent']);
        Route::post('lineup', [GameMatchController::class, 'setLineup']);
        Route::post('start', [GameMatchController::class, 'startMatch']);
        Route::post('complete', [GameMatchController::class, 'completeMatch']);
        Route::post('cancel', [GameMatchController::class, 'cancelMatch']);
    });

    // Player Skills & Evaluations
    Route::apiResource('skills', PlayerSkillController::class)->only(['index', 'show']);
    Route::apiResource('evaluations', PlayerEvaluationController::class);
    Route::prefix('players/{player}')->group(function () {
        Route::get('skills', [PlayerSkillController::class, 'playerSkills']);
        Route::post('skills/{skill}', [PlayerSkillController::class, 'updateSkill']);
        Route::get('evaluations', [PlayerEvaluationController::class, 'playerEvaluations']);
        Route::get('attendance', [PlayerController::class, 'attendance']);
        Route::get('stats', [PlayerController::class, 'stats']);
    });

    // Notifications
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::post('mark-read', [NotificationController::class, 'markAsRead']);
        Route::post('mark-all-read', [NotificationController::class, 'markAllAsRead']);
        Route::delete('clear', [NotificationController::class, 'clear']);
    });

    // Subscriptions
    Route::prefix('subscriptions')->group(function () {
        Route::get('plans', [SubscriptionController::class, 'plans']);
        Route::post('subscribe', [SubscriptionController::class, 'subscribe']);
        Route::post('cancel', [SubscriptionController::class, 'cancel']);
        Route::get('status', [SubscriptionController::class, 'status']);
    });
});
