<?php

return [
    // Governance attestations are intentionally empty by default. Production
    // readiness must not infer legal approval from implemented controls alone.
    'owner_email' => env('PRIVACY_OWNER_EMAIL'),
    'policy_version' => env('PRIVACY_POLICY_VERSION'),
    'breach_plan_reference' => env('PRIVACY_BREACH_PLAN_REFERENCE'),
    'vendor_dpa_review_at' => env('PRIVACY_VENDOR_DPA_REVIEW_AT'),
    'launch_review_at' => env('PRIVACY_LAUNCH_REVIEW_AT'),
    'launch_review_reference' => env('PRIVACY_LAUNCH_REVIEW_REFERENCE'),
    'review_max_age_days' => max(1, (int) env('PRIVACY_REVIEW_MAX_AGE_DAYS', 365)),
];
