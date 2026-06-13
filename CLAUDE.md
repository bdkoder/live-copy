# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What This Plugin Does

WordPress plugin that adds **Copy** and **Download** buttons to Elementor sections/containers on the frontend. Clicking fires an AJAX request that fetches the element's raw Elementor JSON: **Copy** writes it to the clipboard, **Download** saves a `.json` file. Either way the user pastes/imports it into another site's Elementor editor — cross-domain design transfer without export/import plugins.

A React admin panel (Settings → Live Copy) controls visibility/permissions and shows analytics (copy/download counts by page and section) backed by a custom DB table.

## Commands

```bash
npm run dev         # watch: compile LESS + minify JS on save
npm run build       # production: grunt + admin React build + make-pot
npm run build:admin # build only the React admin app (Vite)
npm run make-pot    # regenerate languages/live-copy.pot (needs WP-CLI)
npm run zip         # build + package live-copy-v.X.Y.Z.zip
phpcs               # PHP lint (config auto-loaded from phpcs.xml)
```

No PHP/JS test suite exists (`npm test` exits with error by design).

## Build Pipeline (two systems)

Never edit compiled output directly.

- **Front-end** (Grunt): `src/less/style.less` → `assets/css/style.css` (+ `.rtl.css`); `src/js/script.js` → `assets/js/script.js` (Terser). Tasks: `less` → `rtlcss` → `terser`.
- **Admin** (Vite): `admin/src/*.jsx` → `admin/build/app.js` + `admin/build/style.css`.

Both `assets/` and `admin/build/` are committed so the shipped plugin needs no build step.

## Architecture

**Entry point**: `live-copy.php`
- Defines constants (`LIVE_COPY_VER`, `LIVE_COPY_PATH`, `LIVE_COPY_URL`, `LIVE_COPY_ASSETS_URL`, `LIVE_COPY_ADMIN_URL`)
- `register_activation_hook` → creates the history DB table; `plugins_loaded` → `Live_Copy_DB::maybe_upgrade()`
- `Live_Copy_Rest::init()` registers REST routes; `admin_menu`/`admin_enqueue_scripts` wire the settings page
- `wp` action → `Live_Copy::enqueue_assets()` (skips admin, gated by user permission and the `live_copy_should_load` filter)
- Instantiates `\ElementorLiveCopy\Live_Copy` unconditionally (AJAX hooks must register at load — `admin-ajax.php` never fires `wp`)

**Four PHP classes** (`includes/`, `namespace ElementorLiveCopy`):
- `Live_Copy` — front-end enqueue + unified `ellc_get_data` AJAX handler (copy + download). Validates post status/login, logs each action, returns Elementor JSON. Nonce intentionally unverified (cross-domain nopriv).
- `Live_Copy_Settings` — option `live_copy_settings`, permission gate, React admin page registration + enqueue.
- `Live_Copy_Rest` — `live-copy/v1` routes (`/settings` GET+POST, `/stats` GET), all `manage_options`.
- `Live_Copy_DB` — custom `{prefix}live_copy_history` table; `log()` + `get_stats()`.

**Frontend JS** (`src/js/script.js`): `El_Live_Copy` object — `globalSelector`, `copyBtn`, shared `_fetchData`, `copyData`, `downloadData`. Mobile guard exits early. Posts `action: ellc_get_data` with `action_type: copy|download`.

**Admin React app** (`admin/src/`): Vite + Tailwind SPA mounted in `#live-copy-admin-root`. Tabs: Settings (toggles + visibility) and Reports (stat cards, daily chart, top pages/sections). REST + `X-WP-Nonce`.

See `.ai/skills/admin-reporting.md` for the full admin/DB/REST detail.

## Detailed Skills

Deep-dive references in `.ai/skills/`:

| File | Covers |
|------|--------|
| `copy-flow.md` | End-to-end copy path: element selection → AJAX → Elementor API → clipboard |
| `frontend-ui.md` | Button HTML/CSS, hover states, `El_Live_Copy` JS object, `ElLiveCopyData` |
| `php-class.md` | Class structure, AJAX handler pattern, Elementor API, constants, coding standards |
| `build-pipeline.md` | Grunt tasks, LESS/JS compilation, zip release, version bump process |
| `feature-plan.md` | Prioritized roadmap: gap analysis, phased feature list, file structure plan |
| `admin-reporting.md` | React admin panel, REST routes, settings option, history table + stats |

## Important Notes

- **Nonce IS verified** in the `ellc_get_data` AJAX handler (`el-live-copy-nonce`). Cached pages can serve a stale nonce → 403; the front-end auto-fetches a fresh one from the public `live-copy/v1/nonce` REST route and retries once. Access is further gated by post-status (`publish`/`private`) + login for private posts. The endpoint only returns already-public page JSON.
- `find_element_recursive()` is genuinely recursive — buttons attach to nested containers, so a top-level-only scan would 404 them. Keep it recursive.
- To disable buttons on specific pages, use the `live_copy_should_load` filter (return false) — there are no hardcoded page exclusions.
- **Elementor-safe:** the plugin never fatals if Elementor is deactivated/deleted. `use Elementor\Plugin` is an alias only; `elementor/*` hooks no-op when the action never fires; `Plugin::$instance` is guarded by `class_exists('\Elementor\Plugin')`. With Elementor gone: no `[data-elementor-type]` nodes → no buttons; direct AJAX → graceful WP_Error; admin/reports still work.
- Legacy AJAX action `ellc_copy_data` is still registered (mapped to the same handler) for backward compatibility with older cached scripts. New code uses `ellc_get_data`.
- `specific_section_mode` is wired end-to-end: `ellc_enable` switcher in the Elementor Advanced tab → `ellc-enabled` class on render → JS attaches only to those elements when the setting is on.
- History table is pruned to 180 days by a daily cron (`live_copy_prune_history`, `Live_Copy_DB::RETENTION_DAYS`). All-time reports are bounded by this window.
- `uninstall.php` **keeps data by default**. It always clears the cron, but only deletes options + drops the history table when `LIVE_COPY_ALLOW_CLEAR` is defined truthy (same opt-in as the Clear-data action). Runs without plugin classes loaded, so names are hardcoded — keep them in sync with `Live_Copy_DB`/`Live_Copy_Settings` if renamed.
- **Clear data is constant-gated:** the Reports "Clear data" action (REST `/stats/clear`) is blocked unless `LIVE_COPY_ALLOW_CLEAR` is defined truthy in wp-config.php — both server-side (403) and in the UI (`canClear`). Prevents accidental wipes.
- **IP logging** honors the `ip_logging` setting (`full`/`anonymized`/`none`); default `anonymized` via `wp_privacy_anonymize_ip()`. Frontend JS strings are translatable through the `i18n` block in `ElLiveCopyData`.
- Reports page links (`page_url`/`page_title`) are derived at read time via `get_permalink`/`get_the_title` — not stored — so they stay accurate after slug edits.
- PHP coding standard: WordPress-Extra + WordPress-Core via PHPCS (`phpcs.xml`). Short array syntax `[]` enforced; long `array()` forbidden. Yoda conditions required. Strict comparisons (`===`/`!==`) enforced.
