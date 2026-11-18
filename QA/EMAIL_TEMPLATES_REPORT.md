# TICA Email Templates QA Report

Date: 2026-11-18

## Existing system followed

TICA stores editable email copy in `mail_templates` using the existing `mail_type`, `mail_subject`, and `mail_body` columns. Templates use the existing `{placeholder}` format. `MailTemplateService` loads the database copy first, safely replaces placeholders, and hands the rendered message to the existing encrypted `application_mail_deliveries` queue. `LifecycleMailService` now uses the same database templates for lifecycle messages instead of creating a parallel mail system.

The migration and seeder use insert-only behavior, so an administrator's existing edits are never overwritten. Each new template appears on the existing admin Email templates page, and its edit page lists only the placeholders relevant to that template.

All new mail dispatches are queued. Business operations catch and log mail-enqueue failures rather than failing payments, refunds, payouts, account changes, or support actions. Delivery dedupe keys protect scheduled messages and replayed webhooks. Bank account numbers are masked before rendering.

## Implemented templates

| Template | Trigger | Recipient | File where it is sent | Test status |
|---|---|---|---|---|
| `payment_failed` | Paystack `charge.failed`, provider rejection, or a payment intent becoming failed | Buyer email on the payment intent | `app/Http/Controllers/FrontEnd/PaymentGateway/PaystackController.php`; `app/Models/PaymentIntent.php` | Pass |
| `payment_pending_reminder` | Hourly scan of incomplete checkouts at least one hour old | Buyer email on the payment intent | `app/Console/Commands/DispatchLifecycleNotifications.php` | Pass |
| `refund_initiated` | Refund request is durably created | Booking customer | `app/Services/PaymentRefundService.php` | Pass |
| `refund_completed` | Verified refund financial effects are applied, including Paystack refund completion | Booking customer | `app/Services/PaymentRefundService.php`; `app/Services/LifecycleMailService.php` | Pass |
| `withdraw_request_received` | Organizer submits a withdrawal request | Organizer | `app/Services/WithdrawalService.php` | Pass |
| `payout_successful` | Paystack transfer reaches `success` | Organizer | `app/Services/PayoutTransferService.php`; `app/Jobs/SendPayoutStatusNotification.php`; `app/Mail/PayoutStatusMail.php` | Pass |
| `payout_failed` | Paystack transfer reaches `failed`, `reversed`, or definitive failure; reserved funds are returned | Organizer | `app/Services/PayoutTransferService.php`; `app/Jobs/SendPayoutStatusNotification.php`; `app/Mail/PayoutStatusMail.php` | Pass |
| `bank_account_changed` | Organizer saves or replaces the verified payout destination | Organizer | `app/Services/OrganizerPayoutAccountService.php` | Pass |
| `new_ticket_sale` | Paid/free booking fulfillment completes exactly once | Event organizer | `app/Http/Controllers/FrontEnd/Event/BookingController.php`; `app/Services/LifecycleMailService.php` | Pass |
| `event_updated` | Admin or organizer saves an existing event with changed public details | Paid/free attendees | `app/Services/EventLifecycleNotificationService.php` | Pass |
| `event_cancelled` | Event cancellation notices are released after the cancellation decision | Paid/free attendees | `app/Services/EventCancellationNotificationDispatchService.php`; `app/Jobs/SendEventCancellationNotice.php`; `app/Mail/EventCancellationNoticeMail.php` | Pass |
| `event_reminder` | Hourly scheduler finds an event starting in about 24 hours | Paid/free attendees | `app/Console/Commands/DispatchLifecycleNotifications.php` | Pass |
| `event_sold_out` | Hourly inventory scan finds zero remaining limited tickets | Event organizer | `app/Console/Commands/DispatchLifecycleNotifications.php` | Pass |
| `welcome_customer` | Customer verifies their email for the first time | Customer | `app/Services/CustomerEmailVerificationService.php` | Pass |
| `welcome_organizer` | Organizer verification email is queued after registration | Organizer | `app/Jobs/SendOrganizerEmailVerification.php`; `app/Services/LifecycleMailService.php` | Pass |
| `organizer_account_approved` | Organizer KYC is approved | Organizer | `app/Services/OrganizerKycService.php` | Pass |
| `organizer_account_rejected` | Organizer KYC is rejected | Organizer | `app/Services/OrganizerKycService.php` | Pass |
| `password_changed` | Password hash changes for an admin, organizer, or customer | Account owner | `app/Models/Admin.php`; `app/Models/Organizer.php`; `app/Models/Customer.php` | Pass |
| `account_suspended` | Organizer or customer status is changed to suspended | Account owner | `app/Models/Organizer.php`; `app/Models/Customer.php` | Pass |
| `admin_new_organizer` | New organizer account is created | Admin email from Basic Settings | `app/Models/Organizer.php` | Pass |
| `admin_new_withdrawal_request` | Organizer submits a withdrawal request | Admin email from Basic Settings | `app/Services/WithdrawalService.php` | Pass |
| `admin_payment_webhook_failure` | A verified Paystack webhook cannot be safely matched or fulfilled | Admin email from Basic Settings | `app/Http/Controllers/FrontEnd/PaymentGateway/PaystackController.php` | Pass |
| `support_ticket_created` | Customer or organizer creates a support ticket | Ticket owner | `app/Services/SupportTicketMailService.php`; front-end/organizer support controllers | Pass |
| `support_ticket_reply` | Admin replies to a support ticket | Ticket owner | `app/Services/SupportTicketMailService.php`; `app/Http/Controllers/BackEnd/SupportTicketController.php` | Pass |
| `support_ticket_closed` | Admin closes a support ticket | Ticket owner | `app/Services/SupportTicketMailService.php`; `app/Http/Controllers/BackEnd/SupportTicketController.php` | Pass |
| `admin_contact_form` | Public contact form is submitted | Admin email from Basic Settings | `app/Http/Controllers/FrontEnd/ContactController.php` | Pass |

## Requested templates intentionally skipped

These were not seeded because the requested underlying product workflow does not exist. Adding editable copy without a real, durable trigger would create misleading dead templates.

| Template | Reason skipped |
|---|---|
| `booking_cancelled` | TICA has event cancellation and refund workflows, but no customer booking-cancellation workflow with a durable cancellation transition. |
| `event_submitted_for_review` | Event creation currently publishes/toggles active state; there is no event moderation submission state. |
| `event_approved` | There is no event approval decision workflow. Organizer KYC approval exists and is covered separately. |
| `event_rejected` | There is no event rejection decision workflow. Organizer KYC rejection exists and is covered separately. |
| `admin_new_event_pending` | There is no pending-event moderation queue to notify the admin about. |
| `post_event_feedback` | No post-event feedback collection workflow or feedback URL exists. |
| `product_order_cancelled` | Product ordering exists, but there is no durable product-order cancellation workflow. |

Ticket transfers exist, but no ticket-transfer email was requested in the supplied template list; the existing encrypted ticket-transfer delivery pipeline was left unchanged.

## Verification

- `tests/Unit/TransactionalMailTemplateTest.php`: migration/seed inventory, no-overwrite behavior, placeholder replacement for every template, encrypted queue dispatch, dedupe, admin list/edit/save, bank masking, concrete trigger coverage, and scheduler registration — **Pass (5 tests, 118 assertions)**.
- Payment refund and payout domain suites — **Pass (40 tests, 233 assertions)**.
- Paystack checkout regression suite — **Pass (4 tests, 22 assertions)**.
- Complete Unit suite — **Pass (1,040 tests, 11,984 assertions)**.
- Complete repository suite — **Pass (1,042 tests, 12,000 assertions; 1 pre-existing database-dependent smoke test skipped)**.

