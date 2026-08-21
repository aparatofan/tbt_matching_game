# TBT Matching Games — Claude Code guidance

Keep this file concise. It is loaded at the start of every Claude Code session.

## Start here

- Work from the task, not from a full-repository scan.
- First inspect `git status`, then use targeted search for the relevant class, selector, route, hook, or template.
- Read only the files and sections needed for the task.
- Do not read `README.md` or `CHANGELOG.md` by default; consult them when deeper product/history context is needed.
- Do not repeatedly reread large files already understood; inspect the diff after edits instead.
- Do not change unrelated code, formatting, copy, or styling.

## Project basics

- WordPress plugin; WordPress 6.4+, PHP 8.0+.
- PHP + vanilla JavaScript; no application build step is required.
- Bootstrap: `tbt-matching-games.php`.
- Server-side logic: `includes/`.
- Rendered views: `templates/`.
- Browser assets: `assets/`.
- Manual acceptance guidance: `tests/manual-test-checklist.md`.

## Architecture to preserve

- Each game has one canonical WordPress entry. The standalone permalink and `[tbt_matching_game id="..."]` render that same game rather than parallel copies.
- `[tbt_matching_generator]` and `[tbt_matching_games]` are the teacher-facing authoring/library surfaces.
- A saved game remains playable without OpenAI; AI is for generation, not runtime play.
- Ownership is `post_author`. Front-end CRUD is owner-scoped; do not weaken ownership checks for convenience.
- REST routes under `tbt-matching-games/v1` require the WordPress REST nonce and appropriate access checks.
- `_tbtmg_level` and `_tbtmg_search` are a derived index maintained by `Search_Index`; `_tbtmg_game_data` remains canonical and the index is never a source of truth.
- Generation must not publish automatically. Incomplete work may be preserved as draft rather than discarded.
- Draft/private games are not public; published games are deliberately accessible to students who have the link.

## Shared TBT design system

- TBT-Hub is the canonical owner of the shared `tbt-tokens` handle.
- This plugin may use its vendored fallback under `assets/vendor/tbt/` only when Hub has not registered the shared handle.
- Vendored shared-token copies must remain byte-identical to the Hub original. Do not customize the fallback copy for this plugin.
- Plugin-specific presentation belongs in this plugin's own CSS, not in a forked shared vocabulary.
- Preserve the existing documented TBT Style Book exceptions unless a task explicitly revisits them.

## Security rules

- Never expose or commit OpenAI keys, credentials, `.env` data, or real configuration values.
- Keep AI requests server-side.
- Preserve nonce, capability, ownership, validation, and sanitization checks on authoring routes.
- Generated/stored game text is plain content, not trusted HTML.
- Do not make draft/private content public to solve a rendering problem.

## Coding style

- Follow surrounding WordPress/PHP style and existing naming rather than reformatting whole files.
- Prefer small local changes and existing helpers over new abstractions for one-off behavior.
- Preserve multiple-games-on-one-page isolation when changing front-end JavaScript.
- Respect keyboard interaction and `prefers-reduced-motion` when changing game behavior or animation.
- Avoid adding dependencies when the existing PHP/vanilla-JS stack can do the job.

## Validation

For PHP changes, run syntax checks across the plugin:

```bash
find . -name '*.php' -not -path './vendor/*' -print0 | xargs -0 -n1 php -l
```

For JavaScript changes, run the relevant checks:

```bash
node --check assets/js/admin.js
node --check assets/js/game.js
node --check assets/js/tools.js
```

Use `tests/manual-test-checklist.md` for behavior that needs WordPress/browser verification. State clearly what still needs a live Divi/WordPress check.

## Git and deployment

- Do not commit directly to `main`; use a focused feature branch unless explicitly instructed otherwise.
- Before finishing, inspect the final diff for accidental unrelated changes.
- A code push to `main` triggers the FTPS deployment workflow.
- Markdown-only and `.github/**` changes are ignored by the automatic deploy trigger.
- Never change deployment secrets or FTP paths unless the task is specifically about deployment.

## Context discipline

- Prefer targeted search + narrow reads over broad repository exploration.
- Summarize command output instead of pasting long logs when a short result is enough.
- At task completion, report what changed, what was checked, and any remaining manual verification briefly.
- For a new unrelated task, prefer a fresh Claude Code session over carrying a long old conversation forward.
