<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\KedahAddressController;
use App\Http\Controllers\Api\WaterApplicationController;
use App\Http\Controllers\Api\EffluentApplicationController;
use App\Http\Controllers\Api\ApplicationController;
use App\Http\Controllers\Api\LicenseController;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::prefix('kedah')->group(function () {
    Route::get('/districts', [KedahAddressController::class, 'districts']);
    Route::get('/cities', [KedahAddressController::class, 'cities']);
    Route::get('/resolve-address', [KedahAddressController::class, 'resolve']);
    Route::get('/search-address', [KedahAddressController::class, 'search']);
});

/*
|--------------------------------------------------------------------------
| Public License Verification
|--------------------------------------------------------------------------
|
| This route must remain outside auth:sanctum so anyone scanning the QR code
| can verify the license.
|
*/

Route::get('/licenses/verify/{token}', [
    LicenseController::class,
    'verify',
])->name('licenses.verify');

/*
|--------------------------------------------------------------------------
| Protected Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {
    /*
    |--------------------------------------------------------------------------
    | Auth / Profile
    |--------------------------------------------------------------------------
    */

    Route::get('/profile', [AuthController::class, 'profile']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/update-phone', [AuthController::class, 'updatePhone']);
    Route::post('/update-admin-profile', [
        AuthController::class,
        'updateAdminProfile',
    ]);

    /*
    |--------------------------------------------------------------------------
    | User Management
    |--------------------------------------------------------------------------
    */

    Route::get('/users', [
        AuthController::class,
        'users',
    ]);

    Route::post('/users', [
        AuthController::class,
        'createUser',
    ]);

    Route::put('/users/{userId}', [
        AuthController::class,
        'updateUser',
    ])->whereNumber('userId');

    Route::delete('/users/{userId}', [
        AuthController::class,
        'deleteUser',
    ])->whereNumber('userId');

    Route::put('/users/{userId}/activate', [
        AuthController::class,
        'activateUser',
    ])->whereNumber('userId');

    /*
    |--------------------------------------------------------------------------
    | User Side - Water Applications
    |--------------------------------------------------------------------------
    |
    | Status flow:
    | draf -> fi_pemprosesan -> dalam_proses -> lulus / gagal
    |
    */

    Route::prefix('applications/water')->group(function () {
        Route::get('/', [WaterApplicationController::class, 'index']);

        Route::post('/save-draft', [
            WaterApplicationController::class,
            'saveDraft',
        ]);

        Route::post('/', [WaterApplicationController::class, 'store']);

        Route::post('/{application}/generate-invoice', [
            WaterApplicationController::class,
            'generateInvoice',
        ]);

        Route::post('/{application}/pay', [
            WaterApplicationController::class,
            'pay',
        ]);
        
        Route::post('/{application}/pay-final', [
            WaterApplicationController::class,
            'payFinal',
        ]);

        Route::delete('/{application}/draft', [
            WaterApplicationController::class,
            'destroyDraft',
        ]);

        Route::get('/{application}', [
            WaterApplicationController::class,
            'show',
        ]);
    });

    /*
    |--------------------------------------------------------------------------
    | User Side - Effluent Applications
    |--------------------------------------------------------------------------
    */

    Route::prefix('applications/effluent')->group(function () {
        Route::get('/', [EffluentApplicationController::class, 'index']);

        Route::post('/', [
            EffluentApplicationController::class,
            'store',
        ]);

        Route::post('/save-step', [
            EffluentApplicationController::class,
            'saveStep',
        ]);

        Route::post('/save-draft', [
            EffluentApplicationController::class,
            'saveDraft',
        ]);

        Route::post('/{application}/generate-invoice', [
            EffluentApplicationController::class,
            'generateInvoice',
        ]);

        Route::post('/{application}/pay', [
            EffluentApplicationController::class,
            'pay',
        ]);

        Route::delete('/{application}/draft', [
            EffluentApplicationController::class,
            'destroyDraft',
        ]);

        Route::get('/{application}', [
            EffluentApplicationController::class,
            'show',
        ]);

        Route::post('/{application}/submit', [
            EffluentApplicationController::class,
            'submit',
        ]);
    });

    /*
    |--------------------------------------------------------------------------
    | User Side - Combined Application List
    |--------------------------------------------------------------------------
    */

    Route::get('/applications/my', [
        ApplicationController::class,
        'myApplications',
    ]);

    /*
    |--------------------------------------------------------------------------
    | Admin Side - Applications
    |--------------------------------------------------------------------------
    |
    | Admin should see:
    | dalam_proses, lulus, gagal
    |
    | Admin should not see:
    | draf, fi_pemprosesan
    |
    */

    Route::get('/admin/applications', [
        ApplicationController::class,
        'adminApplications',
    ]);

    Route::get('/admin/applications/water', [
        ApplicationController::class,
        'adminWaterApplications',
    ]);

    Route::get('/admin/applications/effluent', [
        ApplicationController::class,
        'adminEffluentApplications',
    ]);

    Route::get('/admin/applications/water/{id}', [
        ApplicationController::class,
        'showWater',
    ]);

    Route::get('/admin/applications/effluent/{id}', [
        ApplicationController::class,
        'showEffluent',
    ]);

    Route::get('/admin/applications/{id}', [
        ApplicationController::class,
        'show',
    ]);

    Route::post('/admin/applications/{id}/status', [
        ApplicationController::class,
        'updateStatus',
    ]);

    Route::post('/admin/applications/{id}/review', [
        ApplicationController::class,
        'review',
    ]);

    /*
    |--------------------------------------------------------------------------
    | Licenses
    |--------------------------------------------------------------------------
    */

    Route::prefix('licenses')->group(function () {
        Route::get('/', [
            LicenseController::class,
            'index',
        ])->name('licenses.index');

        Route::get('/{license}', [
            LicenseController::class,
            'show',
        ])->whereNumber('license')
            ->name('licenses.show');

        Route::get('/{license}/download-pdf', [
            LicenseController::class,
            'downloadPdf',
        ])->whereNumber('license')
            ->name('licenses.download-pdf');

        Route::get('/{license}/print-pdf', [
            LicenseController::class,
            'printPdf',
        ])->whereNumber('license')
            ->name('licenses.print-pdf');

        Route::get('/{license}/download-qr', [
            LicenseController::class,
            'downloadQr',
        ])->whereNumber('license')
            ->name('licenses.download-qr');
    });

    /*
    |--------------------------------------------------------------------------
    | Manual License Generation
    |--------------------------------------------------------------------------
    |
    | This is useful for testing or regenerating old approved applications.
    | The controller is idempotent: one application produces one license.
    |
    */

    Route::post('/applications/{application}/generate-license', [
        LicenseController::class,
        'generateFromApplication',
    ])->whereNumber('application')
        ->name('licenses.generate');
});
