<?php

return [
    'consent_version' => env('EVENT_REGISTRATION_CONSENT_VERSION', 'registration-v1'),
    'max_attendees_per_submission' => max(1, min(500, (int) env('EVENT_REGISTRATION_MAX_ATTENDEES', 100))),
    'max_questions_per_form' => max(1, min(200, (int) env('EVENT_REGISTRATION_MAX_QUESTIONS', 100))),
    'retention_enabled' => filter_var(env('EVENT_REGISTRATION_RETENTION_ENABLED', false), FILTER_VALIDATE_BOOL),
    'retention_days' => max(30, (int) env('EVENT_REGISTRATION_RETENTION_DAYS', 730)),
    'import_disk' => env('EVENT_REGISTRATION_IMPORT_DISK', 'local'),
    'import_attestation_version' => env('EVENT_REGISTRATION_IMPORT_ATTESTATION_VERSION', 'registration-import-v1'),
    'import_retention_days' => max(1, min(90, (int) env('EVENT_REGISTRATION_IMPORT_RETENTION_DAYS', 7))),
    'import_max_file_kb' => max(64, min(10240, (int) env('EVENT_REGISTRATION_IMPORT_MAX_FILE_KB', 2048))),
    'import_max_rows' => max(1, min(50000, (int) env('EVENT_REGISTRATION_IMPORT_MAX_ROWS', 5000))),
    'import_max_columns' => max(6, min(500, (int) env('EVENT_REGISTRATION_IMPORT_MAX_COLUMNS', 250))),
    'upload_disk' => env('EVENT_REGISTRATION_UPLOAD_DISK', 'local'),
    'upload_max_file_kb' => max(64, min(10240, (int) env('EVENT_REGISTRATION_UPLOAD_MAX_FILE_KB', 5120))),
    'upload_max_files_per_submission' => max(1, min(100, (int) env('EVENT_REGISTRATION_UPLOAD_MAX_FILES', 20))),
    'upload_max_total_kb' => max(64, min(102400, (int) env('EVENT_REGISTRATION_UPLOAD_MAX_TOTAL_KB', 20480))),
    'allocation_attestation_version' => env('EVENT_REGISTRATION_ALLOCATION_ATTESTATION_VERSION', 'registration-allocation-v1'),
    'allocation_default_days' => max(1, min(365, (int) env('EVENT_REGISTRATION_ALLOCATION_DAYS', 30))),
    'allocation_max_capacity' => max(1, min(10000, (int) env('EVENT_REGISTRATION_ALLOCATION_MAX_CAPACITY', 1000))),
    'invoice_disk' => env('EVENT_REGISTRATION_INVOICE_DISK', env('EVENT_REGISTRATION_IMPORT_DISK', 'local')),
    'invoice_default_days' => max(1, min(90, (int) env('EVENT_REGISTRATION_INVOICE_DAYS', 14))),
];
