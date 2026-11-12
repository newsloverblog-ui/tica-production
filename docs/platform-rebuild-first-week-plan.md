# Tica rebuild — first-week execution plan

## Goal

Stabilise the existing platform, measure the real funnel, and produce enough evidence to sequence the Phase 1 rebuild without risking live payments or ticket inventory.

## Day 1 — release baseline and ownership

- Pin supported PHP/Node versions and capture a clean dependency lock/build in CI.
- Set a long random `TICKET_SIGNING_KEY` in every environment and establish its rotation/recovery policy before issuing signed production tickets.
- Create staging with anonymised production-like data and sandbox payment credentials.
- Name owners for payments, ticketing/check-in, organizer finance, frontend, infrastructure and QA.
- Turn `APP_DEBUG` off outside local development and confirm secret separation.
- Record current conversion, payment success, ticket delivery and scanner success baselines.

## Day 2 — production observability

- Configure error tracking and release markers.
- Add uptime checks for home, event details, checkout, organizer login and scanner login.
- Add synthetic checkout through the gateway sandbox.
- Monitor queue failures, email delivery, webhook age/error rate and payment-to-ticket latency.
- Define severity levels and alert recipients.

## Day 3 — payment and inventory proof

- Exercise Paystack duplicate, delayed and replayed webhook cases.
- Audit every enabled gateway against the same idempotency contract.
- Reconcile a day of sandbox charges, internal transactions, bookings and organizer earnings.
- Design the durable inventory reservation table and expiry worker.
- Inventory duplicate transaction references before adding a unique constraint.

## Day 4 — ticket/check-in migration

- Identify all future events with already-issued legacy QR tickets.
- Regenerate/resend signed tickets and notify organizers.
- Test two scanners concurrently against the same ticket.
- Specify attendee-level ticket records, transfer/reissue, revocation and offline sync.
- Run device/network testing on the actual venue hardware profile.

## Day 5 — decision review and Phase 1 backlog

- Review the gap analysis with product, finance, operations, support and engineering.
- Lock payment/payout/KYC/refund decisions.
- Choose corporate registration MVP boundaries.
- Convert accepted requirements into testable stories with owners and dependencies.
- Publish a six-week delivery plan and release gates.

## Engineering estimates (ranges, not commitments)

| Workstream | Estimate | Primary dependencies |
|---|---:|---|
| Phase 0 staging, monitoring, analytics dashboards and full QA | 2–3 engineer-weeks | Infrastructure access, analytics vendor, QA devices |
| Gateway-wide idempotency, reconciliation and refunds | 4–7 engineer-weeks | Gateway APIs, finance rules, migration data |
| Durable inventory reservations and recovery | 2–4 engineer-weeks | Checkout UX and queue infrastructure |
| Attendee-level signed tickets and online multi-scanner | 3–5 engineer-weeks | Ticket schema migration and organizer rollout |
| Offline scanner with sync/conflict handling | 4–7 engineer-weeks | Device support matrix and field testing |
| Payout ledger, KYC and statements | 5–9 engineer-weeks | KYC/payout vendors and compliance rules |
| Corporate registration MVP | 8–14 engineer-weeks | Product scope, form schema, privacy and badge requirements |
| Complete role-aware exports with background jobs/history | 3–5 engineer-weeks | Registration schema and queue/storage choices |

These ranges assume two experienced Laravel engineers, one frontend engineer, shared product/design, and dedicated QA. Parallel work shortens elapsed time but not total effort.

## Launch-blocking inputs unavailable in this workspace

- production and sandbox gateway credentials;
- current settlement/reconciliation exports;
- live error, uptime, email and queue telemetry;
- KYC/refund/payout policies and legal approval;
- production catalogue and issued-ticket migration list;
- staging/hosting/Redis/worker access;
- analytics account and consent decision;
- supported scanner device/network profile;
- named operational owners and on-call escalation path.

