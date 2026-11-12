# Accessibility release standard

Tica's production target is WCAG 2.2 Level AA for public discovery, event detail, registration, checkout, account and authorized scanner journeys. Automated checks are useful but cannot replace keyboard, screen-reader, zoom and real-device verification.

## Accountability and evidence

- `ACCESSIBILITY_OWNER_EMAIL` names the person accountable for triage and release decisions.
- `ACCESSIBILITY_AUDIT_STANDARD` must remain `WCAG 2.2 AA` unless a later standard is deliberately adopted in code and policy.
- `ACCESSIBILITY_AUDIT_AT` and `ACCESSIBILITY_AUDIT_REFERENCE` identify a real audit and its non-secret report or ticket.
- Keyboard, screen-reader, checkout and scanner flags are separate because a generic page audit cannot establish that transactional or event-day workflows work.
- Future dates, stale audits and partial attestations fail `ops:release-readiness`.

## Required journeys

Test at desktop and mobile breakpoints with realistic content, validation errors, empty states and slow/failed requests:

1. Home/discovery: navigate header, search, filters, category and event carousels; understand all controls without relying on hover, color or motion.
2. Event detail: consume title/date/location/price/about content, share safely, choose each ticket type and understand remaining inventory and totals.
3. Checkout/payment: identify fields and errors, recover from validation, understand fees/amount/terms, leave and return from a gateway, and receive ticket confirmation.
4. Corporate registration: read privacy notice, complete repeated attendee fields, select agenda sessions, recover from errors and understand approval/invoice status.
5. Authentication/account: register, verify/reset credentials, review bookings and download owner-scoped documents.
6. Organizer/admin: operate primary event, ticket, registration, refund and incident controls without pointer-only interaction.
7. Scanner: authenticate, load/refresh manifest, enter a code when camera access fails, interpret accepted/duplicate/rejected results and recover online/offline without color alone.

## Manual test matrix

For each journey record browser, viewport/device, assistive technology, result, defect reference and retest evidence.

- Keyboard only: visible focus, logical order, no traps, skip navigation, dialogs return focus, escape behavior, and no click-only controls.
- Screen reader: meaningful page title and landmarks, one primary heading, labels/instructions/errors associated with fields, live status announcements that do not steal focus, meaningful link/button names and decorative images hidden.
- Zoom/reflow: 200% text zoom and 400% page zoom without clipped content, two-dimensional scrolling for ordinary content, or fixed controls obscuring the focused element.
- Visual: AA contrast for text, icons, focus and component states; information not encoded only by color; target sizes and spacing meet WCAG 2.2 expectations.
- Motion: reduced-motion preference removes nonessential animation, carousels do not auto-advance uncontrollably, and no flashing content exceeds safe thresholds.
- Forms: persistent labels, clear required/optional state, field-level plus summary errors, retained valid input, useful autocomplete/input modes and no timeout surprise.
- Media/documents: relevant images have text alternatives, event poster text is not the only event description, and generated ticket/invoice/badge documents remain usable at high zoom.

## Automated checks

The hosted UI audit should scan representative rendered states with a current accessibility engine and fail on serious/critical findings. It must include at least home, search results, one event with several ticket types, registration, checkout errors, booking confirmation and scanner states. Store the exact URLs/fixtures, engine version and results with the audit reference.

Static tests and lint rules should prevent recurrence of missing form labels, duplicate IDs, unnamed controls, invalid ARIA and inaccessible modal patterns. They do not authorize setting the manual verification flags to true.

## Defect policy

- Block launch for an inaccessible core journey, keyboard trap, missing accessible name on a transaction control, unannounced payment/scanner outcome, critical contrast/reflow failure or any serious automated finding.
- High-impact defects require an owner and verified fix before release. A documented exception must include affected users, workaround, expiry and accountable approver; permanent blanket exceptions are not acceptable.
- Re-run the affected journey after every fix. Re-run the full audit after major navigation/design-system changes or when the configured maximum age expires.

## Release checklist

- Audit uses production-like content and supported devices/browsers.
- All seven required journeys have results; checkout and scanner are explicitly signed off.
- Keyboard, screen reader, zoom/reflow, contrast and reduced motion were tested manually.
- Serious/critical automated findings are zero and blocking defects are closed/retested.
- The report reference contains environment, tester, assistive technology and defect evidence.
- `php artisan ops:release-readiness --configuration-only` reports `accessibility.wcag_audit` as `PASS`.

Configuration records the decision; the report and retest evidence prove it.
