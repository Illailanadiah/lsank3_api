<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\KedahAddressController;
use App\Http\Controllers\Api\WaterApplicationController;
use App\Http\Controllers\Api\EffluentApplicationController;
use App\Http\Controllers\Api\ApplicationController;
use App\Http\Controllers\Api\LicenseController;
use App\Http\Controllers\Api\RenewalController;
use App\Http\Controllers\Api\BillplzController;
use App\Http\Controllers\Api\AmendmentController;
use App\Http\Controllers\Api\GoogleMapsController;
use App\Http\Controllers\Api\NoticeController;
use App\Http\Controllers\Api\InspectionReportController;
use App\Http\Controllers\Api\CivilCaseController;
use App\Http\Controllers\Api\CriminalCaseController;
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

Route::post('/billplz/callback', [
    BillplzController::class,
    'callback',
]);

Route::get('/billplz/redirect', [
    BillplzController::class,
    'redirect',
]);

Route::get(
    '/billplz/invoices/{invoice}/status',
    [BillplzController::class, 'status']
)->whereNumber('invoice');

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
    Route::get('/admin/licenses', [
        LicenseController::class,
        'adminIndex'
    ]);

    /*
    |--------------------------------------------------------------------------
    | Google Maps
    |--------------------------------------------------------------------------
    */

    Route::get('/maps/reverse-geocode', [
        GoogleMapsController::class,
        'reverseGeocode',
    ])->name('maps.reverse-geocode');

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

        Route::post('/{application}/invoices/{invoice}/pay', [
            WaterApplicationController::class,
            'payInvoice',
        ])->whereNumber('application')
            ->whereNumber('invoice');

        Route::post(
            '/{application}/final-invoices/{invoice}/pay',
            [
                WaterApplicationController::class,
                'payFinalInvoice',
            ]
        )
            ->whereNumber('application')
            ->whereNumber('invoice');

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

        Route::post('/{application}/invoices/{invoice}/pay', [
            EffluentApplicationController::class,
            'payInvoice',
        ])->whereNumber('application')
            ->whereNumber('invoice');

        Route::post(
            '/{application}/final-invoices/{invoice}/pay',
            [
                EffluentApplicationController::class,
                'payFinalInvoice',
            ]
        )
            ->whereNumber('application')
            ->whereNumber('invoice');

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
    | User Side - Security Refund
    |--------------------------------------------------------------------------
    */

    Route::post('/invoices/{invoice}/security-refund/request', [
        ApplicationController::class,
        'requestSecurityRefund',
    ])->whereNumber('invoice');

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
    | Admin Side - Security Refund
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/admin/invoices/security-refunds',
        [
            ApplicationController::class,
            'adminSecurityRefunds',
        ]
    );

    Route::patch(
        '/admin/invoices/{invoice}/security-refund-status',
        [
            ApplicationController::class,
            'updateSecurityRefundStatus',
        ],
    )->whereNumber('invoice');

    /*
    |--------------------------------------------------------------------------
    | Licenses
    |--------------------------------------------------------------------------
    */

    Route::patch(
        '/admin/licenses/{license}/termination/approve',
        [
            LicenseController::class,
            'approveTermination',
        ]
    )->whereNumber('license')
        ->name('admin.licenses.termination.approve');

    Route::prefix('licenses')->group(function () {

        Route::get('/renewals/eligible', [
            RenewalController::class,
            'eligible',
        ])->name('licenses.renewals.eligible');

        Route::post('/{licenseId}/renewals/start', [
            RenewalController::class,
            'start',
        ])->whereNumber('licenseId')
            ->name('licenses.renewals.start');

        Route::post('/{license}/termination-request', [
            LicenseController::class,
            'requestTermination',
        ])->whereNumber('license')
            ->name('licenses.termination-request');

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


    //Billplz route 
    Route::post(
        '/billplz/invoices/{invoice}/create',
        [
            BillplzController::class,
            'createBill',
        ]
    )->whereNumber('invoice');

    /*
    |--------------------------------------------------------------------------
    | License Amendments
    |--------------------------------------------------------------------------
    */

    Route::post('/licenses/{licenseId}/amendments/start', [
        AmendmentController::class,
        'start',
    ])
        ->whereNumber('licenseId')
        ->name('licenses.amendments.start');

    Route::post(
        '/license-amendments/{amendmentId}/enable-form-a',
        [
            AmendmentController::class,
            'enableFormA',
        ]
    )
        ->whereNumber('amendmentId')
        ->name('license-amendments.enable-form-a');

    /*
    |--------------------------------------------------------------------------
    | Admin Side - Licenses
    |--------------------------------------------------------------------------
    |
    | Admin/staff endpoint for retrieving all generated licenses.
    | Keep this OUTSIDE the admin/notices prefix.
    |
    */

    Route::get('/admin/licenses', [
        LicenseController::class,
        'adminIndex',
    ])->name('admin.licenses.index');

    /*
    |--------------------------------------------------------------------------
    | Admin Side - Notices
    |--------------------------------------------------------------------------
    */

    Route::prefix('admin/notices')->group(function () {

        Route::get('/next-number', [
            NoticeController::class,
            'nextNumber',
        ]);

        Route::get('/', [
            NoticeController::class,
            'index',
        ]);

        Route::post('/', [
            NoticeController::class,
            'store',
        ]);

        Route::get('/{notice}', [
            NoticeController::class,
            'show',
        ])->whereNumber('notice');

        Route::put('/{notice}', [
            NoticeController::class,
            'update',
        ])->whereNumber('notice');

        Route::patch('/{notice}/status', [
            NoticeController::class,
            'updateStatus',
        ])->whereNumber('notice');

        Route::delete('/{notice}', [
            NoticeController::class,
            'destroy',
        ])->whereNumber('notice');





    });

    /*
    |--------------------------------------------------------------------------
    | Admin Side - Inspection Reports
    |--------------------------------------------------------------------------
    |
    | Keep this OUTSIDE the admin/notices prefix so the frontend endpoint is:
    | /api/admin/reports/inspection
    |
    */

    Route::prefix('admin/reports/inspection')->group(function () {

        Route::get('/', [
            InspectionReportController::class,
            'index',
        ])->name('admin.inspection-reports.index');

        Route::post('/', [
            InspectionReportController::class,
            'store',
        ])->name('admin.inspection-reports.store');

        Route::get('/{report}', [
            InspectionReportController::class,
            'show',
        ])
            ->whereNumber('report')
            ->name('admin.inspection-reports.show');

        Route::put('/{report}', [
            InspectionReportController::class,
            'update',
        ])
            ->whereNumber('report')
            ->name('admin.inspection-reports.update');

        Route::delete('/{report}', [
            InspectionReportController::class,
            'destroy',
        ])
            ->whereNumber('report')
            ->name('admin.inspection-reports.destroy');

        Route::patch('/{report}/cancel', [
    InspectionReportController::class,
    'cancel',
])
    ->whereNumber('report')
    ->name('admin.inspection-reports.cancel');
    });

    Route::prefix('admin/legal/civil-cases')->group(function () {

    Route::get('/next-number', [
        CivilCaseController::class,
        'nextNumber',
    ])->name('admin.civil-cases.next-number');

    Route::get('/', [
        CivilCaseController::class,
        'index',
    ])->name('admin.civil-cases.index');

    Route::post('/', [
        CivilCaseController::class,
        'store',
    ])->name('admin.civil-cases.store');

    Route::get('/{case}', [
        CivilCaseController::class,
        'show',
    ])
        ->whereNumber('case')
        ->name('admin.civil-cases.show');

    Route::put('/{case}', [
        CivilCaseController::class,
        'update',
    ])
        ->whereNumber('case')
        ->name('admin.civil-cases.update');

    Route::delete('/{case}', [
        CivilCaseController::class,
        'destroy',
    ])
        ->whereNumber('case')
        ->name('admin.civil-cases.destroy');
});


Route::prefix('admin/legal/criminal-cases')->group(function () {

    Route::get('/next-number', [
        CriminalCaseController::class,
        'nextNumber',
    ])->name('admin.criminal-cases.next-number');

    Route::get('/', [
        CriminalCaseController::class,
        'index',
    ])->name('admin.criminal-cases.index');

    Route::post('/', [
        CriminalCaseController::class,
        'store',
    ])->name('admin.criminal-cases.store');

    Route::get('/{case}', [
        CriminalCaseController::class,
        'show',
    ])
        ->whereNumber('case')
        ->name('admin.criminal-cases.show');

    Route::put('/{case}', [
        CriminalCaseController::class,
        'update',
    ])
        ->whereNumber('case')
        ->name('admin.criminal-cases.update');

    Route::delete('/{case}', [
        CriminalCaseController::class,
        'destroy',
    ])
        ->whereNumber('case')
        ->name('admin.criminal-cases.destroy');
});


});