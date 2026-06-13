# Skill: Copy / Download Flow (End-to-End)

Full data path when a user clicks "Live Copy" or "Download" on a section.

## Overview

```
Hover element → buttons appear → click → AJAX POST (action_type) → PHP validates
  → Elementor JSON fetched → DB log → response → clipboard OR .json download
```

## 1. Element Selection (`src/js/script.js` → `globalSelector`)

Targets **top-level** containers/sections:

```js
'[data-elementor-type="wp-page"] > [data-element_type="container"]'
'[data-elementor-type="wp-post"] > [data-element_type="container"]'
'[data-elementor-type="wp-page"] > [data-element_type="section"]'
'[data-elementor-type="wp-post"] > [data-element_type="section"]'
// fallback:
'.elementor-section.elementor-top-section'
```

Mobile guard runs first: if `ElLiveCopyData.disable_mobile` and (`innerWidth <= 768` or touch), the whole script exits.

## 2. Button Injection (`copyBtn`)

Reads `ElLiveCopyData.show_copy` / `show_download`. Injects only enabled buttons:

```html
<div class="ellc-magic-copy-wrapper">
  <a class="ellc-copy-btn">Live Copy</a>      <!-- if show_copy -->
  <a class="ellc-download-btn">Download</a>   <!-- if show_download -->
</div>
```

Eligibility unchanged: inside `wp-page`/`wp-post`, is element/section/container, parent not `.magic-button-disabled-yes`, has inner content.

## 3. AJAX Request (`fetchData` shared helper)

Both buttons call one helper. On a `403` / `invalid_nonce` response it calls
`refreshNonce()` (GET `live-copy/v1/nonce`, uncached), updates `ElLiveCopyData.nonce`,
and retries **once** (`isRetry` guard prevents loops). This is what keeps copy/download
working on cached pages. Action is `ellc_get_data` (legacy `ellc_copy_data` still mapped):

```js
$.ajax({
  url: ElLiveCopyData.ajax_url,        // admin-ajax.php
  type: 'POST',
  data: {
    action:      'ellc_get_data',
    widget_id:   $parent.data('id'),   // Elementor element ID, e.g. "b0ec141"
    post_id:     ElLiveCopyData.post_id,
    _wp_nonce:   ElLiveCopyData.nonce,
    action_type: 'copy' | 'download',  // determines DB log category
  }
})
```

## 4. PHP Handler (`Live_Copy::handle_get_data`)

Registered for auth + nopriv on both `ellc_get_data` and legacy `ellc_copy_data`.

Validation order:
1. **Verify nonce** (`wp_verify_nonce($_REQUEST['_wp_nonce'], 'el-live-copy-nonce')`) — fail → `wp_send_json_error(['code'=>'invalid_nonce'], 403)`
2. `post_id` via `absint`, `widget_id` + `action_type` via `sanitize_text_field`
3. `action_type` allowlisted to `['copy','download']` (default `copy`)
4. Bail if `post_id` or `widget_id` empty
5. `get_post()` must exist and status in `['publish','private']`
6. `private` posts require `is_user_logged_in()` (else 403)
7. Fetch data → on success, `Live_Copy_DB::log(...)` → `wp_send_json_success`

**Nonce + cache:** the nonce baked into a full-page-cached HTML can go stale. The
client recovers automatically (see step 3 below). The endpoint only returns the
public Elementor JSON of published pages (private require login), so the nonce is
CSRF + anti-abuse — not the sole access gate.

## 5. Elementor Data Retrieval (`get_live_copy_data_settings`)

Guards `class_exists('\Elementor\Plugin')` first (graceful if Elementor inactive).

```php
$page_meta = Plugin::$instance->documents->get($post_id);
$meta_data = $page_meta->get_elements_data();   // flat array
$widget    = $this->find_element_recursive($meta_data, $widget_id);
```

`find_element_recursive` matches top-level `$element['id']`, returns:

```php
[ 'type' => 'elementor', 'siteurl' => get_rest_url(), 'elements' => [$element] ]
```

## 6. Activity Logging (`Live_Copy_DB::log`)

Every successful copy/download writes one row: `page_id`, `page_slug` (from `$post->post_name`), `section_id` (widget_id), `action_type`, `user_id`, `ip_address` (REMOTE_ADDR), UTC `created_at`. Powers the Reports tab. See `admin-reporting.md`.

## 7. Client Output

- **copy** → write `JSON.stringify(widget)` to a temp off-screen `<textarea>` + `execCommand('copy')`
- **download** → `Blob` of pretty-printed JSON → `<a download="live-copy-{id}.json">` → click → `revokeObjectURL`

Buttons are SVG icons (no text). Feedback is a tooltip via `flashTip()`: loading = icon pulse (`.ellc-loading`), success = check icon + green `Copied!`/`Downloaded!` bubble (~1.7s), error = `Failed`. See `frontend-ui.md`.

## Key Constraints

- `widget_id` = Elementor element ID string, not a WP post ID.
- Opt-out: class `magic-button-disabled-yes` on the Elementor wrapper.
- Asset loading can be disabled per page/context via the `live_copy_should_load` filter (return false). No hardcoded page exclusions.
- **Specific Section Mode** (setting): when on, buttons attach only to elements with class `ellc-enabled` — added by the per-element "Show Live Copy Button" switcher in the Elementor Advanced tab (`ellc_enable` control → `mark_enabled_element` render attribute).
- Visibility gate (`everyone`/`logged_in`/`editors`) is enforced server-side in `enqueue_assets` — assets simply don't load for disallowed users.
