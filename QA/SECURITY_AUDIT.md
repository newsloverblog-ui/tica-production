# TICA security audit

Audit date: 19 November 2026
Source revision: `ef200abf7e9d7e1d9419977900ab86ec0a69c30b`
Scope: Laravel application, Blade/React UI, routes, controllers, services, jobs, uploads, exports, webhooks, scheduler, configuration, CI and dependency manifests.

## Executive summary

The source already contains substantial security controls: separate guards, CSRF on browser mutations, signed URLs, scoped organiser/customer queries, role allow-lists, MFA, provider signature verification, webhook idempotency records, private KYC/registration/export storage, HTML purification, upload MIME checks, queued sensitive work and global response headers.

Three source-confirmed defects were found: a high-priority cross-tenant authorization defect in the legacy organiser event editor, public exposure of support-ticket attachments, and requester-controlled support workflow fields. Two further source-confirmed hardening gaps depend on production configuration: CSP enforcement and the optional remote database updater. No production probing was performed, and no production data or secrets were accessed.

| ID | Severity | Status | Finding |
|---|---|---|---|
| SEC-001 | High | Remediated in working tree; isolated runtime verification pending | Legacy multi-date event edit can update a date row owned by another event |
| SEC-002 | Medium | Remediated in working tree; production cleanup command pending execution | Support-ticket ZIP attachments are stored inside public web storage |
| SEC-003 | Low | Remediated in working tree | Customer/organiser support-ticket creation mass-assigns workflow fields |
| SEC-004 | Medium | Partially remediated; nonce migration remains | CSP defaults to report-only and permits inline/eval script execution |
| SEC-005 | High | Remediated in working tree | Stateless database updater becomes remotely callable when its form token is configured |

### Remediation applied after the audit

- Event-date updates now validate integer IDs, reject duplicates, lock rows and require every date to belong to the already-authorized event.
- Support attachments now use a private Supabase bucket (or private filesystem disk), UUID object paths, authenticated owner/admin downloads and no-store/nosniff response headers. Public support-file redirects and bundled public ZIPs were removed. Existing rows are migrated lazily on authorized download or in bulk with `php artisan support:secure-attachments` (use `--dry-run` first).
- Customer and organizer support requests and replies now map allowed fields explicitly; workflow state is server-owned.
- CSP enforcement now defaults on and `unsafe-eval` was removed from the global policy. Three identified Vue 2 DOM-template routes receive a narrowly scoped compatibility exception; the legacy UI still requires `unsafe-inline`. Replacing those exceptions with precompiled views and nonces/hashes remains a separate compatibility migration.
- The stateless `/system/update` routes, controller, token configuration and cPanel instructions were removed. Database updates now require the trusted CLI.
- Regression tests were added under `tests/Unit/SupportTicketAttachmentStorageTest.php` and `tests/Unit/SecurityRemediationBoundaryTest.php`. They have not been executed here because no compliant OS-level test sandbox is available.

## SEC-001 — Cross-tenant event date update

**Impact:** An authenticated organiser using the legacy edit endpoint can submit the ID of a date row belonging to another organiser's event. The controller first proves ownership of the main event, but later loads `EventDates` only by the supplied `date_ids[]` value and saves it without relating it back to that event. This can alter another event's schedule.

**Evidence:**

- The main event is scoped to the current organiser in `app/Http/Controllers/BackEnd/Organizer/EventController.php:405-407`.
- The subsequent date-row lookup is only `EventDates::where('id', ...)->first()` at `app/Http/Controllers/BackEnd/Organizer/EventController.php:474-483`.
- `date_ids[]` is a client-controlled hidden form value in `resources/views/organizer/event/edit.blade.php:250` and is not owner-scoped by `app/Http/Requests/Event/UpdateRequest.php`.

**Remediation:** Every supplied date now resolves through the already-authorized event ID inside a retrying transaction, locks the row and fails when the row belongs to another event. `date_ids` is validated as a distinct integer array. The added regression boundary test is pending execution in an isolated test environment.

## SEC-002 — Public support attachments

Customer and organiser ZIP attachments are written to `public/assets/admin/img/support-ticket/...` in:

- `app/Http/Controllers/FrontEnd/SupportTicketController.php:41-47,95-99`
- `app/Http/Controllers/BackEnd/Organizer/SupportTicketController.php:76-81,143-158`

Message viewing itself is owner-scoped, but a public file bypasses that authorization once its URL is known. The views render direct `asset()` links, the documented cPanel document root is `tica/public`, and the durable-storage branch creates a public bucket and returns public object URLs. Filenames use `uniqid()`, which is not an access-control mechanism.

**Remediation:** Attachments are stored under private UUID paths and served only through authenticated owner/admin routes with download and `nosniff` headers. The public redirect path is blocked, bundled public ZIPs were removed, and the bulk migration command fails non-zero when a legacy database reference cannot be resolved. Run `php artisan support:secure-attachments --dry-run`, then `php artisan support:secure-attachments`, in production after deployment.

## SEC-003 — Support workflow fields are mass-assignable

Both customer and organiser creation paths use `$request->all()` and then call `SupportTicket::create(...)` (`FrontEnd/SupportTicketController.php:38-49`, `BackEnd/Organizer/SupportTicketController.php:70-85`). The model's fillable list includes `status` and `last_message` (`app/Models/SupportTicket.php:11-20`). A requester can therefore supply workflow metadata that should be server-controlled.

**Remediation:** Customer and organizer create/reply actions now validate their inputs and build explicit payloads. Identity, user type, status and timestamps are assigned exclusively by the server.

## SEC-004 — CSP is not protective by default

The previous default was report-only and permitted both `'unsafe-inline'` and `'unsafe-eval'` plus broad HTTPS sources. The working tree now enforces CSP by default and removes `'unsafe-eval'` globally. Three identified Vue 2 DOM-template routes receive a route-scoped compatibility exception because they compile templates in the browser. The policy still permits inline scripts/styles and broad HTTPS sources, and it has no reporting destination.

**Remaining work:** Replace the three Vue runtime-compiler screens with precompiled views, move inline scripts/styles to nonce- or hash-based assets, narrow external hosts, verify the intended `frame-ancestors` allow-list, and configure CSP reporting.

## SEC-005 — Remote database updater exposure

`/system/update` was sessionless and CSRF-less. When enabled, it accepted a secret in a POST form field and executed database updates, while its unauthenticated GET path could perform status work before token proof.

**Remediation:** Both web routes, the controller and token configuration were removed. The cPanel workflow and deployment guide now require trusted CLI migration and verify that `/system/update` returns 404.

## Areas reviewed with no source-backed vulnerability confirmed

- Primary Paystack, Flutterwave, Monnify and Stripe webhooks verify provider signatures; payment acceptance converges on locked/idempotent services.
- Organiser team roles are allow-listed and removed team sessions are revoked.
- Event descriptions, support replies and CMS rich content use Purifier or escaping on the reviewed paths.
- KYC PDFs are checked for active PDF constructs and stored privately.
- Registration imports and guest-list imports are bounded, queued, private and delete their source artifacts.
- Most current CSV exports neutralize spreadsheet formulas. Legacy export classes should still be removed or routed through a single sanitizer.
- Popup URLs are restricted to HTTP(S), colours are regex-constrained, and text is escaped in Blade.
- Event gallery mutations currently scope images/events to the authenticated organiser.

## Deployment and package checks still required

These checks were intentionally not claimed as passed. The audit instructions prohibited unisolated target-controlled execution; Docker, Podman and Colima were unavailable; no disposable database or isolated storage root was established; and network access was unavailable for current advisory queries. Local dependencies existed, but local Node was `26.3.0` while the project declares `22.x`, and the local PHP Redis extension did not match CI. The missing safe, faithful execution boundary—not a blanket absence of dependencies—was the blocker.

1. Run the Laravel suite in an ephemeral container with a disposable database and no production environment variables.
2. Run cross-tenant tests for events, tickets, forms, support files, payouts, promotions, scanner resources and exports.
3. Run `composer audit` and `npm audit --audit-level=high` with current advisory data.
4. Verify production values: `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, HTTPS-only `APP_URL`, CSP enforced, HSTS present, no `DB_UPDATE_TOKEN`, no installer routes/files, queue worker active and private disks non-public.
5. Verify only required payment callback routes remain CSRF-exempt and that each enabled route authenticates the provider independently.
6. Confirm Vercel/cPanel logs redact authorization headers, webhook signatures, bank details, document paths and payment secrets.

## Release follow-up order

1. Run the isolated Laravel and dependency-audit checks above; attach the results to this report.
2. Deploy the private support storage configuration, then run `support:secure-attachments --dry-run` and the real cleanup command.
3. Verify owner, cross-owner and anonymous support attachment access in the deployed environment.
4. Finish the CSP nonce/precompile migration and remove the three route-scoped Vue compiler exceptions.
