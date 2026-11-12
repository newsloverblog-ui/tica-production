<?php

$heartbeatQueues = array_values(array_filter(array_map(
    static fn ($queue) => trim($queue),
    explode(',', (string) env('QUEUE_HEARTBEAT_QUEUES', 'payments,default'))
)));

return [
    'sync_queue_allowed' => filter_var(env('QUEUE_SYNC_ALLOWED', false), FILTER_VALIDATE_BOOL),
    'queue_heartbeat_queues' => $heartbeatQueues,
    'queue_heartbeat_grace_seconds' => (int) env('QUEUE_HEARTBEAT_GRACE_SECONDS', 180),
    'queue_backlog_monitoring_enabled' => (bool) env('QUEUE_BACKLOG_MONITORING_ENABLED', true),
    'queue_backlog_limit' => (int) env('QUEUE_BACKLOG_LIMIT', 500),
    'queue_failed_window_minutes' => (int) env('QUEUE_FAILED_WINDOW_MINUTES', 60),
    'queue_failed_limit' => (int) env('QUEUE_FAILED_LIMIT', 10),
    'queue_health_observation_ttl_seconds' => (int) env('QUEUE_HEALTH_OBSERVATION_TTL_SECONDS', 10),
    'queue_monitor_alert_cooldown_seconds' => (int) env('QUEUE_MONITOR_ALERT_COOLDOWN_SECONDS', 900),
    'incident_email' => env('OPS_INCIDENT_EMAIL'),
    'incident_webhook_url' => env('OPS_INCIDENT_WEBHOOK_URL'),
    'incident_webhook_secret' => env('OPS_INCIDENT_WEBHOOK_SECRET'),
    'incident_alert_cooldown_seconds' => (int) env('OPS_INCIDENT_ALERT_COOLDOWN_SECONDS', 900),
    'incident_connect_timeout_seconds' => (int) env('OPS_INCIDENT_CONNECT_TIMEOUT_SECONDS', 2),
    'incident_timeout_seconds' => (int) env('OPS_INCIDENT_TIMEOUT_SECONDS', 5),
    'refund_recovery_alert_minutes' => max(10, (int) env('REFUND_RECOVERY_ALERT_MINUTES', 30)),
    'webhook_monitor_window_minutes' => (int) env('WEBHOOK_MONITOR_WINDOW_MINUTES', 15),
    'webhook_failure_limit' => (int) env('WEBHOOK_FAILURE_LIMIT', 5),
    'webhook_rejection_limit' => (int) env('WEBHOOK_REJECTION_LIMIT', 20),
    'webhook_stuck_minutes' => (int) env('WEBHOOK_STUCK_MINUTES', 5),
    'webhook_receipt_retention_days' => max(30, (int) env('WEBHOOK_RECEIPT_RETENTION_DAYS', 90)),
    'backup' => [
        // These values describe an externally operated backup contract. They do
        // not run backups from the web process; release readiness uses them to
        // fail closed when ownership or tested recovery evidence is absent.
        'owner_email' => env('BACKUP_OWNER_EMAIL'),
        'provider' => env('BACKUP_PROVIDER'),
        'database_enabled' => filter_var(env('BACKUP_DATABASE_ENABLED', false), FILTER_VALIDATE_BOOL),
        'private_files_enabled' => filter_var(env('BACKUP_PRIVATE_FILES_ENABLED', false), FILTER_VALIDATE_BOOL),
        'encryption_enabled' => filter_var(env('BACKUP_ENCRYPTION_ENABLED', false), FILTER_VALIDATE_BOOL),
        'offsite_enabled' => filter_var(env('BACKUP_OFFSITE_ENABLED', false), FILTER_VALIDATE_BOOL),
        'rpo_minutes' => (int) env('BACKUP_RPO_MINUTES', 0),
        'rto_minutes' => (int) env('BACKUP_RTO_MINUTES', 0),
        'restore_drill_at' => env('BACKUP_RESTORE_DRILL_AT'),
        'restore_drill_reference' => env('BACKUP_RESTORE_DRILL_REFERENCE'),
        'restore_drill_max_age_days' => max(1, (int) env('BACKUP_RESTORE_DRILL_MAX_AGE_DAYS', 90)),
    ],
    'monitoring' => [
        'owner_email' => env('MONITORING_OWNER_EMAIL'),
        'external_uptime_enabled' => filter_var(env('MONITORING_EXTERNAL_UPTIME_ENABLED', false), FILTER_VALIDATE_BOOL),
        'error_tracking_enabled' => filter_var(env('MONITORING_ERROR_TRACKING_ENABLED', false), FILTER_VALIDATE_BOOL),
        'synthetic_checkout_enabled' => filter_var(env('MONITORING_SYNTHETIC_CHECKOUT_ENABLED', false), FILTER_VALIDATE_BOOL),
        'liveness_url' => env('MONITORING_LIVENESS_URL'),
        'readiness_url' => env('MONITORING_READINESS_URL'),
        'exercise_at' => env('MONITORING_EXERCISE_AT'),
        'exercise_reference' => env('MONITORING_EXERCISE_REFERENCE'),
        'exercise_max_age_days' => max(1, (int) env('MONITORING_EXERCISE_MAX_AGE_DAYS', 90)),
    ],
];
