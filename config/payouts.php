<?php

return [
    'paystack_transfers_enabled' => (bool) env('PAYSTACK_TRANSFERS_ENABLED', false),
    'automatic_release_enabled' => (bool) env('PAYOUT_AUTOMATIC_RELEASE_ENABLED', false),
    'reconcile_after_minutes' => (int) env('PAYOUT_RECONCILE_AFTER_MINUTES', 5),
    'reconcile_batch_limit' => (int) env('PAYOUT_RECONCILE_BATCH_LIMIT', 100),
    'verification_max_attempts' => (int) env('PAYOUT_VERIFICATION_MAX_ATTEMPTS', 5),
    'finance_email' => env('PAYOUT_FINANCE_EMAIL'),
    'production_drill_at' => env('PAYOUT_PRODUCTION_DRILL_AT'),
    'production_drill_reference' => env('PAYOUT_PRODUCTION_DRILL_REFERENCE'),
    'production_drill_max_age_days' => (int) env('PAYOUT_PRODUCTION_DRILL_MAX_AGE_DAYS', 180),

    /*
    | Automatic release is deliberately governed by an explicit, versioned
    | policy. Enabling the worker without recorded management approval or a
    | settlement owner must fail closed in release readiness and at runtime.
    */
    'eligibility_policy' => [
        'version' => (string) env('PAYOUT_ELIGIBILITY_POLICY_VERSION', '2026-11-v1'),
        'approved' => (bool) env('PAYOUT_ELIGIBILITY_POLICY_APPROVED', false),
        'approval_reference' => env('PAYOUT_ELIGIBILITY_POLICY_APPROVAL_REFERENCE'),
        'settlement_owner' => env('PAYOUT_SETTLEMENT_OWNER'),
        'timezone' => env('PAYOUT_RELEASE_TIMEZONE', 'Africa/Lagos'),
        'release_hour' => (int) env('PAYOUT_RELEASE_HOUR', 10),
        'business_days' => array_values(array_filter(array_map(
            'intval',
            explode(',', (string) env('PAYOUT_RELEASE_BUSINESS_DAYS', '1,2,3,4,5'))
        ), fn (int $day) => $day >= 1 && $day <= 7)),
        'holiday_dates' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('PAYOUT_RELEASE_HOLIDAYS', ''))
        ))),
        'minimum_organizer_age_days' => (int) env('PAYOUT_MIN_ORGANIZER_AGE_DAYS', 14),
        'minimum_kyc_age_days' => (int) env('PAYOUT_MIN_KYC_AGE_DAYS', 7),
        'minimum_account_age_days' => (int) env('PAYOUT_MIN_ACCOUNT_AGE_DAYS', 3),
        'minimum_successful_payouts' => (int) env('PAYOUT_MIN_SUCCESSFUL_PAYOUTS', 1),
        'minimum_amount' => (float) env('PAYOUT_AUTOMATIC_MIN_AMOUNT', 1000),
        'maximum_amount' => (float) env('PAYOUT_AUTOMATIC_MAX_AMOUNT', 500000),
        'daily_organizer_limit' => (float) env('PAYOUT_AUTOMATIC_DAILY_ORGANIZER_LIMIT', 1000000),
        'batch_limit' => (int) env('PAYOUT_AUTOMATIC_BATCH_LIMIT', 100),
        'scan_limit' => (int) env('PAYOUT_AUTOMATIC_SCAN_LIMIT', 5000),
    ],
];
