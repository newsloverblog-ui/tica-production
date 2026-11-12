<?php

return [

    'vercel' => [
        'cron_secret' => env('CRON_SECRET'),
    ],

    'supabase' => [
        'url' => env('SUPABASE_URL'),
        // These are server-only credentials. Never expose them through a Blade view
        // or a variable prefixed with NEXT_PUBLIC_/VITE_.
        'secret_key' => env('SUPABASE_SECRET_KEY'),
        'service_role_key' => env('SUPABASE_SERVICE_ROLE_KEY'),
        'public_asset_bucket' => env('SUPABASE_PUBLIC_ASSET_BUCKET', 'tica-public'),
        'public_asset_max_bytes' => (int) env('SUPABASE_PUBLIC_ASSET_MAX_BYTES', 52428800),
        'storage_timeout' => (int) env('SUPABASE_STORAGE_TIMEOUT', 15),
    ],

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

  'mailgun' => [
    'domain' => env('MAILGUN_DOMAIN'),
    'secret' => env('MAILGUN_SECRET'),
    'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
  ],

  'postmark' => [
    'token' => env('POSTMARK_TOKEN'),
  ],

  'ses' => [
    'key' => env('AWS_ACCESS_KEY_ID'),
    'secret' => env('AWS_SECRET_ACCESS_KEY'),
    'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
  ],

  'webpush' => [
    // Keep bulk fan-out below the selected push provider/browser allowance.
    'campaigns_per_minute' => (int) env('PUSH_CAMPAIGNS_PER_MINUTE', 120),
  ],

  'paytm-wallet' => [
    'env' => env('PAYTM_ENVIRONMENT'),
    'merchant_id' => env('PAYTM_MERCHANT_ID'),
    'merchant_key' => env('PAYTM_MERCHANT_KEY'),
    'merchant_website' => env('PAYTM_MERCHANT_WEBSITE'),
    'channel' => env('PAYTM_CHANNEL'),
    'industry_type' => env('PAYTM_INDUSTRY_TYPE'),
  ],

  'stripe' => [
    'key' => env('STRIPE_KEY'),
    'secret' => env('STRIPE_SECRET'),
  ],

  'flutterwave' => [
    'secret_hash' => env('FLUTTERWAVE_SECRET_HASH'),
  ],

  'facebook' => [
    'client_id' => env('FACEBOOK_CLIENT_ID'),
    'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
    'redirect' => env('FACEBOOK_CALLBACK_URL'),
  ],
  'google' => [
    'client_id' => env('GOOGLE_CLIENT_ID'),
    'client_secret' => env('GOOGLE_CLIENT_SECRET'),
    'redirect' => env('GOOGLE_CALLBACK_URL'),
  ],

];
