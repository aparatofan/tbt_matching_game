# Changelog

## 0.3.7 — 2026-08-20

- The share panel no longer shows a QR code. The game is desktop-only, so a code
  meant to be scanned with a phone pointed students at a surface the game does
  not run on. The panel now offers just the two things that work on a desktop:
  the game link and the lesson shortcode.
- Removed the vendored QRCode library and its script registration, since nothing
  loads it any more, along with the now-unused "Students scan this to play"
  string and the matching CSS.

## 0.3.6 — 2026-08-13

- When the result overlay clears, both columns slide into alphabetical order by
  the left-hand term, so row *n* on the left is the partner of row *n* on the
  right. A finished board used to keep the shuffle it was played in, which is
  the one order nobody can read down; now it settles into a reference table a
  teacher can walk through with the class.
- The cards move rather than being rebuilt: each one is measured, appended into
  its new place, then slid from where it was, with a 30ms stagger down the rows
  so both columns cascade together. Rebuilding would have been simpler and would
  have dropped every listener bound when the card was created.
- Connections are cleared before the slide and drawn again after it. They are
  positioned against the board, so left in place they would stay pinned where
  the cards used to be. The record of which lines have already animated is
  deliberately kept, so they reappear against the new positions instead of
  replaying the draw for a match the learner made minutes ago.
- Sorting uses the visitor's own collation rather than a hardcoded locale, so
  Polish diacritics land where a Polish reader expects them.
- Under `prefers-reduced-motion` the reorder still happens, without the slide.

## 0.3.5 — 2026-08-13

- Finishing a game now raises a result overlay over the board itself instead of
  revealing a green panel underneath it. The old panel sat below the fold on a
  tall game, so the game scrolled itself into view to show it, pulling the
  finished board off screen at the exact moment the class wanted to look at it.
  The overlay floats over the board, holds for five seconds with a draining
  timer bar, and fades out on its own; a tap, click, `Enter` or `Escape`
  dismisses it early. Matched cards are `disabled` by then, so the keyboard
  route matters — without it the overlay would be a dead end.
- The completion heading and message are still the per-game fields a teacher
  edits; nothing about their defaults or their wording changed. The overlay
  carries a `data-state="success"` hook so a second variant can be added later
  without restructuring the markup.
- The overlay blurs the board behind it. This is the only use of blur in the
  TBT system and is a deliberate exception: the finished board stays legible
  through the panel, which is the point of holding it on screen at all.
- Removing the old panel also removes the last two hardcoded hex values in
  `game.css`, `#166534` and `#3f6250`, which had survived the 0.3.4 token
  sweep. Every colour in the new block comes from the shared vocabulary.

## 0.3.4 — 2026-08-12

- The playable game surface now takes its design tokens from TBT-Hub's shared
  `tbt-tokens` stylesheet. `game.css` had been declaring twelve private
  `--tbtmg-*` colour and elevation variables, which the shared vocabulary
  forbids by name; eleven held values byte-identical to the canonical tokens,
  so removing them changes nothing on screen. The twelfth, the raised shadow,
  moves from a slate-grey tint at 8% to the canonical blue tint at 12% — the
  hero and cards get a very slightly bluer, slightly stronger elevation, which
  is the intended system behaviour and the only visible change in this release.
- `tbt-tokens` is now a hard dependency of the game stylesheet, and
  `enqueue_game()` registers the shared sheet the same way the tools path
  already did. Without this the tokens would never be present on a page that
  only renders a game.
- Restored the bundled `assets/vendor/tbt/` fallback, which 0.3.3 intended to
  ship but never actually stored: an unanchored `vendor/` pattern in
  `.gitignore` matched it at any depth and silently dropped it from the
  repository. With the file absent, the shared handle pointed at a URL that
  404s whenever TBT-Hub was inactive, leaving the teaching tools unstyled. The
  ignore rule is now anchored to the repository root.

## 0.3.3 — 2026-08-11

- The teaching tools now take their design tokens from TBT-Hub's shared
  `tbt-tokens` stylesheet instead of defining a private copy inside
  `tools.css`. The two were identical, so nothing changes on screen; what
  changes is that a future edit to the shared palette reaches this plugin
  instead of silently passing it by.
- A bundled fallback copy in `assets/vendor/tbt/` keeps the tools rendering
  correctly when TBT-Hub is inactive. It is registered under the shared handle
  only when Hub has not already registered it, so a page carrying both a
  matching game and another TBT tool still loads exactly one copy.

## 0.3.2 — 2026-08-07

- The game library no longer renders a hero of its own. It normally shares a
  page with the generator, whose hero already owns the page identity, so the
  second one only repeated it. `hero="yes"` brings it back for a library on a
  page of its own.
- After a game is saved, the save button becomes "Generate new game", which
  reloads the generator ready for the next one. Editing anything afterwards
  brings the save button back, so a follow-up correction can still be saved.

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
