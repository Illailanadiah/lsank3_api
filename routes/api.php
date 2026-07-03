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
    | Status flow:
    | draf -> fi_pemprosesan -> dalam_proses -> lulus / gagal
    |--------------------------------------------------------------------------
    */
    Route::prefix('applications/water')->group(function () {
        Route::get('/', [WaterApplicationController::class, 'index']);

        /*
        | User tengah isi borang atau keluar page sebelum bayar.
        | Status akan kekal: draf
        */
        Route::post('/save-draft', [WaterApplicationController::class, 'saveDraft']);

        /*
        | Route lama, boleh kekal sementara untuk compatibility.
        */
        Route::post('/', [WaterApplicationController::class, 'store']);

        /*
        | Bila user sudah tick Terms & Syarat dan klik Next.
        | Status: draf -> fi_pemprosesan
        */
        Route::post('/{application}/generate-invoice', [
            WaterApplicationController::class,
            'generateInvoice',
        ]);

        /*
        | Bila user klik Bayar dan bayaran berjaya.
        | Status: fi_pemprosesan -> dalam_proses
        */
        Route::post('/{application}/pay', [
            WaterApplicationController::class,
            'pay',
        ]);

        /*
        | User hanya boleh padam permohonan berstatus draf sahaja.
        | Draf belum bayar dan belum masuk proses semakan.
        */
        Route::delete('/{application}/draft', [
            WaterApplicationController::class,
            'destroyDraft',
        ]);

        /*
        | Detail permohonan user.
        | Letak bawah supaya route lain tidak dikacau oleh {application}.
        */
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
    | Admin hanya patut nampak:
    | dalam_proses, lulus, gagal
    |
    | Admin jangan nampak:
    | draf, fi_pemprosesan
    |--------------------------------------------------------------------------
    */
    Route::get('/admin/applications', [ApplicationController::class, 'adminApplications']);

    Route::get('/admin/applications/water', [ApplicationController::class, 'adminWaterApplications']);
    Route::get('/admin/applications/effluent', [ApplicationController::class, 'adminEffluentApplications']);

    Route::get('/admin/applications/water/{id}', [ApplicationController::class, 'showWater']);
    Route::get('/admin/applications/effluent/{id}', [ApplicationController::class, 'showEffluent']);

    Route::get('/admin/applications/{id}', [ApplicationController::class, 'show']);

    /*
    | Admin update status:
    | dalam_proses -> lulus / gagal
    */
    Route::post('/admin/applications/{id}/status', [ApplicationController::class, 'updateStatus']);
    Route::post('/admin/applications/{id}/review', [ApplicationController::class, 'review']);
});