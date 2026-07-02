<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\KedahAddressController;
use App\Http\Controllers\Api\WaterApplicationController;
use App\Http\Controllers\Api\EffluentApplicationController;
use App\Http\Controllers\Api\ApplicationController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::prefix('kedah')->group(function () {
    Route::get('/districts', [KedahAddressController::class, 'districts']);
    Route::get('/cities', [KedahAddressController::class, 'cities']);
    Route::get('/resolve-address', [KedahAddressController::class, 'resolve']);
    Route::get('/search-address', [KedahAddressController::class, 'search']);
});

Route::middleware('auth:sanctum')->group(function () {
    /*
    |--------------------------------------------------------------------------
    | Auth / Profile
    |--------------------------------------------------------------------------
    */
    Route::get('/profile', [AuthController::class, 'profile']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/update-phone', [AuthController::class, 'updatePhone']);
    Route::post('/update-admin-profile', [AuthController::class, 'updateAdminProfile']);

    /*
    |--------------------------------------------------------------------------
    | User Management
    |--------------------------------------------------------------------------
    */
    Route::get('/users', [AuthController::class, 'users']);
    Route::post('/users', [AuthController::class, 'createUser']);

    /*
    |--------------------------------------------------------------------------
    | User Side - Water Applications
    |--------------------------------------------------------------------------
    */
    Route::prefix('applications/water')->group(function () {
        Route::get('/', [WaterApplicationController::class, 'index']);
        Route::post('/', [WaterApplicationController::class, 'store']);
        Route::get('/{application}', [WaterApplicationController::class, 'show']);
    });

    /*
    |--------------------------------------------------------------------------
    | User Side - Effluent Applications
    |--------------------------------------------------------------------------
    */
    Route::prefix('applications/effluent')->group(function () {
        Route::get('/', [EffluentApplicationController::class, 'index']);
        Route::post('/', [EffluentApplicationController::class, 'store']);
        Route::post('/save-step', [EffluentApplicationController::class, 'saveStep']);
        Route::get('/{application}', [EffluentApplicationController::class, 'show']);
        Route::post('/{application}/submit', [EffluentApplicationController::class, 'submit']);
    });

    /*
    |--------------------------------------------------------------------------
    | User Side - Combined Application List
    |--------------------------------------------------------------------------
    */
    Route::get('/applications/my', [ApplicationController::class, 'myApplications']);

    /*
    |--------------------------------------------------------------------------
    | Admin Side - Applications
    |--------------------------------------------------------------------------
    */
    Route::get('/admin/applications', [ApplicationController::class, 'adminApplications']);

    Route::get('/admin/applications/water', [ApplicationController::class, 'adminWaterApplications']);
    Route::get('/admin/applications/effluent', [ApplicationController::class, 'adminEffluentApplications']);

    Route::get('/admin/applications/water/{id}', [ApplicationController::class, 'showWater']);
    Route::get('/admin/applications/effluent/{id}', [ApplicationController::class, 'showEffluent']);

    Route::get('/admin/applications/{id}', [ApplicationController::class, 'show']);

    Route::post('/admin/applications/{id}/status', [ApplicationController::class, 'updateStatus']);
    Route::post('/admin/applications/{id}/review', [ApplicationController::class, 'review']);
});