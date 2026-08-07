# Changelog

## 0.3.1 — 2026-08-07

- Fixed teachers being locked out of the tools. A role granted TBT Swipe's
  `tbts_manage` capability now reaches the matching game as well, and the
  plugin's own capability is granted on upgrade to every role Swipe already
  trusts.
- Buttons follow the Swipe pill: fully rounded, uppercase and tracked.
- The game library's row spine uses the Learn English domain colour, and keeps
  it while the rest of the row highlights on hover.
- Widened the hero's eyebrow-to-title spacing to match the player's hero.
- The Wording stage now explains what it is for.

## 0.3.0 — 2026-08-07

- Aligned the front-end tool pages with The Blue Tree Style Book v1.0.
- Fixed destructive and error affordances being painted in the Learn English
  domain colour (`#660000`) instead of the error red (`#C62828`). Delete
  buttons and error notices were carrying a content-identity colour.
- Replaced the near-duplicate local colours with the canonical Style Book
  tokens, declared in a cascade layer so a site-wide token file or a one-line
  snippet overrides them while the plugin stays canonical on its own.
- Added the canonical Tool Hero to the generator and the library, carrying the
  white TBT mark, with copy settable through the new `tbt_matching_games_hero`
  filter and a `hero="no"` shortcode attribute for pages that already have one.
- The tool pages now sit on an edge-to-edge pale canvas with no inset panel.
- Generator sections are numbered stages with the Swipe blue top rule, and the
  game title moved into the first stage.
- The game library follows the Swipe deck list: section head, compact rows and
  a blue spine on the leading edge.
- Applied the Style Book typography split: Roboto Slab for content the teacher
  authors, Roboto for interface chrome, Roboto Mono for the hero identity.
  Roboto is added to the font request the plugin already makes.
- Generator panels are now stage cards and library rows are object cards, on
  the pale tool canvas, with spacing from the shared scale.
- Corrected two drifted tokens in the player's stylesheet (`muted`, `border`).

## 0.2.0 — 2026-08-07

- Added `[tbt_matching_generator]`, a front-end generator so teachers build and edit games
  without ever seeing wp-admin.
- Added `[tbt_matching_games]`, a front-end library of the teacher's own games with search,
  pagination, edit, duplicate, delete and sharing.
- Added sharing with a QR code that renders as soon as the panel opens, the public game link,
  and the embed shortcode, all with copy buttons.
- Added a REST CRUD API at `tbt-matching-games/v1/games` — list, create, read, update,
  duplicate and trash — scoped to the games a teacher owns.
- Added the `tbt_use_teaching_tools` capability and a single filterable access gate, so a
  membership or WooCommerce check can be wired in without the plugin depending on either.
- Saving now decides the status: a complete game publishes, an incomplete one is kept as a
  draft with the validation message, and never loses the teacher's work.
- Replaced the AI generation throttle with a per-user counter, a site-wide default and
  per-user overrides, matching TBT Swipe.
- Fixed the admin publish guard forcing every REST-created game to draft.
- Front-end tools ship their own CSS and JS; `admin.css` and `admin.js` never load publicly.

## 0.1.1 — 2026-08-07

- Fixed a drag ghost and card highlight that could stay on screen after a drag ended.
- Drag now ignores a second simultaneous pointer instead of orphaning the first drag.
- Drag events no longer depend on pointer capture surviving; lost capture, cancelled
  gestures, context menus, and window blur all end the drag cleanly.
- The drag ghost is only created once a drag actually starts, so a plain click no longer
  builds one.

## 0.1.0 — 2026-07-29

- Added the Matching Games custom post type.
- Added editable game metadata and pair management.
- Added secure OpenAI Responses API generation with strict structured output.
- Added shortcode and standalone public rendering from one canonical game.
- Added drag, click, keyboard, restart, attempts, and completion interactions.
- Added responsive, accessible, reduced-motion-aware front-end styles.
- Added validation, publish guarding, throttling, uninstall protection, and documentation.
