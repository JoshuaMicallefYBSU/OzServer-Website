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
        // Gates /console: checked server-side (ConsoleController::unlock) and remembered in the
        // session. It guards the developer messages, which reach every controller's vatSys, and
        // the log feed, which this server reads from the API on the console's behalf.
        // Unset means no console at all (404).
        'console_password' => env('OZSERVER_CONSOLE_PASSWORD'),
        // The API's CONSOLE_TOKEN, sent as a bearer token when the console sends, lists or
        // withdraws developer messages (App\Http\Controllers\ConsoleController). Server-side only:
        // it never reaches the browser. Unset hides the console's Messages tab.
        'console_token' => env('OZSERVER_CONSOLE_TOKEN'),
        // The API's CLIENT_LOGS_TOKEN, sent as a bearer token when the console reads the forwarded
        // plugin logs. Server-side only. Unset sends none, which is right while the API has none.
        'client_logs_token' => env('OZSERVER_CLIENT_LOGS_TOKEN'),
    ],

];
