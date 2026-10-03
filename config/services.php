<?php

use Illuminate\Support\Env;

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

    // "Continue with Google" on the sign-in form (AuthController). Hidden until both are set;
    // the callback URL follows the site (route auth.google.callback) unless one is given here.
    'google' => [
        // Trimmed: a space or line break pasted with a key makes Google answer 401.
        'client_id' => trim((string) env('GOOGLE_CLIENT_ID')) ?: null,
        'client_secret' => trim((string) env('GOOGLE_CLIENT_SECRET')) ?: null,
        'redirect' => env('GOOGLE_REDIRECT_URI', env('GOOGLE_CALLBACK_REDIRECTS')),
    ],

    'vercel_blob' => [
        'enabled' => (bool) env('BLOB_READ_WRITE_TOKEN'),
        'token' => env('BLOB_READ_WRITE_TOKEN'),
    ],

    // Vercel Cron sends "Authorization: Bearer <CRON_SECRET>" when the variable is set.
    'cron' => [
        'secret' => env('CRON_SECRET'),
    ],

];
