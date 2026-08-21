# TBT Matching Games

TBT Matching Games is a WordPress plugin for creating editable, AI-assisted matching games. Each game has one canonical WordPress entry that can be opened at its own permalink or embedded with a shortcode.

## MVP features

- Custom **Matching Games** post type
- AI generation from a topic and a selected size of 4–12 pairs
- Editable title, instructions, column labels, completion text, and every pair
- Add, delete, and reorder pairs
- Draft and published workflows
- Shortcode: `[tbt_matching_game id="123"]`
- Front-end teaching tools: `[tbt_matching_generator]` and `[tbt_matching_games]`
- Standalone public game URL
- Dragging in either direction
- Click and keyboard matching
- Correct snap/lock behaviour and incorrect jitter feedback
- Attempts, completion message, shuffle and restart
- Multiple independent games on one page
- Responsive and reduced-motion support
- Server-side OpenAI connection; the API key is never sent to the browser

## Requirements

- WordPress 6.4+
- PHP 8.0+
- An OpenAI API key for generation

The interactive games continue to work without OpenAI after they have been generated and saved.

## Installation

1. Download or build `tbt-matching-games.zip`.
2. In WordPress, open **Plugins → Add Plugin → Upload Plugin**.
3. Upload the ZIP and activate **TBT Matching Games**.
4. Open **Matching Games → Add New**.

## OpenAI API key

The MVP deliberately does not store the key in the WordPress database. Define it in a secure existing snippet or in `wp-config.php`.

Preferred plugin-specific constant:

```php
define( 'TBT_MATCHING_GAMES_OPENAI_API_KEY', 'sk-...' );
```

The plugin also recognises:

```php
define( 'OPENAI_API_KEY', 'sk-...' );
```

Or provide the key through a filter:

```php
add_filter(
    'tbt_matching_games_openai_api_key',
    function () {
        return 'sk-...';
    }
);
```

Never put the API key in JavaScript, a shortcode, a page, or a public repository.

## Optional model configuration

The default model is `gpt-5-mini`. Override it with:

```php
define( 'TBT_MATCHING_GAMES_OPENAI_MODEL', 'gpt-5-mini' );
```

or:

```php
add_filter(
    'tbt_matching_games_openai_model',
    function () {
        return 'gpt-5-mini';
    }
);
```

## Creating a game

1. Open **Matching Games → Add New**.
2. Enter a topic.
3. Choose 4–12 pairs.
4. Choose the language level.
5. Add optional instructions about pair structure or anything else the topic
   does not cover.
6. Select **Generate game**.
7. Review and edit every field.
8. Save as a draft or publish.
9. Copy the generated shortcode or use the standalone permalink.

Generation never publishes automatically.

### The language level

Stage 1 of the front-end generator offers six CEFR bands — A1, A2, B1, B2, C1
and C2 — as a row of tiles between the pair count and the additional
instructions. Each band is more than a label: it sets a vocabulary and grammar
ceiling, an item-length range, what the pairing is meant to test, and the kind
of subject matter that belongs at that level, and those rules are sent to the
model rather than the bare band name.

The picker opens on the last band the teacher generated at, or B1 on a first
run. Only a successful generation updates that memory, so a failed API call
never moves it. A saved game records the band it was generated at and reopens on
it when the game is edited; games saved before this feature existed record no
band and fall back to the teacher's own default.

The level shapes the language the model writes, never the teacher's own request:
when the topic or the additional instructions name specific target items, those
items are kept exactly as given and the band shapes only the material written
around them.

## Shortcode

Basic use:

```text
[tbt_matching_game id="123"]
```

Optional display attributes are already supported:

```text
[tbt_matching_game id="123" show_title="no" show_instructions="yes" compact="yes"]
```

## Teaching tools on the front end

Two shortcodes put the whole authoring flow on the public site, so teachers never need
wp-admin. Both are gated: logged-out visitors get a login prompt, logged-in users without
access get an upsell, and no tool markup is rendered for either.

```text
[tbt_matching_generator]
[tbt_matching_games]
```

`[tbt_matching_generator]` generates, edits and saves a game. It edits an existing game when
the page is opened with `?game_id=123`, which is what the library's Edit links do.

### Two pages, or one

The two surfaces are designed to live on separate pages: the library is a catalogue of what
exists, the generator a workspace for one game. Each takes the other's URL as an attribute,
so the arrangement is set by editing the Divi pages rather than by editing PHP.

**On two pages the attributes are required.** A bare `[tbt_matching_games]` on a page of its
own has no way to know where the generator lives, so Create new does not render at all. Set
both:

```text
On the library page:    [tbt_matching_games hero="yes" generator="/create-a-game/"]
On the generator page:  [tbt_matching_generator library="/my-games/"]
```

Substitute your own page paths. Using the plugin's own example, a site with the generator at
`https://example.com/make-a-game/` and the library at `https://example.com/my-games/` sets
`generator="/make-a-game/"` on the library page and `library="/my-games/"` on the generator
page — each names the *other* page, never itself.

`generator="…"` is where the library's **Create new** and per-row **Edit** send the teacher.
`library="…"` is where the generator's **Back to library** link and a completed **Discard
game** send them back. Both accept an absolute URL or a site-root-relative path; anything
else is ignored. A generator URL that already carries a query string keeps it — `game_id` is
appended rather than substituted.

**Omitting both attributes preserves the single-page behaviour exactly.** On a page holding
both shortcodes, Edit and Create new point at that page, which is correct; with no `library`
set, neither the back link nor Discard game renders, because there is nowhere for them to go
and browser history is not a substitute. Nothing has to change on an existing installation.

That current-page fallback applies only where it can be right. When the page holds
`[tbt_matching_games]` and no generator, no generator URL resolves, so Create new is not
offered — a button that returned the teacher to the catalogue they clicked it from would be
worse than no button. The check reads `post_content`; a page assembled somewhere the plugin
cannot read it, such as a Divi Library layout or a Theme Builder template, keeps the
fallback rather than losing a working button to a guess. `tbt_matching_games_generator_url` still overrides
the generator URL for anyone already filtering it: it is applied last, over whatever the
attribute resolved to.

The generator opens with the canonical Tool Hero. The library defaults to no hero: the two
originally shared a page, and a second hero would only repeat the first. Either shortcode
can be told otherwise — `hero="no"` suppresses the generator's, `hero="yes"` gives a
library on its own page one of its own:

```text
[tbt_matching_generator hero="no"]
[tbt_matching_games hero="yes"]
```

```php
add_filter(
	'tbt_matching_games_hero',
	function ( $hero, $context ) {
		// $context is 'generator' or 'library'.
		return array(
			'eyebrow' => 'THE BLUE TREE',
			'title'   => 'library' === $context ? 'MOJE GRY' : 'GRA W DOPASOWANIE',
			'support' => 'Stwórz grę dla swojej klasy',
		);
	},
	10,
	2
);
```

The page background is the Style Book tool canvas, `#F6F8FC`. To make the white stage
cards stand out more, override the token from a snippet rather than editing the plugin:

```css
:root { --tbt-canvas: #F0F5FE; }
```

The tool pages follow The Blue Tree Style Book v1.0 with two deliberate exceptions —
buttons use the Swipe pill and the library spine uses the Learn English domain colour —
and canonical tokens declared in a `tbt-defaults` cascade layer, so a site-wide token file or a snippet setting
`--tbt-blue` on `:root` overrides them and the plugin still renders canonically on its
own. Roboto, Roboto Slab and Roboto Mono arrive on the font request the plugin already
makes; content the teacher authors is set in Roboto Slab and interface chrome in Roboto.

`[tbt_matching_games]` lists the games the current teacher owns, with server-side search,
pagination, and per-row Edit, Share, Duplicate and Delete. Share shows the public link, a QR
code rendered as the panel opens, and the embed shortcode.

Search covers both the game title and its topic, and is case-insensitive without losing
diacritics — a search for `Ćwiczenie` and one for `ćwiczenie` return the same games. Beside
the search field, a level filter narrows the list to one CEFR band, or to `Not set` for the
games made before the level picker existed. Search and filter combine, and either one resets
the list to page 1.

### Creating and discarding

**Create new**, beside the search field, opens a dialog that asks for the game title and
nothing else — no level, no visibility, no settings. Confirming it creates a draft owned by
the current teacher and opens the generator on that game. The field is capped at 30
characters with a live count of what is left, because that cap is what the player's hero can
hold rather than an arbitrary limit. The dialog traps focus, cancels on `Esc` or a click on
the backdrop, and returns focus to the button that opened it; a failed create leaves the
teacher in the dialog with the error rather than on a blank generator page. The button does
not render when no generator URL resolves, since there would be nowhere to land — on two
pages, that means setting `generator="…"`.

The CEFR level stays in Stage 1 of the generator, where the Generate button consumes it. It
is a generation parameter, not part of naming the game.

**Discard game** appears at the foot of the generator, and only while the game being edited
is a draft. It moves the draft to the trash and returns to the library. A published game is
deleted from the library instead, where the row shows what is being removed; discard exists
to undo an abandoned creation, not to duplicate delete. Like the back link, it does not
render without a library URL. Ownership is enforced by the REST route, so it can never reach
another teacher's game.

Access requires the `tbt_use_teaching_tools` capability, granted on activation to the
administrator role and to every role listed in TBT Swipe's `tbt_swipe_manager_roles`
option. A user holding Swipe's own `tbts_manage` capability is also allowed, so a teacher
who can reach Swipe can reach this tool with nothing to configure. Beyond that, grant the
capability to a role or wire a membership check through
`tbt_matching_games_can_use_tools`:

```php
add_filter(
	'tbt_matching_games_can_use_tools',
	function ( $allowed, $user_id ) {
		return $allowed || my_membership_is_active( $user_id );
	},
	10,
	2
);
```

Saving decides the status: a game with 4–12 complete pairs publishes, anything less is kept
as a draft with the validation message, and the teacher's work is never discarded. Ownership
is `post_author`; a published game is deliberately viewable by anyone holding the link, since
students scan a QR code without logging in.

### REST routes

All routes live under `tbt-matching-games/v1`, require a `wp_rest` nonce in `X-WP-Nonce`, and
are scoped to the games the current user owns. An administrator may pass `author=0` to `GET
/games` to see every game; nobody else can widen the scope.

| Method | Route | Purpose |
|---|---|---|
| POST | `/generate` | Generate content without saving it (`topic`, `pair_count`, `additional_instructions`, `level`) |
| GET | `/games` | List own games (`search`, `level`, `page`, `per_page`, `status`) |
| POST | `/games` | Create |
| GET | `/games/{id}` | Read one |
| PUT | `/games/{id}` | Update |
| POST | `/games/{id}/duplicate` | Copy as a draft owned by the current user |
| DELETE | `/games/{id}` | Move to trash (never a force delete) |

### AI usage limits

Successful generations are counted per user, per day, in the `tbtmg_gen_count_{Y-m-d}` user
meta. The site-wide default comes from the `tbtmg_max_generations_per_day` option (20; `0`
means unlimited) and one teacher can be given a different limit through the user meta key of
the same name. A failed API call never consumes quota.

## Theme override

A theme may override the standalone template by adding:

```text
your-theme/tbt-matching-games/single-game.php
```

The plugin fallback template remains available when no override exists.

## Filters

- `tbt_matching_games_openai_api_key`
- `tbt_matching_games_openai_model`
- `tbt_matching_games_generation_capability`
- `tbt_matching_games_can_use_tools`
- `tbt_matching_games_upsell_html`
- `tbt_matching_games_generator_url`
- `tbt_matching_games_hero`
- `tbt_matching_games_tool_roles`
- `tbt_matching_games_openai_endpoint`
- `tbt_matching_games_openai_timeout`
- `tbt_matching_games_generation_limit`
- `tbt_matching_games_generation_window`
- `tbt_matching_games_default_settings`
- `tbt_matching_games_game_data`

## Actions

- `tbt_matching_games_before_generate` — `( string $topic, int $pair_count, string $additional_instructions, int $user_id, string $level )`
- `tbt_matching_games_after_generate`
- `tbt_matching_games_before_render`
- `tbt_matching_games_after_render`
- `tbt_matching_games_after_save`

## Security notes

- The browser sends generation requests only to a protected WordPress REST route.
- The route requires a WordPress REST nonce and the `tbt_use_teaching_tools` capability. A site that sets `tbt_matching_games_generation_capability` narrows it further.
- All game data is validated and sanitised on the server.
- Generated game text is stored and rendered as plain text, not HTML.
- AI generation is capped per user per day (20 by default), counted only on success.
- Draft and private games are not rendered publicly.

### Derived search index

Library search and the level filter read two flat post meta keys, written only by
`Search_Index`:

| Key | Contents |
|---|---|
| `_tbtmg_level` | The CEFR band, or `''` for a game with no level |
| `_tbtmg_search` | The lowercased title and topic, space-joined |

They exist because `meta_query` cannot reach inside `_tbtmg_game_data`, which is a single
serialised array. They are a derived index and never a source of truth: `_tbtmg_game_data`
remains canonical and every read goes through `Game_Repository::get()`. The index is
rewritten on every save and on duplication, and an install that predates it is filled in
batches on `admin_init` until the `tbtmg_index_version` option catches up.

## Uninstalling

Deactivation never deletes games.

Uninstall preserves all game data unless this constant is explicitly set before uninstalling:

```php
define( 'TBT_MATCHING_GAMES_DELETE_DATA', true );
```

## Development checks

Run PHP syntax checks:

```bash
find . -name '*.php' -not -path './vendor/*' -print0 | xargs -0 -n1 php -l
```

Run JavaScript syntax checks:

```bash
node --check assets/js/admin.js
node --check assets/js/game.js
node --check assets/js/tools.js
```

See `tests/manual-test-checklist.md` for WordPress and browser acceptance testing.

## Packaging

From the parent directory of the plugin folder:

```bash
zip -r tbt-matching-games.zip tbt-matching-games \
  -x 'tbt-matching-games/.git/*' \
     'tbt-matching-games/dist/*' \
     'tbt-matching-games/tests/browser/.playwright/*'
```

## Licence

GPL-2.0-or-later.
