# Design QA — Refined Homepage Hero

**Source visual truth**
- `/Users/mac/Downloads/Screenshot 2026-10-30 at 2.21.17 PM.png`
- Source pixels: 3384 × 1428.
- Source intent: framed photographic hero, centered copy, simple search, restrained spacing.

**Implementation evidence**
- Desktop screenshot: `/Users/mac/Downloads/tica/design-qa-desktop.png`
- Mobile screenshot: `/Users/mac/Downloads/tica/design-qa-mobile.png`
- Side-by-side comparison: `/Users/mac/Downloads/tica/design-qa-comparison.png`
- Desktop viewport/capture: 1672 × 921 CSS px at device scale 1; implementation image 1672 × 921 px.
- Mobile viewport/capture: 390 × 844 CSS px at device scale 1; implementation image 390 × 844 px.
- Comparison normalization: source scaled to 1672 px wide and vertically padded to 1672 × 921; implementation kept at 1672 × 921; both placed side by side.
- State: homepage hero with an active category slide, search controls collapsed, no modal or menu open.

## Full-view comparison evidence

The implementation keeps the source's centered framed composition, photographic focus, rounded container, compact search placement, and generous white surround. It intentionally retains Tica's dynamic category slider, category-specific messaging, brand navy, and `#FE9B59` accent rather than copying the reference's blue action or static content.

## Focused region comparison evidence

- Hero composition: centered heading, copy, and search align cleanly over a readable darkened image.
- Search: keyword, city, and category are consolidated into one 64 px desktop control; mobile reflows to 48–52 px stacked touch targets.
- Category rail: remains horizontally scrollable and touch-friendly; active state uses the Tica accent.
- Slider controls: remain visible without competing with the primary search task.

## Findings

- No actionable P0/P1/P2 findings remain.
- Fonts and typography: Avenir-first stack with Plus Jakarta Sans fallback; heading and body hierarchy remain legible over every slide checked.
- Spacing and layout rhythm: desktop frame, centered vertical rhythm, search spacing, and mobile stack are balanced with no page-level horizontal overflow.
- Colors and visual tokens: 60/30/10 balance is maintained with navy/photo as the dominant layer, off-white controls, and orange reserved for active/primary states.
- Image quality: existing category assets remain sharp, properly cropped, and use the product's real slider data.
- Copy and content: dynamic category titles, descriptions, links, city choices, and event categories remain intact.

## Comparison history

1. Initial desktop pass found a P2 category-rail issue: centered overflow clipped the first and last categories. Fixed by left-aligning the scroll track and preserving horizontal scrolling. Post-fix desktop evidence shows the first category fully visible and the remaining rail reachable by scroll.
2. Initial mobile pass verified the layout at 390 × 844. Search fields stack without overflow, all primary controls meet a practical touch height, and the category rail remains swipeable.
3. Slider interaction was tested by selecting `Arts & Culture`; the hero heading, description, link, selected tab, and background changed together. Automatic slide rotation also remained active.
4. Browser console was checked after interaction: no errors or warnings.

## Primary interactions tested

- Category-tab selection and synchronized hero content/background update.
- Automatic category-slide rotation.
- Search keyword, location, and category controls rendered and remained accessible at desktop and mobile breakpoints.
- Search and slider controls retain visible focus/selected states.

## Follow-up polish

- P3: a subtle edge affordance could be added later to make horizontal category scrolling more discoverable on narrow desktop widths.

final result: passed

---

## Editorial homepage redesign — selected City Guide direction

**Source visual truth**
- `/Users/mac/.codex/generated_images/01a1889c-e383-7792-a5d8-2f352f66c102/exec-27d52b44-a294-4431-8a63-1178e2314dee.png`
- The existing production hero was explicitly preserved; the selected reference governs the content hierarchy below it.

**Implementation evidence**
- Live browser target: `http://127.0.0.1:8000/`.
- Desktop verification viewport: 1728 × 996 CSS px at device scale 2.
- Browser-rendered captures were inspected for the hero transition, event/editorial region, category/city region, and organizer/newsletter/footer region. The browser capture API did not expose persistent filesystem paths.

**Layout and visual comparison**
- Replaced the duplicated event rails with one editorial trending composition and one chronological agenda, both driven by the existing Laravel event and ticket data.
- Preserved the current cinematic hero, search controls, category tabs, slider navigation, imagery, and header without redesigning them.
- Reworked category discovery into a compact seven-card visual ribbon and city discovery into a four-card grid.
- Moved the organizer conversion banner after city discovery, followed by a slim newsletter panel and the restrained site footer.
- Applied the selected reference's white editorial canvas, navy type hierarchy, restrained blue labels, orange conversion actions, fine rules, and generous section spacing.

**Responsive and accessibility verification**
- Desktop document-level horizontal overflow: none.
- Responsive breakpoints provide stacked trending cards, compact agenda rows, horizontally swipeable category/city ribbons, a stacked organizer banner, and full-width newsletter controls at narrow widths.
- Interactive cards remain semantic links; agenda dates use `time`; decorative organizer imagery is ignored by assistive technology; lazy loading and async decoding are retained for below-fold images.
- Reduced-motion behavior remains covered by the existing reveal system.

**Findings and fixes**
- [Resolved P1] A nested Blade comment prematurely exposed a retired event grid and caused an undefined-variable error. Replaced the comment wrapper with a guaranteed non-rendering condition and rebuilt the Blade cache.
- [Resolved P1] An inline Blade PHP assignment prevented the remainder of the view from compiling. Replaced it with a full PHP directive block.
- [Resolved P2] Agenda descriptions exposed encoded HTML entities. Added entity decoding before truncation.
- [Resolved P2] The global organizer banner duplicated the homepage conversion CTA. Suppressed the global banner on the index route only.
- No browser console errors remain; the only observed messages are the existing service-worker and development-tool informational logs.

**Functional verification**
- Required headings present: `Trending near you`, `Happening next`, `Find your kind of experience`, `Events in your city`, `Bigger events. Real people.`
- Retired headings absent: `TRENDING NOW`, `HAPPENING NOW`, and `More events you’ll love`.
- Real event, category, city, organizer, search, and newsletter routes remain connected.
- Blade templates cached successfully. Existing PHP 8.5 dependency deprecation notices are unrelated to this redesign.

final result: passed

---

## Refinement pass — homepage hero image fade

**Source visual truth**
- `/Users/mac/Downloads/458af175467e6a04cefac580fae2dcfb.webp`
- Reference behavior: photography remains legible across the upper and middle hero, then dissolves through a dark tonal gradient into the following page content instead of ending at a hard image boundary.

**Implementation evidence**
- Browser-rendered homepage: `http://127.0.0.1:8000/`
- Verified viewport: 1728 px wide.
- State: live category hero with the existing Tica photography, copy, discovery controls, progress indicators, and slider navigation.

**Findings and fixes**
- [Resolved P2] The hero previously ended as a framed image with a visually abrupt lower edge. The image and overlay now extend to the section boundary, retain rounded corners only at the top, and transition through staged Tica navy values into the content below.
- [Resolved P2] A simple uniform dark overlay would have flattened the photographs. The new overlay keeps the upper and middle image readable, strengthens contrast behind the discovery controls, and concentrates the deepest navy at the bottom.
- [Resolved P3] The first events section previously began as a separate light block. Its background now continues the same navy before easing into the light event surface, making the hero and event discovery feel like one composition.
- The effect uses the existing real category photography; no generated or replacement imagery was introduced.

**Responsive and interaction verification**
- Desktop hero navigation advanced from category index 6 to index 0 and updated the heading from `Gather together. Leave inspired.` to `Feel the sound. Live the moment.`
- Desktop document width matched the 1728 px viewport with no horizontal overflow.
- The same fade structure is defined for tablet and mobile breakpoints, with a stronger mobile contrast ramp to keep text and controls legible over cropped photography.
- Browser console contained no errors; only the expected service-worker registration log was present.
- Blade templates cached successfully. Existing PHP dependency deprecation notices are unrelated to this visual change.

final result: passed

---

# Design QA — Simplified Event Detail Page

**Source visual truth**
- `/Users/mac/Downloads/Screenshot 2026-10-30 at 9.25.54 AM.png`
- Source pixels: 1434 × 1416.
- Source intent: full-width event art followed by a restrained two-column information layout, a compact ticket selector, separate About / Date & Time / Venue groups, and a quiet neutral palette.

**Implementation evidence**
- Route: `http://127.0.0.1:8000/event/freedom-to-speak-%28i-can-explain%29/342`
- Browser-rendered implementation captures: in-app browser tab 45, captured inline during this QA pass; the browser provider does not expose a filesystem screenshot path.
- Desktop comparison viewport: 1440 × 1000 CSS px, device scale 1.
- Mobile comparison viewport: 390 × 844 CSS px, device scale 1.
- State: page start, ticket quantities at zero for visual comparison; first Access ticket selected for interaction verification.
- Density normalization: source and implementation were compared at device scale 1. The source was browser-fitted inside the 1440 px comparison viewport; the implementation was captured at the same CSS width.

## Full-view comparison evidence

The source and implementation were emitted together in one comparison pass. The implementation reproduces the source's primary structure: event artwork first, event identity below, event information in a wider left column, ticket selection in a narrower right column, and separate About / Date & Time / Venue surfaces. Tica's existing header, footer, real event data, related events and organizer callout are retained as product requirements outside the reference crop.

## Focused region comparison evidence

- Header region: the event title, host, share/save actions and ticket card align along one clear horizontal band on desktop.
- Ticket selector: white surface, subtle separators, compact quantity controls, visible remaining stock, total and a single primary checkout action match the source's density and hierarchy.
- Information cards: About, Date & Time and Venue use the source's simple light surfaces and restrained borders instead of the previous dark/shouty styling.
- Poster: the real portrait poster is preserved in full against a softened version of its own artwork because the supplied event asset is portrait while the reference image is landscape.
- Mobile: the poster becomes a square framed stage, the ticket panel moves into the reading flow, controls remain touch-friendly, and no fixed action covers content.

## Required fidelity surfaces

- **Fonts and typography:** the site's existing clean sans-serif is retained. Heading weight, compact ticket labels, body line-height and title wrapping match the reference's hierarchy without importing a conflicting type system.
- **Spacing and layout rhythm:** desktop uses a controlled 1.7 / .82 column split, 22–38 px component gaps and 16 px card radii. Mobile collapses to one column without horizontal overflow.
- **Colors and visual tokens:** neutral white/soft-gray surfaces dominate; navy is reserved for global navigation/footer; `#FE9B59` is limited to action and emphasis states. Green is used only as a secure/verified semantic cue.
- **Image quality and asset fidelity:** no generated imagery or placeholders were introduced. The real event poster is shown fully with a softened backdrop, preserving all poster text and artwork.
- **Copy and content:** live event title, host, description, date, time, venue, policy, ticket names, prices and remaining inventory are retained.

## Findings

- No actionable P0/P1/P2 findings remain.
- P3: a landscape-specific event hero asset would produce a closer full-bleed match to the reference; the current portrait-aware containment is preferable to cropping important poster information.
- P3: the embedded map may briefly show its neutral loading surface on slow connections; Directions remains available as a resilient fallback.

## Comparison history

1. Initial implementation retained the previous dark ticket panel and mixed information hierarchy. Rebuilt it as a light, compact ticket selector and separated About / Date & Time / Venue into clear cards.
2. First visual comparison found two P2 issues: the portrait poster was severely cropped inside the wide hero and an advertisement interrupted the information flow. Fixed by preserving the full poster within a blurred event-art stage and removing the ad from this detail route.
3. Desktop comparison showed the same primary proportions and scan order as the reference. Product-specific header/footer and related events were retained intentionally.
4. Mobile comparison initially used a wide poster crop. Changed the mobile stage to a square composition so the real portrait poster remains readable without increasing page width.
5. Ticket controls were given ticket-specific accessible names. Related-event arrows were given meaningful labels.
6. The stale “No event content found” session warning was cleared on successful event responses, and the chat widget was removed from this conversion-focused page.
7. Final mobile verification at 390 × 844 found no horizontal overflow. Selecting one Access ticket changed its quantity from 0 to 1 and enabled “Continue to checkout”.
8. Browser console was checked after the interaction: no errors or warnings.

## Primary interactions tested

- Incrementing a ticket quantity.
- Total/checkout state synchronization.
- Description expand/collapse control rendered with its state semantics.
- Responsive reflow at desktop and 390 px mobile.
- Embedded map and external Directions fallback.

## Follow-up polish

- P3: provide organizers with an optional landscape cover image distinct from the ticket poster for stronger hero compositions.

final result: passed

---

## Refinement pass — carousel edge blur and light-card body

**Source visual truth**
- `/Users/mac/Downloads/Screenshot 2026-10-30 at 3.26.37 PM.png` — clipped outer card and arrow-edge problem.
- `/Users/mac/Downloads/Screenshot 2026-10-30 at 3.25.32 PM.png` — disconnected white card body and oversized action area.

**Implementation evidence**
- Browser-rendered homepage inspected in the in-app browser at desktop width.
- The current three-event `Happening Now` rail is scrollable and both arrow controls remain visible.
- Computed edge treatment: 112 px overlay, `blur(5px) saturate(.88)`, 16% translucent white surface, and a soft 30 px transition shadow.

**Findings and fixes**
- [Resolved P2] The first and last cards looked abruptly cropped at the carousel boundary. Added a pointer-transparent backdrop-blur layer behind each navigation arrow only when the rail can scroll. The wider softened overlay now obscures truncated text and poster edges without blocking card links.
- [Resolved P2] The lower card area appeared as a detached white rectangle. Unified the light card into a single `#f7f8fb` surface with an 18 px radius, subtle border and shadow, more deliberate title/metadata spacing, and a contained `#eef1f5` action footer.
- [Resolved P3] Ticket actions lacked visual integration. Increased the button's touch height to 38 px and added a restrained accent shadow while keeping urgency text secondary.

**Responsive verification**
- The blur layers are desktop-only; mobile keeps direct horizontal swiping and unobscured card content.
- The blur overlays use `pointer-events: none`, while arrows retain the top interaction layer.
- Blade templates cached successfully.

final result: passed

---

## Refinement pass — strict five-position carousel composition

**Source visual truth**
- `/Users/mac/Downloads/Screenshot 2026-10-30 at 10.13.24 AM.png`
- Source pixels: 1032 × 410.
- Target composition: five poster-led cards, largest center card, medium inner cards, faded/clipped outer cards, and circular edge arrows.

**Implementation evidence**
- Browser-rendered homepage inspected in the in-app browser at the default desktop viewport and at 1032 px reference width.
- State: `Happening Now` with three real upcoming database events; no generated or duplicated event images.
- Current browser screenshot was captured in-app. The browser screenshot API did not expose a persistent filesystem path for this capture.

**Full-view comparison evidence**
- Poster proportions now follow the tall reference silhouette and the cards are materially larger than the previous implementation.
- The carousel is initialized with its middle event centered.
- Circular previous/next controls overlay the left and right poster edges, matching the reference's control placement.
- With the current three-event fixture, all posters remain sharp. At five or more events, the middle card becomes the largest, its two neighbors use medium emphasis, and only the two outer positions receive fade, desaturation, and soft blur.

**Focused region comparison evidence**
- Desktop cards: approximately 346 px wide in the current 1170 px rail; the center scales to 1.08 while the two supporting cards remain full-opacity because the catalogue has fewer than five items.
- Mobile: 270 px swipe cards, no blur, no transform, no page overflow, and edge arrows hidden in favor of touch scrolling.

**Findings**
- No actionable P0/P1/P2 mismatch remains for the available three-event state.
- The exact five-card visual state cannot be populated from the current database without duplicating or fabricating events, which was intentionally avoided.
- Typography, white background, poster crop, card spacing, arrow shape, image sharpness, and copy hierarchy align with the supplied reference while preserving Tica's event metadata and ticket actions.

**Interaction verification**
- Next-arrow navigation moved the desktop rail from scroll position 31.5 to 63.
- Desktop arrows are visible because the enlarged rail overflows.
- Mobile responsive state was verified at 390 × 844 with no document-level horizontal overflow.
- Blade templates cached successfully.

final result: passed

---

## Refinement pass — upcoming-event rail

**Behavior verified**
- `Happening Now` displays the complete upcoming event collection loaded for the homepage, ordered chronologically rather than limited to the current calendar week.
- The section copy is `Discover events, spots and plans happening around you`.
- The rail uses only each event's existing database thumbnail; no generated or substitute artwork is introduced.
- With five events, the center card receives the strongest scale and saturation, its two neighbors are medium emphasis, and the two outer cards are smaller, faded, and softly blurred.
- Desktop arrows appear only when the five-card rail overflows and can actually scroll. Mobile keeps equally sized, sharp cards with native horizontal swiping.

**Live-data verification**
- Current fixture/date: 30 October 2026.
- All active upcoming homepage events render in chronological order, with arrow controls enabled only when the rail overflows.
- The center/near/edge hierarchy remains data-driven; no fake events were added for visual testing.
- Blade templates cached successfully. Existing PHP dependency deprecation notices remain unrelated to this change.

final result: passed

---

## Refinement pass — category picker and event depth

**Additional source visual truth**
- `/Users/mac/Downloads/Screenshot 2026-10-30 at 2.36.48 PM.png` — documents the oversized native category menu failure.
- `/Users/mac/Downloads/Screenshot 2026-10-30 at 10.13.24 AM.png` — documents the intended carousel hierarchy: strong central imagery with smaller, faded edge cards.

**Additional implementation evidence**
- Mobile category picker: `/Users/mac/Downloads/tica/design-qa-category-mobile.png` at 390 × 844 px/CSS px, device scale 1.
- Desktop carousel depth: `/Users/mac/Downloads/tica/design-qa-rail-depth.png` at 1672 × 921 px/CSS px, device scale 1.

**Findings and fixes**
- [Resolved P1] Native category select escaped the hero and overlaid the next section. Replaced it with an app-owned listbox: contained white surface, orange selected row, check state, keyboard navigation, Escape support, outside-click dismissal, and a scrolling mobile menu that expands in normal flow.
- [Resolved P2] Event cards had nearly equal emphasis. Added center-distance depth states: the center card is full-size and saturated; edge cards scale down, desaturate, and fade. With three events, both outside cards deliberately use the edge treatment. Mobile keeps uniform cards for legibility and predictable swiping.

**Interaction verification**
- Opened and closed the custom category picker at desktop and mobile widths.
- Selected `Arts & Culture`; the visible label and hidden form value updated, and the picker collapsed.
- Verified the category picker no longer overlaps `Trending now` on mobile.
- Verified desktop event cards render with a bold center card and smaller faded ends.
- Browser console after interactions: no errors or warnings.

final result: passed
