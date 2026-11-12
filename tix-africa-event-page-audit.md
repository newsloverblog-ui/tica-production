# Tix Africa event-page structure audit

Reference: https://tix.africa/discover/the-noisy-book-club-october-meeting-mainland-chapter

Audit date: 2026-10-30

## Scope

Desktop and mobile review of the public event-detail page, focusing on hierarchy, content layering, conversion structure, responsive reflow, related-event presentation, and visible accessibility risks.

## Step 1 — Header and breadcrumb

Health: Strong.

- Desktop navigation is restrained and leaves the event itself as the visual focus.
- The breadcrumb establishes context without becoming another large banner.
- Mobile reduces navigation to logo, search, and menu.
- Risk: the mobile breadcrumb truncates aggressively and does not expose the complete event title visually.

## Step 2 — Event identity and hero

Health: Excellent.

- Desktop uses a two-part hero: event facts and CTA on the left, artwork on the right.
- The image supplies the atmosphere while a darkened text surface preserves readability.
- Title, date, time, venue, address, and CTA form one clear decision block.
- Mobile keeps title and artwork together, then moves event facts into a quiet white facts list below.
- This is the strongest pattern to adapt to Tica.

## Step 3 — Ticket action

Health: Strong, with one mobile risk.

- Desktop has one large, low-friction “Get a Ticket” action directly after the event facts.
- Mobile turns the action into a persistent bar, keeping conversion available while reading.
- Risk: in the captured mobile state, the fixed bar overlaps the first lines of the About content. Tica should retain its safe-area spacing and reserve page space for the fixed checkout action.

## Step 4 — About and host information

Health: Strong.

- The About content is placed in a narrow reading column with high line-height and minimal visual chrome.
- Contact links are separated from the narrative instead of competing with it.
- “Hosted by” is a compact trust block with avatar, name, and handle.
- The sequence answers: what is it, who is behind it, and where is it.

## Step 5 — Directions

Health: Structurally sound; visual state could not be fully verified.

- Directions appear after the narrative and host, which matches user intent.
- The desktop layout gives the map substantial width without making it the hero.
- The map surface did not fully render in the mobile capture, so map controls, contrast, keyboard behavior, and loading states remain unverified.

## Step 6 — Related events

Health: Excellent.

- Desktop uses a two-column list of compact horizontal cards.
- Mobile becomes a single vertical list with text on the left and a small poster on the right.
- Title, date, venue, and price are the only repeated details, which keeps scanning fast.
- Card shadows and borders are subtle; spacing creates separation instead of heavy decoration.
- This validates the direction already used on Tica’s mobile related-event cards.

## Step 7 — Footer

Health: Strong.

- A warm, low-contrast footer separates support and company links from the primary event journey.
- Newsletter, company navigation, social links, policies, and copyright are grouped in a predictable order.
- Mobile stacks these groups with generous vertical rhythm.

## What Tica should borrow

1. Treat the desktop hero as a decision block: title, facts, address, and ticket action beside event artwork.
2. Use a narrower reading measure for About content instead of putting every section inside equally prominent cards.
3. Keep related events as compact horizontal cards with restrained metadata.
4. Preserve one clear ticket action; avoid multiple equally loud buttons.
5. Separate trust, directions, discovery, and footer content into a predictable narrative sequence.
6. Use whitespace and subtle dividers as the primary layering system; use shadows sparingly.

## What not to copy directly

1. Do not allow a fixed mobile ticket CTA to overlap content.
2. Do not truncate the only visible version of an event title without another accessible path to the full text.
3. Keep Tica’s explicit ticket inventory and tier controls; Tix’s single CTA does not cover Tica’s multi-tier purchase requirements.
4. Preserve Tica’s navy and `#FE9B59` color system rather than copying Tix’s coral palette.

## Evidence limits

- Desktop and mobile public states were visually captured in the in-app browser during this audit.
- Checkout was not opened because the request focused on page structure, not purchase flow.
- Keyboard navigation, screen-reader output, focus order, zoom behavior, and full map interaction require a separate accessibility test.
