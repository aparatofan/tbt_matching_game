# TBT Matching Games

TBT Matching Games is a WordPress plugin for creating editable, AI-assisted matching games. Each game has one canonical WordPress entry that can be opened at its own permalink or embedded with a shortcode.

## MVP features

- Custom **Matching Games** post type
- AI generation from a topic and a selected size of 4–12 pairs
- Editable title, instructions, column labels, completion text, and every pair
- Add, delete, and reorder pairs
- Draft and published workflows
- Shortcode: `[tbt_matching_game id="123"]`
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
- The route requires a WordPress REST nonce and the configured capability, `manage_options` by default.
- All game data is validated and sanitised on the server.
- Generated game text is stored and rendered as plain text, not HTML.
- A simple per-user generation throttle defaults to ten requests per five minutes.
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
