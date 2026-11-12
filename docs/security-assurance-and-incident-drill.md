# Security assurance and incident-drill standard

This standard turns launch security review into release evidence. Automated dependency audits and application tests are necessary but do not replace penetration testing, controlled key rotation or an exercised incident path.

## Release evidence

- `SECURITY_OWNER_EMAIL` identifies the accountable security decision owner.
- `SECURITY_PENETRATION_TEST_AT` and `SECURITY_PENETRATION_TEST_REFERENCE` identify a current independent or appropriately separated review after blocking findings are retested.
- `SECURITY_KEY_ROTATION_AT` and `SECURITY_KEY_ROTATION_REFERENCE` identify the launch rotation change. References must not contain keys.
- `SECURITY_INCIDENT_DRILL_AT` and `SECURITY_INCIDENT_DRILL_REFERENCE` identify an exercise that proved the off-server alert and response path.
- `CSP_ENFORCE=true` is required after staging reports are reviewed. The application now parses the literal strings `true` and `false` safely; do not rely on PHP truthiness for environment booleans.

Future-dated, missing or older-than-policy evidence fails production readiness.

## Penetration-test scope

Test production-like builds and roles, using synthetic data and sandbox gateways:

1. Account registration, verification, login, reset, session rotation, deactivation and organizer/admin/scanner guard separation.
2. Object-level authorization across bookings, tickets, private documents, exports, events, registrations, guests, refunds, withdrawals and operational records.
3. Checkout, payment callbacks/webhooks, retry/recovery, reservation expiry, refund and payout state transitions, including replay and concurrency attempts.
4. Signed ticket, transfer, RSVP, waitlist, cancellation, embed and download URLs for expiry, replay, tampering and cross-tenant access.
5. Scanner enrollment, offline manifest/sync and manual lookup for credential theft, event/device substitution and duplicate admission races.
6. File upload/import/export handling for traversal, formula injection, oversized/decompression payloads, content-type confusion and public exposure.
7. CSRF, XSS, CSP, clickjacking, CORS, open redirects, SSRF, SQL injection, mass assignment, rate limiting and error/data leakage.
8. Installer/updater/vendor routes, debug endpoints, default credentials, headers, cookies, TLS and dependency/configuration exposure.

Critical/high findings affecting authentication, authorization, money, admission credentials, private data or remote execution block release. Record remediation and retest evidence in the referenced report.

## Key rotation

Inventory application, ticket signing, gateway webhook/API, incident webhook, database, Redis, mail, object-storage and deployment credentials. Rotate through the secret manager or provider control plane, never through source control or shell history.

`TICKET_SIGNING_KEY` is deliberately independent of `APP_KEY`. Rotating it invalidates ticket QR signatures, so first inventory future tickets, schedule regeneration/resend, coordinate gates and retain the old verification plan only for the approved transition window. Do not rotate both keys impulsively during an unresolved incident unless the compromise requires it.

For every rotation record owner, UTC time, systems, credential version (not value), deployment/restart actions, verification result, rollback/revocation and ticket migration outcome.

## Incident drill

At least every configured interval, exercise one finance-impacting and one availability/security scenario. Suggested launch exercise:

1. Trigger a synthetic critical queue or reconciliation condition without customer data or live money.
2. Confirm the application log and durable incident record are created, and the direct email or signed HTTPS webhook reaches the assigned operator.
3. Verify alert signature/timestamp/deduplication and acknowledge through the operational process.
4. Triage request IDs, queue/payment state and recent deploys without exposing secrets or raw personal data.
5. Exercise traffic removal through `/health/ready`, worker recovery, authoritative reconciliation and the rule against blind job replay.
6. Resolve the incident with a note, then trigger a new occurrence and verify it reopens.
7. Measure detection, acknowledgement, containment and recovery time; record failed contacts, unclear ownership and corrective actions.

The drill fails when the alert stays on-host, reaches nobody accountable, cannot be correlated, requires unaudited state edits, or recovery is declared before readiness and reconciliation pass.

## Continuous verification

- GitHub Actions validates the Composer lock, installs locked dependencies, runs `composer audit --locked`, runs `npm audit --audit-level=high`, builds assets, migrates a MySQL baseline, runs the suite with Redis and compiles caches.
- Dependabot/provider alerts and infrastructure/image scanning must be enabled in the hosting repository/platform; those external settings are not proven by this workspace.
- Re-run the penetration review after material authentication, payment, scanner, upload, authorization or infrastructure changes, even when the age window has not elapsed.
- Keep CSP violation monitoring during staging and after enforcement; do not weaken the policy globally to fix one integration.

## Release checklist

- Locked dependency audits are green on the exact release revision.
- CSP is enforced and staging violations are resolved.
- Penetration-test blocking findings are remediated and retested.
- Launch credentials are rotated, old credentials revoked and ticket-signing transition completed.
- Off-server incident delivery and the documented response path have been exercised.
- `php artisan ops:release-readiness --configuration-only` reports all four `security.*` checks as `PASS`.

Configuration is a release attestation. The reports, provider logs and drill record are the proof.
