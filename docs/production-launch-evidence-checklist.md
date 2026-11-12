# Production launch evidence checklist

This is the single release-control sheet for the gates enforced by
`php artisan ops:release-readiness`. It complements the detailed runbooks; it
does not replace the real provider, device, security, privacy or recovery
exercise. Evidence booleans and timestamps are attestations of completed work,
not switches that perform the work.

## Rules

- Assign one release coordinator and record the deployment revision.
- Keep secrets in the deployment secret manager. Evidence references must be
  non-secret ticket, report or change identifiers.
- Never set a `*_VERIFIED`, `*_ENABLED`, review date or drill date merely to
  clear the command.
- A critical or warning result is unresolved until the underlying exercise is
  complete and the command independently reports `pass`.
- Run the configuration preflight before migration and the full readiness gate
  only after both workers have consumed fresh heartbeats.

## Environment and infrastructure

| Gate | Required action and evidence | Owner / evidence |
|---|---|---|
| Database | Provision the production MySQL database, apply the authoritative baseline and migrations, reconcile the singleton earnings row and run the schema/data integrity checks. The application account must connect with least privilege. | Database owner, migration log, backup reference |
| Runtime | Set `APP_ENV=production`, `APP_DEBUG=false`, a canonical HTTPS `APP_URL` and a non-debug `LOG_LEVEL`. Keep web installer/updater disabled. | Release owner, deployment manifest |
| Stable keys | Generate and escrow `APP_KEY`, a separate `TICKET_SIGNING_KEY`, and a separate `TICKET_BUYER_IDENTITY_HASH_KEY`, each with the documented rotation/recovery plan. Never reuse one value for another purpose. | Security owner, key-vault/change references |
| Shared state | Use Redis or an approved shared equivalent for cache, sessions, locks and rate limits. Configure a durable asynchronous queue for `payments` and `default`. | Infrastructure owner, topology reference |
| Workers and scheduler | Deploy both queue workers and one scheduler, consume at least two heartbeat cycles, then verify backlog and failed-job thresholds. Run the bounded delivery monitor repeatedly until every batch reports clear; retain its final summary instead of increasing the enforced per-family ceiling or hiding unresolved rows. | Operations owner, supervisor/orchestrator evidence and final monitor summary |
| Private storage | Confirm product receipts/invoices, booking evidence/invoices, registration imports/uploads/invoices, guest imports and exports use private storage with backup coverage. Retain successful validating dry-run evidence for both checkout-document migrations, including zero missing, unreadable, disguised, active-content, oversized or MIME/extension/dimension-mismatched sources. Apply both migrations, reconcile database references to the validated private objects and prove a forced record failure leaves no orphan private copy. Verify backup coverage, dry-run and approve `documents:purge-legacy-public`, then prove both stable readiness checks report zero database references and zero physical files in the legacy public directories. | Storage owner, validation/migration/purge logs, object reconciliation, failure-cleanup evidence, access-policy and backup references |
| Mail | Configure the production mail transport and verified `MAIL_FROM_ADDRESS`; prove one queued delivery through a real worker. | Messaging owner, provider message reference |
| Incident destination | Configure `OPS_INCIDENT_EMAIL` or an HMAC-signed HTTPS webhook and exercise an alert that leaves the application host. | On-call owner, received-alert reference |

## Transaction and messaging drills

| Gate | Required action and evidence | Owner / evidence |
|---|---|---|
| Payment contract | For every enabled hardened provider, run event checkout, signed webhook and replay, lost-callback recovery, reservation expiry, terminal refund and reconciliation. Reconciliation evidence must come from a successfully completed, non-truncated provider scan; exercise multi-page input and prove the 1,000-page safety ceiling creates a failed run with no false local-only classification. Never use partial entries or counters from a failed run for finance close. Paystack also requires the shop path. Record `PAYMENT_OPERATIONS_OWNER_EMAIL`, `PAYMENT_PRODUCTION_DRILL_AT`, `PAYMENT_PRODUCTION_DRILL_REFERENCE` and only the verified scenario flags. | Payments owner, provider/database/ledger evidence |
| Legacy gateways | Keep `ALLOW_LEGACY_NON_PRODUCTION_GATEWAYS=false`. Do not enable a gateway outside `PRODUCTION_READY_PAYMENT_GATEWAYS` until its durable contract is implemented and certified. | Payments owner, gateway allowlist review |
| Ticket SMS | Configure the approved Termii sender and secret. Prove local and international delivery, provider rejection, ambiguous timeout handling, exact signed-link access and payments-worker SLA before setting the SMS drill fields. | SMS owner, Termii case/message references |
| Payouts | Keep automatic release off unless the eligibility policy is approved and the production payout/reconciliation drill has passed. Confirm finance alerts and exact transfer-reference replay behavior. | Finance owner, approval and drill references |

## Governance and assurance

| Gate | Required action and evidence | Owner / evidence |
|---|---|---|
| Audit retention | Approve 365–3650 days, dry-run `audit:apply-retention`, review the sample, then enable `AUDIT_LOG_RETENTION_ENABLED`. | Compliance owner, policy/version reference |
| Registration/guest retention | Approve both bounded periods, dry-run each retention command, review affected records and only then enable the two scheduled controls. | Privacy owner, policy and dry-run references |
| Privacy launch | Record the privacy owner, policy version, breach plan, vendor-DPA review, launch review and non-secret sign-off reference. | Privacy owner, signed review references |
| Accessibility | Complete a current WCAG 2.2 AA audit. Retain automated results plus keyboard, screen-reader, checkout and scanner journey evidence before setting the four verification flags. | Accessibility owner, audit reference |
| CSP | Review production-like report-only violations, remove unjustified sources, test payment/social/embed journeys and then set `CSP_ENFORCE=true`. | Security owner, violation review reference |
| Penetration test | Test the documented authenticated and transactional scope, remediate every blocking finding and retest before recording the report reference/date. | Security owner, final report reference |
| Key rotation | Exercise provider/application credential rotation and the ticket-signing migration plan before recording the change reference/date. | Security owner, approved change reference |
| Incident drill | Exercise detection, off-server notification, triage, containment and recovery without placing secrets or customer data in evidence. | Security/on-call owners, drill reference |
| Browser push | Generate one deployment-managed VAPID key pair, use a valid contact subject and prove subscribe/send/unsubscribe. | Messaging owner, provider/browser evidence |

## Resilience and external monitoring

| Gate | Required action and evidence | Owner / evidence |
|---|---|---|
| Backup policy | Configure encrypted off-site database and every private-file family, name the provider/owner, and set realistic RPO/RTO values no greater than 24 hours. | Backup owner, provider policy reference |
| Restore drill | Restore database and private files into an isolated environment, verify representative financial/ticket/document records, measure RPO/RTO and record the current drill reference/date. | Backup and finance owners, restore report |
| External monitoring | Configure independent uptime, privacy-scrubbed error tracking and a financially safe synthetic checkout against the exact production `/health/live` and `/health/ready` URLs. | Monitoring owner, provider configuration reference |
| Monitoring exercise | Force a safe liveness/readiness failure, error capture and synthetic-checkout failure; prove the on-call destination received and resolved all three. | Monitoring/on-call owners, exercise reference |

## Final release sequence

1. Record a fresh database/private-file backup and the release revision.
2. Run locked dependency installation,
   `composer audit --locked --abandoned=fail`, `npm audit`, the production asset
   build and the complete test suite on the pinned runtime.
3. Run `php artisan ops:release-readiness --configuration-only` and resolve
   every critical result before migration or traffic movement.
4. Apply migrations once, compile config/routes/views and restart both workers.
5. Wait for at least two scheduler/heartbeat cycles.
6. Require `php artisan ops:release-readiness` to report no critical results.
7. Require external probes for `/health/live` and `/health/ready` to pass.
8. Complete one low-value production smoke transaction, prove booking/order,
   ticket/delivery and reconciliation state, and record finance approval.
9. Move traffic gradually and monitor queue, webhook, payment-intent, delivery,
   error and synthetic-checkout signals through the rollback window.

Release is not approved when evidence is missing, stale, contradictory or based
only on configuration. Preserve the readiness output and every referenced
external artifact with the release record.
