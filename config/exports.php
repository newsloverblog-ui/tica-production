<?php

return [
    'disk' => env('DATA_EXPORT_DISK', 'local'),
    'retention_days' => (int) env('DATA_EXPORT_RETENTION_DAYS', 7),
    'download_link_minutes' => (int) env('DATA_EXPORT_LINK_MINUTES', 15),
    'max_rows' => (int) env('DATA_EXPORT_MAX_ROWS', 250000),
    'immediate_finance_rows' => (int) env('DATA_EXPORT_IMMEDIATE_FINANCE_ROWS', 10000),
];
