<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI / NLP Service Configuration
    |--------------------------------------------------------------------------
    | Set PREFERRED_AI in .env to: openai | gemini | nlp_local
    | nlp_local uses the built-in PHP NLP engine (no API key required).
    */

    'ai' => [
        'preferred' => env('PREFERRED_AI', 'nlp_local'),
    ],

    'openai' => [
        'key'   => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4o'),
    ],

    'gemini' => [
        'key'   => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
    ],

    'python_nlp' => [
        'enabled' => env('PYTHON_NLP_ENABLED', false),
        'base_url' => env('PYTHON_NLP_URL', 'http://127.0.0.1:8001'),
        'timeout' => env('PYTHON_NLP_TIMEOUT', 8),
    ],

    /*
    |--------------------------------------------------------------------------
    | Postmark (email — swap for Mailtrap / SMTP in .env)
    |--------------------------------------------------------------------------
    */
    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key'    => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY') ?: env('RESEND_API_KEY'),
    ],

    'sendgrid' => [
        'key' => env('SENDGRID_API_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel'              => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
