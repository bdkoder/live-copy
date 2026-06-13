=== Live Copy & Download for Elementor – Cross-Domain Design Transfer ===
Plugin Name: Live Copy & Download for Elementor – Cross-Domain Design Transfer
Version: 1.1.0
Author: wowdevs
Author URI: https://wowdevs.com
Contributors: wowdevs, bdkoder
Tags: elementor copy paste, cross domain, elementor addon, design transfer, website builder
Donate link: https://buy.stripe.com/8x214f0XKf0cfop6a14wM02
Requires at least: 5.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Copy or download any Elementor section as JSON and paste it on another site. Frontend icon buttons, role-based access, and a built-in copy analytics dashboard.

== Description ==

🚀 **Move Elementor designs between sites in seconds — copy to clipboard or download as a file.**

This plugin puts a sleek action panel right on your live pages. Hover any Elementor section or container and crisp icon buttons appear: **Copy** sends the design to your clipboard, **Download** saves it as a portable `.json` file. Paste it into any other Elementor site with the native "Paste from other site" feature, or import the file later — no exports, no FTP, no headaches.

Built for people who build a lot of sites: agencies, freelancers, and teams who reuse layouts across projects, domains, and staging environments.

= ⚡ What makes it different =

* **Copy *and* Download** – Grab a section to the clipboard, or download it as JSON to build your own reusable library.
* **Beautiful frontend panel** – Clean SVG icons, rounded corner styling, and hover tooltips. No clutter for your visitors.
* **Built-in analytics** – A native dashboard shows which pages and sections get copied and downloaded most, so you know which designs actually work.
* **Role-based access** – Show the buttons to everyone, logged-in users only, or editors and admins only.
* **Cache-safe & secure** – Nonce-protected requests that keep working even on fully cached pages.

= 🎯 Core Features =

**📋 Copy to Clipboard**
* Hover-to-reveal copy button on every section and container
* Preserves styling, animations, custom CSS, and widget settings
* Works with both flexbox containers and classic sections
* Reliable on deeply nested containers and inner sections

**📥 Download as JSON**
* Save any section as a clean `.json` file with one click
* Import it on any Elementor site whenever you're ready
* Perfect for building a personal, reusable design library
* Great fallback when clipboard access is restricted

**📊 Native Reporting Dashboard**
* Total copies and downloads at a glance
* Top copied pages — with clickable links straight to each page
* Top copied sections — see which blocks perform
* Daily activity chart (copies vs downloads), always the last 30 days
* Filter totals by 7 / 30 / 90 days or all time
* Page ID, page slug, and page title recorded for every action

**⚙️ Modern Settings Panel**
* Clean React-powered admin UI under **Settings → Live Copy**
* Toggle the copy button and download button independently
* Choose button visibility by role
* Disable on mobile with one switch
* Add a "How it works" help/video link for the info icon

**🔒 Security First**
* Every request is CSRF-protected with a nonce
* Automatically refreshes the security token on cached pages so actions never silently fail
* Private pages require a logged-in user
* Read-only — never modifies your content

= 🌟 Perfect For =

* **Agencies** – keep design consistent across many client sites
* **Freelancers** – reuse proven sections and ship faster
* **Developers** – move layouts between staging, dev, and production
* **Template builders** – assemble and distribute section libraries

== Installation ==

= Automatic =
1. In your dashboard go to **Plugins → Add New**
2. Search for "Live Copy"
3. Click **Install Now**, then **Activate**

= Manual =
1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate it through the **Plugins** menu
3. Done

= First-Time Setup =
1. Go to **Settings → Live Copy**
2. Enable the plugin and pick your button visibility
3. Choose whether to show the Copy button, Download button, or both
4. (Optional) Add a help/video URL for the info icon
5. Save — buttons appear on your Elementor pages on hover

== Frequently Asked Questions ==

= Which page builder is supported? =

Elementor. The plugin reads Elementor's native data structure, so copied sections paste back into Elementor with full fidelity.

= How does copying between sites work? =

On the source site you click **Live Copy** — the section's full Elementor data is placed on your clipboard. On the target site, right-click in the Elementor editor and choose **Paste from other site**. The design appears with its styling intact. Both sites should have a compatible Elementor version (and any third-party widgets the design uses).

= What is the Download button for? =

It saves the section as a `.json` file. Use it to keep a design for later, share it with a teammate, or build a reusable library. You can re-import the JSON on any Elementor site.

= Can I control who sees the buttons? =

Yes. Under **Settings → Live Copy** you can show the buttons to everyone, to logged-in users only, or to editors and admins only. You can also enable the Copy and Download buttons independently, and disable them on mobile.

= What does the analytics dashboard track? =

Each copy and download is recorded so the Reports tab can show totals, top pages (with quick links), top sections, and a daily activity chart. For each action it stores the page ID, page slug, page title, the section ID, the user ID (if logged in), the visitor IP address, and a timestamp. By default the IP is **anonymized** (you can switch to full or off in settings). Records older than 180 days are automatically deleted, and you can export the full log to CSV from the Reports tab.

= How do I clear or reset the analytics data? =

Clearing is intentionally locked down so it can't be wiped by accident. Add `define( 'LIVE_COPY_ALLOW_CLEAR', true );` to your `wp-config.php`, then a **Clear data** button appears on the Reports tab. Without that constant the button stays disabled (and the action is blocked on the server too). Exporting to CSV is always available.

= Do the buttons work on mobile? =

By default they are hidden on mobile for a clean experience and to prevent accidental taps. You can change this in settings. They work great on desktop and tablet.

= Will it work with full-page caching? =

Yes. The plugin verifies a security token (nonce) on every request. If a cached page serves an expired token, the script silently fetches a fresh one and retries, so copy and download keep working.

= Does it slow down my site? =

No. Assets load only on the frontend, only for users allowed to see the buttons, and not at all on mobile when disabled. Nothing loads in the WordPress admin area for visitors.

= Is it translation ready? =

Yes. All strings are internationalized and a `.pot` file is included.

== Privacy ==

This plugin stores a usage log in your own database (a custom table) to power the analytics dashboard. Each copy/download record includes the page ID, page slug, page title, section ID, the acting user ID where available, the visitor's IP address, and a timestamp. The IP is anonymized by default (configurable: full, anonymized, or not stored). No data is sent to any third party. Records are pruned automatically after 180 days and can be exported to CSV. Your data is kept even when the plugin is deleted — it is only removed on uninstall if you opt in with `define( 'LIVE_COPY_ALLOW_CLEAR', true );` in wp-config.php.

== Changelog ==

= 1.1.0 [13th June 2026] =

* New: Redesigned frontend panel with crisp SVG icons, rounded corner styling, and hover tooltips
* New: One-click **Download** button — save any section as a JSON file
* New: React-powered settings panel under **Settings → Live Copy**
* New: Role-based visibility — everyone / logged-in / editors only
* New: Native reporting dashboard (totals, top pages with links, top sections, daily activity chart)
* New: Info icon with a configurable "how it works" help/video link (cached in the browser, version-aware)
* Added: Copy & download history with page ID, page slug, and page title
* Added: Automatic 180-day history cleanup to keep the database lean
* Added: Buttons now also appear on dynamically loaded content (popups, AJAX, lazy sections)
* Added: Per-section opt-in toggle in the Elementor Advanced tab (for Specific Section Mode)
* Added: IP logging control — anonymized (default), full, or off — for GDPR-friendly analytics
* Added: Export analytics to CSV, plus guarded "Clear data" (requires a wp-config constant)
* Added: Translatable frontend button labels and messages
* Added: Clean uninstall — removes the data table, options, and cron on delete
* Improved: Developer `live_copy_should_load` filter to disable buttons per page/context
* Improved: Multisite-safe table creation and cron scheduling
* Improved: Nonce (CSRF) verification with automatic, cache-safe token refresh
* Improved: Reliable copying of deeply nested containers and inner sections
* Improved: Confirmation shown as a clean tooltip instead of button text
* Improved: Graceful handling when Elementor is inactive

= 1.0.13 [19th August 2025] =

* System improved

= 1.0.12 [16th August 2025] =

* System improved

= 1.0.9 [9th May 2025] =

* System improved

= 1.0.7 [1st May 2025] =

* System improved

= 1.0.6 [28th May 2024] =

* System improved

= 1.0.0 [25th June 2023] =

* Initial Release

== Upgrade Notice ==

= 1.1.0 =
Major update: new Download button, modern settings panel, copy/download analytics, cache-safe security, and reliable support for nested containers.
