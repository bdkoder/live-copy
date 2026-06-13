# Skill: Feature Plan & Roadmap

Gap analysis + prioritized build plan for Live Copy. Never mention competitor names in code, comments, or UI strings.

## Current State (v1.1.0)

Shipped:
- Frontend hover **Copy** button → clipboard
- Frontend hover **Download** button → `.json` file (item 15, pulled forward)
- React admin panel under Settings → Live Copy (item 1)
- Permission controls: everyone / logged-in / editors (item 2)
- Mobile disable, JS + `wp_is_mobile()` (item 5)
- Hardened AJAX: post-status + login validation, input allowlisting (item 6, partial)
- Custom history table + Reports tab: copies/downloads, top pages, top sections, daily chart
- Page slug + page ID stored per action

Still missing:
- Per-section enable toggle (item 3 — `specific_section_mode` stored but not wired)
- Page/post duplicator (item 4)
- Inner-container visibility edge cases (item 7)
- History-table pruning / retention cron

## Feature Gaps vs Market (Priority Order)

### Phase 1 — Foundation Parity (build next)

These are table-stakes features that users expect. Ship these before marketing.

---

**1. Admin Settings Panel**
- Location: `Elementor > Settings > Live Copy Paste` tab (use `elementor/settings/controls/` action)
- Settings to expose:
  - Enable/disable magic copy buttons globally
  - Visibility: Everyone / Logged-in users only / Specific roles
  - Enable/disable page duplicator
  - Enable specific-section mode (per-element toggle in Elementor editor)
- Storage: `get_option('live_copy_settings', [])` — single serialized array
- Implementation: new `includes/class-live-copy-settings.php`

---

**2. User Permission Controls**
- Option A — Logged-in only: check `is_user_logged_in()` before calling `enqueue_assets()`
- Option B — Role-based: `current_user_can('edit_posts')` or custom capability check
- JS side: `ElLiveCopyData.enable` already exists — set it to `false` server-side when user lacks permission
- Visitor-facing: zero JS/CSS loaded for users without access (performance win)

---

**3. Per-Section Enable Toggle**
- Add custom Elementor control to section/container Advanced tab
- Hook: `elementor/element/section/section_advanced/before_section_end` + container equivalent
- Control: Toggle "Show Live Copy Button" (default: depends on global mode)
- Two modes controlled by global setting:
  - **Global mode**: buttons on all sections unless toggled OFF per-section
  - **Specific mode**: buttons only on sections toggled ON per-section
- Store as element custom data; read in JS via `data-live-copy-enabled` attribute on element

---

**4. Page / Post Duplicator**
- Add "Duplicate" row action to WP admin list tables: Posts, Pages, Elementor Templates
- Hook: `post_row_actions` / `page_row_actions`
- On click: `wp_insert_post()` clone of the original, copy all `_elementor_*` post meta
- Call `\Elementor\Plugin::$instance->files_manager->clear_cache()` after duplication so CSS regenerates
- Set duplicated post status to `draft`
- New file: `includes/class-live-copy-duplicator.php`

---

**5. Mobile Device Disable**
- JS: detect mobile via `window.innerWidth <= 768` OR `navigator.maxTouchPoints > 0`
- Skip `El_Live_Copy.init()` entirely on mobile
- Add to `ElLiveCopyData`: `is_mobile` bool (set server-side via `wp_is_mobile()`) as authoritative check
- CSS: add media query `@media (max-width: 768px) { .ellc-magic-copy-wrapper { display: none !important; } }`

---

**6. Proper Nonce Security (controlled)**
- Current: nonce generated but never verified — cross-domain use case requires this
- Solution: add `ElLiveCopyData.site_key` (a static hashed site identifier, not a nonce) as lightweight anti-scraping measure
- Verify `site_key` server-side without blocking legitimate cross-domain requests
- Real nonce verification only when `same_origin` mode is enabled in settings
- Document this decision in `copy-flow.md`

---

**7. Inner Container Button Visibility**
- Current bug: buttons injected into nested containers are hidden by CSS (z-index/overflow issues)
- Fix: change CSS selector from parent hover to self hover using `:hover > .ellc-magic-copy-wrapper`
- Also handle `overflow: hidden` on parent — add `position: relative; overflow: visible` via JS on hover

---

### Phase 2 — Differentiation (after Phase 1)

Features that create real competitive separation.

---

**8. Copy History (Clipboard Manager)**
- Store last 10 copied elements in `localStorage` keyed by `ellc_history`
- Each entry: `{ id, label, timestamp, data }` where label = element type + section index
- UI: small history panel (collapsed by default, toggled by button)
- User can re-copy any previous item without revisiting the source page
- No server storage — pure client-side, privacy-friendly

---

**9. Visual Preview Before Copy**
- On button hover (300ms delay): show a small floating card with element tag, widget count, and approximate height
- Data already available from DOM — no extra AJAX
- Prevents accidental copies of wrong sections

---

**10. Named Saved Designs Library (Local)**
- After copy, prompt: "Save this design? (optional name)"
- Store in `localStorage` under `ellc_library`
- UI: accessible from a floating "Library" button (only visible to logged-in users)
- Export library to JSON file / import from JSON file
- This is the "personal component library" use case

---

**11. Batch Section Copy**
- Multi-select mode: hold Shift/Ctrl + click buttons to queue multiple sections
- Queue shows count badge; one final "Copy All" action serializes all as array
- Paste side: Elementor receives array, places sections sequentially

---

### Phase 3 — Advanced / Pro Features

Revenue-generating capabilities for a future Pro tier.

---

**12. Team Shared Library (REST API)**
- Server-side library stored as custom post type `ellc_template`
- REST endpoint: `GET/POST /wp-json/live-copy/v1/library`
- Share library URL with team members — they subscribe to your site's library
- Remote fetch with site token authentication
- Pro feature: unlimited entries; free: 5 entries max

---

**13. Import by URL**
- Instead of clipboard paste, enter source page URL
- Plugin fetches source site's REST endpoint directly (server-side cURL) to bypass CORS
- Returns element data without requiring user to visit source site
- Requires source site to have Live Copy active + allow remote fetch in settings

---

**14. WooCommerce Product Duplication**
- Extend duplicator to WooCommerce products (`product` post type)
- Copy product meta: `_price`, `_sku`, `_stock`, `_product_attributes`
- Duplicate variations for variable products
- Auto-suffix SKU with `-copy` to avoid conflicts

---

**15. Export/Import JSON File**
- "Download as JSON" button alongside copy button
- User saves `.json` file, uploads on destination site via drag-drop or file picker
- Fallback for environments where clipboard API is blocked (corporate networks, some browsers)

---

## Implementation Order

```
v1.1.0  DONE: items 1, 2, 5, 6(partial), 15 + reporting/analytics ✅
v1.2.0  Phase 1: items 3, 7 (per-section toggle + inner-container fix)
v1.3.0  Phase 1: item 4 (duplicator) + history pruning cron
v1.4.0  Phase 2: items 8, 9 (history panel + preview)
v1.5.0  Phase 2: items 10, 11 (library + batch)
v2.0.0  Phase 3: items 12–14 (Pro tier)
```

Reporting/analytics was pulled forward — it answers "which designs to build next".

## File Structure (current + planned)

```
includes/  class-live-copy.php · -settings.php · -rest.php · -db.php  (exist)
           class-live-copy-duplicator.php                            (planned, item 4)
admin/src/ App.jsx · tabs/Settings.jsx · tabs/Reports.jsx · api.js   (exist)
src/js/    script.js (exist) · history.js · library.js               (planned, 8/10)
```

## Keywords to Target (SEO/readme.txt)

Market-gap terms with demand (never use competitor brand names):
elementor cross domain copy paste · copy elementor sections between sites ·
elementor page duplicator · elementor design transfer · download elementor section json ·
copy paste elementor widgets cross domain · elementor template copy another site
