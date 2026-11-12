# Homepage UI/UX Audit and Improvement Report

## Audit scope

Public Tica homepage at desktop 1280 × 720 and mobile 390 × 844. The user goal is to discover a relevant event quickly, understand what is available, and move into event details or organizer signup without unnecessary scanning.

## Evidence

1. Before — desktop: `/Users/mac/Downloads/tica/audit-uiux/01-home-desktop.png`
2. Before — mobile: `/Users/mac/Downloads/tica/audit-uiux/02-home-mobile.png`
3. Improved — mobile: `/Users/mac/Downloads/tica/audit-uiux/03-home-mobile-improved.png`
4. Improved — desktop: `/Users/mac/Downloads/tica/audit-uiux/04-home-desktop-improved.png`
5. Same-input mobile comparison: `/Users/mac/Downloads/tica/audit-uiux/mobile-before-after.png`

## Step review

### 1. Hero and event search — healthy

- Strength: the main promise, city choices, search field, category selector, and primary action are visible together.
- Improvement: added an accessible search label while preserving the compact visual treatment.
- Remaining limit: screenshots cannot prove full screen-reader behavior or form error announcements.

### 2. Trending and This Week — healthy

- Strength: the dark-to-light layered composition gives featured events a clear hierarchy.
- Improvement: increased carousel controls from 36px to 44px and strengthened metadata contrast.
- Content constraint: only three upcoming events are currently available, so the two layers repeat real events rather than inventing placeholder content.

### 3. Explore categories — improved and healthy

- Before: seven full-width mobile cards created a long, repetitive scroll.
- After: a two-column grid preserves imagery and labels while cutting the section height substantially.
- Accessibility: card links now receive a visible lime focus outline.

### 4. Organizer and city discovery — improved and healthy

- Strength: the organizer promotion has a clear single CTA and good contrast.
- Improvement: city cards now use a two-column mobile layout, reducing scanning distance without shrinking labels below a practical size.

### 5. Newsletter and footer — improved and healthy

- Strength: the newsletter remains visually distinct and the email field has an explicit label.
- Improvement: footer navigation uses two columns on mobile while brand and contact information retain full width.

## Measured outcomes

- Mobile page height: reduced from 7,523px to 5,621px (about 25%).
- Document-level horizontal overflow: 0px at desktop and mobile.
- Carousel control target: 44px.
- Mobile categories: two columns.
- Mobile cities: two columns.
- Browser console: no errors or warnings in the fresh final state.

## Accessibility evidence limits

The audit confirms visible contrast improvements, focus styling, labeled search input, target sizing, semantic headings, and responsive reflow. It does not claim full WCAG compliance; screen-reader announcements, complete keyboard order, zoom behavior above 200%, and form error recovery still need dedicated testing.

## Final verdict

The homepage is materially easier to scan on mobile, keeps the classic layered desktop composition, and has no actionable P0/P1/P2 design issues remaining.
