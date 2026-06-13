# Skill: PHP Class & AJAX Architecture

Server-side organization and WordPress + Elementor hooks.

## Entry Point (`live-copy.php`)

Load order:
```php
require: class-live-copy-db.php, -settings.php, -rest.php, class-live-copy.php
register_activation_hook → Live_Copy_DB::create_table
plugins_loaded           → Live_Copy_DB::maybe_upgrade   (option-version compare)
Live_Copy_Rest::init()                                    (REST routes)
admin_menu               → Live_Copy_Settings::register_admin_page
admin_enqueue_scripts    → Live_Copy_Settings::enqueue_admin_assets
wp                       → Live_Copy::enqueue_assets       (front-end, gated)
new Live_Copy()                                            (AJAX hooks, always)
```

`Live_Copy` is instantiated unconditionally because `admin-ajax.php` never fires the `wp` hook — AJAX registration must be load-time.

## Constants

| Constant | Value |
|----------|-------|
| `LIVE_COPY_VER` | version string |
| `LIVE_COPY_PATH` | `plugin_dir_path(__FILE__)` |
| `LIVE_COPY_URL` | plugin root URL |
| `LIVE_COPY_ASSETS_URL` | `LIVE_COPY_URL . 'assets/'` |
| `LIVE_COPY_ADMIN_URL` | `LIVE_COPY_URL . 'admin/build/'` |

## Classes (all namespace `ElementorLiveCopy`)

```
Live_Copy            (class-live-copy.php)       — front-end enqueue + AJAX
Live_Copy_Settings   (class-live-copy-settings.php) — options + admin page
Live_Copy_Rest       (class-live-copy-rest.php)  — REST: settings, stats
Live_Copy_DB         (class-live-copy-db.php)    — history table + stats
```

For Settings/Rest/DB details see `admin-reporting.md`.

## `Live_Copy` Detail

```
__construct()                — registers ellc_get_data + legacy ellc_copy_data (auth+nopriv)
static enqueue_assets()      — gated by Settings::is_enabled_for_current_user()
                               + wp_is_mobile() skip; then hooks enqueue methods
enqueue_styles()             — style.css
enqueue_scripts()            — script.js + localize ElLiveCopyData
enqueue_editor_scripts()     — editor.js (paste interceptor)
handle_get_data()            — unified AJAX handler (copy + download)
get_live_copy_data_settings() — Elementor doc lookup (guards class_exists)
find_element_recursive()     — match top-level element by id
```

### AJAX Handler Security (order matters)

```php
public function handle_get_data() {
    // 1. CSRF nonce — fail returns code 'invalid_nonce' + 403 (client refreshes & retries).
    if ( ! wp_verify_nonce( $_REQUEST['_wp_nonce'] ?? '', 'el-live-copy-nonce' ) ) {
        wp_send_json_error( ['code'=>'invalid_nonce', ...], 403 );
    }
    // 2. sanitize: post_id absint, widget_id/action_type sanitize_text_field; action_type allowlist
    // 3. bail if post_id|widget_id empty
    // 4. get_post() exists && status in [publish, private]
    // 5. private → require is_user_logged_in() (else 403)
    // 6. fetch → log → wp_send_json_success(['widget'=>$result])
}
```

**Nonce + cache.** Nonce IS verified. A full-page-cached HTML can serve a stale
nonce → 403; the front-end fetches a fresh one from REST `live-copy/v1/nonce`
(public, no-cache) and retries once. Endpoint only returns public published-page
JSON (private require login), so nonce = CSRF/anti-abuse, not the sole gate.

### Element lookup is recursive

`find_element_recursive()` does a **depth-first** search into each node's child
`elements` array (buttons attach to nested containers, so a top-level-only scan
would miss them). Returns the raw element node; the wrapper
(`type`/`siteurl`/`elements`) is built once in `get_live_copy_data_settings()`.
ID compare is `(string)` cast on both sides (all-digit ids stay strings).

## Response Shape

**For Copy Action:**
```json
{ "success": true, "data": { "widget": {
  "type": "elementor",
  "siteurl": "https://source-site.com/wp-json/",
  "elements": [ { ...elementor node... } ]
} } }
```

**For Download Action:**
```json
{ "success": true, "data": { "widget": {
  "version": "0.4",
  "title": "Live Copy Element",
  "type": "section",
  "content": [ { ...elementor node... } ]
} } }
```

## Adding New AJAX Actions

```php
// __construct():
add_action('wp_ajax_ellc_new_action',        [$this, 'handle_new']);
add_action('wp_ajax_nopriv_ellc_new_action', [$this, 'handle_new']);
```
Always prefix `ellc_`. Sanitize every `$_REQUEST` value. End with `wp_send_json_*` + `wp_die()`.

## PHP Coding Standards (`phpcs.xml`)

- Short array `[]` only — `array()` forbidden
- Yoda conditions: `'private' === $x`
- Strict comparisons `===` / `!==`
- Namespace `ElementorLiveCopy`, text domain `live-copy`
- Lint: `phpcs` · auto-fix: `phpcbf`
- Direct DB queries (`Live_Copy_DB`) are prepared + format-typed; table name interpolated only after `$wpdb->prefix`.
