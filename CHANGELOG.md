# Changelog

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
