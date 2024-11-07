<?php

use App\Http\Controllers\Api\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| Here is where you can register tenant-specific routes for your application.
| These routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "tenant" middleware group.
|
*/

// Tenant-specific profile routes
Route::prefix('profile')->group(function () {
    Route::get('/', [ProfileController::class, 'show'])->name('profile.show');
    Route::put('/', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::put('/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar');
    Route::put('/preferences', [ProfileController::class, 'updatePreferences'])->name('profile.preferences');
});

// Tenant settings routes
Route::prefix('settings')->group(function () {
    Route::get('/', 'SettingsController@show')->name('settings.show');
    Route::put('/', 'SettingsController@update')->name('settings.update');
    Route::put('/features', 'SettingsController@updateFeatures')->name('settings.features');
    Route::put('/capabilities', 'SettingsController@updateCapabilities')->name('settings.capabilities');
});

// Tenant user management routes
Route::prefix('users')->group(function () {
    Route::get('/', 'UserController@index')->name('users.index');
    Route::post('/', 'UserController@store')->name('users.store');
    Route::get('/{user}', 'UserController@show')->name('users.show');
    Route::put('/{user}', 'UserController@update')->name('users.update');
    Route::delete('/{user}', 'UserController@destroy')->name('users.destroy');
});

// Tenant role management routes
Route::prefix('roles')->group(function () {
    Route::get('/', 'RoleController@index')->name('roles.index');
    Route::post('/', 'RoleController@store')->name('roles.store');
    Route::get('/{role}', 'RoleController@show')->name('roles.show');
    Route::put('/{role}', 'RoleController@update')->name('roles.update');
    Route::delete('/{role}', 'RoleController@destroy')->name('roles.destroy');
});

// Tenant activity log routes
Route::prefix('activity')->group(function () {
    Route::get('/', 'ActivityController@index')->name('activity.index');
    Route::get('/{activity}', 'ActivityController@show')->name('activity.show');
});

// Tenant analytics routes
Route::prefix('analytics')->group(function () {
    Route::get('/dashboard', 'AnalyticsController@dashboard')->name('analytics.dashboard');
    Route::get('/users', 'AnalyticsController@users')->name('analytics.users');
    Route::get('/activity', 'AnalyticsController@activity')->name('analytics.activity');
});

// Tenant backup routes
Route::prefix('backups')->group(function () {
    Route::get('/', 'BackupController@index')->name('backups.index');
    Route::post('/', 'BackupController@create')->name('backups.create');
    Route::get('/{backup}', 'BackupController@download')->name('backups.download');
    Route::delete('/{backup}', 'BackupController@destroy')->name('backups.destroy');
});
