# Tica Homepage Design Audit

Audit date: 31 October 2026  
Surface: `http://127.0.0.1:8000/`  
Viewport: 1728 × 996 CSS px

## Overall verdict

The homepage now has a coherent Tica palette and a useful event-discovery structure, but it still feels like several good components placed next to each other rather than one intentional journey. The largest improvements should come from simplifying the hero controls, tightening event-card hierarchy, replacing misleading city imagery, strengthening small-text contrast, and making the conversion area feel like a deliberate ending rather than an appended footer block.

## Step 1 — Hero and navigation

Evidence: `audit-home-01-hero.png`  
Health: Needs refinement

Strengths:
- Strong real photography, clear headline and recognizable Tica navy/orange palette.
- Search, location, category, slider and quick-category controls are all functional.
- The hero is contained cleanly and does not cause document-level horizontal overflow.

Findings:
- [P1] The hero asks users to process the same category choice twice: once in the search form and again in the chip row.
- [P1] The category label truncates to `All cat...` at a wide desktop viewport, weakening trust in the primary search control.
- [P2] The headline, search bar, chips, pagination and arrow controls compete within one image; the lower half feels crowded.
- [P2] Small white body text and chips rely heavily on the image overlay; contrast will vary by slide.
- [P2] The dark bottom treatment visually weighs down the hero transition and makes the following white section feel disconnected.

## Step 2 — Trending and upcoming events

Evidence: `audit-home-02-events.png`  
Health: Mostly healthy

Strengths:
- Real database events, dates, ticket availability and direct ticket actions are visible.
- The agenda is more scannable than another horizontal carousel.
- Primary orange actions are easy to identify.

Findings:
- [P1] The lead card is materially taller than the supporting cards without earning a different editorial role; it reads as an accidental crop mismatch.
- [P2] Card title sizes and image ratios change abruptly between columns.
- [P2] The action strip uses a second gray surface inside every card, creating unnecessary card-within-card treatment.
- [P2] Agenda metadata and descriptions are too small/light for comfortable reading, especially on lower-contrast displays.
- [P2] `Trending near you` and `Happening next` reuse the same three events, making the page feel repetitive.

## Step 3 — Category and city discovery

Evidence: `audit-home-03-discovery.png`  
Health: Needs refinement

Strengths:
- Category cards are compact, image-led and easy to scan.
- Section headings and links have a consistent hierarchy.

Findings:
- [P1] City cards use event posters rather than recognizable city photography, so the visual promise does not match the navigation destination.
- [P2] Category imagery varies in quality and subject relevance; several cards reuse nearly identical nightlife imagery.
- [P2] The section has large vertical gaps but very dense card rows, producing an uneven rhythm.
- [P2] Faded end cards look disabled until reveal motion completes; this is visually ambiguous.

## Step 4 — Organizer conversion, newsletter and footer

Evidence: `audit-home-04-conversion.png`  
Health: Mostly healthy

Strengths:
- Organizer and newsletter actions are clearly separated from attendee discovery.
- Footer grouping is simple and restrained.

Findings:
- [P1] The copyright renders as `Copyright ©{2025}` instead of a natural current-year statement.
- [P2] Newsletter body copy uses inconsistent title casing and reads like placeholder marketing text.
- [P2] The newsletter input is very wide relative to its content and visually dominates the nearby organizer CTA.
- [P2] Footer secondary text and social icons have weak contrast against navy.
- [P2] The organizer image is generic nightlife imagery rather than a clear organizer/creator moment.

## Highest-impact changes

1. Simplify the hero into one primary search action and one lightweight category navigation pattern.
2. Give the trending area one genuine feature card plus two clearly supporting cards, then exclude those events from the agenda list.
3. Replace event posters in the city section with real city photography.
4. Standardize event-card image ratios, metadata hierarchy and action placement.
5. Increase small-text contrast and minimum readable sizing across cards, agenda rows and footer.
6. Refine the organizer/newsletter ending and fix the copyright year output.

## Accessibility evidence limits

The screenshots support observations about visible contrast, density, hierarchy and apparent target sizing. They do not prove keyboard order, screen-reader announcements, zoom behavior, reduced-motion behavior or WCAG contrast ratios; those require interaction and code-level testing.

