<?php

return [
    'legacy_public_qr_directory' => public_path('assets/admin/qrcodes'),
    /*
    |--------------------------------------------------------------------------
    | Ticket signing key
    |--------------------------------------------------------------------------
    |
    | Keep this value stable for the lifetime of issued tickets. Production
    | should set TICKET_SIGNING_KEY to a long, random secret independent of
    | APP_KEY so routine application-key rotation does not invalidate tickets.
    |
    */
    'signing_key' => env('TICKET_SIGNING_KEY', env('APP_KEY')),

    // Stable lookup key for privacy-preserving guest purchase-limit identity.
    // Do not rotate this implicitly with APP_KEY; use a controlled rehash when
    // rotation is required so historical guest purchases continue to match.
    'buyer_identity_hash_key' => env('TICKET_BUYER_IDENTITY_HASH_KEY', env('APP_KEY')),
    'buyer_identity_hash_version' => max(1, (int) env('TICKET_BUYER_IDENTITY_HASH_VERSION', 1)),

    /* Time held while the buyer completes checkout/payment. */
    'reservation_minutes' => (int) env('TICKET_RESERVATION_MINUTES', 15),

    /* A waitlist invitation is an opportunity to buy, never a stock hold. */
    'waitlist_notification_minutes' => max(5, (int) env('TICKET_WAITLIST_NOTIFICATION_MINUTES', 30)),
    'waitlist_dispatch_limit' => max(1, min(500, (int) env('TICKET_WAITLIST_DISPATCH_LIMIT', 200))),
    'waitlist_consent_version' => env('TICKET_WAITLIST_CONSENT_VERSION', '2026-11'),

    /* Organizer guest invitations expire and must be explicitly answered. */
    'guest_invitation_days' => max(1, min(90, (int) env('TICKET_GUEST_INVITATION_DAYS', 14))),
    'guest_invitation_attestation_version' => env('TICKET_GUEST_ATTESTATION_VERSION', 'guest-invite-v1'),
    'guest_import_disk' => env('TICKET_GUEST_IMPORT_DISK', 'local'),
    'guest_import_retention_days' => max(1, (int) env('TICKET_GUEST_IMPORT_RETENTION_DAYS', 7)),
    'guest_import_max_file_kb' => max(64, min(10240, (int) env('TICKET_GUEST_IMPORT_MAX_FILE_KB', 2048))),
    'guest_import_max_rows' => max(1, min(25000, (int) env('TICKET_GUEST_IMPORT_MAX_ROWS', 5000))),
    'guest_import_max_columns' => max(3, min(50, (int) env('TICKET_GUEST_IMPORT_MAX_COLUMNS', 10))),
    'guest_retention_days' => max(30, (int) env('TICKET_GUEST_RETENTION_DAYS', 730)),
    'guest_retention_enabled' => filter_var(env('TICKET_GUEST_RETENTION_ENABLED', false), FILTER_VALIDATE_BOOL),

    /* Offline scanner snapshots are deliberately short-lived and bounded. */
    'offline_manifest_hours' => max(1, (int) env('SCANNER_OFFLINE_MANIFEST_HOURS', 12)),
    'offline_sync_grace_hours' => max(1, (int) env('SCANNER_OFFLINE_SYNC_GRACE_HOURS', 24)),
    'offline_manifest_max_tickets' => max(100, (int) env('SCANNER_OFFLINE_MAX_TICKETS', 25000)),
    'offline_sync_batch_size' => max(1, min(500, (int) env('SCANNER_OFFLINE_SYNC_BATCH_SIZE', 250))),
    'offline_retention_days' => max(30, (int) env('SCANNER_OFFLINE_RETENTION_DAYS', 180)),
];

