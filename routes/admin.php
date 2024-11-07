<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
|
| Here is where you can register admin routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will be
| assigned to the "admin" middleware group with super-admin role requirement.
|
*/

// Tenant management routes
Route::prefix('tenants')->group(function () {
    Route::get('/', 'TenantController@index')->name('tenants.index');
    Route::post('/', 'TenantController@store')->name('tenants.store');
    Route::get('/{tenant}', 'TenantController@show')->name('tenants.show');
    Route::put('/{tenant}', 'TenantController@update')->name('tenants.update');
    Route::delete('/{tenant}', 'TenantController@destroy')->name('tenants.destroy');
    Route::post('/{tenant}/restore', 'TenantController@restore')->name('tenants.restore');
    Route::post('/{tenant}/impersonate', 'TenantController@impersonate')->name('tenants.impersonate');
    Route::post('/{tenant}/backup', 'TenantController@backup')->name('tenants.backup');
});

// Global user management routes
Route::prefix('users')->group(function () {
    Route::get('/', 'UserController@index')->name('users.index');
    Route::post('/', 'UserController@store')->name('users.store');
    Route::get('/{user}', 'UserController@show')->name('users.show');
    Route::put('/{user}', 'UserController@update')->name('users.update');
    Route::delete('/{user}', 'UserController@destroy')->name('users.destroy');
    Route::post('/{user}/restore', 'UserController@restore')->name('users.restore');
});

// Global role management routes
Route::prefix('roles')->group(function () {
    Route::get('/', 'RoleController@index')->name('roles.index');
    Route::post('/', 'RoleController@store')->name('roles.store');
    Route::get('/{role}', 'RoleController@show')->name('roles.show');
    Route::put('/{role}', 'RoleController@update')->name('roles.update');
    Route::delete('/{role}', 'RoleController@destroy')->name('roles.destroy');
});

// Global permission management routes
Route::prefix('permissions')->group(function () {
    Route::get('/', 'PermissionController@index')->name('permissions.index');
    Route::post('/', 'PermissionController@store')->name('permissions.store');
    Route::get('/{permission}', 'PermissionController@show')->name('permissions.show');
    Route::put('/{permission}', 'PermissionController@update')->name('permissions.update');
    Route::delete('/{permission}', 'PermissionController@destroy')->name('permissions.destroy');
});

// System settings routes
Route::prefix('settings')->group(function () {
    Route::get('/', 'SettingsController@show')->name('settings.show');
    Route::put('/', 'SettingsController@update')->name('settings.update');
    Route::put('/email', 'SettingsController@updateEmail')->name('settings.email');
    Route::put('/security', 'SettingsController@updateSecurity')->name('settings.security');
    Route::put('/features', 'SettingsController@updateFeatures')->name('settings.features');
});

// Activity log routes
Route::prefix('activity')->group(function () {
    Route::get('/', 'ActivityController@index')->name('activity.index');
    Route::get('/{activity}', 'ActivityController@show')->name('activity.show');
    Route::delete('/{activity}', 'ActivityController@destroy')->name('activity.destroy');
    Route::post('/clear', 'ActivityController@clear')->name('activity.clear');
});

// System analytics routes
Route::prefix('analytics')->group(function () {
    Route::get('/dashboard', 'AnalyticsController@dashboard')->name('analytics.dashboard');
    Route::get('/tenants', 'AnalyticsController@tenants')->name('analytics.tenants');
    Route::get('/users', 'AnalyticsController@users')->name('analytics.users');
    Route::get('/activity', 'AnalyticsController@activity')->name('analytics.activity');
});

// System maintenance routes
Route::prefix('maintenance')->group(function () {
    Route::get('/', 'MaintenanceController@show')->name('maintenance.show');
    Route::post('/down', 'MaintenanceController@down')->name('maintenance.down');
    Route::post('/up', 'MaintenanceController@up')->name('maintenance.up');
    Route::post('/cache/clear', 'MaintenanceController@clearCache')->name('maintenance.cache.clear');
    Route::post('/cache/warm', 'MaintenanceController@warmCache')->name('maintenance.cache.warm');
});

// System backup routes
Route::prefix('backups')->group(function () {
    Route::get('/', 'BackupController@index')->name('backups.index');
    Route::post('/', 'BackupController@create')->name('backups.create');
    Route::get('/{backup}', 'BackupController@download')->name('backups.download');
    Route::delete('/{backup}', 'BackupController@destroy')->name('backups.destroy');
});

// System health routes
Route::prefix('health')->group(function () {
    Route::get('/', 'HealthController@show')->name('health.show');
    Route::get('/queue', 'HealthController@queue')->name('health.queue');
    Route::get('/database', 'HealthController@database')->name('health.database');
    Route::get('/cache', 'HealthController@cache')->name('health.cache');
    Route::get('/storage', 'HealthController@storage')->name('health.storage');
});
