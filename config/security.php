<?php

return [
    /* Enforce by default. Inline scripts remain temporarily allowed for the legacy UI. */
    'enforce_content_security_policy' => filter_var(env('CSP_ENFORCE', true), FILTER_VALIDATE_BOOL),

    'content_security_policy' => env(
        'CONTENT_SECURITY_POLICY',
        "default-src 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'self'; " .
        "img-src 'self' data: blob: https:; media-src 'self' blob: https:; " .
        "font-src 'self' data: https:; style-src 'self' 'unsafe-inline' https:; " .
        "script-src 'self' 'unsafe-inline' https:; " .
        "connect-src 'self' https: wss:; frame-src 'self' https:; form-action 'self' https:"
    ),

    // These three legacy Vue 2 screens compile DOM templates in the browser.
    // Keep eval permission isolated to them until they are moved to precompiled views.
    'legacy_vue_compiler_routes' => [
        'admin.withdraw_payment_method.mange_input',
        'admin.withdraw_payment_method.edit_input',
        'organizer.event.add.ticket',
    ],

    // External assurance evidence. These references must identify controlled
    // reports or change records, never contain report contents or credentials.
    'owner_email' => env('SECURITY_OWNER_EMAIL'),
    'penetration_test_at' => env('SECURITY_PENETRATION_TEST_AT'),
    'penetration_test_reference' => env('SECURITY_PENETRATION_TEST_REFERENCE'),
    'penetration_test_max_age_days' => max(1, (int) env('SECURITY_PENETRATION_TEST_MAX_AGE_DAYS', 180)),
    'key_rotation_at' => env('SECURITY_KEY_ROTATION_AT'),
    'key_rotation_reference' => env('SECURITY_KEY_ROTATION_REFERENCE'),
    'key_rotation_max_age_days' => max(1, (int) env('SECURITY_KEY_ROTATION_MAX_AGE_DAYS', 365)),
    'incident_drill_at' => env('SECURITY_INCIDENT_DRILL_AT'),
    'incident_drill_reference' => env('SECURITY_INCIDENT_DRILL_REFERENCE'),
    'incident_drill_max_age_days' => max(1, (int) env('SECURITY_INCIDENT_DRILL_MAX_AGE_DAYS', 180)),
];

