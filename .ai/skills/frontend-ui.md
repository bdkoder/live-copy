# Skill: Frontend UI

Icon-based Copy / Download / Info panel: look, detection, hover, tooltips.

## File Locations

| Concern | Source | Compiled Output |
|---------|--------|-----------------|
| Styles | `src/less/style.less` | `assets/css/style.css` + `assets/css/style.rtl.css` |
| JS behavior | `src/js/script.js` | `assets/js/script.js` (minified) |

**Always edit source files, never compiled output.**

## Panel Structure

A floating dark pill near the element's right edge (`right:8px`, all 4 corners rounded `14px`), icons stacked vertically. All-corner rounding + inset so it looks right anywhere — including nested containers that sit mid-page, not just full-width top sections. Buttons are **SVG icons** (no text); labels show as hover tooltips.

```html
<div class="ellc-magic-copy-wrapper">
  <a class="ellc-btn ellc-copy-btn"     href="#" role="button" data-tip="Live Copy">[copy svg]</a>
  <a class="ellc-btn ellc-download-btn" href="#" role="button" data-tip="Download JSON">[download svg]</a>
  <a class="ellc-btn ellc-info-btn"     href="{help}" data-tip="How it works">[info svg]</a>
</div>
```

All three are `<a>` tags (copy/download use `href="#"` + `role="button"`; click handlers `preventDefault`). Icons are inline SVG constants in `script.js` (`ICONS.copy/download/info/check`), stroked with `currentColor` so hover recoloring works. Copy/download render only if their setting is on; info renders always.

## Detection (fixes "missing on some sections")

`globalSelector()` now selects **nested** containers + inner sections, not just top-level:

```js
$roots = $('[data-elementor-type="wp-page"], [data-elementor-type="wp-post"]');
this._elItem = $roots.find('.e-con, .elementor-section');   // includes nested
// fallback: '.elementor-section.elementor-top-section'
```

`attach()` per element:
- skips if inside `.magic-button-disabled-yes`, no `data-id`, empty, or already attached
- **forces `position: relative`** when the element is `static` (root cause of panels anchoring to the wrong ancestor / not appearing)

## Single-Panel Hover (no ancestor stacking)

Nested targets would otherwise show multiple panels. `bindHover()` uses bubbling `mouseover` + `stopPropagation` so only the **innermost** target gets `.ellc-active`:

```js
$doc.on('mouseover', '.ellc-copy-target', function (e) {
  e.stopPropagation();
  $('.ellc-copy-target.ellc-active').removeClass('ellc-active');
  $(this).addClass('ellc-active');
});
```

CSS: `.ellc-copy-target.ellc-active > .ellc-magic-copy-wrapper { display:flex }` (+ fade-in keyframe).

## Tooltips & Feedback

- CSS `.ellc-btn[data-tip]::after/::before` renders a left-side bubble + tail; shown on `:hover` or when JS adds `.ellc-tip-show`.
- `flashTip($btn, msg, success)` — swaps `data-tip` to `Copied!`/`Downloaded!`/`Failed`, force-shows it ~1.7s. On success it also swaps the icon to a check (`ICONS.check`) and recolors green via `.ellc-tip-success`.
- Loading: `fetchData` adds `.ellc-loading` (icon pulse animation, `cursor:wait`); re-click guarded.

## Help URL via Versioned Cookie

Cookie `ellc_help_url` stores `"ver|url"`. `help_ver` = `substr(md5(url),0,8)`.

`resolveHelpUrl()`:
1. parse cookie → `cookieVer`, `cookieUrl`
2. if `cookieVer === ElLiveCopyData.help_ver` → use `cookieUrl`
3. else re-seed cookie from `ElLiveCopyData.help_url` (30d) and use it

Server side (`enqueue_scripts`) computes `help_ver` and **only resends `help_url` when the client's cached version is missing/stale** (parses the cookie's `ver`) — repeat visits skip the value. Because the version is a hash of the URL, **editing it in Settings → Live Copy bumps the version and propagates immediately** (no 30-day wait).

## Dynamic Content (popups / AJAX)

`observe()` sets a `MutationObserver` on `document.body`. New element nodes (ignoring our own `.ellc-magic-copy-wrapper` insertions) trigger a 250ms-debounced `refresh()` (= `globalSelector()` + `attach()`), so Elementor popups, AJAX-loaded, and lazy sections get buttons too. `attach()` is idempotent (skips already-attached), so the observer settles without looping.

## Localized Data (`ElLiveCopyData`)

```js
{ enable, post_id, post_slug, ajax_url, nonce,
  show_copy, show_download, disable_mobile, help_url }
```

## Guards

- Exits if `body.elementor-editor-active` (don't render in the editor preview)
- Mobile: JS early-return (`innerWidth <= 768 || maxTouchPoints`) + CSS `@media (max-width:768px)` net + `wp_is_mobile()` server skip
- Opt-out: `.magic-button-disabled-yes` on doc, `.sky-live-copy-off` CSS kill

## RTL

Grunt `rtlcss` regenerates `style.rtl.css` — panel flips to the left edge automatically. No manual RTL work.
