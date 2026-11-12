# Privacy and data-governance standard

This is the application data map and operational minimum for Tica. It supports review under Nigeria's data-protection requirements but is not a substitute for counsel or the controller's signed policies. Production readiness intentionally fails until accountable people record that external approval.

## Accountability and evidence

- `PRIVACY_OWNER_EMAIL` identifies the person responsible for requests, retention, processor review and breach coordination.
- `PRIVACY_POLICY_VERSION` must identify the public policy and consent language approved for launch.
- `PRIVACY_BREACH_PLAN_REFERENCE` points to the controlled incident procedure; it must contain no secret or personal data.
- `PRIVACY_VENDOR_DPA_REVIEW_AT` records the most recent review of processor contracts and data locations.
- `PRIVACY_LAUNCH_REVIEW_AT` and `PRIVACY_LAUNCH_REVIEW_REFERENCE` record external launch sign-off. Future-dated or older-than-policy evidence is rejected.
- A setting is an attestation only. The signed policy, processing agreements and review record remain authoritative.

## Application data map

| Data family | Typical subjects and fields | Purpose | Application controls | Retention owner |
|---|---|---|---|---|
| Accounts and identity | Customers, organizers and administrators; name, email, phone, password hash, verification and role state | Authentication, account support and fraud prevention | Hashed credentials, session rotation, role allowlist, status/ownership checks and audit logs | Privacy owner with security owner |
| Event bookings and tickets | Buyer/contact data, attendee identity, ticket token, event/seat/price and check-in state | Contract fulfilment, admission and support | Dedicated signed ticket payload, organizer/scanner authorization, private invoices, immutable financial references | Finance/legal policy |
| Payments, refunds and payouts | Gateway, reference, amount, status, bank destination metadata and reconciliation evidence | Payment execution, accounting, disputes and settlement | No card data, idempotent intents, one-way webhook fingerprints, redacted incidents and audited state changes | Finance/legal policy |
| Corporate registrations | Company/buyer details, attendee contact data, answers, approval, sessions, invoice and badge mapping | Conference registration, approval, invoicing and admission | Versioned consent snapshot, encrypted answers, private imports/exports/invoices, explicit export attestation and anonymization service | Corporate-registration policy |
| Guest invitations and waitlists | Invitee name/contact, claim status, source attestation and delivery state | Guest admission and capacity allocation | Private expiring imports, signed claim links, source attestation and anonymization service | Guest-contact policy |
| Organizer/KYC records | Identity and bank-verification evidence | Organizer verification and payout protection | Owner/admin scoping, verified destination requirement and audit logging | Legal/KYC policy and processor contract |
| Operational evidence | Audit events, request IDs, incidents, queue failures, delivery history and privacy-safe webhook receipts | Security, availability, investigation and financial integrity | Sensitive-value redaction, access permission, append-only history and scheduled receipt pruning | Security/operations policy |
| Analytics and consent | Consent choice and permitted usage events | Product measurement and communication preference | Consent package; vendor and event taxonomy require launch review | Privacy/product policy |
| Private documents and exports | Invoices, receipts, evidence, CSV/XLSX/PDF exports, customer personal-data archives and import sources | Authorized delivery, data-subject access and operational processing | Non-public disks, actor/owner scoped signed downloads, encrypted queued request metadata, expiry and pruning; cancellation case exports exclude customer contacts and free-text reasons/resolutions; check-in ledgers exclude attendee contact fields and conflict notes while retaining accountable gate/staff evidence | Data-family owner |

Do not collect payment-card data, passwords, secrets, government identifiers or unstructured sensitive information through event questions. Every new field, integration or analytics event must update this map before release.

## Retention and deletion

1. The privacy owner approves bounded retention for corporate registrations and guest contacts before enabling `EVENT_REGISTRATION_RETENTION_ENABLED` and `TICKET_GUEST_RETENTION_ENABLED`.
2. The configured durations must be 30–3,650 days. Export artifacts must expire within 1–30 days. Import sources have their own shorter operational windows.
3. Daily scheduled services anonymize eligible registration/guest records while preserving non-personal financial, capacity and audit history. Preview the commands before enabling apply mode.
4. Webhook receipt metadata is one-way and pruned on its configured schedule. Scanner offline data, exports and import sources have separate scheduled expiry.
5. Legal holds must be documented, narrowly scoped and time-bound; do not disable platform-wide retention silently.
6. Restored backups must immediately re-run retention previews so expired personal data is not reintroduced into live processing.

## Data-subject and customer requests

1. Authenticate the requester and record the request ID, scope, received date and deadline outside ordinary support text.
2. Identify every relevant data family above. Organizer access never authorizes disclosure of another organizer's or buyer's records.
3. An active, verified customer can use **My Data** to request a private ZIP of UTF-8 CSV tables for their account, bookings, held tickets, product orders and customer-authored records. The request is rate-limited and audited; the short-lived signed download still requires the owning account. Use the controlled operational process for data families not covered by that archive. Never email a public file path or reusable signed URL.
4. Review financial, security and legal retention before deletion. Where erasure is allowed, use the existing anonymization/deactivation paths so referential and audit integrity remains intact.
5. Record the decision, approver, completion evidence and any lawful exception. Do not place exported personal data in incident logs or source control.

Authenticated customers initiate deletion through **My Data** after confirming their password and acknowledging retention exceptions. The request ledger is encrypted and auditable. Administrators must move a case under review before fulfilment, record the applied retention/anonymization decision and supply a non-secret evidence reference. Fulfilment deactivates the account; it does not itself erase financial, admission, fraud-prevention or audit history. Any approved field-level anonymization outside existing automated retention services must be performed under the controlled case and referenced in its evidence record.

## Processors and international transfers

The vendor register must cover hosting/database/object storage, cache/queue, email, payment gateways, analytics, monitoring and support tools. For each vendor record the controller/processor role, data categories, locations/subprocessors, transfer basis, retention/deletion capability, security commitments, breach notification terms and contract/DPA owner. Re-run the review when a vendor, region, data field or purpose changes.

## Privacy incident response

1. Preserve evidence and stop further disclosure without destroying financial/audit records.
2. Notify the incident commander and privacy owner through the off-server incident channel.
3. Establish affected data families, subjects, time range, processors and whether backup/export links were involved.
4. Rotate compromised credentials or links, contain the path and reconcile authoritative payment/ticket state.
5. The privacy owner and counsel determine notification duties and timing; engineers must not make that legal determination in logs or customer templates.
6. Record decisions, notifications, corrective actions and a follow-up review. Update this map and automated controls when the cause was a missing classification or retention rule.

## Launch checklist

- Public policy/consent copy is approved and versioned.
- Registration and guest retention dry-runs have been reviewed, then apply mode is enabled.
- Export/import/document disks are private and expiry schedules are active.
- Vendor register and DPAs cover every production processor and data location.
- Breach process has an owner, contact path and tabletop exercise.
- Access, deletion and export request handling has been exercised with synthetic data.
- `php artisan ops:release-readiness --configuration-only` reports both privacy checks as `PASS`.

External policy and legal sign-off remain launch evidence; passing tests alone cannot prove them.
