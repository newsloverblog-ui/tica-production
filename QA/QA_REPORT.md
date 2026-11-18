# TICA end-to-end QA report

**Audit date:** 18 November 2026  
**Source:** `main` at `a8b8059` plus the fixes documented below  
**Method:** isolated local database, fake Paystack responses, PHPUnit, TypeScript build, and Playwright at desktop and 390 px mobile widths  
**Production impact:** none; no production database, deployment, Paystack account, email, SMS, or storage service was contacted

## 1. Release summary

TICA boots, builds, migrates from its documented SQL baseline, and passes its automated PHP and browser suites in the isolated QA environment. Two payment defects were found and fixed:

1. payment fulfillment used MySQL-only advisory-lock SQL even though the live Supabase database is PostgreSQL; and
2. event-payment recovery did not explicitly compare the reference returned by Paystack with the application-owned intent reference.

Both paths now fail closed and have regression coverage. This report certifies the **local release candidate**, not a real Paystack transaction or the live deployment.

| Check | Result |
|---|---|
| Repository secret scan | ✅ No live-looking Paystack key found; distributable example leaves the secret empty |
| Route discovery | ✅ 860 application routes booted and inventoried |
| Schema setup | ✅ Documented baseline imported; all applicable Laravel migrations completed |
| Seed data | ✅ Admin, organizer, customer, event, ticket, booking, and 63 mail templates |
| PHPUnit | ✅ 1,047 tests; 12,014 assertions; 1 environment-dependent smoke skipped |
| Skipped DB smoke rerun | ✅ 1 test; 1 assertion against the disposable MySQL database |
| Focused payment regression suite | ✅ 20 tests; 53 assertions |
| Playwright | ✅ 218 desktop/mobile scenarios |
| TypeScript and frontend production build | ✅ Built both Laravel frontend entries |
| Locked Composer/npm production dependency audit | ✅ 0 known advisories |
| Application failures remaining | ✅ 0 reproduced in the controlled test environment |
| Real Paystack API/card/transfer | ⚠️ Not executed; see the manual checklist |

The first attempt to run `migrate:fresh` against an empty SQLite database stopped at the first legacy-table alteration. This repository intentionally uses `database/evento_v2.0.sql` as its baseline; importing that baseline and then running the migrations completed successfully. This is a deployment prerequisite, not a newly introduced migration regression.

## 2. System and route coverage

The complete per-route registry—including method, URI, route name, controller action, and middleware—and the complete controller/model/request/job/migration/form/Paystack inventories are in [SYSTEM_MAP.md](SYSTEM_MAP.md). It is the authoritative row-by-row checklist for all 860 routes.

### Route areas

| Area | Routes | Automated evidence | Status |
|---|---:|---|---|
| Admin | 447 | route boot, auth/permission tests, mutation-boundary tests, desktop/mobile browser sweep | ✅ Pass |
| Organizer | 145 | route boot, team-role matrix, ownership tests, desktop/mobile browser sweep | ✅ Pass |
| Customer | 44 | route boot, account/profile/privacy tests, browser authentication checks | ✅ Pass |
| TICA API | 49 | route boot, throttling/auth boundaries, checkout/login-code/wishlist tests | ✅ Pass |
| Event booking/payment | 31 | gateway policy, authoritative quote, reservations, recovery, callbacks, refunds | 🔧 Fixed |
| Product order/payment | 22 | cart/guest checkout, inventory, Paystack, fulfillment and refund tests | ✅ Pass |
| Scanner/check-in | 17 | device/staff scope, duplicate/conflict/offline/check-in ledger tests | ✅ Pass |
| Shop | 12 | cart mutation, pricing, session, guest checkout, production-disable policy | ✅ Pass |
| Public event/account/content | 68 | route boot, public-write boundaries, visibility, content policy, browser sweep | ✅ Pass |
| Other callbacks/system endpoints | 25 | production gateway allowlist, signature/token/throttle boundaries | ✅ Pass |

The browser sweep executed public, owner, manager, finance, gate, rich-organizer, and admin states at desktop and phone widths. It covered dashboard, events, orders, payouts, promote, team, settings, create/edit event, check-in, finance, KYC, support, reports, forms, registration, exports, integrations, coupons, organizer/customer management, payout operations, templates, promotions, and support pages. Dynamic destructive actions were covered by service/controller tests instead of mutating shared data in a browser.

### Feature matrix

| Feature | Automated checks | Status |
|---|---|---|
| Admin/organizer/customer login and logout | guard, presentation, session, browser role sweep | ✅ Pass |
| Registration and login codes | validation, duplicate suppression, throttling | ✅ Pass |
| Forgot/reset/change password | policy, reset service, mutation boundary, mail template | ✅ Pass |
| Email verification | customer/organizer delivery, expiry, idempotency | ✅ Pass |
| MFA | setup, authenticator verification, recovery codes, disable, replay protection | ✅ Pass |
| Profiles and avatars | ownership, validation, MIME checks, durable storage path | ✅ Pass |
| Roles and permissions | admin permission middleware, organizer team-role matrix, revocation | ✅ Pass |
| Event create/edit/publish | builder parity, persistence, browser publish flow, programme/speakers | ✅ Pass |
| Event visibility/cancel/delete | visibility rules, cancellation service, signed links, notifications | ✅ Pass |
| Tickets and tiers | free/paid/variation, quantity, advanced numbering, seats, lifecycle | ✅ Pass |
| Sale windows and purchase limits | server-owned selection, cumulative limit, reservation expiry | ✅ Pass |
| Event media/files | valid image, mismatch, SVG/script, size and private-document rules | ✅ Pass |
| Listings/search/categories/details | content, visibility, failure semantics and responsive browser pages | ✅ Pass |
| Coupons, fees and commissions | coupon, quote and fee calculator services; accounting ledgers | ✅ Pass |
| Paystack initialization | application-owned reference, Kobo conversion, metadata, safe checkout host | ✅ Pass |
| Paystack callback/recovery | server verification, signed URL, pending/failure/recovery state | 🔧 Fixed |
| Paystack webhook | raw-body HMAC SHA-512, invalid signature rejection, receipt evidence | ✅ Pass |
| Payment identity/tampering | reference, status, amount, currency and customer must all match | 🔧 Fixed |
| Payment idempotency | advisory lock, row lock and unique gateway/reference ledger | 🔧 Fixed |
| Failed/abandoned/reversed payment | state transition, reservation release, audit/alert | ✅ Pass |
| Ticket fulfillment | booking ledger, issued ticket units, queued email/SMS delivery | ✅ Pass |
| Sold-out/overselling | reservations, database constraints and purchase-limit tests | ✅ Pass |
| Refunds/disputes | controlled evidence, approval, provider recovery and reconciliation | ✅ Pass |
| Bank list/account resolution | fake Paystack client, validation, KYC/name decision state | ✅ Pass |
| Transfer recipients/payouts | fake recipient/transfer, eligibility, double-spend and balance guards | ✅ Pass |
| Transfer webhooks | success, failure, reversal, replay and balance restoration | ✅ Pass |
| Dashboards and analytics | admin/organizer metric services use ledger data and ownership scopes | ✅ Pass |
| Exports | CSV/PDF/private download, formula-injection protection, ownership | ✅ Pass |
| Check-in and QR | signed payload, staff/device/gate scope, duplicate and offline conflicts | ✅ Pass |
| Registration forms/imports | editor, allocation, file access, validation, idempotent imports | ✅ Pass |
| Email/SMS/notifications | template rendering, queue delivery, lifecycle dispatch and preferences | ✅ Pass |
| Scheduled tasks/jobs | scheduler inventory, overlap guards, bounded monitors/reconciliation | ✅ Pass |
| CSRF and public writes | standard web CSRF; only verified provider callbacks are excluded | ✅ Pass |
| IDOR/ownership | customer, organizer, event, ticket, document, export and payout boundaries | ✅ Pass |
| Secret exposure | repository hygiene, encrypted diagnostic/intent fields, safe logging | ✅ Pass |
| Rate limiting | login, MFA, checkout, callbacks, forms, scanner and system update | ✅ Pass |

## 3. Defects found and fixed

### FIX-01 — PostgreSQL payment fulfillment crash

**Severity:** Critical  
**Affected:** Paystack event fulfillment, shared verified-payment fulfillment, Flutterwave fulfillment  
**Root cause:** controllers executed MySQL-only `GET_LOCK` and `RELEASE_LOCK`; production uses Supabase PostgreSQL.

**Fix:** added `app/Services/DatabaseAdvisoryLock.php`. MySQL/MariaDB use named locks, PostgreSQL uses a deterministic BIGINT with `pg_try_advisory_lock`/`pg_advisory_unlock`, SQLite retains the unique-row/transaction test fallback, and unknown drivers fail closed. All three payment fulfillment paths now use the shared service.

**Tests:** `tests/Unit/DatabaseAdvisoryLockTest.php` proves the MySQL and PostgreSQL statements use safe bindings and the same stable PostgreSQL key; unknown drivers are rejected.

### FIX-02 — Paystack reference mismatch was not explicit

**Severity:** High  
**Affected:** event-payment webhook and browser recovery  
**Root cause:** verification checked status, amount, currency, and customer, but not the provider-returned reference against the durable local intent.

**Fix:** `PaystackPaymentVerifier` now returns `reference_mismatch`; both webhook and recovery supply the intent-owned reference. A mismatch marks the intent failed, records/alerts the incident, and never fulfills the booking.

**Tests:** `PaystackPaymentVerifierTest` adds the mismatch regression; the recovery and full payment suites remain green.

### FIX-03 — Artifact upload action used a deprecated runtime

**Severity:** Medium (future CI reliability)

**Affected:** Browser QA screenshots/report and cPanel package artifact uploads

**Root cause:** the workflows referenced `actions/upload-artifact@v4`, which still targeted the deprecated Node.js 20 action runtime. GitHub was temporarily forcing it onto Node.js 24 and emitted a deprecation warning.

**Fix:** upgraded all three artifact-upload steps to the official Node.js 24-compatible `v7.0.2` release, pinned to immutable commit `cf430e030ddbb5b0abf93d22962f4752f3646cd9`.

**Tests:** the CI supply-chain policy test passes; the pushed workflows provide the final integration check.

### FIX-04 — Event line-up existed in code but not in production storage

**Severity:** High (published event content was silently unavailable)

**Affected:** Admin and organizer event create/edit flows, public featured speakers/artist line-up, and event agenda.

**Root cause:** the production deployment contained the event programme UI, validation, edit prefill, public rendering, and tests, but Supabase did not contain `event_programme_people` or `event_agenda_items`. The service correctly avoided querying missing tables, so programme data could not persist or appear publicly.

**Fix:** applied only the two additive programme tables, their lookup/order indexes, and RLS to production Supabase. Renamed the fourth builder step from the generic “Media & settings” to “Line-up & media” so the optional feature is discoverable in both admin and organizer flows.

**Verification:** both production tables now exist with RLS enabled; the programme unit suite and organizer workspace suite pass (12 tests, 173 assertions), and the frontend production build succeeds.

## 4. Security review

| Control | Finding |
|---|---|
| Paystack credentials | No live-looking credential in the working tree. Runtime secrets remain server-side. |
| Webhook authentication | Paystack verifies `x-paystack-signature` with raw-body HMAC SHA-512 and `hash_equals`. |
| Webhook replay | Receipt tracking, advisory/row locks, durable intents, and a unique `(gateway, gateway_reference)` ledger prevent duplicate fulfillment. |
| Browser callback | A callback reference is never treated as proof of payment; the server calls verification and compares all authoritative fields. |
| Authorization | Separate guards, permission middleware, team permissions, scanner staff/device scope, and ownership queries are covered. |
| CSRF | Web forms use the web CSRF middleware. Only provider callback/webhook routes are excluded and independently authenticated. |
| Validation | Form requests/controller validation and upload content/MIME rules are present; checkout price and capacity are recomputed server-side. |
| SQL injection | Reviewed raw expressions use constants or parameter bindings; payment lock SQL is parameterized. |
| XSS | Rich content and support replies are purified before raw rendering; structured JSON uses hex-escaping flags. |
| Mass assignment | High-risk finance/account/event mutations use validated/allowlisted data or service-owned field sets. |
| Sensitive data | payment intent payload/email/errors and operational diagnostics use encrypted casts; logs avoid raw provider bodies/secrets. |
| Production gateway policy | Hardened Paystack/Flutterwave paths are allowlisted; legacy gateways require explicit non-production opt-in. |

No unresolved critical/high issue was reproduced after the fixes. Legacy gateway controllers remain in the repository, but production policy tests confirm they cannot be promoted accidentally. Remove them in a later cleanup if they are permanently retired.

## 5. Manual checks still required

Real provider and production checks were deliberately not performed during this controlled audit. Run these against a preview environment with **Paystack test keys only**:

1. Start the queue worker and scheduler, and confirm failed jobs are empty.
2. In Paystack test mode, set the webhook URL to `https://<preview-host>/paystack/webhook`.
3. Create a low-value paid test event and buy one ticket using Paystack's current documented test card.
4. Confirm the initialized amount is the expected Naira total multiplied by 100, the reference begins with the TICA prefix, and the browser returns to the signed completion/recovery URL.
5. Confirm exactly one payment intent, transaction, booking, ticket unit, confirmation email job, and SMS delivery record exist.
6. Replay the exact `charge.success` webhook. Confirm HTTP 200 and no duplicate ledger, booking, ticket, email, or SMS.
7. Send a correctly signed payload with the wrong amount, currency, customer email, and reference one at a time. Each must return a rejection/failure and issue no ticket.
8. Exercise failed and abandoned test payments and confirm reservations are released.
9. Resolve a test bank account, create a test transfer recipient, submit a minimum payout, and deliver `transfer.success`, `transfer.failed`, and `transfer.reversed`; verify status and balance exactly once for each replay.
10. Run one controlled refund and confirm initiated/completed email, booking/ticket state, and ledger reversal.
11. Check the Paystack dashboard, application webhook receipts, audit logs, reconciliation pages, queue monitor, and incident alerts agree on every reference.

Do not use a real card, real organizer balance, or production event for these checks.

## 6. Go-live recommendations

1. Run the full Supabase update script and verify its final health row before deployment.
2. Keep `DB_CONNECTION=pgsql` for Supabase and run the payment advisory-lock regression suite in CI.
3. Configure production Paystack keys only in the deployment secret store; never save them through source-controlled files.
4. Rotate any Paystack secret that ever appeared in a historical dump, even if it has since been removed.
5. Configure the production webhook as `https://tica-production.vercel.app/paystack/webhook` and verify test-mode evidence before switching the Paystack dashboard to live mode.
6. Run queue workers continuously and the Laravel scheduler every minute. Alert on stale intents, failed deliveries, webhook failures, payout reconciliation, and queue heartbeats.
7. Keep Supabase's generated REST roles away from application tables; Laravel is the intended data-access layer. Confirm RLS/API exposure remains closed.
8. Take and restore-test a production database backup before the schema update or release.
9. Run `php artisan ops:release-readiness` with production configuration and resolve every critical result.
10. After deployment, perform a read-only page smoke test for all three roles and a single controlled low-value live payment only after the account owner explicitly authorizes it.

## 7. Reproduction commands

Use isolated values; do not paste real credentials into terminal history.

```text
php artisan route:list --except-vendor --json
php artisan migrate --force
php artisan db:seed --force
vendor/bin/phpunit
npm run build
npx playwright test --reporter=line
```

The QA run used fake Paystack responses and a disposable local database. Exact credentials and connection strings are intentionally omitted.

## 8. Extended breakage sweep

The post-fix release candidate received an additional build-system and framework sweep on 18 November 2026:

| Check | Result |
|---|---|
| `composer validate --strict` | ✅ Valid dependency manifest and lock metadata |
| Strict authoritative Composer autoload | ✅ No PSR-4 or ambiguous-class failures; package discovery completed |
| First-party PHP syntax scan | ✅ All PHP files in `app`, `bootstrap`, `config`, `database`, `routes`, and `tests` passed |
| First-party browser-script syntax scan | ✅ Service worker, checkout, cart, scanner, PWA, event, withdrawal, and notification scripts passed |
| Laravel production optimization | ✅ Configuration, events, routes, and Blade views cached successfully, then caches were cleared |
| Scheduler discovery | ✅ All scheduled commands registered successfully |
| TypeScript and production frontend build | ✅ Both frontend entries built |
| Composer security and abandoned-package audit | ✅ No advisories or abandoned-package failure |
| npm security audit | ✅ 0 vulnerabilities |
| Full PHPUnit regression after the lock fix | ✅ 1,046 passed, 1 environment-dependent smoke skipped, 12,014 assertions |
| Existing remote `main` CI baseline | ✅ Platform verification, organiser settings, database script, cPanel package, and browser QA were green on base commit `a8b8059` |
| GitHub Actions runtime compatibility | 🔧 Artifact uploads upgraded from the deprecated Node.js 20 action runtime to pinned `upload-artifact` v7.0.2 |

No additional application defect was reproduced. One CI deprecation was fixed before it could become a release-pipeline failure. The payment and workflow-fix commits require their own CI run after push; the older green baseline above is supporting evidence only.
