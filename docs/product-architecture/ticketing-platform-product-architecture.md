# TICA UI Test Dashboard Product Architecture

## Purpose and recommendation

This specification defines the redesign and technical architecture for the TICA UI Test admin, organizer, and user dashboards. It keeps the visual language established in the supplied TICA UI Test interfaces, but replaces duplicated navigation, vanity metrics, ambiguous ownership, and incomplete workflows with a responsive role-based dashboard system. Public discovery, checkout, and ticket flows are included only where they create, update, or explain dashboard state.

The current Laravel application already contains most of the difficult platform capabilities: payments, payment intents, ticket reservations, ticket units, transfers, refunds, disputes, organizer KYC, payout accounts, withdrawals, reconciliation, scanner devices, offline check-in, audit logs, exports, cancellations, registrations, waitlists, and operational incidents. The recommended rebuild should therefore preserve the existing domain services and database integrity while replacing the presentation layer incrementally with Laravel, Inertia, and React.

The primary product decisions are:

1. Use one user identity and one organizer workspace. A user may become an organizer without creating a second account experience.
2. Keep guest checkout for conversion, but require a verified email or one-time login code to manage tickets after purchase.
3. Treat an event as an explicit state machine rather than a status toggle.
4. Treat money as an auditable ledger. Dashboard totals must come from payment, refund, fee, and payout records, not editable balance fields.
5. Let admins govern risk and platform policy. Organizers own their events, inventory, attendees, promotion, and payout requests.
6. Replace the current very large admin menu with task-based groups and contextual settings pages.

### Assumptions and evidence

- The attached designs are the visual source of truth for the public header, admin overview, organizer overview and sidebar, user account overview, next-event ticket, compact statistics, quick actions, and recommendation cards.
- The current Laravel routes, models, policies, services, and database relationships are the functional source of truth. The route inventory currently exposes 413 admin, 138 organizer, 48 customer, and 186 public routes; this confirms that consolidation is more important than inventing another parallel product.
- NGN is the MVP currency. Paystack and Flutterwave are the primary gateways. Currency fields remain explicit so multi-currency support can be added later.
- Commission percentages, payout delays, refund windows, KYC document types, and organizer trust thresholds are configurable policy, not hard-coded UI decisions.
- Mobile is the default interaction target. Desktop layouts may add columns, but they must preserve the same priority and actions.

## Step 1 Design audit and system

### Design issues

| Screen | Issue | Severity | Fix |
|---|---|---|---|
| Public header | The bright orange Create an event button dominates every page, including pages where buying or managing a ticket is the primary task. | Medium | Use calm brand blue for primary actions. Retain orange only for small status or identity accents. Hide or demote the organizer CTA on narrow buyer journeys. |
| Public pages | Primary buttons, search submit controls, and legacy buttons use different colors, radii, shadows, and hover motion. | High | Use one PrimaryButton component with blue fill, 44–48 pixel minimum height, 10–12 pixel radius, white text, consistent focus ring, disabled state, and no decorative shine. |
| Organizer sidebar | The dark rail visually separates the workspace from its content and conflicts with the earlier light organizer design. | Medium | Restore the light sidebar, one-pixel divider, soft-blue active item, muted icons, and fixed Create event action. Use the dark navy rail only where the original design explicitly requires it. |
| Organizer sidebar | Top identity block, navigation, logout, and Create event compete for vertical space; on shorter screens the action can fall below the fold. | High | Make the rail scrollable, keep identity compact, place Logout in the footer, and keep Create event sticky above the safe-area inset. |
| Organizer overview | Metric cards repeat similar finance totals and consume too much vertical space. | Medium | Use four compact priority tiles, then one finance summary and one sales chart. Remove duplicated monthly account cards. |
| Organizer overview | Chart and adjacent cards stretch to equal heights even when one has little content. | Medium | Let cards size to content. Use explicit chart height and independent grid rows; never stretch empty states to match another column. |
| User overview | The next-event panel stretches across the workspace and reads like a banner rather than a ticket. | High | Use a bounded ticket component with a 16:10 poster stub, perforation treatment, event information, and actions. Cap content width and leave intentional whitespace outside the ticket. |
| User overview | Four large statistic cards overstate low-information counts. | Medium | Replace with compact 80–96 pixel summary tiles. Make Upcoming tickets primary and Orders a navigation item rather than a vanity KPI. |
| User recommendations | Poster cards are oversized, leaving a large unused right side on wide screens. | High | Use a responsive 2–4 column grid with bounded card width and consistent 4:5 media. On mobile, use a horizontal snap rail or one-column list. |
| Admin overview | Gross buyer payments are labelled Total revenue, which misstates platform revenue. | High | Rename to Gross ticket sales and add Platform fees earned, Refunds, and Net organizer payable from the ledger. |
| Admin overview | People on TICA donut uses valuable space without supporting an operational decision. | Medium | Replace with the review queue or organizer approvals trend. |
| Admin overview | Waiting for you chips lack visible severity, age, ownership, and SLA. | High | Use a review queue table or list with type, subject, amount where relevant, risk, age, assignee, and next action. |
| All dashboards | Some icons use filled glyphs, others outlines, and stroke weights vary. | Low | Standardize on one 20 pixel outline icon set at 1.75–2 pixel stroke. Reserve filled icons for selected state only. |
| All dashboards | Status is sometimes expressed only through color dots. | High | Pair every color with a text label and, where useful, an icon. Provide accessible names and minimum 4.5 to 1 text contrast. |
| Tables | Wide action columns and unbounded text will overflow on phones. | High | On mobile, convert rows to summary cards or reveal secondary columns in a details drawer. Keep the primary row action visible and use an overflow menu for secondary actions. |
| Forms | Required fields, helper text, error placement, and button wording are inconsistent across legacy screens. | High | Use shared Field, Select, FileUpload, RadioGroup, ErrorMessage, and FormActions components. Errors appear under the field; Save draft and Continue remain distinct. |
| Checkout | Gateway redirect, pending verification, and retry states are not visually distinguished. | High | Provide Processing, Verification pending, Paid, Failed, and Expired screens with one safe next action and persistent order reference. |
| Empty and loading states | Several panels reserve full card height even with no content, and some lists have no loading skeleton. | Medium | Use compact empty states that explain the next action, stable skeleton geometry, inline retry, and no full-height blank panels. |
| Responsive navigation | Desktop sidebars cannot simply shrink on mobile without hiding actions or trapping focus. | High | Replace sidebars with an accessible sheet, preserve current-page context, keep the primary action in the sheet header, and return focus when closed. |

### Design system

| Token group | Tokens and rules |
|---|---|
| Color | Navy 900 `#0B2447` for primary text and dark surfaces; Brand Blue 600 `#1F5FCC` for primary actions; Brand Blue 700 `#174DA8` for hover; Blue 50 `#EEF3FD` for selected and informational surfaces; Page `#FFFFFF`; Canvas `#F5F7FA`; Border `#E6EAF0`; Text `#111827`; Muted `#6B7280`; Orange `#F97316` only for small accents and warnings; Success `#067647`; Warning `#93370D`; Danger `#B42318`. |
| Typography | System UI stack. Display 36 or 32 at 700; page title 28 or 24 at 700; section title 20 at 650; card title 16 at 650; body 15 at 400; small 13 at 400; label 13 at 600. Minimum mobile body size is 15 pixels. |
| Spacing | Four-pixel base: 4, 8, 12, 16, 20, 24, 32, 40, 48, 64. Controls use 12–16 horizontal padding; cards use 16 mobile and 20–24 desktop; dashboard gaps use 12 mobile and 16–20 desktop. |
| Shape and elevation | Inputs and buttons use 10 pixel radius; cards 14–16; pills only for statuses, filters, and compact tags. Use border plus `0 1px 2px rgba(17,24,39,.04)` for cards. Avoid heavy shadows and decorative gradients on controls. |
| Interaction | Minimum touch target 44 by 44 pixels. Focus ring is 3 pixels with Blue 600 at 18 percent opacity. Hover never communicates a required state. Disabled controls keep readable labels, reduce contrast, and expose a reason where blocking is not obvious. |

### Reusable components

- AppShell variants: Public, User, Organizer, and Admin; desktop rail becomes a mobile sheet.
- Button variants: Primary, Secondary, Quiet, Destructive, and Icon; states default, hover, focus, loading, disabled, and success confirmation.
- Card variants: MetricTile, ActionCard, TicketCard, EventCard, QueueCard, EmptyState, and ChartCard.
- Data components: ResponsiveTable, StatusBadge, Money, DateTime, Pagination, FilterBar, SearchField, and OverflowMenu.
- Form components: Field, TextArea, Select, RadioGroup, Checkbox, FileUpload, CurrencyInput, DateTimePicker, FormErrorSummary, and FormActions.
- Feedback components: Skeleton, InlineError, Toast, ConfirmationDialog, ProgressStep, PendingBanner, and PermissionDenied.
- Ticketing components: TicketTypeSelector, OrderSummary, PaymentStatus, QRPass, TransferStatus, RefundEligibility, CheckInResult, and InventoryMeter.
- Every component must ship with empty, loading, error, disabled, and permission-restricted behavior where applicable.

## Product roles and ownership

### Admin

The admin operates the marketplace. Admins approve organizers, moderate events when required, manage payment and fee policy, resolve refunds and disputes, supervise payouts and reconciliation, manage taxonomy and homepage curation, and investigate operational incidents. Admins should not create or edit organizer events as a routine workflow. Impersonation is support-only, explicitly audited, time-limited, and visibly indicated.

### Organizer

The organizer owns event setup, ticket inventory, event content, sales controls, attendees, check-in, team access, promotions, and payout requests for their own events. An organizer cannot approve their own KYC, mark a payout paid, or override payment truth.

### User

The user discovers events, buys or claims tickets, receives and manages ticket units, saves events, requests eligible refunds, transfers tickets where allowed, and manages profile and communication preferences. A guest buyer receives the same durable order and ticket records as a registered user.

## Step 2 Screen and feature audit

The audit uses the current admin dashboard, organizer workspace, user account dashboard, public discovery and checkout surfaces, plus the implemented Laravel routes and services.

| Screen | Element | Verdict | Reason | Action |
|---|---|---|---|---|
| Public homepage | Search and discovery entry | KEEP | It is the primary marketplace job and matches user intent. | Keep location, date, category, format, and keyword search. Preserve the current visual style. |
| Public homepage | Featured events | KEEP | Homepage curation is commercially useful. | Source only from published, public, currently bookable events. Admin controls feature state; organizer may submit for consideration. |
| Public homepage | Multiple repeated event rails | FIX | Too many similar rails create choice fatigue and dilute priority. | Limit to Featured, Near you or Popular, and Browse by category. Personalize only when enough behavioral data exists. |
| Event card | Poster, date, venue, price, availability | KEEP | These are the minimum facts needed to decide whether to open an event. | Add consistent sold-out, cancelled, online, free, and sales-ending states. |
| Event card | Countdown on every card | REMOVE | Constant countdowns create noise and imply urgency even when it is not useful. | Show only for sales ending soon or events starting within 72 hours. |
| Event page | Event details, organizer, date, venue, ticket selector | KEEP | This is the core conversion page. | Keep ticket selection above the fold on desktop and sticky on mobile. Show refund policy before checkout. |
| Event page | Organizer management controls | MOVE | Editing or sales controls do not belong on a public attendee page. | Move to organizer event workspace. Public preview remains read-only. |
| Checkout | Ticket selection, buyer details, payment | FIX | The current system supports many gateways and legacy paths, which can confuse the primary Nigerian flow. | Use Paystack and Flutterwave as primary NGN gateways. Keep one server-owned payment intent and one return flow. |
| Checkout | Mandatory account creation | REMOVE | It increases abandonment and is not required for durable ticket ownership. | Allow guest checkout. Verify email with a magic code and offer account activation after payment. |
| User dashboard | Next event | KEEP | It is the highest-value user card. | Keep the ticket visual, but show only the nearest upcoming event and direct actions: open ticket, calendar, directions or join link. |
| User dashboard | Upcoming tickets, attended, saved, orders cards | FIX | The information is useful, but large cards waste space and most values do not require separate navigation. | Use compact summary tiles. Upcoming tickets is primary; attended and saved are secondary. Orders may be a direct navigation item instead of a KPI. |
| User dashboard | Coming up | KEEP | It gives a scannable list beyond the single next-event ticket. | Show up to three events. Do not stretch an empty list card to match another column. |
| User dashboard | Quick actions | FIX | Some actions duplicate the sidebar and header. | Keep only context-sensitive actions: download tickets, transfer eligible ticket, request refund, and get help. Remove Discover events when a persistent discovery CTA already exists. |
| User dashboard | Events you may like | KEEP | It supports retention and repeat purchase. | Use compact cards, a bounded row, and clear recommendation logic. Do not render oversized poster cards. |
| User navigation | Overview, My tickets, Orders, Saved, Profile, Help | KEEP | The structure is understandable and role-correct. | Rename Overview to Home if preferred. Move Help to a footer utility if the sidebar needs to be leaner. |
| Organizer dashboard | Setup checklist | KEEP | KYC, publishing, and payout readiness are critical blockers. | Make it state-aware and dismiss it only after all required steps pass. Never represent KYC as complete from client state. |
| Organizer dashboard | Gross sales, tickets sold, page views, available to withdraw | KEEP | These metrics drive decisions. | Add selected period, currency, refunds, and data freshness. Available balance must come from the finance ledger. |
| Organizer dashboard | Monthly account summary duplicated below | FIX | The lower account tiles repeat information from the top KPIs. | Replace with one finance summary: gross sales, refunds, fees, net earnings, pending payout, available balance. |
| Organizer dashboard | Ticket-sales chart | KEEP | It reveals sales momentum. | Allow Sales and Tickets toggle, period selection, and event filter. Show an actionable empty state. |
| Organizer dashboard | Next up | KEEP | It supports operational readiness. | Add check-in readiness, tickets sold versus capacity, sales status, and manage or check-in actions. |
| Organizer dashboard | Your events table | KEEP | Organizers need rapid event-level control. | Show lifecycle status, date, sold, revenue, and a contextual menu. Do not place destructive actions directly in the row. |
| Organizer dashboard | Recent orders | KEEP | It supports customer service and sales monitoring. | Link to order detail; show payment truth and fulfilment state. |
| Organizer dashboard | Where sales come from | LATER | Attribution is useful but not an MVP blocker. | Keep only after tracking links and attribution are reliable. |
| Organizer sidebar | Overview, Events, Orders and attendees, Payouts, Promote, Team, Settings | FIX | The current grouping is good, but Gate check-in and More tools fragment primary jobs. | Promote Check-in to a primary item during active events. Put reports and exports under Analytics. Remove duplicate legacy links from More tools. |
| Organizer sidebar | Create event button | KEEP | It is the primary organizer action. | Keep persistent on desktop and accessible from the mobile action menu. |
| Organizer legacy dashboard | Separate dashboard, bookings, withdrawals, transactions pages | REMOVE | It duplicates the new workspace and creates two mental models. | Retire route-by-route after the Inertia workspace reaches parity. Redirect legacy entries to the new pages. |
| Admin dashboard | Total revenue | FIX | Gross buyer payments are not the platform's revenue. | Rename to Gross ticket sales and separately show Platform fees earned, Refunds, and Net organizer payable. |
| Admin dashboard | Tickets sold | KEEP | It is a marketplace health metric. | Count issued ticket units for paid and free tickets; disclose period. |
| Admin dashboard | Payouts issued | FIX | Lifetime payout total is less actionable than pending risk. | Show Payouts awaiting review, Amount pending, and Failed or held payouts. |
| Admin dashboard | People on TICA donut | REMOVE | Account composition rarely drives an operational decision. | Replace with organizer approval queue or new verified organizers trend. |
| Admin dashboard | Waiting for you | KEEP | This is the strongest operational component. | Include KYC, event reviews, payout reviews, refunds and disputes, failed webhooks, and open incidents. Sort by severity and age. |
| Admin dashboard | Recent payout requests | KEEP | It supports the admin's financial operations. | Show risk flags, verified destination, amount, age, and approval state. |
| Admin dashboard | Platform at a glance | FIX | Several totals are vanity metrics and duplicate navigation. | Limit to active events, organizers awaiting approval, successful orders, refund rate, and payment success rate. |
| Admin navigation | Hundreds of direct menu items | REMOVE | The current route inventory exposes implementation detail rather than admin jobs. | Consolidate into task-based sections with contextual tabs and settings. |
| Admin | Organizer secret login | FIX | It is useful for support but creates impersonation risk. | Require reason, elevated permission, short session, visible banner, immutable audit log, and no finance mutations while impersonating. |
| Admin | Event creation | MOVE | Routine event creation belongs to organizers. | Keep an emergency support path behind a special permission, or remove it from normal navigation. |
| Admin | Shop and product commerce | REMOVE | It is outside the event ticketing product unless merchandise is an explicit business line. | Remove from the first React rebuild. Preserve legacy routes only if live revenue depends on them. |
| Admin | Ads, blog, popups, extensive CMS controls | LATER | These do not block event marketplace operation. | Keep a lean Content area for homepage, legal pages, and categories. Defer general CMS tooling. |

## Step 3 Gap analysis

### Admin gaps

| Feature | Priority | Recommendation |
|---|---|---|
| Unified review queue | Must-have | One queue for KYC, event moderation, payouts, refunds, disputes, and incidents with owner, age, severity, and SLA. |
| Event moderation state machine | Must-have | Draft, submitted, changes requested, approved, published, suspended, ended, cancelled. Current status and featured toggles are insufficient. |
| Risk flags and approval evidence | Must-have | Record why KYC, event, refund, or payout was approved or rejected and who acted. |
| Ledger-based finance summary | Must-have | Gross payments, refunds, gateway fees, platform fees, organizer payable, paid payouts, and liabilities. |
| Permission templates | Must-have | Operations, Finance, Support, Content, and Super Admin roles with least privilege. |
| Support impersonation controls | Must-have | Reason, expiry, banner, audit record, and restricted finance actions. |
| Saved report filters | Later | Useful for recurring operational analysis after core reporting stabilizes. |
| Automated risk scoring | Later | Add only after sufficient fraud and dispute data exists. |

### Organizer gaps

| Feature | Priority | Recommendation |
|---|---|---|
| One guided onboarding | Must-have | Profile, identity verification, payout account, and first event readiness in one checklist. |
| Explicit publish review flow | Must-have | Publish immediately only for trusted organizers if policy allows; otherwise submit for review. |
| Event-level roles | Must-have | Owner, manager, finance viewer, marketer, and check-in staff. Current team data should become scoped permissions. |
| Ticket sales windows and limits | Must-have | Start and end, min and max per order, quantity, visibility, and sale status per ticket type. |
| Duplicate event | Must-have | Copy content, ticket structure, settings, and media without orders or attendees. |
| Discount code rules | Must-have | Fixed or percentage discount, usage limit, validity window, applicable ticket types, and per-user limit. |
| Refund policy configuration | Must-have | Select platform-approved policy and display it before purchase. |
| Event communication | Later | Target ticket holders by event or ticket type with audited delivery. |
| Advanced attribution | Later | Tracking links, campaign source, and conversion reporting after analytics reliability is proven. |

### User gaps

| Feature | Priority | Recommendation |
|---|---|---|
| Guest purchase claim flow | Must-have | Email link or one-time code attaches guest orders to a user account without duplicate identities. |
| Per-ticket transfer status | Must-have | Pending, accepted, cancelled, expired, revoked; the original QR must stop working after acceptance. |
| Refund eligibility explanation | Must-have | Show policy, deadline, checked-in restriction, amount, and current request status. |
| Communication preferences | Must-have | Transactional email remains required; marketing email and push are optional. |
| Ticket wallet integration | Later | Apple Wallet and Google Wallet after PDF and web tickets are stable. |
| Personalized recommendations | Later | Use explicit saves and purchases before behavioral profiling. |

## Step 4 Correct flows and states

### Admin overview

1. Load a period-filtered overview from server-computed aggregates.
2. Show Gross ticket sales, Platform fees earned, Tickets issued, Active events, Payment success rate, and Refund rate.
3. Show the review queue ordered by severity, SLA breach, and oldest first.
4. Show failed payment, webhook, delivery, and reconciliation incidents.
5. Loading uses stable skeleton geometry. Empty states explain that no work is waiting. Errors preserve the selected period and offer Retry. Stale data shows its last calculated time.

### Organizer verification and approval

1. Organizer enters legal identity and contact details.
2. Organizer uploads only required documents to private storage.
3. Organizer adds a payout account. The platform resolves the bank account name and compares it with the verified legal name.
4. Submission enters pending review and becomes immutable until decided.
5. Admin reviews identity evidence, name match, duplicate accounts, and risk flags.
6. Admin approves or rejects with a reason. Rejection identifies exactly what may be resubmitted.
7. States are not started, draft, pending, verified, rejected, expired, or suspended.
8. Errors never discard uploaded progress. Sensitive files use authorization checks and expiring download links.

### Event moderation

1. Organizer saves an event as draft.
2. The server validates minimum publish requirements.
3. Trusted organizers may publish directly if policy allows. Others submit for review.
4. Admin approves, requests changes, rejects, or suspends. Every action requires a reason and audit record.
5. Approved events become published only when the scheduled publishing condition is met.
6. Suspended events stop sales immediately but preserve orders, tickets, and evidence.
7. Featured is a separate merchandising decision and never implies approval.

### Payout review

1. Available balance is calculated from settled sales minus refunds, platform fees, reserves, prior payouts, and pending liabilities.
2. Organizer requests a payout to a verified, unlocked account.
3. The request enters pending review. Funds are reserved so they cannot be requested twice.
4. Admin reviews KYC, destination, amount, disputes, refunds, event completion policy, and risk flags.
5. Admin approves, rejects, or holds with a reason.
6. Approved payouts are submitted through the gateway or marked for controlled manual processing.
7. Only verified gateway confirmation or controlled finance evidence marks the payout paid.
8. Unknown gateway outcomes remain processing and are reconciled; they are never guessed as failed.

### Refunds and disputes

1. User opens an order or ticket and sees eligibility, deadline, refundable amount, and reason choices.
2. A request is blocked if the ticket has checked in, is transferred beyond policy, or exceeds the refundable balance.
3. Organizer may review requests where policy allows but cannot force payment truth.
4. Admin handles exceptions and disputes.
5. Gateway submission enters processing. Only gateway evidence applies the final financial reversal.
6. States are requested, organizer review, admin review, submitted, processing, refunded, rejected, failed, disputed, or resolved.

### Organizer onboarding

1. Create or upgrade a user account to organizer capability.
2. Capture organizer name, public profile, email, phone, and support contact.
3. Verify email and phone where required.
4. Complete KYC and payout account verification.
5. Show platform fee, payout timing, prohibited events, and cancellation obligations before first publish.
6. Let the organizer draft an event before KYC completes, but block paid-event publication and payout until policy requirements pass.

### Create event wizard

1. Details: title, category, description, organizer, age restriction, visibility, and registration mode.
2. Date and venue: one or multiple dates; physical, online, or hybrid; timezone; venue details; private join URL; reveal timing.
3. Tickets: free, paid, early bird, VIP, or custom; price, currency, quantity, sales window, min and max per order, visibility, and transferability.
4. Media: card image, hero image, gallery, and accessible alt text. Enforce dimensions and safe file types.
5. Policies: refund policy, fee payer, waitlist, remaining-inventory display, attendee questions, and check-in rules.
6. Preview: public page, mobile ticket selector, checkout total, and organizer earnings example.
7. Finish: Save draft, Schedule, Publish, or Submit for review according to organizer trust and platform policy.
8. Each step autosaves to the server, validates locally and server-side, and can be resumed.

### Event lifecycle

- Draft: editable, not discoverable, no sales.
- Submitted: locked except withdrawal from review.
- Changes requested: editable with admin comments.
- Approved or scheduled: accepted but not necessarily live.
- Published: discoverable according to visibility and sales window.
- Sales paused: public page remains; new checkout is blocked.
- Sold out: public page remains; waitlist may open.
- Ended: sales closed; tickets and reports remain.
- Cancellation pending: sales stop while refund impact is calculated.
- Cancelled: attendees notified; refunds and disputes tracked.
- Suspended: admin risk action; sales blocked and reason recorded.
- Archived: hidden from daily organizer views but never deletes finance or attendance records.

### Manage event

1. Overview shows status, readiness, sales, inventory, and next operational action.
2. Edit uses the same wizard with restrictions for fields that affect existing buyers.
3. Duplicate copies configuration only.
4. Pause sales is reversible and does not invalidate tickets.
5. Cancel opens an impact summary: orders, checked-in tickets, refund liability, notifications, and irreversible consequences.
6. Cancellation becomes a workflow, not immediate deletion.

### Sales, attendees, and check-in

1. Orders list shows reference, buyer, amount, gateway, payment state, fulfilment state, and refund state.
2. Attendee list is ticket-unit based, not order based. One order with four tickets produces four attendees or assignable units.
3. Export is queued, private, scoped to the organizer, and auditable.
4. Resend ticket creates a delivery record and does not regenerate identity unless reissue is explicit.
5. Check-in verifies a signed ticket unit, event ownership, revocation state, transfer state, and prior admission.
6. Scanner shows valid, already used, wrong event, revoked, cancelled, or offline-conflict states.
7. Manual lookup uses the same check-in service and records operator, device, source, and time.

### Discover to confirmation

1. User searches or filters by keyword, city, date, category, price, and online or physical format.
2. Event page shows essential facts, organizer identity, ticket availability, refund policy, and fees.
3. User selects ticket types and quantities. Server creates a short reservation and returns authoritative totals.
4. Checkout collects the minimum buyer and attendee data. Guest checkout is allowed because forcing registration reduces conversion and the platform can securely claim orders by verified email.
5. Server creates one payment intent and initializes Paystack or Flutterwave.
6. Redirect return is informative only. The server verifies the transaction independently and also processes signed webhooks idempotently.
7. On success, the server finalizes the booking once, issues ticket units, queues delivery, and shows confirmation.
8. On pending, the user sees verification in progress and may safely revisit the order.
9. On failure, inventory is released according to reservation policy and Retry creates or resumes the correct intent without duplicating payment.

### My tickets

1. Upcoming and past tabs group ticket units by event.
2. Opening a ticket shows QR, attendee name, ticket type, event details, offline availability, and status.
3. User can download current tickets, add to calendar, open directions or join link at the allowed time, transfer an eligible unit, and request a refund.
4. Transfer requires recipient email, explicit confirmation, expiry, and acceptance. Acceptance revokes the sender's QR and issues the recipient's identity.
5. Checked-in, refunded, revoked, expired, and transferred tickets display unmistakable non-valid states.

## Step 5 Dashboard cards and navigation

### Admin dashboard priority order

| Card | Purpose | Data shown | Source | Action |
|---|---|---|---|---|
| Review queue | Direct admin attention | Counts and oldest age for KYC, event reviews, payouts, refunds, disputes, incidents | OrganizerKycSubmission, Event review state, Withdraw, PaymentRefund, disputes, OperationalIncident | Open filtered queue |
| Gross sales | Marketplace volume | Completed payment amount, period comparison, currency | PaymentIntent, Transaction, Booking | Open finance report |
| Platform fees earned | Actual platform revenue | Recognized booking fees minus reversals | Earning or ledger entries, refunds | Open fee report |
| Tickets issued | Marketplace activity | Paid and free ticket units, period comparison | BookingTicket or ticket-unit table | Open bookings report |
| Payment health | Conversion and risk | Success rate, failed and pending intents, webhook delays | PaymentIntent, WebhookReceipt, FunnelAnalyticsEvent | Open payment operations |
| Payout exposure | Finance workload | Pending amount, held amount, failed transfers | Withdraw, PayoutTransfer, PayoutDispute | Open payouts |
| Active events | Supply health | Published upcoming events, ending soon, suspended | Event | Open event moderation |
| Recent high-risk activity | Investigation | Refund spikes, repeated failures, reconciliation mismatches | PaymentRefund, reconciliation entries, incidents, AuditLog | Open incident detail |

Remove People on TICA, lifetime blog count, category count, and other navigation totals from the dashboard.

### Organizer dashboard priority order

| Card | Purpose | Data shown | Source | Action |
|---|---|---|---|---|
| Readiness checklist | Remove blockers | KYC, payout account, publish readiness, urgent event issues | Organizer, OrganizerKycSubmission, OrganizerPayoutAccount, Event | Continue required step |
| Sales summary | Track current performance | Gross sales, tickets sold, refunds, net earnings for selected period | PaymentIntent or Transaction, BookingTicket, PaymentRefund, fee ledger | Open analytics |
| Available balance | Make payout decision | Available, pending settlement, reserved, pending payout | Organizer finance ledger, Withdraw, PayoutTransfer | Request payout |
| Sales trend | Detect momentum | Tickets or sales by day, selected event and period | Booking, BookingTicket, PaymentIntent | Open detailed report |
| Next event | Prepare operations | Date, venue, sold versus capacity, sales status, check-in readiness | Event, EventDates, TicketType, BookingTicket, scanner setup | Manage or Check in |
| Events requiring attention | Prevent failure | Missing payout details, low inventory, sales paused, unresolved cancellation | Event and operational services | Open affected event |
| Recent orders | Support buyers | Buyer, ticket count, amount, state, age | Booking, PaymentIntent, BookingDelivery | Open order |

Page views and attribution are Later unless tracking quality is demonstrated.

### User dashboard priority order

| Card | Purpose | Data shown | Source | Action |
|---|---|---|---|---|
| Next event ticket | Get attendee ready | Event, date, venue or join mode, ticket count, time remaining | Booking, BookingTicket, Event | Open ticket |
| Upcoming list | See all near-term plans | Next three events and ticket counts | BookingTicket, Event | Open My tickets |
| Ticket summary | Fast status | Upcoming ticket units, pending transfers, refund updates | BookingTicket, transfer and refund records | Open relevant section |
| Context actions | Complete common tasks | Download, transfer, calendar, directions, refund eligibility | Current nearest ticket and policy services | Perform task |
| Recommendations | Encourage return | Four eligible public events based on saves, city, and categories | Event, Wishlist, bookings | Open event or discovery |

Orders, saved events, and profile remain navigation pages rather than large dashboard KPIs.

### Navigation and sitemap

### Admin sidebar

- Overview
- Review queue
  - Organizer verification
  - Event reviews
  - Payout reviews
  - Refunds and disputes
- Events
  - All events
  - Categories
  - Homepage curation
- People
  - Organizers
  - Users
  - Admin team and roles
- Finance
  - Payments
  - Payouts
  - Refunds
  - Reconciliation
  - Reports and exports
- Operations
  - Incidents
  - Webhooks and deliveries
  - Audit log
- Content
  - Homepage and navigation
  - Legal and help content
- Settings
  - Fees and commission
  - Payment gateways
  - Ticket and checkout policy
  - Notifications
  - Platform settings

Blog, advertisements, popups, shop commerce, PWA internals, language keywords, and low-level gateway pages should not occupy primary navigation. Keep them behind Advanced or retire them after confirming live dependencies.

### Organizer sidebar

- Overview
- Events
- Orders and attendees
- Check-in
- Promotions
- Finance
  - Earnings
  - Payouts
  - Transactions
- Team
- Reports and exports
- Settings
  - Organizer profile
  - Identity and payout account
  - Notification preferences

Create event remains a persistent primary action, not a duplicate menu item.

**User navigation:** Home; My tickets; Orders; Saved events; Profile and preferences; Help. Discovery remains in the global site header; Logout belongs in the account menu or sidebar footer.

## Step 6 Design source traceability

| Product page or component | Design source used | Decision |
|---|---|---|
| Public header and global Create an event action | Attached public and user desktop header | KEEP the structure; FIX the CTA from bright orange to calm brand blue and collapse navigation into a mobile sheet. |
| Admin overview | Attached Admin control center dashboard | KEEP breadcrumb, greeting, priority metrics, action row, queue, analytics region, and payout list; FIX finance labels and responsive card geometry; REMOVE People on TICA donut. |
| Admin review queue | Attached Waiting for you strip | NEW page using the same white card, soft border, icon tile, badge, and compact action language. Expand the strip into a filterable queue with age, severity, assignee, and SLA. |
| Admin organizer verification | Attached admin cards and table language | NEW split layout: organizer summary and risk status on the left, document and bank verification on the right, sticky Approve and Reject actions at the bottom. |
| Admin event review | Attached event and payout table patterns | NEW event preview beside a review panel using existing card, badge, button, and timeline components. |
| Admin payout review | Attached Recent payout requests section | NEW detail drawer or page using the same table row, status badge, bordered summary cards, and quiet/destructive actions. |
| Organizer overview | Attached organizer dashboard and original light sidebar | KEEP the overview structure and light workspace; FIX compact metrics, balance truth, chart height, and attention list. |
| Organizer navigation | Attached organizer sidebar with identity, Overview, Events, Orders and attendees, Payouts, Promote, Team, Settings, Gate check-in, More tools, Logout, and Create event | KEEP identity and core tasks; MOVE Check-in into the primary group during live events; MOVE reports into Analytics; REMOVE duplicate legacy links from More tools. |
| Organizer onboarding | Organizer sidebar identity block and dashboard setup checklist | NEW three-step flow using the existing card, input, status badge, and progress components: profile, KYC, payout account. |
| Create event wizard | Existing TICA create-event page and organizer workspace | NEW complete flow using existing step pills, white form card, preview card, blue primary buttons, and quiet secondary actions. |
| Organizer event workspace | Organizer dashboard cards and event table | NEW tabbed page using Overview, Orders, Attendees, Tickets, Promotions, Check-in, and Settings with one shared event header. |
| Organizer check-in | Existing Gate check-in entry and scanner surface | KEEP scanner visual language; FIX invalid-state copy, duplicate scan result, manual lookup, offline conflicts, and live count. |
| User account home | Attached user Overview dashboard | KEEP greeting, bounded next-event ticket, compact summaries, Coming up, Quick actions, and recommendations; FIX excessive card height and width. |
| User next-event ticket | Attached Your next event panel and ticket artwork | KEEP the ticket visual; FIX it into a bounded perforated TicketCard with date, venue or join link, status, and three contextual actions. |
| User recommendations | Attached Events you may like cards | KEEP event content; FIX card width, responsive grid, image containment, and empty recommendation behavior. |
| Event discovery | Existing public event cards and header | KEEP card visual language; NEW mobile filter sheet using existing inputs, badges, and primary action. |
| Event detail and checkout | Existing public event and checkout surfaces | KEEP visual components; FIX ticket inventory states, refund disclosure, fee explanation, guest checkout, payment pending, retry, and confirmation. |
| My tickets | User account navigation and next-event TicketCard | NEW list and detail pages reusing TicketCard, compact tabs, status badges, QRPass, and contextual action buttons. |

Nothing in the supplied design is silently discarded. Items removed from the product appear in the Step 2 REMOVE rows; all missing operational pages above are marked NEW and reuse the defined component language.

## Step 7 Engineering mapping

### Recommended core domain model

| Model | Key relationships and responsibility |
|---|---|
| User | Authentication identity. Has one customer profile and optional organizer membership or capabilities. Long-term replacement for split Customer and Organizer authentication. |
| Role and Permission | User has roles; roles have permissions. Organizer team permissions are additionally scoped to organizer or event. |
| Organizer | Public and legal organizer entity. Has members, events, KYC submissions, payout accounts, and withdrawals. |
| OrganizerMember | Belongs to organizer and user; role is owner, manager, finance, marketer, or check-in staff. Replaces loose team-member identity where possible. |
| Event | Belongs to organizer and category; has content, dates, venue, media, ticket types, orders, waitlist, and moderation history. |
| EventReview | Belongs to event and admin actor; records submitted snapshot, decision, reason, and timestamps. |
| TicketType | Belongs to event; defines name, price, currency, quantity, sales window, per-order limits, visibility, and transfer/refund policy. The existing App Models Event Ticket should evolve toward this explicit name. |
| TicketReservation | Holds inventory during checkout; belongs to event, session or buyer, and expires. Existing model should remain the authoritative hold. |
| Order or Booking | Belongs to buyer identity and event; has line items, totals, payment intent, tickets, refunds, and deliveries. Choose one canonical term in code and UI. |
| OrderItem | Belongs to order and ticket type; stores quantity and price snapshot. Do not infer historical totals from current ticket prices. |
| PaymentIntent | Belongs to order; owns provider, reference, amount, currency, state, attempts, and verified provider identity. Existing model and services should be preserved. |
| Ticket | A single admission unit. Belongs to order item and event, has holder identity, QR credential, transfer, check-in, revoke, and refund state. Existing BookingTicket tables should become the canonical ticket-unit aggregate. |
| TicketTransfer | Belongs to ticket, sender, and recipient identity; states pending, accepted, cancelled, expired, or revoked. |
| CheckIn | Belongs to ticket, event, device, and staff actor; immutable admission event with source and conflict metadata. Existing booking ticket events and scanner conflict records should remain. |
| Refund | Belongs to order or ticket scope and payment intent; owns request, approvals, provider state, amount, and dispute. Existing PaymentRefund and PaymentRefundDispute remain. |
| LedgerEntry | Immutable debit or credit for payment, platform fee, organizer payable, refund, reserve, payout, or adjustment. Introduce if current Transaction and Earning records cannot prove every balance. |
| Payout | Belongs to organizer and verified payout account; uses reserved ledger amount and PayoutTransfer provider attempts. Existing Withdraw can be migrated or renamed. |
| AuditLog | Immutable actor, action, subject, before and after metadata, reason, IP or device context where appropriate. Existing AuditLog remains. |

### Existing models to preserve

Preserve and wrap the mature existing capabilities instead of rewriting them: PaymentIntent, TicketReservation, PaymentRefund, PaymentRefundDispute, OrganizerKycSubmission, OrganizerKycDocument, OrganizerPayoutAccount, Withdraw, PayoutTransfer, PayoutDispute, PaymentReconciliationRun, GatewaySettlement, BookingDelivery, WebhookReceipt, OperationalIncident, ScannerDevice, ScannerStaff, ScannerOfflineManifest, EventCancellationRequest, EventWaitlistEntry, and the event registration models.

### Models requiring consolidation

1. Customer, Organizer, Admin, and User currently represent separate authentication concepts. The Inertia rebuild should move toward one User identity with role and organizer membership, but migration must be incremental because existing guards and foreign keys are live.
2. Booking, Transaction, PaymentIntent, and Earning overlap in financial meaning. Define one source of truth for order state, one for provider payment state, and an immutable ledger for balances.
3. Event Ticket currently represents a ticket type while BookingTicket represents a ticket unit. Rename these concepts in the React API and later in PHP to prevent recurring logic errors.
4. Event status, lifecycle_status, sales_closed_at, cancellation fields, and featured state should be represented by an explicit state service with guarded transitions.

### Inertia and React boundaries

- Laravel remains responsible for authentication, authorization, validation, state transitions, money calculations, inventory reservations, provider integrations, signed tickets, queues, and audit logging.
- Inertia page controllers return typed page props and pagination resources. React never calculates authoritative totals, permissions, balances, or status transitions.
- Use React for forms, wizards, filters, charts, optimistic presentation, scanner UI, and accessible interaction states.
- Mutations use dedicated Form Requests and action or service classes. Avoid large controllers and generic update endpoints.
- Create shared enums or value objects for event, payment, ticket, refund, KYC, payout, and delivery states. Expose serialized labels to React while keeping transition rules on the server.
- Use policies for every organizer-owned resource and permission middleware for admin functions.
- Queue emails, exports, ticket PDFs, image processing, notifications, payout submission, and reconciliation.

### Status enums and transitions

| Aggregate | States | Allowed transitions and guards |
|---|---|---|
| Event | draft, submitted, changes_requested, approved, scheduled, published, sales_paused, sold_out, ended, cancellation_pending, cancelled, suspended, archived | Organizer drafts and submits; authorized admin approves, requests changes, or suspends; publish requires valid content, date, inventory, organizer eligibility, and moderation policy; cancel requires impact calculation and refund workflow; archived never deletes finance or attendance history. |
| Order | reserved, payment_pending, paid, payment_failed, expired, partially_refunded, refunded, cancelled | Reservation creates the order; only verified gateway evidence moves to paid; expiry releases inventory; refund totals derive from immutable line snapshots; transitions are idempotent. |
| Payment intent | created, initialized, pending, verified, failed, expired, reversed | Server initializes provider; signed webhook or server verification moves to verified; browser return is never authoritative; repeated provider events return the stored outcome without duplicating tickets. |
| Ticket | pending_issue, valid, transferred, checked_in, revoked, refunded, cancelled, expired | Ticket issues only from paid order or approved free order; transfer acceptance revokes sender credential; check-in is atomic; refunded, cancelled, revoked, expired, and already-used units cannot admit. |
| Transfer | pending, accepted, cancelled, expired, revoked | Owner creates; recipient accepts before expiry; acceptance is atomic with credential rotation; organizer or admin may revoke only under documented policy. |
| Refund | requested, organizer_review, admin_review, submitted, processing, refunded, rejected, failed, disputed, resolved | Eligibility validates amount, deadline, check-in, transfer, and existing refunds; provider evidence finalizes money; every override requires reason and audit log. |
| KYC | not_started, draft, pending, verified, rejected, expired, suspended | Organizer submits immutable evidence; authorized admin decides; rejection names resubmittable fields; expiration or suspension blocks paid publication and payout according to policy. |
| Payout | requested, held, approved, submitted, processing, paid, rejected, failed, reversed | Request reserves available ledger balance; approval requires KYC, verified destination, risk and liability checks; provider confirmation marks paid; unknown outcomes remain processing for reconciliation. |

### Authorization matrix

| Capability | Admin | Organizer owner or manager | Organizer finance | Check-in staff | User |
|---|---|---|---|---|---|
| Moderate or feature any event | Authorized operations or content role | No | No | No | No |
| Edit organizer event | Support-only impersonation with audit and restricted finance | Own organizer only | Read-only unless granted | No | No |
| View orders and attendees | Authorized support or operations | Own organizer or scoped event | Own organizer finance view | Assigned event, minimum attendee data | Own orders and ticket units only |
| Check in ticket | Support exception with audit | Own event | No | Assigned event | No |
| Request payout | No | Owner only unless explicitly delegated | May prepare or view; approval policy decides submission | No | No |
| Approve payout or refund override | Authorized finance role | No | No | No | No |
| Manage own ticket | Support exception with audit | No | No | No | Own valid unit only |

Every controller and Inertia action uses a policy or permission middleware. Organizer queries always include organizer scope on the server; hiding a link in React is never treated as authorization.

### Inertia page and component structure

- `layouts`: PublicLayout, CustomerLayout, OrganizerLayout, and AdminLayout.
- `pages/public`: Discover, Event, Checkout, and Confirmation.
- `pages/customer`: Home, Tickets, Ticket detail, Orders, Order detail, Saved, and Profile.
- `pages/organizer`: Onboarding, Dashboard, Events, EventCreate, EventManage, Orders, Attendees, CheckIn, Promotions, Finance, Team, and Settings.
- `pages/admin`: Dashboard, ReviewQueue, OrganizerReview, EventReview, PayoutReview, RefundReview, People, Finance, Reports, and Settings.
- `components/actions`: Button, IconButton, OverflowMenu, and ConfirmationDialog.
- `components/data`: ResponsiveTable, StatusBadge, Money, DateTime, Pagination, and FilterBar.
- `components/forms`: Field, Select, FileUpload, CurrencyInput, DateTimePicker, and FormActions.
- `components/events`: EventCard, TicketTypeSelector, InventoryMeter, and EventStateBanner.
- `components/tickets`: TicketCard, QRPass, TransferStatus, RefundEligibility, and CheckInResult.
- `components/feedback`: Skeleton, EmptyState, InlineError, Toast, and PendingBanner.

Page controllers return explicit resources such as EventSummaryResource, OrderResource, TicketResource, OrganizerReviewResource, and FinanceSummaryResource. Shared TypeScript contracts are generated or tested against PHP resource shapes.

### Payment webhook flow

1. Checkout validates inventory and creates a short TicketReservation plus one Order in reserved state.
2. The server creates a PaymentIntent with a unique internal reference, exact amount, NGN currency, gateway, and idempotency key.
3. The server initializes Paystack or Flutterwave and returns the provider authorization URL.
4. Browser return shows Processing and asks the server for current status; it never marks the order paid.
5. Webhook endpoint verifies signature, provider identity, reference, amount, and currency, then stores WebhookReceipt before processing.
6. A database transaction locks the PaymentIntent and Order. A duplicate receipt returns the existing result.
7. Verified success moves intent to verified and order to paid, consumes the reservation, issues exactly the required ticket units, and creates BookingDelivery records once.
8. Failure or expiry updates the intent and releases inventory according to reservation rules. Unknown or delayed outcomes remain pending and enter reconciliation.
9. Email, PDF generation, and analytics are queued after commit. They may retry without issuing another ticket.

### Concurrency and integrity rules

- Ticket reservations decrement or lock inventory atomically. Two buyers competing for the last unit cannot both complete a valid reservation.
- Money is stored as integer minor units with explicit currency. Provider amount and currency must match the intent before verification.
- Webhook receipts, ticket issuance, transfers, check-ins, refunds, and payouts use unique idempotency keys or database constraints.
- Check-in uses an atomic conditional update or append-only event with a unique active admission constraint; the second scan returns Already used with the first admission time.
- Signed QR content identifies a ticket unit and credential version, never an editable order total or raw sequential ID.

## Step 8 Flow and feature verification

This walkthrough treats Pass as fully represented by an existing route, model, service, policy, and visible outcome. Fixed means the architecture now defines the missing screen, action, data, state, or guard that the rebuild must implement. Gap would mean no complete resolution exists; no unresolved Gap remains in this specification.

| Flow | Step | Result | Problem | Fix or evidence |
|---|---|---|---|---|
| User paid ticket | Discover and filter | Pass | None material in domain support. | Public discovery exists; rebuild uses Discover page, mobile filter sheet, public Event resources, and published-event scope. |
| User paid ticket | Select ticket and reserve inventory | Fixed | Existing UI does not consistently explain sales windows, remaining inventory, or reservation expiry. | TicketTypeSelector displays availability, min and max, sales state, fees, and countdown; TicketReservation locks inventory atomically. |
| User paid ticket | Guest checkout | Fixed | Account creation and guest recovery are inconsistent across legacy paths. | Guest email is verified by one-time code or magic claim; durable order and ticket units attach to one buyer identity. |
| User paid ticket | Pay and confirm | Fixed | Browser return can be mistaken for payment truth. | PaymentStatus remains Processing until signed webhook or independent verification marks the intent verified; then confirmation and delivery are queued once. |
| User free ticket | Claim ticket | Fixed | Free inventory must not bypass quantity, duplicate, or eligibility rules. | Free order uses the same reservation and issuance service with a zero-value verified path and per-user limits. |
| User promo code | Apply valid or invalid code | Fixed | Discount rules and feedback were missing from the supplied checkout. | PromoCode service validates event, ticket type, window, usage, per-user limit, and authoritative total; invalid codes preserve selection and explain why. |
| User payment retry | Payment fails then retries | Fixed | A blind retry may create a second charge or reservation. | Retry resumes or creates the correct PaymentIntent under one order, releases expired holds, and never duplicates verified payment. |
| User ticket access | Receive and open My Tickets | Pass | Existing delivery and ticket models exist, but presentation is fragmented. | My Tickets groups valid units by event; delivery retries do not regenerate identity; QRPass exposes current status. |
| User transfer | Transfer and recipient acceptance | Fixed | Sender credential lifecycle was not explicit. | TransferStatus shows pending, accepted, expired, cancelled, or revoked; acceptance rotates the credential atomically and invalidates the sender QR. |
| User refund | Request eligible refund | Fixed | Buyer cannot always see policy, amount, deadline, or ineligibility reason. | RefundEligibility explains check-in, transfer, deadline, refundable amount, and status before submission. |

### Organizer walkthrough

| Flow | Step | Result | Problem | Fix or evidence |
|---|---|---|---|---|
| Organizer onboarding | Sign up and create workspace | Fixed | Split customer and organizer accounts create duplicate identities. | One User gains organizer membership; onboarding captures public profile, support contact, KYC, and payout account. |
| Organizer verification | Submit KYC and bank account | Pass | Mature KYC and payout-account models exist; flow needs consolidation. | Onboarding page uses private uploads, immutable submission, bank name resolution, pending and rejection states, and admin review. |
| Organizer event creation | Add details, venue, dates, media, tickets, and policy | Fixed | Current screens do not form one resumable, validated wizard. | Eight-step server-autosaved wizard with draft recovery, preview, field errors, back and cancel, and publish-readiness summary. |
| Organizer publish | Unverified organizer tries to publish | Fixed | Client state may imply readiness without policy proof. | Server transition blocks paid publication and shows the exact incomplete KYC, payout, content, or moderation requirement. |
| Organizer sale | Receive paid order | Pass | Payment, booking, and delivery models exist. | Recent orders links to Order detail showing provider truth, fulfilment, refund state, and attendee units. |
| Organizer check-in | Scan valid QR | Pass | Scanner infrastructure exists. | CheckIn service verifies event scope, signature, credential version, ticket state, and prior admission; UI shows Valid plus attendee and ticket type. |
| Organizer check-in | Scan same ticket twice | Fixed | Duplicate result must not look like a generic failure. | Atomic check-in returns Already used with first scan time, device, and operator; it never creates a second admission. |
| Organizer check-in | Wrong event, cancelled, revoked, or offline conflict | Fixed | These states were not consistently visible. | CheckInResult has distinct text and icon states, auditable manual override policy, and offline conflict reconciliation. |
| Organizer cancellation | Cancel event | Fixed | Immediate deletion or status toggle ignores refund and communication impact. | Cancellation impact page shows paid orders, checked-in units, liability, notifications, and confirmation; workflow tracks refund execution. |
| Organizer payout | Request payout | Pass | Withdrawal and payout-transfer models exist. | Finance page shows ledger-derived available balance, verified destination, reserve, pending liabilities, request state, and history. |

### Admin and edge-case walkthrough

| Flow | Step | Result | Problem | Fix or evidence |
|---|---|---|---|---|
| Admin organizer review | Approve or reject organizer | Pass | Existing KYC records support review. | OrganizerReview page restricts action by role, records reason and evidence, exposes no public file URLs, and notifies organizer. |
| Admin event review | Reject or request changes | Fixed | Status toggles do not preserve review evidence or an editable path. | EventReview stores submitted snapshot, decision, comments, actor, and time; organizer receives Changes requested with field-level guidance. |
| Admin merchandising | Feature event | Fixed | Featured can be confused with moderation approval. | Separate authorized action requires published public event, start and end schedule, placement, and audit record. |
| Admin refund | Process refund or dispute | Pass | Refund and dispute models exist. | Finance role verifies eligibility or exception, submits provider request, displays processing, and waits for gateway evidence. |
| Admin payout | Approve and mark paid | Fixed | Manual Mark paid risks inventing payment truth. | Approval reserves liability; paid requires provider confirmation or controlled finance evidence with immutable audit record. |
| Cross-role authorization | Attempt another organizer's resource | Fixed | UI hiding alone cannot prove isolation. | Every resource query is organizer-scoped and policy-tested; unauthorized IDs return 404 or 403 without leaking existence or data. |
| Inventory edge case | Two buyers request the last ticket | Fixed | Read-then-write inventory can oversell. | Transactional reservation with row lock or atomic counter permits one hold; loser sees Sold out and no payment intent is initialized. |
| Webhook edge case | Delayed, failed, or duplicate webhook | Pass | WebhookReceipt and reconciliation capabilities exist. | Signature, reference, amount, and currency verification plus unique receipt keys make processing idempotent; delayed success can finalize the pending order once. |

### State visibility checklist

- Every list and detail page defines loading skeleton, empty guidance, recoverable error with Retry, permission denied, and stale-data time where relevant.
- Every mutation has a disabled and loading state, success confirmation, error recovery, and safe back or cancel route.
- Sold out, sales ended, paused, cancelled, suspended, payment pending, refund processing, KYC pending, payout held, and duplicate check-in always use text plus color or icon.
- Mobile tables collapse into readable records with the primary action visible; no action requires hover.

## Step 9 Automated test plan

### User and checkout feature tests

| Test | What it proves |
|---|---|
| `it_lists_only_discoverable_published_events` | Draft, suspended, private, cancelled, and policy-ineligible events do not leak into discovery. |
| `it_reserves_paid_ticket_inventory_atomically` | A valid reservation owns inventory for its expiry window and records authoritative totals. |
| `it_allows_only_one_buyer_to_reserve_the_last_ticket` | Concurrent requests cannot oversell the final unit. |
| `it_completes_guest_checkout_and_claims_order_by_verified_email` | Guest conversion works without creating duplicate buyer identities. |
| `it_issues_a_free_ticket_without_bypassing_limits` | Free checkout respects capacity, per-order, and per-user limits. |
| `it_applies_promo_code_rules_to_eligible_ticket_types` | Discount window, scope, amount, usage, and per-user rules are authoritative. |
| `it_rejects_expired_or_exhausted_promo_codes_without_losing_selection` | Invalid discounts preserve the buyer's ticket selection and do not alter totals. |
| `it_retries_failed_payment_without_duplicate_charge_or_order` | Retry is safe and idempotent. |
| `it_shows_paid_ticket_in_my_tickets_and_queues_delivery_once` | Successful payment creates one set of units and one durable delivery workflow. |
| `it_transfers_one_ticket_and_rotates_the_qr_credential` | Recipient acceptance invalidates the sender credential and preserves audit history. |
| `it_blocks_refund_for_checked_in_or_ineligible_ticket` | Refund eligibility enforces policy and admission truth. |

### Organizer feature tests

| Test | What it proves |
|---|---|
| `it_creates_organizer_membership_without_duplicate_user` | Buyer and organizer capability share one identity. |
| `it_saves_and_resumes_each_event_wizard_step` | Draft data persists server-side and validation is step-specific. |
| `it_blocks_paid_event_publish_for_unverified_organizer` | KYC and platform policy are enforced on the server. |
| `it_publishes_valid_event_or_submits_it_for_review_by_policy` | Trust and moderation policy select the correct transition. |
| `it_duplicates_event_configuration_without_orders_or_attendees` | Duplicate copies content and ticket structure only. |
| `it_pauses_sales_without_invalidating_existing_tickets` | Sales control and ticket validity remain separate. |
| `it_cancels_event_through_impact_and_refund_workflow` | Cancellation cannot bypass buyer liability, notification, or audit requirements. |
| `it_exports_only_the_organizers_scoped_orders_and_attendees` | CSV jobs are private and tenant-scoped. |
| `it_resends_ticket_without_reissuing_identity` | Delivery retry does not create a new ticket or QR. |
| `it_calculates_available_balance_from_ledger_entries` | Organizer payout balance cannot be edited or inferred from a stale field. |

### Admin feature tests

| Test | What it proves |
|---|---|
| `it_approves_or_rejects_kyc_with_reason_and_audit_actor` | Organizer verification is evidence-based and auditable. |
| `it_records_event_review_snapshot_and_requested_changes` | Moderation preserves what was reviewed and provides a resubmission path. |
| `it_features_only_eligible_published_events` | Merchandising cannot bypass moderation or publication rules. |
| `it_processes_refund_once_and_waits_for_provider_truth` | Refund approval cannot duplicate reversal or guess final provider state. |
| `it_reserves_funds_when_payout_is_requested` | Available balance cannot be paid twice. |
| `it_marks_payout_paid_only_from_verified_transfer_or_controlled_evidence` | Finance UI cannot invent a successful payout. |
| `it_restricts_impersonation_and_audits_reason_expiry_and_actions` | Support impersonation is time-limited and finance mutations remain blocked. |

### Policy tests

| Test | What it proves |
|---|---|
| `organizer_cannot_view_or_mutate_another_organizers_event` | Tenant isolation covers show, edit, update, duplicate, pause, and cancel. |
| `organizer_team_role_limits_finance_marketing_and_checkin_actions` | Event and workspace roles grant least privilege. |
| `checkin_staff_can_access_only_assigned_events_and_minimum_attendee_data` | Gate staff cannot browse unrelated customer or finance records. |
| `attendee_can_access_only_own_orders_tickets_transfers_and_refunds` | Buyer ownership is enforced on every customer route. |
| `admin_permissions_separate_operations_finance_support_content_and_super_admin` | Admin role templates prevent broad accidental access. |

### Payment and webhook tests

| Test | What it proves |
|---|---|
| `verified_paystack_webhook_finalizes_order_and_issues_tickets_once` | Valid provider evidence creates tickets exactly once. |
| `verified_flutterwave_webhook_finalizes_order_and_issues_tickets_once` | The second primary gateway follows the same invariant. |
| `invalid_webhook_signature_is_rejected_without_state_change` | Forged callbacks cannot alter money or tickets. |
| `webhook_amount_currency_or_reference_mismatch_creates_incident` | Provider mismatch never finalizes an order silently. |
| `duplicate_webhook_returns_existing_result_without_duplicate_ticket` | Receipt idempotency protects issuance and delivery. |
| `browser_return_never_marks_payment_paid` | Client redirect is informational only. |
| `delayed_success_reconciles_pending_intent_once` | Late provider truth can safely complete an order. |

### Check-in tests

| Test | What it proves |
|---|---|
| `valid_ticket_checks_in_once` | Signed current credential admits at the correct event. |
| `second_scan_returns_already_used_with_first_admission` | Duplicate scan does not create another admission. |
| `wrong_event_ticket_is_rejected` | A valid credential cannot enter an unrelated event. |
| `cancelled_refunded_revoked_or_expired_ticket_is_rejected` | Non-valid ticket states are enforced. |
| `offline_scans_reconcile_conflicts_without_duplicate_admission` | Offline operation preserves the one-ticket-one-admission invariant. |

### Regression and migration tests

- Snapshot and smoke-test public discovery, event detail, checkout, customer tickets and orders, organizer event and booking routes, KYC, withdrawals, scanner routes, admin event review, refunds, payouts, reconciliation, and exports before redirecting any route.
- Preserve tables behind PaymentIntent, TicketReservation, Booking, BookingTicket, PaymentRefund, OrganizerKycSubmission, OrganizerPayoutAccount, Withdraw, PayoutTransfer, WebhookReceipt, BookingDelivery, ScannerDevice, ScannerStaff, and AuditLog until new pages pass parity tests.
- Add compatibility adapters rather than destructive renames during Phase 1. Backfill new enum fields in batches, dual-read with logged mismatches, then dual-write only where necessary.
- Migrate one capability at a time behind a feature flag. Verify counts, monetary totals, status distribution, foreign keys, and sample records before and after cutover.
- Redirect old routes only after authorization, empty/loading/error states, exports, queued jobs, and browser tests pass. Keep reversible route flags for at least one release window.

## Prioritized build order

### Phase 1 MVP

1. Freeze terminology, design tokens, component contracts, enums, policies, and audit requirements.
2. Build shared Inertia layouts and role-aware navigation with mobile sheets.
3. Rebuild discovery, event detail, reservation, guest checkout, Paystack and Flutterwave verification, confirmation, delivery, My Tickets, transfer, and refund request.
4. Rebuild organizer onboarding, KYC, payout account, event wizard, event workspace, orders, attendees, check-in, finance, and payout request.
5. Rebuild admin review queue, organizer and event review, refunds, payouts, reconciliation, core reporting, and settings.
6. Run all Phase 1 feature, policy, payment, check-in, concurrency, and regression tests before legacy redirects.

### Phase 2 Operations and growth

- Promo reporting, event communication, waitlist automation, saved report filters, wallet passes, improved exports, event-level team invitations, homepage scheduling, and operational SLA dashboards.

### Phase 3 Scale

- Multi-currency readiness, automated risk scoring, advanced attribution, personalized recommendations, deeper marketplace curation, and intentionally approved CMS or merchandise capabilities.

## Definition of done

The redesign is complete only when each role has one coherent navigation system, every financial number has an authoritative source, every mutable workflow has explicit states and permissions, guest purchases can be securely recovered, one ticket unit admits one person exactly once, organizer and admin actions are auditable, and legacy duplicate surfaces are either redirected or intentionally retained with a documented reason.
