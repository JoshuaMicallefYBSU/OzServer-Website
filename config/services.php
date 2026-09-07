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

    'mapbox' => [
        'token' => env('MAPBOX_ACCESS_TOKEN'),
    ],

    // The operational API is deployed independently from this website. Keeping the origin in
    // configuration lets staging use its own API without rebuilding the frontend assets.
    'ozserver_api' => [
        'url' => env('OZSERVER_API_URL', 'https://api.ozserver.org'),
        // Gates /console (live client_logs tail) - a deterrent, not real access control: it's
        // checked client-side in the page's own JS, which anyone can read regardless of whether
        // they've typed it in. The underlying API endpoint it calls has no stronger protection of
        // its own either (self-asserted identity, like every other OzServer-API route), so this is
        // no weaker than the rest of the system - just enough to keep the console off casual view.
        'console_password' => env('OZSERVER_CONSOLE_PASSWORD'),
    ],

];
