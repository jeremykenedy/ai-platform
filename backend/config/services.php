<?php

declare(strict_types=1);

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
        'client_id' => env('SLACK_CLIENT_ID'),
        'client_secret' => env('SLACK_CLIENT_SECRET'),
        'redirect_uri' => env('SLACK_REDIRECT_URI'),
    ],

    'ollama' => [
        'base_url' => 'http://'.env('OLLAMA_HOST', 'ollama').':'.env('OLLAMA_PORT', 11434),
    ],

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'organization' => env('OPENAI_ORGANIZATION'),
    ],

    'google' => [
        'gemini_api_key' => env('GOOGLE_GEMINI_API_KEY'),
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect_uri' => env('GOOGLE_REDIRECT_URI'),
    ],

    'mistral' => [
        'api_key' => env('MISTRAL_API_KEY'),
    ],

    'groq' => [
        'api_key' => env('GROQ_API_KEY'),
    ],

    'together' => [
        'api_key' => env('TOGETHER_API_KEY'),
    ],

    'openrouter' => [
        'api_key' => env('OPENROUTER_API_KEY'),
        'app_name' => env('OPENROUTER_APP_NAME', 'My AI'),
        'app_url' => env('OPENROUTER_APP_URL'),
    ],

    'replicate' => [
        'api_token' => env('REPLICATE_API_TOKEN'),
    ],

    'stability' => [
        'api_key' => env('STABILITY_API_KEY'),
    ],

    'elevenlabs' => [
        'api_key' => env('ELEVENLABS_API_KEY'),
    ],

    'deepgram' => [
        'api_key' => env('DEEPGRAM_API_KEY'),
    ],

    'comfyui' => [
        'base_url' => 'http://'.env('COMFYUI_HOST', 'comfyui').':'.env('COMFYUI_PORT', 8188),
    ],

    'searxng' => [
        'base_url' => env('SEARXNG_BASE_URL', 'http://searxng:8080'),
    ],

    'github' => [
        'client_id' => env('GITHUB_CLIENT_ID'),
        'client_secret' => env('GITHUB_CLIENT_SECRET'),
        'redirect_uri' => env('GITHUB_REDIRECT_URI'),
    ],

    'microsoft' => [
        'client_id' => env('MICROSOFT_CLIENT_ID'),
        'client_secret' => env('MICROSOFT_CLIENT_SECRET'),
        'redirect_uri' => env('MICROSOFT_REDIRECT_URI'),
    ],

    'calendly' => [
        'client_id' => env('CALENDLY_CLIENT_ID'),
        'client_secret' => env('CALENDLY_CLIENT_SECRET'),
        'redirect_uri' => env('CALENDLY_REDIRECT_URI'),
    ],

    'spotify' => [
        'client_id' => env('SPOTIFY_CLIENT_ID'),
        'client_secret' => env('SPOTIFY_CLIENT_SECRET'),
        'redirect_uri' => env('SPOTIFY_REDIRECT_URI'),
    ],

    'postman' => [
        'api_key' => env('POSTMAN_API_KEY'),
    ],

    'brave_search' => [
        'api_key' => env('BRAVE_SEARCH_API_KEY'),
    ],

    'apify' => [
        'api_token' => env('APIFY_API_TOKEN'),
    ],

    'paypal' => [
        'client_id' => env('PAYPAL_CLIENT_ID'),
        'client_secret' => env('PAYPAL_CLIENT_SECRET'),
    ],

    'stripe' => [
        'secret_key' => env('STRIPE_SECRET_KEY'),
    ],

    'miro' => [
        'client_id' => env('MIRO_CLIENT_ID'),
        'client_secret' => env('MIRO_CLIENT_SECRET'),
    ],

    'figma' => [
        'access_token' => env('FIGMA_ACCESS_TOKEN'),
    ],

    'notion' => [
        'api_key' => env('NOTION_API_KEY'),
    ],

    'linear' => [
        'api_key' => env('LINEAR_API_KEY'),
    ],

    'jira' => [
        'api_token' => env('JIRA_API_TOKEN'),
    ],

    'vercel' => [
        'token' => env('VERCEL_TOKEN'),
    ],

    'cloudflare' => [
        'api_token' => env('CLOUDFLARE_API_TOKEN'),
    ],

    'indeed' => [
        'api_key' => env('INDEED_API_KEY'),
    ],

    'dice' => [
        'api_key' => env('DICE_API_KEY'),
    ],

    'harvey' => [
        'api_key' => env('HARVEY_API_KEY'),
    ],

];
