# Changelog

## 0.9.0 — 2026-09-12

- Finishing a game now reports the completion to the teacher's Class Progress
  panel, which turns that student green with the game's title. While a game is
  being played the page also beats a presence signal, so the student reads as
  working; the beat starts on the first card touched, not on page load, and
  stops the moment the board is finished.
- The reporting is optional in every direction. TBT Notes owns the routes and is
  checked by class, not by plugin file: with Notes inactive the keys are absent,
  nothing is sent, and the game behaves exactly as it did in 0.8.2. A failed
  request is swallowed — reporting is a side effect of playing, never a gate on
  it — and nothing sent identifies anybody, the server taking the user from the
  session.
- One page, one pulse: a lesson carrying several games beats once, not once per
  game, and each game reports its own title when it is finished. **Shuffle &
  restart** followed by a second finish does not report twice.

## 0.8.2 — 2026-09-11

- The library title reads **Your games** again, not **YOUR GAMES**: the theme
  uppercases headings site-wide and the Admin Bar title now opts out.

## 0.8.1 — 2026-09-11

- The library header takes the shared Admin Bar layout: a thin line runs through
  the row from **Your games** to **Create new game**, and the search field, the
  level filter and the button are fixed at 300, 300 and 250px. The bar now lines
  up with the same row in the other tools instead of being sized by whatever the
  page gave it. The button lost its drop shadow, at rest and on hover.
- Below about 1100px the lines step aside and the search and level move to a
  second line, as before.
- An administrator opening an empty library no longer sees the empty state when
  there are games to show: the check that decides the first paint now counts the
  same games the list itself will load, which for an administrator is every
  teacher's, not only their own.
- Two internal names tidied: the visually-hidden label class is
  `.tbtmg-sr-only` everywhere now, matching the player's stylesheet, and a
  comment in the games controller quotes the search placeholder as it actually
  reads.

## 0.8.0 — 2026-09-11

- The library header is one row now: **Your games**, the search field, the level
  filter and **Create new game**, in that order, the same shape Swipe has. The
  stacked arrangement spent three rows saying what one can — a title, then two
  labelled fields, then a button pushed to its own line — and the list itself
  started halfway down the page.
- The field labels are still there for a screen reader; on screen the search
  field says what it does. It reads **Search by game or topic**, which is what
  the index has always matched.
- Clearing a search no longer means selecting the text and deleting it. A ×
  appears in the field as soon as there is something to clear, Escape empties it
  from the keyboard, and `/` puts the cursor in it from anywhere on the page —
  except inside the create dialog, where `/` is just a slash.
- While a search or a level is active the library says how much it is hiding:
  **3 of 14 games**, with **Clear filters** beside it. Deleting or duplicating a
  game while filtered keeps that second number honest.
- A teacher with no games yet no longer gets a search bar for an empty library.
  The count comes from the server, so the bar is absent from the first paint
  rather than appearing and then vanishing.

## 0.7.1 — 2026-08-21

- Fixed Create new sending a teacher back to the catalogue. On a library page
  that does not also hold the generator, the current-page fallback resolved to
  the library itself, so naming a game created the draft and then reloaded the
  page it was started from. The generator never opened, and the teacher was
  left looking at a pairless draft they appeared not to have asked for.
- The current-page fallback is now withheld when the page demonstrably has no
  generator on it, which is what 0.7.0 already promised: with no generator URL
  resolved, Create new does not render rather than rendering a button that goes
  nowhere useful. **A two-page arrangement needs the `generator` and `library`
  attributes set** — see the README. Nothing about a shared page changes.
- The absence of a generator is only trusted when the library shortcode is
  visible in the same `post_content`, which is what proves that content
  produced the page. A page assembled where the plugin cannot read it — a Divi
  Library layout, a Theme Builder template — keeps the old fallback rather than
  losing a working button to a guess.

## 0.7.0 — 2026-08-21

- The library and the generator are meant to live on two pages now. Sharing one
  made the page two things at once — a catalogue of what exists and a workspace
  for what does not yet — and the library had to give up its hero to the
  generator to avoid saying the same thing twice. Split apart, each page is one
  thing: a catalogue, and a workspace that is only about the game named in its
  hero.
- Two shortcode attributes wire them together. `[tbt_matching_games
  generator="/create-a-game/"]` is where Edit and Create new send the teacher;
  `[tbt_matching_generator library="/my-games/"]` is where Back to library and a
  completed discard send them back. Both take an absolute URL or a
  site-root-relative path. The site owner edits Divi pages, not PHP, so this is
  an attribute rather than a settings screen, and the existing
  `tbt_matching_games_generator_url` filter still overrides it for anyone using
  it.
- With neither attribute set the plugin behaves exactly as 0.6.0 did: both
  shortcodes work on one shared page, edit links point at the current page, and
  neither the back link nor discard renders. That is what makes this release
  deployable before the second Divi page exists.
- Creating a game is now a deliberate act. Create new opens a dialog asking for
  the title and nothing else — no level, no visibility, no settings — and
  creating it lands the teacher on the generator, editing that draft. The title
  field counts down from the 30 characters the hero can hold, because that cap
  is a layout constraint and a teacher should see the boundary rather than find
  it by having a title truncated. The dialog traps focus, closes on `Esc` or a
  backdrop click, returns focus to the button that opened it, and refuses to
  submit twice.
- The CEFR picker stays where it is, in Stage 1. The level is a generation
  parameter that travels with the topic and the pair count to the button that
  consumes it; the dialog exists to name the object and bring it into being.
- Discard game removes an abandoned draft and returns to the library. It appears
  only on a draft: deleting a published game is the library's job, where a
  teacher can see it leaving the whole collection, and discard is here to undo a
  creation rather than to duplicate delete. It also stays hidden when no library
  URL resolves, since discarding the game being edited with nowhere to go would
  leave the teacher on a page about a game that no longer exists.

## 0.6.0 — 2026-08-21

- Library search now finds a game by its topic, not only its title. The
  placeholder has promised "Title or topic" since the field was added, but the
  search ran through `WP_Query`'s `s` parameter, which reaches `post_title` and
  nothing else — topic lives inside `_tbtmg_game_data`, a single serialised
  array that `meta_query` cannot see into. The promise now holds.
- Two flat meta keys make the blob queryable. `_tbtmg_search` holds the
  lowercased title and topic; `_tbtmg_level` holds the CEFR band. They are a
  derived index written only by `Search_Index` — `_tbtmg_game_data` stays
  canonical, and every read still goes through `Game_Repository::get()`.
- The index is lowercased rather than transliterated, and the search term is
  lowercased the same way, so matching is case-insensitive without depending on
  the database collation and a Polish topic keeps its diacritics: `ćwiczenie`
  stays `ćwiczenie` and is found by typing it.
- Existing games are indexed in batches of 100 on `admin_init`, drafts and
  trashed games included, until a pass finds nothing left to do. A large library
  finishes over several admin page loads instead of timing out on one. This is
  an index, not a schema change: there is no database version to bump.
- A level filter sits beside the search field: All levels, the six bands, and
  Not set. A game made before the level picker existed has no level and keeps
  saying so — nothing is inferred from its pairs or its topic — so Not set
  returns exactly those games. Filter and search combine, and either resets the
  list to the first page.
- Library rows show the band as a chip beside the status badge. It is neutral
  rather than blue: the level is a fact about the game, and blue belongs to
  things a teacher can act on. An unlevelled game shows no chip at all.

## 0.5.0 — 2026-08-21

- Stage 1 now asks how hard the language should be. Six CEFR tiles, A1 to C2,
  sit between the pair count and the additional instructions. Until now the only
  way to ask for a difficulty was to write "B2" into the topic field, and a bare
  band label means whatever the model last read it to mean, so the same topic
  came back at wildly different levels from one generation to the next.
- Each band carries concrete rules rather than a label: a vocabulary and grammar
  ceiling, an item-length range, what the pairing is meant to test, and the kind
  of subject matter that belongs at that level. The topic range matters as much
  as the grammar — when only the grammar moves, two adjacent bands come out
  indistinguishable. The rules go to the model as a tagged <LEVEL> section, and
  the developer prompt now says plainly that the band is a constraint on the
  model's own writing, never something to mention to a student.
- The level never overrides a teacher's own request. When the topic or the
  additional instructions name specific target items, those items are kept
  exactly as given and the band shapes only the definitions and contexts written
  around them. And the one-correct-partner rule outranks the whole level
  section: at C1 and C2 the instruction to draw fine distinctions pulls directly
  against the ban on ambiguous matches, and ambiguity is the one defect a
  matching game cannot survive.
- The picker opens where a teacher left it. A successful generation records the
  band against the user, the same way the generation counter is recorded, so a
  failed API call never moves it. A saved game records the band it was generated
  at and reopens on it for editing. Games saved before this release record no
  band at all and keep saying so rather than claiming they were made at B1.
- The picker is a group of real radios, hidden from sight but not from the
  keyboard, so arrow-key navigation and the single tab stop come from the
  browser rather than from script, and the focus ring lands on the tile a
  teacher can actually see.
- Library rows lead with Open. Looking at a game before a lesson is the thing
  teachers do most often, and it used to take three actions — expand Share, find
  the link, click it. Open appears on published games only, because a draft
  permalink 404s for teacher and student alike.

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
