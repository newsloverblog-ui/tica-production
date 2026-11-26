# Organizer payout release runbook

This runbook is the production gate for Paystack organizer transfers and the optional next-business-day automatic release worker. It does not authorize a payout policy. Finance and platform owners must approve the limits and settlement ownership outside the application and record a non-secret evidence reference.

## Owners and separation of duties

- Finance owns eligibility limits, settlement ownership, payout exceptions and the approval reference.
- Platform owns Paystack credentials, signed webhook delivery, workers, scheduler, monitoring and rollback.
- Support may observe payout status but must not release, reverse or mark a withdrawal paid.
- Manual payout release requires two distinct, active admins. The approver needs both Withdraw Method and Transaction permissions. The releasing admin needs Withdraw Method. Approval applies to the displayed amount, currency and verified beneficiary for `PAYOUT_APPROVAL_MINUTES` (default 30). The worker rechecks the terms and permissions before sending.
- The application marks a withdrawal paid only after a matching Paystack terminal success. A browser redirect, job dispatch or transfer submission is never payment evidence.

## Preconditions

1. Deploy all migrations, including payout approval metadata. Any queued manual payout from before the migration requires Finance review and fresh approval before it can be requeued. Reconcile any already-submitted transfer against its original reference.
2. Use production-like MySQL, shared cache, durable queues and the same network path as production.
3. Register the signed Paystack webhook and confirm the `payments` and `default` workers plus scheduler are healthy.
4. Configure a deliverable `PAYOUT_FINANCE_EMAIL` and an off-server incident destination.
5. Keep `PAYSTACK_TRANSFERS_ENABLED=false` while preparing the drill. Keep **Automatic payout release** off in **Admin → Payout Accounts**. The deployment default is `PAYOUT_AUTOMATIC_RELEASE_ENABLED=false`.
6. Finance approves the versioned policy values and records the decision in the change system. Do not put confidential discussion, bank details or credentials in the approval reference.

## Required provider drill

Use a verified test organizer and bank destination created through the normal KYC/account-resolution flow. Use bounded test amounts.

1. Temporarily enable Paystack transfers only. Leave automatic release disabled.
2. In the finance interface, approve the terms, sign in as a different authorized admin and queue the approved withdrawal. Verify self-approval, expired approval, revoked access and a replaced beneficiary prevent submission.
3. Confirm exactly one `payout_transfers` row and one provider transfer exist for the controlled reference.
4. Confirm the organizer receives the queued notice and finance receives exception notices at the configured address.
5. Deliver a valid signed success webhook. Confirm the reference, amount, currency and transfer code match before the withdrawal and transaction ledger become paid.
6. Replay the success webhook. Confirm no duplicate ledger mutation or duplicate terminal notice occurs.
7. Exercise a lost/timeout response. Confirm the transfer becomes `verification_required`, remains financially reserved and is recovered by `payouts:reconcile-paystack` rather than resubmitted with a new reference.
8. Exercise failed and reversed outcomes. Confirm the withdrawal moves to hold, reserved funds are not silently restored, finance is notified and manual review is required.
9. Exercise an amount or currency mismatch. Confirm the transfer becomes `needs_attention`, the withdrawal is not marked paid and the off-server incident alert fires.
10. Record provider screenshots/IDs, queue evidence, webhook receipts, ledger comparisons, notification evidence and incident evidence in the change record.

After the drill passes, record only its UTC completion time and non-secret reference:

```dotenv
PAYOUT_PRODUCTION_DRILL_AT=2026-11-03T12:00:00Z
PAYOUT_PRODUCTION_DRILL_REFERENCE=PAYOUT-DRILL-CHANGE-1234
```

## Eligibility dry run

Configure the approved policy while automatic release remains disabled. At minimum, review the policy version, approval reference, settlement owner, timezone, business days, holiday dates, cooling periods, successful-manual-payout requirement, single-transfer cap, daily organizer cap, batch limit and scan limit.

Run:

```bash
php artisan payouts:release-eligible --json
```

The command is dry-run-only without `--apply`. Finance must review every reason count and a sample of eligible/ineligible withdrawals. The candidate list must contain no unverified organizer, new/changed bank destination, unresolved dispute, held payout, indeterminate transfer or amount above the approved limits.

## Enablement

Set the approved policy evidence and enable provider transfers through deployment configuration.

```dotenv
PAYOUT_ELIGIBILITY_POLICY_APPROVED=true
PAYOUT_ELIGIBILITY_POLICY_APPROVAL_REFERENCE=FINANCE-RISK-CHANGE-1234
PAYOUT_SETTLEMENT_OWNER=platform
PAYSTACK_TRANSFERS_ENABLED=true
```

Then run the release gate before shifting traffic:

```bash
php artisan ops:release-readiness --configuration-only --json
```

After the checks pass, an authorised finance admin can turn on **Automatic payout release** under **Admin → Payout Accounts**. The saved setting takes precedence over the deployment default. The scheduler evaluates withdrawals daily at the configured local hour. It queues withdrawals whose next configured business day has arrived and rechecks eligibility under database locks before creating the transfer.

## Monitoring and daily reconciliation

- Review `payouts:reconcile-paystack --limit=100` results and all `verification_required` / `needs_attention` transfers. The worker is isolated and processes the oldest bounded batch first; tune `PAYOUT_RECONCILE_BATCH_LIMIT` only from staging throughput evidence and keep it within 1–500.
- Reconcile Paystack transfers to the withdrawal ledger and settlement account daily.
- Treat failed, reversed, mismatch and verification-exhaustion alerts as finance incidents.
- Never repair a mismatch by manually editing a transfer, withdrawal or transaction row.
- Update the holiday calendar before each non-working day; a missed holiday requires disabling automatic release until corrected.

## Emergency stop and rollback

1. Turn off **Automatic payout release** under **Admin → Payout Accounts**. New automatic releases and queued automatic transfers awaiting submission pause. Already-submitted transfers continue through reconciliation.
2. If provider behavior is unsafe, also set `PAYSTACK_TRANSFERS_ENABLED=false` and reload the payments workers. New queued transfers stay paused. Keep signed webhooks and reconciliation running for transfers already submitted. Reconcile interrupted submissions by their original reference.
3. Do not delete queued or processing transfer rows. Reconcile each controlled reference to a terminal provider state.
4. Place affected withdrawals on hold and follow the incident runbook. Do not restore organizer balances until finance has proven the provider did not pay.
5. Record the incident/change reference and require a fresh production-like drill before re-enablement.

## Evidence retained for release

- Finance policy approval reference and exact policy version.
- Settlement owner decision.
- Current Paystack drill date/reference.
- Dry-run JSON and reviewer sign-off.
- Release-readiness JSON with zero critical checks.
- Worker/scheduler health, signed webhook evidence and off-server alert evidence.
- Daily reconciliation and exception-resolution records.

