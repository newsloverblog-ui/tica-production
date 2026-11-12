<?php

return [
    // Seven years is the default operational/financial audit evidence window.
    'retention_days' => max(365, (int) env('AUDIT_LOG_RETENTION_DAYS', 2555)),
    // Retention is deliberately opt-in until the controller approves the policy.
    'retention_enabled' => filter_var(env('AUDIT_LOG_RETENTION_ENABLED', false), FILTER_VALIDATE_BOOL),
];
