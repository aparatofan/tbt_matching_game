# Changelog

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
