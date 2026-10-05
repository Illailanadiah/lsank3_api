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
use App\Http\Controllers\Api\UserNoticeController;
use App\Http\Controllers\Api\StatementController;
use App\Http\Controllers\Api\LegalReferralController;
use App\Http\Controllers\Api\UserLegalCaseController;
use App\Http\Controllers\Api\LegalCaseEmailController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\NotificationPreferenceController;
use App\Http\Controllers\Api\UserDeviceController;
use App\Http\Controllers\Api\WaterApplicationDocumentController;
use App\Http\Controllers\Api\EffluentApplicationDocumentController;
use App\Http\Controllers\Api\InvoicePdfController;
use App\Http\Controllers\Api\ReceiptPdfController;
use App\Http\Controllers\Api\DeviceTokenController;
use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\Api\ChecklistController;
use App\Http\Controllers\Api\ContactUsController;
use App\Http\Controllers\Api\AdminAnnouncementController;
use App\Http\Controllers\Api\AdminEnquiryController;
use App\Http\Controllers\Api\HeaderCheckController;

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
| Announcements - User
|--------------------------------------------------------------------------
*/

Route::get(
    '/announcements',
    [AnnouncementController::class, 'index']
);

Route::get(
    '/announcements/{announcement}',
    [AnnouncementController::class, 'show']
)->whereNumber('announcement');

/*
|--------------------------------------------------------------------------
| Senarai Semak / Checklist
|--------------------------------------------------------------------------
*/

Route::get(
    '/checklists',
    [ChecklistController::class, 'index']
);

Route::get(
    '/checklists/{checklist}',
    [ChecklistController::class, 'show']
)->whereNumber('checklist');

Route::get(
    '/checklists/{checklist}/download',
    [ChecklistController::class, 'download']
)->whereNumber('checklist');

/*
|--------------------------------------------------------------------------
| Contact Us / User
|--------------------------------------------------------------------------
*/

Route::get(
    '/contact-us',
    [ContactUsController::class, 'info']
);

/*
|--------------------------------------------------------------------------
| Protected Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Invoice & Receipt PDF
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/invoices/{invoice}/pdf',
        [
            InvoicePdfController::class,
            'download',
        ]
    )->whereNumber('invoice');

    Route::get(
        '/receipts/{receipt}/pdf',
        [
            ReceiptPdfController::class,
            'download',
        ]
    )->whereNumber('receipt');
    
    /*
    |--------------------------------------------------------------------------
    | Document / File Uploads
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/applications/water/{application}/documents',
        [WaterApplicationDocumentController::class, 'index']
    );

    Route::post(
        '/applications/water/{application}/documents',
        [WaterApplicationDocumentController::class, 'store']
    );

    Route::delete(
        '/applications/water/{application}/documents/{document}',
        [WaterApplicationDocumentController::class, 'destroy']
    );

    Route::get(
        '/applications/water/{application}/documents/{document}/download',
        [WaterApplicationDocumentController::class, 'download']
    );

    Route::get(
        '/applications/effluent/{application}/documents',
        [EffluentApplicationDocumentController::class, 'index']
    );

    Route::post(
        '/applications/effluent/{application}/documents',
        [EffluentApplicationDocumentController::class, 'store']
    );

    Route::get(
        '/applications/effluent/{application}/documents/{document}/download',
        [EffluentApplicationDocumentController::class, 'download']
    );

    Route::delete(
        '/applications/effluent/{application}/documents/{document}',
        [EffluentApplicationDocumentController::class, 'destroy']
    );

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
| Admin Enquiries
|--------------------------------------------------------------------------
*/

    Route::prefix('admin/enquiries')->group(function () {

        Route::get('/', [
            AdminEnquiryController::class,
            'index',
        ]);

        Route::get('/{enquiry}', [
            AdminEnquiryController::class,
            'show',
        ])->whereNumber('enquiry');

        Route::patch('/{enquiry}/status', [
            AdminEnquiryController::class,
            'updateStatus',
        ])->whereNumber('enquiry');

        Route::delete('/{enquiry}', [
            AdminEnquiryController::class,
            'destroy',
        ])->whereNumber('enquiry');
    });

    /*
|--------------------------------------------------------------------------
| Admin Announcements
|--------------------------------------------------------------------------
*/

    Route::prefix('admin/announcements')->group(function () {

        Route::get('/', [
            AdminAnnouncementController::class,
            'index',
        ]);

        Route::post('/', [
            AdminAnnouncementController::class,
            'store',
        ]);
    }
    );
    

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
    | Enquiries - User
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/enquiries',
        [ContactUsController::class, 'index']
    );

    Route::post(
        '/enquiries',
        [ContactUsController::class, 'store']
    );

    Route::get(
        '/enquiries/{enquiry}',
        [ContactUsController::class, 'show']
    )->whereNumber('enquiry');

    /*
|--------------------------------------------------------------------------
| Admin Enquiries
|--------------------------------------------------------------------------
*/

    Route::prefix('admin/enquiries')->group(function () {

        Route::get('/', [
            AdminEnquiryController::class,
            'index',
        ]);

        Route::get('/{enquiry}', [
            AdminEnquiryController::class,
            'show',
        ])->whereNumber('enquiry');

        Route::patch('/{enquiry}/status', [
            AdminEnquiryController::class,
            'updateStatus',
        ])->whereNumber('enquiry');

        Route::delete('/{enquiry}', [
            AdminEnquiryController::class,
            'destroy',
        ])->whereNumber('enquiry');
    });

    /*
|--------------------------------------------------------------------------
| Admin Announcements
|--------------------------------------------------------------------------
*/

    Route::prefix('admin/announcements')->group(function () {

        Route::get('/', [
            AdminAnnouncementController::class,
            'index',
        ]);

        Route::post('/', [
            AdminAnnouncementController::class,
            'store',
        ]);

        Route::get('/{announcement}', [
            AdminAnnouncementController::class,
            'show',
        ])->whereNumber('announcement');

        Route::put('/{announcement}', [
            AdminAnnouncementController::class,
            'update',
        ])->whereNumber('announcement');

        Route::patch('/{announcement}', [
            AdminAnnouncementController::class,
            'update',
        ])->whereNumber('announcement');

        Route::delete('/{announcement}', [
            AdminAnnouncementController::class,
            'destroy',
        ])->whereNumber('announcement');
    });

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
/*
|--------------------------------------------------------------------------
| Admin Side - Statements
|--------------------------------------------------------------------------
*/

Route::prefix('admin/statements')->group(function () {

    // Senarai penyata
    Route::get('/', [
        StatementController::class,
        'index',
    ])->name('admin.statements.index');

    // Detail penyata berdasarkan application + tahun
    Route::get('/{application}/{year}', [
        StatementController::class,
        'show',
    ])
        ->whereNumber('application')
        ->whereNumber('year')
        ->name('admin.statements.show');

    // Download PDF penyata
    Route::get('/{application}/{year}/download-pdf', [
        StatementController::class,
        'downloadPdf',
    ])
        ->whereNumber('application')
        ->whereNumber('year')
        ->name('admin.statements.download-pdf');

    // Print PDF penyata
    Route::get('/{application}/{year}/print-pdf', [
        StatementController::class,
        'printPdf',
    ])
        ->whereNumber('application')
        ->whereNumber('year')
        ->name('admin.statements.print-pdf');
});

Route::get(
    '/legal-cases',
    [
        UserLegalCaseController::class,
        'index',
    ]
);

Route::get(
    '/legal-cases/{type}/{case}',
    [
        UserLegalCaseController::class,
        'show',
    ]
)
    ->whereIn(
        'type',
        [
            'civil',
            'criminal',
        ]
    )
    ->whereNumber('case');


Route::prefix('notices')->group(function () {
    Route::get('/', [
        UserNoticeController::class,
        'index',
    ]);

    Route::get('/restriction/status', [
        UserNoticeController::class,
        'restrictionStatus',
    ]);

    // CIVIL + CRIMINAL FOR LICENSE HOLDER
    // IMPORTANT: must be before /{notice}
    Route::get('/legal-cases', [
        UserLegalCaseController::class,
        'index',
    ]);

    Route::get('/legal-cases/{type}/{case}', [
        UserLegalCaseController::class,
        'show',
    ])
        ->whereIn('type', [
            'civil',
            'criminal',
        ])
        ->whereNumber('case');

    Route::get('/{notice}', [
        UserNoticeController::class,
        'show',
    ])->whereNumber('notice');

    Route::post('/{notice}/acknowledge', [
        UserNoticeController::class,
        'acknowledge',
    ])->whereNumber('notice');

    Route::post('/{notice}/respond', [
        UserNoticeController::class,
        'respond',
    ])->whereNumber('notice');
});

Route::get(
    'legal-status/me',
    [
        LegalReferralController::class,
        'myStatus',
    ]
);

// Keep these inside auth:sanctum.
Route::prefix(
    'admin/legal/referrals'
)->group(function () {
    // GET is readable by staff so all departments can see
    // triggered/under-legal indicators.
    Route::get(
        '/',
        [
            LegalReferralController::class,
            'index',
        ]
    );

    Route::get(
        '/{referral}',
        [
            LegalReferralController::class,
            'show',
        ]
    )->whereNumber('referral');

    // Only Legal can change referral status.
    Route::patch(
        '/{referral}/status',
        [
            LegalReferralController::class,
            'updateStatus',
        ]
    )
        ->whereNumber('referral')
        ->middleware('legal.department');
});


Route::post(
    'admin/legal/cases/{type}/{case}/send-pengabstrakan-email',
    [
        LegalCaseEmailController::class,
        'sendPengabstrakan',
    ]
)
    ->whereIn('type', [
        'civil',
        'criminal',
    ])
    ->whereNumber('case')
    ->middleware('legal.department');

/*
|--------------------------------------------------------------------------
| Notifications
|--------------------------------------------------------------------------
|
| In-app notification endpoints used by Notification Bell and Ribbon.
|
*/

Route::prefix('notifications')->group(function () {
    Route::get('/', [
        NotificationController::class,
        'index',
    ])->name('notifications.index');

    Route::get('/unread', [
        NotificationController::class,
        'unread',
    ])->name('notifications.unread');

    Route::get('/action-required', [
        NotificationController::class,
        'actionRequired',
    ])->name('notifications.action-required');

    Route::get('/ribbon', [
        NotificationController::class,
        'ribbon',
    ])->name('notifications.ribbon');

    Route::post('/read-all', [
        NotificationController::class,
        'markAllRead',
    ])->name('notifications.read-all');

    Route::post('/{notification}/shown', [
        NotificationController::class,
        'markShown',
    ])
        ->whereNumber('notification')
        ->name('notifications.shown');

    Route::post('/{notification}/read', [
        NotificationController::class,
        'markRead',
    ])
        ->whereNumber('notification')
        ->name('notifications.read');

    Route::post('/{notification}/dismiss', [
        NotificationController::class,
        'dismiss',
    ])
        ->whereNumber('notification')
        ->name('notifications.dismiss');

    Route::post('/{notification}/complete', [
        NotificationController::class,
        'complete',
    ])
        ->whereNumber('notification')
        ->name('notifications.complete');
});

/*
|--------------------------------------------------------------------------
| Notification Preferences
|--------------------------------------------------------------------------
*/

Route::get('/notification-preferences', [
    NotificationPreferenceController::class,
    'show',
])->name('notification-preferences.show');

Route::patch('/notification-preferences', [
    NotificationPreferenceController::class,
    'update',
])->name('notification-preferences.update');

Route::post('/notification-preferences/reset', [
    NotificationPreferenceController::class,
    'reset',
])->name('notification-preferences.reset');

/*
|--------------------------------------------------------------------------
| Push Notification Devices
|--------------------------------------------------------------------------
*/

Route::get('/devices', [
    UserDeviceController::class,
    'index',
])->name('devices.index');

Route::post('/devices/register', [
    UserDeviceController::class,
    'register',
])->name('devices.register');

Route::post('/devices/touch', [
    UserDeviceController::class,
    'touch',
])->name('devices.touch');

Route::post('/devices/unregister', [
    UserDeviceController::class,
    'unregister',
])->name('devices.unregister');

Route::post('/devices/unregister-all', [
    UserDeviceController::class,
    'unregisterAll',
])->name('devices.unregister-all');


Route::post(
    '/notification-devices/test',
    [DeviceTokenController::class, 'test']
);

Route::delete('/devices/{device}', [
    UserDeviceController::class,
    'destroy',
])
    ->whereNumber('device')
    ->name('devices.destroy');


    Route::patch('/admin/licenses/{license}', [
    LicenseController::class,
    'update',
])->whereNumber('license')->name('admin.licenses.update');


Route::middleware('auth:sanctum')->group(function () {
    Route::post(
        '/notification-devices',
        [DeviceTokenController::class, 'store']
    );

    Route::delete(
        '/notification-devices',
        [DeviceTokenController::class, 'destroy']
    );

    Route::delete(
        '/notification-devices/all',
        [DeviceTokenController::class, 'destroyAll']
    );
});

Route::get(
    '/announcements',
    [AnnouncementController::class, 'index']
);
Route::get('/check-header', [HeaderCheckController::class, 'check']);
/*
|--------------------------------------------------------------------------
| End Protected Routes
|--------------------------------------------------------------------------
*/

});