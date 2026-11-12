<?php

return [
    'sms' => [
        'enabled' => filter_var(env('TICKET_SMS_ENABLED', false), FILTER_VALIDATE_BOOL),
        'driver' => env('TICKET_SMS_DRIVER', 'termii'),
        'base_url' => env('TERMII_BASE_URL', 'https://api.ng.termii.com'),
        'api_key' => env('TERMII_API_KEY'),
        'sender_id' => env('TERMII_SENDER_ID', 'Tica'),
        'channel' => env('TERMII_CHANNEL', 'dnd'),
        'default_country_code' => env('TICKET_SMS_DEFAULT_COUNTRY_CODE', '234'),
        'connect_timeout_seconds' => max(1, (int) env('TICKET_SMS_CONNECT_TIMEOUT_SECONDS', 5)),
        'timeout_seconds' => max(2, (int) env('TICKET_SMS_TIMEOUT_SECONDS', 10)),
        'owner_email' => env('TICKET_SMS_OWNER_EMAIL'),
        'production_drill_at' => env('TICKET_SMS_DRILL_AT'),
        'production_drill_reference' => env('TICKET_SMS_DRILL_REFERENCE'),
        'production_drill_max_age_days' => max(1, (int) env('TICKET_SMS_DRILL_MAX_AGE_DAYS', 180)),
        'drill_local_number_verified' => filter_var(env('TICKET_SMS_DRILL_LOCAL_NUMBER_VERIFIED', false), FILTER_VALIDATE_BOOL),
        'drill_international_number_verified' => filter_var(env('TICKET_SMS_DRILL_INTERNATIONAL_NUMBER_VERIFIED', false), FILTER_VALIDATE_BOOL),
        'drill_rejection_verified' => filter_var(env('TICKET_SMS_DRILL_REJECTION_VERIFIED', false), FILTER_VALIDATE_BOOL),
        'drill_timeout_verified' => filter_var(env('TICKET_SMS_DRILL_TIMEOUT_VERIFIED', false), FILTER_VALIDATE_BOOL),
        'drill_signed_link_verified' => filter_var(env('TICKET_SMS_DRILL_SIGNED_LINK_VERIFIED', false), FILTER_VALIDATE_BOOL),
        'drill_worker_sla_verified' => filter_var(env('TICKET_SMS_DRILL_WORKER_SLA_VERIFIED', false), FILTER_VALIDATE_BOOL),
    ],
];
