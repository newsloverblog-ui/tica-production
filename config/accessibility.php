<?php

return [
    'owner_email' => env('ACCESSIBILITY_OWNER_EMAIL'),
    'audit_standard' => env('ACCESSIBILITY_AUDIT_STANDARD', 'WCAG 2.2 AA'),
    'audit_at' => env('ACCESSIBILITY_AUDIT_AT'),
    'audit_reference' => env('ACCESSIBILITY_AUDIT_REFERENCE'),
    'audit_max_age_days' => max(1, (int) env('ACCESSIBILITY_AUDIT_MAX_AGE_DAYS', 180)),
    'keyboard_verified' => filter_var(env('ACCESSIBILITY_KEYBOARD_VERIFIED', false), FILTER_VALIDATE_BOOL),
    'screen_reader_verified' => filter_var(env('ACCESSIBILITY_SCREEN_READER_VERIFIED', false), FILTER_VALIDATE_BOOL),
    'checkout_verified' => filter_var(env('ACCESSIBILITY_CHECKOUT_VERIFIED', false), FILTER_VALIDATE_BOOL),
    'scanner_verified' => filter_var(env('ACCESSIBILITY_SCANNER_VERIFIED', false), FILTER_VALIDATE_BOOL),
];
