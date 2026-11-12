# Backup and recovery runbook

This document is the application-owned recovery contract. The infrastructure owner must attach provider-specific procedures and evidence; setting environment values without a successful restore does not satisfy this contract.

## Recovery objectives and ownership

- A named operator must own backup failures and restore decisions through `BACKUP_OWNER_EMAIL`.
- `BACKUP_PROVIDER` identifies the managed service or internal platform responsible for the copies; it must not contain credentials.
- `BACKUP_RPO_MINUTES` is the maximum acceptable transaction-data loss and must be between 1 and 1,440 minutes.
- `BACKUP_RTO_MINUTES` is the maximum time from a declared recovery event to a verified service restoration and must be between 1 and 1,440 minutes.
- Database and private-file backups must both be enabled, encrypted and stored off site. Public assets can be rebuilt from the immutable release and are not recovery evidence.
- Backup encryption keys must be stored separately from the backup copies and application runtime credentials. Access must be least-privilege and logged.

## Required backup scope

Every recovery point must preserve a consistent version of:

1. The primary database, including bookings, payments, refunds, payouts, tickets, audit logs, queue evidence and operational incidents.
2. Private event invoices and offline-payment evidence.
3. Private product receipts and invoices.
4. Registration and guest import sources that are still inside their approved retention windows.
5. Generated private exports that have not expired.
6. Deployment metadata needed to identify the application release and migration level compatible with the restored data.

Do not restore expired personal-data exports or import sources from an older backup back into live service without applying the current retention commands immediately after recovery.

## Daily verification

The assigned operator must verify, from the backup provider rather than the application host:

- the most recent database and private-file jobs succeeded;
- their timestamps satisfy the declared RPO;
- copies are encrypted and present in the off-site failure domain;
- the provider did not silently reduce retention or lose access to its encryption key;
- failure notifications reached the configured on-call destination.

A failed or late backup is an operational incident. Do not mark it resolved until a subsequent valid recovery point exists.

## Quarterly restore drill

Run the drill in an isolated, access-controlled environment with no outbound customer email, webhooks, transfers or payment execution.

1. Record the incident/change reference, participants, selected recovery point, expected RPO and RTO, and the immutable application release.
2. Provision an empty database and private object-store namespace. Never restore over production.
3. Restore the database and private files using provider-authenticated tooling. Keep secrets out of shell history, logs and screenshots.
4. Deploy the matching application release, run migrations only after confirming the restored schema version, and start workers with external side effects disabled.
5. Run `php artisan ops:release-readiness --configuration-only --json`. Backup evidence values may reference this drill, but all other critical checks must genuinely pass.
6. Verify representative records end-to-end: a paid booking and issued ticket, refund history, organizer settlement, corporate registration/invoice, audit history and each private document family.
7. Reconcile booking, ticket, transaction, refund and payout totals against the drill manifest. Confirm private documents are owner-scoped and reusable QR artifacts are absent from the public root.
8. Run privacy retention previews and record any restored data that would immediately expire.
9. Measure the recovery point and elapsed restoration time. The drill fails if measured loss exceeds RPO, elapsed time exceeds RTO, reconciliation differs, documents are missing, or any critical readiness check fails.
10. Destroy the isolated restore after evidence is approved according to the security retention policy.

After a successful drill, set `BACKUP_RESTORE_DRILL_AT` to its UTC ISO-8601 completion time and `BACKUP_RESTORE_DRILL_REFERENCE` to a non-secret ticket or evidence identifier. `ops:release-readiness` rejects missing, future-dated or older-than-policy evidence. The default maximum age is 90 days.

## Production recovery

1. Declare an incident, assign the recovery owner and freeze writes, payment dispatch, payout execution and queue retries.
2. Determine whether the failure is data corruption, regional loss or application regression. Prefer a forward repair when the authoritative external payment state is newer than the candidate backup.
3. Select the newest verified recovery point before the fault and record the expected data-loss window.
4. Restore into a new environment. Never overwrite the only remaining copy.
5. Reconcile gateway-side payment/refund/transfer outcomes created after the recovery point before accepting traffic. Use the existing reconciliation services; never replay ambiguous jobs blindly.
6. Require database integrity, private-file sampling, `ops:release-readiness`, queue health and the representative journey checks from the drill.
7. Shift traffic only with incident-commander approval. Keep payment and payout execution held until reconciliation is complete.
8. Document actual RPO/RTO, lost or reconstructed records, customer impact, remediation and the next drill date.

## Release gate configuration

Production must configure all values below. False or blank defaults are intentional and fail closed:

```dotenv
BACKUP_OWNER_EMAIL=platform-owner@example.com
BACKUP_PROVIDER=managed-backup-service
BACKUP_DATABASE_ENABLED=true
BACKUP_PRIVATE_FILES_ENABLED=true
BACKUP_ENCRYPTION_ENABLED=true
BACKUP_OFFSITE_ENABLED=true
BACKUP_RPO_MINUTES=60
BACKUP_RTO_MINUTES=240
BACKUP_RESTORE_DRILL_AT=2026-11-02T10:00:00Z
BACKUP_RESTORE_DRILL_REFERENCE=OPS-RESTORE-2026-11
BACKUP_RESTORE_DRILL_MAX_AGE_DAYS=90
```

These settings are attestations, not a backup implementation. Provider evidence and the measured drill remain authoritative.
