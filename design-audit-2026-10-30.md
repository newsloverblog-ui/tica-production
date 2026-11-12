# Tica Design Audit

Date: 30 October 2026  
Scope: Homepage discovery experience and individual event page  
Viewports reviewed: desktop and 390 px mobile  
Focus: responsiveness, aesthetics, functionality, UI consistency, UX and accessibility

## Executive view

The redesign already has a strong visual direction: real event imagery, a confident navy/orange identity, a clearer homepage search, improved event hierarchy, visible ticket availability, and a functioning quantity-to-checkout flow. The biggest remaining problem is not the general visual style. It is consistency and resilience across states and screen sizes.

Three issues should be fixed before more decorative refinement:

1. Homepage content can remain invisible while its section height remains, producing very large blank areas in reduced-interaction and full-page views.
2. The fixed mobile checkout button overlaps the event description instead of reserving its own safe space.
3. The event page can show a contradictory “No event content found” warning even while the requested event is visible.

Once those are corrected, the next pass should standardize spacing, card behavior, contrast, controls, and content rules rather than adding more one-off styles.

## What is working well

- The homepage hero now has a clear purpose: discover events by search, city and category.
- The hero uses real event photography and a restrained overlay, which is more credible than generated imagery.
- The homepage has no horizontal overflow at 390 px.
- Search fields and category chips stack cleanly on mobile.
- Trending and event rails retain swipe behavior on mobile and arrow controls on desktop.
- The event page establishes a strong hierarchy: poster, title, host, facts, ticket selection, about, location and related events.
- Ticket stock is visible per ticket type.
- Selecting a ticket changes the quantity, recalculates the total and correctly enables “Continue to checkout”.
- The related-event mobile cards are compact and easier to scan than the older large-card treatment.
- The pre-footer organizer callout and footer now share the same visual family.
- Brand orange `#FE9B59` is generally used as an action/accent instead of as a page-wide background.

## Priority findings

### P1 — Fix before visual polish

#### 1. Scroll-reveal leaves blank sections

**Evidence**

- `home-city-pulse.css` gives every `[data-reveal]` element `opacity: 0`.
- The homepage script only restores visibility after an `IntersectionObserver` event.
- Mobile and full-page captures show a large dark empty region followed by a large white empty region where category, organizer and city content should be.

**Why it matters**

The page can look broken in screenshots, print views, webviews, slow devices, browser restoration, automation and any situation where the observer does not fire as expected.

**Improve it**

- Make content visible by default.
- Add an enhancement class only after JavaScript is confirmed ready.
- Animate only elements near the viewport; never make critical content depend on animation.
- Reduce reveal travel from 24 px to about 12–16 px.
- Keep the existing `prefers-reduced-motion` fallback.
- Remove large fixed/minimum section heights that remain after content is hidden.

#### 2. Mobile checkout action covers event content

**Evidence**

- At mobile width `.pulse-ticket-submit` becomes `position: fixed` with a high z-index.
- The bar visibly sits over the “About this event” panel.

**Why it matters**

The primary action obscures content and can block taps or reading. It feels like an advertisement rather than part of the purchase flow.

**Improve it**

- Use a dedicated fixed bottom action bar with an opaque surface.
- Reserve matching bottom padding on the page, including `env(safe-area-inset-bottom)`.
- Show the bar only after the ticket panel leaves the viewport.
- Hide it while the ticket panel is visible.
- Display selection context in the bar, for example `1 ticket · ₦10,000`.
- When nothing is selected, use “Choose tickets” and scroll/focus the user back to ticket options.

#### 3. False missing-content warning

**Evidence**

- The event page displays “No event content found for English Language” while the event content is visibly rendered.
- The message is created in `EventController::details()` and survives as session feedback.

**Why it matters**

It creates immediate doubt about the event and the reliability of the ticket platform.

**Improve it**

- Flash this warning only when the request actually redirects because no localized event content exists.
- Clear stale warning state before a successful event response.
- If a fallback language is used, say “Showing this event in [language]” instead.
- Add a dismiss control and an appropriate live-region role to genuine alerts.

### P2 — Strong usability and consistency improvements

#### 4. Homepage sections compete instead of forming one rhythm

Trending, Happening Now, moods, organizer, cities and more events each use a different density and visual rule. The page becomes long before the user reaches the footer.

**Improve it**

- Use one spacing scale: 16, 24, 32, 48, 72 and 96 px.
- Use 72–96 px between major desktop sections and 48–64 px on mobile.
- Keep heading-to-content spacing at 24–32 px.
- Avoid repeating the same events in Trending and Happening Now.
- Define Trending by editorial/popularity data and Happening Now by date/proximity.
- If there are fewer than three unique sections of event data, combine them with tabs instead of duplicating cards.

#### 5. “Find your mood” contrast is too fragile

The dark mood section is attractive in concept but its text and cards become nearly invisible when reveal or image loading states fail.

**Improve it**

- Give every card a stable navy surface before its image loads.
- Maintain at least 4.5:1 contrast for normal text.
- Use one consistent image overlay rather than stacking opacity, blur and reveal effects.
- Supply useful image `alt` text or mark decorative images with an empty alt.

#### 6. Organizer and city imagery has inconsistent fallback behavior

The organizer block can appear as a flat gray/empty panel and one city card can appear significantly more faded than its siblings.

**Improve it**

- Add a deterministic fallback gradient and image placeholder.
- Keep image fade at the rail edges only; do not fade a normal content card in the grid.
- Use a shared image-loading state with a skeleton and a failed-image fallback.
- Test object-position for mobile so faces and key subjects remain visible.

#### 7. Edge blur is too strong for small event sets

The current event rail can obscure useful card content even when only three items exist.

**Improve it**

- Apply edge blur only when the rail can actually scroll.
- Use a 48–72 px gradient, not an opaque mask.
- Hide the previous arrow at the start and next arrow at the end.
- If five or more events exist, preserve the emphasized center-card composition; otherwise use a normal centered grid/rail.

#### 8. Event desktop composition becomes empty below the ticket panel

The sticky ticket panel is useful, but after its content ends the right column becomes a large blank strip while the left column continues.

**Improve it**

- Keep the ticket card sticky only within the primary event-intro region.
- Add small trust/support modules below it: secure checkout, refund summary, organizer contact and QR/share.
- Do not extend a two-column grid through unrelated sections such as About and Location.

#### 9. Related events are not data-aware on desktop

Two related cards occupy a wide carousel with unused horizontal space and always-visible navigation.

**Improve it**

- Use a responsive grid for one to three results.
- Enable the carousel only when results exceed the visible card count.
- Hide arrows when there is nowhere to navigate.
- Use a 24–32 px desktop gap and a 20–24 px mobile gap.
- Keep all titles to two lines and align metadata/price rows.

#### 10. Map needs a resilient mobile state

The mobile capture showed a large blank map surface.

**Improve it**

- Use a fixed aspect ratio instead of a large hard-coded height.
- Show a map skeleton while loading.
- If embedding fails, show the venue address with “Open in Maps” and “Get directions”.
- Keep the map around 240–280 px tall on narrow screens and 340–420 px on desktop.

#### 11. Footer is visually cohesive but long on mobile

**Improve it**

- Keep brand, newsletter and legal content always visible.
- Collapse Explore, Organizers and Contact into accessible accordions below 768 px.
- Keep social controls on one clear row with visible focus states.
- Reduce repeated vertical padding while preserving 44 px touch targets.

### P3 — Refinement and accessibility

#### 12. Quantity controls have weak accessible names

The `+` and `−` buttons are announced only as symbols.

**Improve it**

- Add ticket-specific labels such as “Increase Regular ticket quantity”.
- Give each numeric field a programmatic ticket label.
- Announce total-price changes with `aria-live="polite"`.
- Replace repeated element IDs with unique IDs or class-based event handling.

#### 13. Carousel and footer links have ambiguous names

- Related-event arrows are exposed as icon glyphs.
- Footer social links are all labelled “Social media”.

**Improve it**

- Label arrows “Previous related events” and “Next related events”.
- Name footer links by network: Facebook, Instagram, X, LinkedIn, etc.
- Ensure icon-only controls have a visible tooltip and a 44 × 44 px target.

#### 14. Motion needs user control

Hero category rotation and animated rails should not surprise users or compete with reading.

**Improve it**

- Pause automatic rotation on hover, focus and user interaction.
- Stop or substantially reduce autoplay when `prefers-reduced-motion` is enabled.
- Preserve the active slide when the browser tab regains focus.
- Ensure hero progress controls are buttons with selected-state semantics.

#### 15. Typography should use fewer roles

The current pages mix large display type, all-caps card titles, multiple weights and several muted-gray treatments.

**Improve it**

- Use one clean sans-serif family throughout.
- Limit weights to approximately 400, 600 and 800.
- Reserve all caps for short labels, not multi-line event titles.
- Use `clamp()` for hero and section headings.
- Keep body copy near 16–18 px desktop and at least 15–16 px mobile with 1.5–1.7 line height.

## Recommended design system

### Color balance (60 / 30 / 10)

- **60% neutral base:** white and soft gray surfaces (`#FFFFFF`, `#F6F7FA`).
- **30% structural dark:** navy (`#050B1C`) for hero, footer, selected rails and ticket panels.
- **10% action accent:** orange (`#FE9B59`) for primary buttons, active states, icons and short highlights.

Blue and violet should be supporting accents only. They should not compete with orange for primary actions on the same screen.

### Components to standardize

- Primary, secondary and text buttons.
- Search field and select/dropdown states.
- Event card: image, title, date, venue, price, stock and action.
- Section heading: eyebrow, title, subtitle and optional action.
- Ticket row: label, description, stock, price and quantity stepper.
- Alert/toast: information, success, warning and error.
- Image loading, empty and failure states.
- Desktop carousel and mobile swipe rail.

### Shared interaction rules

- Minimum touch target: 44 × 44 px.
- Visible keyboard focus on every interactive element.
- No essential content hidden by animation.
- No fixed element may cover page content.
- Disabled actions must explain what the user needs to do next.
- Carousels must hide unavailable controls and remain usable by keyboard.

## Responsive layout targets

### Mobile: 320–767 px

- One-column hero and event-detail flow.
- Search controls stacked with 12–16 px gaps.
- Horizontal chips/rails may scroll, but the page itself must not.
- Related event cards use compact horizontal composition.
- Ticket CTA uses a safe-area-aware bottom bar only when needed.
- Footer navigation becomes accordions.

### Tablet: 768–1023 px

- Two-column category/city grids.
- Event information remains one column; ticket selector may become a full-width panel below the hero.
- Use two related-event cards per row.

### Desktop: 1024 px and above

- Hero content width should remain controlled even on ultrawide screens.
- Event intro uses a roughly 65/35 content/ticket split.
- Major sections should align to one shared maximum-width container.
- Related events use three cards where data allows.

## Functional UX improvements

1. Preserve the user’s city/category/search filters when returning from an event page.
2. Debounce search and expose suggestions with keyboard navigation.
3. Make stock messaging honest and timely: “10 left”, “Selling fast”, “Sold out”.
4. Prevent quantity increases beyond remaining stock or per-order limits and explain the limit inline.
5. Show a lightweight order summary before checkout.
6. Copy/share should confirm success without blocking the page.
7. Use native Web Share on supported mobile devices, with the social modal as fallback.
8. Add map and calendar fallbacks rather than relying on embedded third-party content alone.
9. Track empty-result states and offer nearby dates, cities or categories.
10. Use real event data and real imagery throughout; do not substitute AI-generated posters.

## Implementation order

### Phase 1 — Reliability and blockers

1. Make reveal content visible by default.
2. Correct the false language warning.
3. Rebuild the mobile checkout bar so it never overlaps content.
4. Add map loading/error fallback.
5. Add accessible names to ticket, carousel and social controls.

### Phase 2 — Responsive structure

1. Normalize section spacing and container widths.
2. Separate/deduplicate Trending and Happening Now data.
3. Make rails and related events adapt to their item count.
4. Add stable image loading/failure states.
5. Compact the mobile footer.

### Phase 3 — Visual system and polish

1. Consolidate colors to the 60/30/10 system.
2. Standardize typography and button/card variants.
3. Reduce edge blur and excessive shadows.
4. Refine motion and reduced-motion behavior.
5. Perform final contrast, keyboard, zoom and screen-reader checks.

## Acceptance checklist

- No horizontal page overflow at 320, 360, 390, 768, 1024 and 1440 px.
- No blank content sections when JavaScript is slow, unavailable or motion is reduced.
- No sticky/fixed control covers readable or interactive content.
- Search, selects, carousels, ticket steppers, share modal and footer are keyboard accessible.
- All normal text meets WCAG AA contrast.
- 200% browser zoom does not clip controls or text.
- Ticket stock, limits, totals and CTA state remain synchronized.
- Carousel controls appear only when navigation is possible.
- Images have meaningful alternatives and resilient loading states.
- Mobile safe areas are respected.
- Empty, loading, success and error states are visibly designed.

