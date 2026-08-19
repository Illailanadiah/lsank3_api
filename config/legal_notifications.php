<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Pengabstrakan Air legal-case notification
    |--------------------------------------------------------------------------
    |
    | Development/testing default:
    | illailanadiah19@gmail.com
    |
    | Production:
    | change LSANK_PENGABSTRAKAN_LEGAL_EMAIL in .env
    | to the real person-in-charge email.
    |
    */
    'pengabstrakan_email' => env(
        'LSANK_PENGABSTRAKAN_LEGAL_EMAIL',
        'illailanadiah19@gmail.com'
    ),
];
