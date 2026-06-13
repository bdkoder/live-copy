# Skill: Admin Settings & Reporting

React-based admin panel under **Settings → Live Copy** + analytics backed by a custom DB table.

## Stack

- React 18 + Vite + Tailwind (in `admin/`)
- Single bundle output: `admin/build/app.js` + `admin/build/style.css`
- Mounts into `<div id="live-copy-admin-root">` rendered by `Live_Copy_Settings::render_page()`
- All data via REST (`live-copy/v1`) with `X-WP-Nonce` auth

## Build

```bash
npm run build:admin      # from plugin root: installs admin deps + vite build
# or inside admin/:
cd admin && npx vite build      # production
cd admin && npx vite           # dev server
```

Output committed to `admin/build/` so the shipped plugin needs no build step.

## PHP Classes

### `Live_Copy_Settings` (`includes/class-live-copy-settings.php`)
- `OPT_KEY = 'live_copy_settings'` — single option array
- Keys: `enable`, `visibility` (everyone|logged_in|editors), `show_copy_btn`, `show_download_btn`, `specific_section_mode`, `disable_on_mobile`, `help_url`, `ip_logging` (full|anonymized|none, default anonymized)
- `help_url` (`esc_url_raw`) feeds the frontend info icon; sent to JS only when the `ellc_help_url` cookie is absent (see `frontend-ui.md`)
- `is_enabled_for_current_user()` — visibility gate used by frontend enqueue
- `register_admin_page()` — `add_options_page`, hook = `settings_page_live-copy-settings`
- `enqueue_admin_assets($hook)` — loads React bundle on that hook only, localizes `liveCopyAdmin` (restUrl, nonce, settings)

### `Live_Copy_Rest` (`includes/class-live-copy-rest.php`)
Namespace `live-copy/v1` (admin routes `manage_options`; `/nonce` public):

| Route | Method | Auth | Returns |
|-------|--------|------|---------|
| `/settings` | GET | manage_options | current settings array |
| `/settings` | POST | manage_options | saves + returns settings |
| `/stats?days=N` | GET | manage_options | aggregate stats (N=0 all-time, else 1–3650) |
| `/stats/clear` | POST | manage_options | wipes history — **also requires `LIVE_COPY_ALLOW_CLEAR` constant** (else 403) |
| `/export` | GET | manage_options | up to 5000 raw rows; client builds the CSV |
| `/nonce` | GET | **public** | `{ nonce }` — fresh `el-live-copy-nonce`, no-cache |

`canClear` (= `defined('LIVE_COPY_ALLOW_CLEAR') && LIVE_COPY_ALLOW_CLEAR`) is passed to the admin app via `liveCopyAdmin`; the Clear button is hidden (shown locked) unless set. Server enforces it too. IP logging respects the `ip_logging` setting (`wp_privacy_anonymize_ip` for anonymized; empty for none).

`/nonce` is intentionally public so cached-page copy/download can recover a valid CSRF token (`nocache_headers()` + `Cache-Control: no-store`).

### `Live_Copy_DB` (`includes/class-live-copy-db.php`)
Custom table `{prefix}live_copy_history`:

| Column | Type | Note |
|--------|------|------|
| `id` | bigint PK | |
| `page_id` | bigint | indexed |
| `page_slug` | varchar(200) | stored at action time |
| `section_id` | varchar(100) | Elementor element ID |
| `action_type` | varchar(20) | `copy` \| `download`, indexed |
| `user_id` | bigint nullable | |
| `ip_address` | varchar(45) | `REMOTE_ADDR`, per `ip_logging` setting |
| `created_at` | datetime | UTC, indexed |

- `create_table()` — `dbDelta`, run on `register_activation_hook`
- `maybe_upgrade()` — runs on `plugins_loaded` per site: version-compares the table AND ensures the prune cron is scheduled (covers multisite subsites the activation hook never touched)
- `log(...)` — `$wpdb->insert`; `get_ip()` returns `''` (none), masked (anonymized, `wp_privacy_anonymize_ip`), or raw (full) per setting
- `get_stats($days)` — returns `totals`, `top_pages`, `top_sections`, `daily`, `unique_pages`, `unique_sections`. All queries `$wpdb->prepare`'d.
- `prune($days = RETENTION_DAYS)` — deletes rows older than the window. `RETENTION_DAYS = 180`.
- `clear_all()` — deletes every row (Reports → Clear data). Returns count.
- `get_all_rows($limit)` — newest-first rows for CSV export (capped 50000).

**Page URL/title are derived, not stored.** `get_stats()` enriches each `top_pages`/`top_sections` row via `add_page_links()` → `page_url` (`get_permalink`) + `page_title` (`get_the_title`). Deriving keeps links accurate after slug/permalink edits; deleted pages resolve to empty `page_url` (rendered non-clickable). `top_sections` query selects `page_id` so the section's page can be linked.

### Retention Cron (`live-copy.php`)
- Activation schedules daily `live_copy_prune_history` (`Live_Copy_DB::CRON_HOOK`); `maybe_upgrade()` re-ensures it per site (multisite-safe)
- `add_action(CRON_HOOK, [Live_Copy_DB::class, 'prune'])`
- Deactivation `wp_clear_scheduled_hook` (table + data kept)

### Uninstall (`uninstall.php`)
- **Data-safe by default.** Always clears the cron; deletes options + drops the table ONLY when `LIVE_COPY_ALLOW_CLEAR` is defined truthy (same opt-in as Clear data).
- Runs without plugin classes — table/option names are hardcoded; keep in sync.

## React Files (`admin/src/`)

```
index.jsx          mount into #live-copy-admin-root
App.jsx            tab nav: Settings | Reports
api.js             apiFetch wrapper (getSettings/saveSettings/getStats/clearStats/exportRows)
index.css          tailwind + .lc-card/.lc-btn/.lc-toggle helpers
tabs/Settings.jsx  toggles + visibility + IP-logging selects, REST save
tabs/Reports.jsx   stat cards + daily bar chart + top tables + filter + Export CSV / Clear data
```

## Reporting UI (Reports tab)

- 4 stat cards: Total Copies, Total Downloads, Unique Pages, Unique Sections
- Daily stacked bar chart — **always last 30 days**, zero-filled by backend, bars flex to fill width (never scrolls), independent of the period filter
- Two tables: Top Pages (linked page), Top Sections (section_id + linked page)
- `PageLink` renders `page_title` (fallback slug/id) as a `target="_blank"` link to `page_url` with a `↗` glyph; non-clickable when url empty
- Period filter: 7 / 30 / 90 days / **All time** (All time = `days: 0`)
- **Export CSV** (always available) — fetches `/export`, builds the CSV client-side as a Blob download
- **Clear data** — shown only when `liveCopyAdmin.canClear`; otherwise rendered as a locked label with a tooltip pointing to the `LIVE_COPY_ALLOW_CLEAR` constant

### days=0 (All time) plumbing
- `getStats(0)` → REST `validate_callback` allows `>= 0`; callback uses `absint` (no `?: 30` falsy trap)
- `Live_Copy_DB::get_stats(0)` sets `$since = '1970-01-01 00:00:00'` so prepared queries stay unchanged
- Effective max history under All time is the 180-day retention window

## Security

- Every REST route gated by `current_user_can('manage_options')`
- Admin nonce = `wp_create_nonce('wp_rest')`, verified by WP core via `X-WP-Nonce`
- Settings save validates `visibility` + `ip_logging` against allowlists; all bools cast
- Stats `days` param `absint` + range-validated (0–3650; 0 = all time)
- Destructive ops double-gated by `LIVE_COPY_ALLOW_CLEAR` (server 403 + hidden UI)

## Behavior Notes

- `specific_section_mode` is **wired**: `Live_Copy` registers an `ellc_enable` switcher in the Elementor Advanced tab (section/column/container) and tags opted-in elements with class `ellc-enabled` via `elementor/frontend/before_render`. When the setting is on, JS attaches only to `.ellc-enabled` elements.
- History retention is **180 days** (`Live_Copy_DB::RETENTION_DAYS`), enforced by the daily `live_copy_prune_history` cron. To keep data longer, raise the constant; All-time reports are bounded by it.
