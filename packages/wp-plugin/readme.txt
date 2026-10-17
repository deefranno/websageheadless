=== WebSage Headless Core ===
Contributors: websagecreative
Tags: headless, nextjs, rest-api, blocks
Requires at least: 7.1.3
Tested up to: 7.1.3
Requires PHP: 8.1
Stable tag: 1.0.0-rc.1
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Turns WordPress into the headless CMS for a WebSage Headless Next.js frontend.

== Description ==

WebSage Headless Core is the WordPress half of the WebSage Headless kit. It adds:

* Page-section blocks (hero, text and image, feature grid, call to action, testimonials, FAQ, contact form) for building pages in the block editor.
* A WebSage Settings screen for branding, header, footer, contact details, SEO and integration.
* A public, read-only REST API at `/wp-json/websage/v1` for the website, and private endpoints for previews and the contact form.
* Instant website updates through signed webhooks, and previews of unpublished changes.
* Private storage for contact-form messages, with email notifications and rate limiting.
* Hardening for a headless CMS: noindex, redirects to the website, no XML-RPC, no user enumeration.

It bundles CMB2 and needs no other plugins. It is designed for the WebSage Headless Next.js frontend; on its own it does not change how a traditional WordPress theme renders.

== Installation ==

1. Upload the plugin zip under Plugins → Add New → Upload Plugin, and activate it.
2. Add the configuration constants to wp-config.php (see INSTALL.md in the kit).
3. Optionally load the demo content with WP-CLI: `wp websage seed --yes`.
4. Open WebSage → Diagnostics to confirm the website connection.

== Frequently Asked Questions ==

= Does it need ACF or another plugin? =

No. Everything, including CMB2, is bundled.

= Where are contact-form messages kept? =

In a private post type visible only to administrators, deleted after the retention period set in WebSage Settings → Contact.

== Changelog ==

See CHANGELOG.md in the kit.
