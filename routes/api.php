<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\KedahAddressController;
use App\Http\Controllers\Api\WaterApplicationController;
use App\Http\Controllers\Api\EffluentApplicationController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::prefix('kedah')->group(function () {
    Route::get('/districts', [KedahAddressController::class, 'districts']);
    Route::get('/cities', [KedahAddressController::class, 'cities']);
    Route::get('/resolve-address', [KedahAddressController::class, 'resolve']);
    Route::get('/search-address', [KedahAddressController::class, 'search']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/profile', [AuthController::class, 'profile']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/update-phone', [AuthController::class, 'updatePhone']);
    Route::post('/update-admin-profile', [AuthController::class, 'updateAdminProfile']);

    Route::get('/users', [AuthController::class, 'users']);
    Route::post('/users', [AuthController::class, 'createUser']);

    Route::prefix('applications/water')->group(function () {
        Route::get('/', [WaterApplicationController::class, 'index']);
        Route::post('/', [WaterApplicationController::class, 'store']);
        Route::get('/{application}', [WaterApplicationController::class, 'show']);
    });

    Route::prefix('applications/effluent')->group(function () {
        Route::get('/', [EffluentApplicationController::class, 'index']);
        Route::post('/', [EffluentApplicationController::class, 'store']);

        Route::post('/save-step', [EffluentApplicationController::class, 'saveStep']);
        Route::get('/{application}', [EffluentApplicationController::class, 'show']);
        Route::post('/{application}/submit', [EffluentApplicationController::class, 'submit']);
    });
});