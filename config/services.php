<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'billplz' => [
        
        //nanti buang
        'enabled' => env('BILLPLZ_ENABLED', false),
        //

        'api_url' => env(
            'BILLPLZ_API_URL',
            'https://www.billplz.com/api'
        ),

        'secret_key' => env('BILLPLZ_SECRET_KEY'),

        'x_signature_key' => env(
            'BILLPLZ_X_SIGNATURE_KEY'
        ),

        'collection_id' => env(
            'BILLPLZ_COLLECTION_ID'
        ),
    ],

    'google_maps' => [
        'api_key' => env('GOOGLE_MAPS_API_KEY'),
    ],

    'recaptcha' => [
        'secret_key' => env('RECAPTCHA_SECRET_KEY'),
        'minimum_score' => (float) env('RECAPTCHA_MIN_SCORE', 0.5),
    ],

    'firebase' => [
    'project_id' =>
        env('FIREBASE_PROJECT_ID'),

    'credentials' =>
        env('FIREBASE_CREDENTIALS'),
],

'whatsapp' => [
    'access_token' =>
        env('WHATSAPP_ACCESS_TOKEN'),

    'phone_number_id' =>
        env('WHATSAPP_PHONE_NUMBER_ID'),

    'graph_version' =>
        env('WHATSAPP_GRAPH_VERSION'),
],



];
