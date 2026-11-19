# One email, one account type

TICA keeps customers (attendees) and organizers in separate tables, each with its own log-in (`customer` and `organizer` guards). Before this change, the same email could sign up on both sides, for example Google as an organizer and then Google as a customer. That created two unrelated accounts.

Now an email belongs to exactly one account type. Customers can become organizers only when an admin allows it.

## What changed

### The rule
- `App\Services\AccountTypeGuard` is the single check. Emails are trimmed and compared without case. Gmail dots and `+aliases` are not folded, because the rest of TICA treats them as different addresses.
- `App\Rules\AccountTypeEmail` wraps the check as a validation rule.
- A customer upgraded to an organizer keeps its row, but no longer counts as a customer. Its email counts as the organizer's.

### Where the rule is enforced

| Path | Behaviour |
|---|---|
| TICA sign-up (`/tica-api/signup`), customer and organizer | Refused with the other-type message |
| TICA log-in (`/tica-api/login`) | A failed log-in says which side to log in on, with a "Log in as …" switch |
| Organizer Google (`SocialSignInController`) | Never creates an organizer for a customer email; redirects to `/login?role=att` with the message |
| Customer Google (`CustomerController::authenticationViaProvider`) | Never creates a customer for an organizer email; an upgraded customer is sent to the organizer log-in |
| Legacy customer register and username log-in | Rule added; an upgraded account shows the upgraded message |
| Legacy organizer register (`/organizer/signup`) | Rule added |
| Change email: customer profile, organizer profile (legacy), organizer settings (dashboard) | Rule added |
| Admin create and edit customer, admin create and edit organizer | Rule added |

- **Admin organizer edit bug:** this form checked the email against the `username` column. It now checks `email`.
- **API:** the only API route is the default `auth:api` `/user`, so there is nothing to change there.

### Database safeguard
The migration adds a unique email index to each table:
- **Postgres:** `LOWER(email)`
- **MySQL:** the column's case-insensitive collation

The index is skipped (with a log warning) if a table already holds duplicates. The cross-table rule lives in the service.

### Messages
- **Organizer sign-up with a customer email:** "This email is registered as a customer account. Please log in as a customer."
  - When upgrades are on, it adds: "You can upgrade to an organizer account from your customer dashboard."
- **Customer sign-up with an organizer email:** "This email belongs to an organizer account. Please log in as an organizer."
- **Customer log-in after an upgrade:** "Your account has been upgraded to an organizer account. Log in as an organizer with the same email."

The log-in page now accepts `?role=org` or `?role=att`. Messages show inside the form with a button to switch to the right log-in.

## New settings
Both live in **Admin > Platform settings > Organiser sign-up**, stored in `basic_settings`:

| Setting | Column | Default | What it does |
|---|---|---|---|
| Allow customers to become organizers | `customer_organizer_upgrade` | Off | Shows "Become an Organizer" in the customer dashboard. Off: no button, and the upgrade routes return 404. |
| Require admin approval for organizer upgrades | `customer_organizer_upgrade_approval` | On | On: requests wait for an admin. Off: the customer becomes an organizer straight away. |

Changes to either setting are written to the audit log.

## How the upgrade works
1. **The button.** The customer dashboard header shows **Become an Organizer** when upgrades are on and there is no pending request. While a request is waiting, it shows "Your organizer request is under review" instead.
2. **The form** (`/customer/become-organizer`):
   - Asks for organisation or brand name, full name and phone, with the same rules as organizer sign-up.
   - The customer confirms with their current password.
   - Google-only customers can choose **Confirm with Google** instead. The same Google account must come back, and the proof lasts 10 minutes.
3. **When approval is required:**
   - A pending `organizer_upgrade_requests` row is created.
   - The customer gets "Organizer upgrade request received".
   - The admin gets "New organizer upgrade request" by email, and the admin bell shows a new "Organizer upgrade requests" queue.
   - Admins decide in **Admin > Organizers > Upgrade requests**: approve, or reject with a reason that is emailed to the customer.
4. **On approval, or right away when approval is off**, one database transaction:
   - Creates the organizer with the same email, name, **password hash**, phone, Google ID (`provider`/`provider_id`) and the new organisation details.
   - Marks the customer as moved (`migrated_to_organizer_id`, `migrated_at`) and switches its log-in off (`status = 0`). This save is quiet, so no "account suspended" email goes out.
   - Marks the request approved and writes the audit log.
   - With no approval step, the person is logged straight into the organizer dashboard.
5. **History is kept.** Orders, tickets and the wishlist still point at the customer row, so nothing is copied or lost. Upgraded organizers get **More tools > My tickets** (`/organizer/my-tickets`), a read-only list of their past orders with links to open the tickets.
6. **Afterwards**, the email follows organizer rules everywhere.

The new organizer is created active, as the spec asks, even when "Approve each organiser" is on. The upgrade approval setting is the control for upgrades.

## New email templates
These are editable in Admin > Email templates, and the migration and Supabase SQL add them without overwriting existing copy:

| Template | Sent to | When |
|---|---|---|
| `organizer_upgrade_received` | Customer | Request received |
| `admin_organizer_upgrade_request` | Admin | New upgrade request |
| `organizer_upgrade_approved` | Customer | Approved (or instant upgrade) |
| `organizer_upgrade_rejected` | Customer | Rejected, with the reason |

## Existing duplicate accounts
- **Command:** `php artisan accounts:duplicates` (or `--json`) lists every email that has both a customer and an organizer. For each side it shows the created date, log-in method, orders and tickets, and events, balance and payouts. The command changes nothing.
- **Admin page:** **Admin > Organizers > Duplicate accounts** shows the same list. Each row needs a confirmation tick before anything happens:
  - **Keep as organizer:** links the customer's history to the organizer and switches the customer log-in off.
  - **Keep as customer:** allowed only if the organizer has no events, balance or payouts. The empty organizer is switched off and its email released. The original email is kept in the audit log; nothing is deleted.
  - **Leave as is:** recorded only.

**Duplicates found in the production database (Supabase, 19 Nov 2026):** none. It holds 1 customer and 1 organizer, with different emails. The two-account case in the bug report must have been cleaned up already, or made on another database. Run the command after deploying to confirm.

## Files changed
- **New:**
  - `app/Services/AccountTypeGuard.php`
  - `app/Rules/AccountTypeEmail.php`
  - `app/Services/OrganizerUpgradeService.php`
  - `app/Services/DuplicateAccountService.php`
  - `app/Models/OrganizerUpgradeRequest.php`
  - `app/Console/Commands/ListDuplicateAccounts.php`
  - `app/Http/Controllers/FrontEnd/OrganizerUpgradeController.php`
  - `app/Http/Controllers/BackEnd/Organizer/OrganizerUpgradeRequestController.php`
  - `app/Http/Controllers/BackEnd/Organizer/DuplicateAccountController.php`
  - `app/Http/Controllers/BackEnd/Organizer/OrganizerPastTicketsController.php`
  - `resources/views/auth/become-organizer.blade.php`
  - `resources/views/backend/end-user/organizer/upgrades/index.blade.php`
  - `resources/views/backend/end-user/organizer/duplicates/index.blade.php`
  - `resources/views/organizer/my-tickets.blade.php`
  - `database/migrations/2026_11_19_180000_add_account_type_controls.php`
  - `tests/Unit/AccountTypeSeparationTest.php`
- **Changed:**
  - `TicaApiController`, `SocialSignInController`, `CustomerController`
  - `BackEnd/Organizer/OrganizerController`, `TicaWorkspaceController`
  - `CustomerManagementController`, `OrganizerManagementController`, `TicaPlatformController`
  - `TicaUiPayload`, `AdminQueues`, `MailTemplateCatalog`
  - Routes: `routes/web.php`, `routes/admin.php`, `routes/organizer.php`
  - Views: the platform settings page, the admin organizer index, the organizer shell, the TICA account page
  - `tica.js`, `tica.css`
  - `database/supabase/tica_database_update.sql`
  - `tests/Unit/TransactionalMailTemplateTest.php`

## To go live
1. Merge the PR. Vercel deploys it.
2. Run `database/supabase/tica_database_update.sql` on Supabase. Section 14 adds the columns, table, indexes and templates.
3. Optional: turn on **Allow customers to become organizers** in Platform settings.
