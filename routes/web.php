<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group.
|
*/

// Public routes
Route::get('/', function () {
    return view('welcome');
})->name('welcome');

// Authentication routes
Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('login', [AuthController::class, 'login']);
    Route::get('register', [AuthController::class, 'showRegistrationForm'])->name('register');
    Route::post('register', [AuthController::class, 'register']);
    Route::get('password/reset', [AuthController::class, 'showLinkRequestForm'])->name('password.request');
    Route::post('password/email', [AuthController::class, 'sendResetLinkEmail'])->name('password.email');
    Route::get('password/reset/{token}', [AuthController::class, 'showResetForm'])->name('password.reset');
    Route::post('password/reset', [AuthController::class, 'resetPassword'])->name('password.update');
});

// Protected routes
Route::middleware(['auth', 'verified', 'tenant'])->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Profile routes
    Route::prefix('profile')->group(function () {
        Route::get('/', [ProfileController::class, 'show'])->name('profile.show');
        Route::put('/', [ProfileController::class, 'update'])->name('profile.update');
        Route::put('/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
        Route::put('/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar');
        Route::put('/preferences', [ProfileController::class, 'updatePreferences'])->name('profile.preferences');
        Route::delete('/', [ProfileController::class, 'destroy'])->name('profile.destroy');
    });

    // User management routes
    Route::middleware('permission:manage-users')->group(function () {
        Route::resource('users', 'UserController');
        Route::post('users/{user}/restore', 'UserController@restore')->name('users.restore');
    });

    // Role management routes
    Route::middleware('permission:manage-roles')->group(function () {
        Route::resource('roles', 'RoleController');
    });

    // Permission management routes
    Route::middleware('permission:manage-permissions')->group(function () {
        Route::resource('permissions', 'PermissionController');
    });

    // Activity log routes
    Route::middleware('permission:view-activity')->group(function () {
        Route::get('activity', 'ActivityController@index')->name('activity.index');
        Route::get('activity/{activity}', 'ActivityController@show')->name('activity.show');
    });

    // Settings routes
    Route::middleware('permission:manage-settings')->group(function () {
        Route::get('settings', 'SettingsController@show')->name('settings.show');
        Route::put('settings', 'SettingsController@update')->name('settings.update');
    });

    // Analytics routes
    Route::middleware('permission:view-analytics')->group(function () {
        Route::get('analytics/dashboard', 'AnalyticsController@dashboard')->name('analytics.dashboard');
        Route::get('analytics/users', 'AnalyticsController@users')->name('analytics.users');
        Route::get('analytics/activity', 'AnalyticsController@activity')->name('analytics.activity');
    });

    // Admin routes
    Route::middleware('role:super-admin')->prefix('admin')->group(function () {
        Route::get('/', 'AdminController@dashboard')->name('admin.dashboard');
        
        // Tenant management
        Route::resource('tenants', 'TenantController');
        Route::post('tenants/{tenant}/restore', 'TenantController@restore')->name('tenants.restore');
        Route::post('tenants/{tenant}/impersonate', 'TenantController@impersonate')->name('tenants.impersonate');

        // System settings
        Route::get('settings', 'AdminSettingsController@show')->name('admin.settings.show');
        Route::put('settings', 'AdminSettingsController@update')->name('admin.settings.update');

        // System maintenance
        Route::get('maintenance', 'MaintenanceController@show')->name('admin.maintenance.show');
        Route::post('maintenance/down', 'MaintenanceController@down')->name('admin.maintenance.down');
        Route::post('maintenance/up', 'MaintenanceController@up')->name('admin.maintenance.up');

        // System backups
        Route::resource('backups', 'BackupController')->only(['index', 'store', 'destroy']);
        Route::get('backups/{backup}/download', 'BackupController@download')->name('backups.download');

        // System health
        Route::get('health', 'HealthController@show')->name('admin.health.show');
    });
});

// Email verification routes
Route::middleware('auth')->group(function () {
    Route::get('email/verify', [AuthController::class, 'showVerificationNotice'])
        ->name('verification.notice');
    Route::get('email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
        ->middleware('signed')
        ->name('verification.verify');
    Route::post('email/verification-notification', [AuthController::class, 'resendVerificationEmail'])
        ->middleware('throttle:6,1')
        ->name('verification.send');
});

// Logout route
Route::post('logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

// Fallback route
Route::fallback(function () {
    return view('errors.404');
});
