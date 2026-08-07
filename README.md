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
4. Add optional instructions about language, level, or pair structure.
5. Select **Generate game**.
6. Review and edit every field.
7. Save as a draft or publish.
8. Copy the generated shortcode or use the standalone permalink.

Generation never publishes automatically.

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

Both pages open with the canonical Tool Hero. Its copy is filterable, and a page that
already has a hero of its own can suppress it:

```text
[tbt_matching_generator hero="no"]
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

The tool pages follow The Blue Tree Style Book v1.0: canonical tokens declared in a
`tbt-defaults` cascade layer, so a site-wide token file or a snippet setting
`--tbt-blue` on `:root` overrides them and the plugin still renders canonically on its
own. Roboto, Roboto Slab and Roboto Mono arrive on the font request the plugin already
makes; content the teacher authors is set in Roboto Slab and interface chrome in Roboto.

`[tbt_matching_games]` lists the games the current teacher owns, with server-side search,
pagination, and per-row Edit, Share, Duplicate and Delete. Share shows the public link, a QR
code rendered as the panel opens, and the embed shortcode.

Access requires the `tbt_use_teaching_tools` capability, granted to the administrator role on
activation. Grant it to a teacher role, or wire a membership check through
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
| POST | `/generate` | Generate content without saving it |
| GET | `/games` | List own games (`search`, `page`, `per_page`, `status`) |
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

- `tbt_matching_games_before_generate`
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
