=== Lookit CF Purge ===
Contributors: lookitdesign
Tags: cloudflare, cache, purge, cdn, admin bar
Requires at least: 5.8
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Surgical Cloudflare cache purge from the WordPress admin bar. Purge any URL, the entire site, or type in any custom URL — right from wp-admin.

== Description ==

Most Cloudflare integrations for WordPress only give you one option: purge everything. That's the nuclear option — it clears every cached page simultaneously, causing a temporary performance hit as your entire site re-caches from scratch.

**Lookit CF Purge gives you surgical control.**

From any page in WordPress — whether you're editing a post in wp-admin or viewing the live site while logged in — a lightweight "CF Purge" menu appears in the admin bar with three options:

* **Purge This URL** — clears only the page you are currently on or editing
* **Purge Entire Site** — full cache purge when you need it (with a confirmation dialog so it's never accidental)
* **Or enter any URL** — type any URL on the site and purge it directly, without navigating to that page first

The manual URL field is especially useful for URLs that don't correspond to a standard WordPress post or page — such as custom archive paths, Events Calendar URLs, or other custom post type archives.

= Why This Plugin Exists =

If you manage WordPress sites on Cloudflare with a caching plugin like WP Rocket, you've likely run into this problem: WP Rocket's Cloudflare integration only supports full-site purge. The official Cloudflare WordPress plugin is heavyweight and touches your zone settings. Neither gives you a simple, context-aware button to clear just one URL.

This plugin does exactly that — nothing more, nothing less. It connects to Cloudflare via a scoped API token, adds a clean menu to the admin bar, and stays completely out of your Cloudflare zone configuration.

= Features =

* Context-aware: automatically detects the URL of the post or page you are editing or viewing
* Works in both the wp-admin editor and the frontend (when logged in)
* Manual URL field for purging arbitrary URLs without navigating to them
* Full-site purge option with confirmation dialog
* Lightweight — no zone settings, no DNS, no SSL toggles, no bloat
* Secure — uses scoped API token with minimum required permissions
* Compatible with WP Rocket, the official Cloudflare plugin, and any other caching setup

= Requirements =

* A Cloudflare account with your site's domain active
* A Cloudflare API Token with **Zone / Cache Purge / Purge** and **Zone / Zone / Read** permissions
* Your Cloudflare Zone ID (found on the domain's Overview page in the Cloudflare dashboard)

= Setup =

1. Install and activate the plugin
2. Go to **Settings → CF Purge Settings**
3. Paste your Cloudflare API Token and Zone ID
4. Click **Test Cloudflare Connection** to verify
5. The **☁ CF Purge** menu will now appear in your admin bar

= Creating a Scoped API Token =

In your Cloudflare dashboard: **My Profile → API Tokens → Create Token → Create Custom Token**

Set these permissions:
* Zone / Cache Purge / Purge
* Zone / Zone / Read

Set Zone Resources to the specific zone for this site. This keeps the token scoped and secure.

**Do not use your Global API Key.** A scoped token is always the correct approach.

== Installation ==

1. Upload the `lookit-cf-purge` folder to `/wp-content/plugins/`
2. Activate the plugin through the **Plugins** menu in WordPress
3. Go to **Settings → CF Purge Settings** and enter your Cloudflare API Token and Zone ID
4. Use the **Test Connection** button to confirm everything is working

== Frequently Asked Questions ==

= Do I need the official Cloudflare WordPress plugin? =

No. This plugin connects directly to the Cloudflare API and does not depend on any other plugin. It works alongside WP Rocket, the official Cloudflare plugin, or any other setup.

= Will this affect my Cloudflare zone settings? =

No. This plugin only calls Cloudflare's cache purge endpoint. It cannot read or modify any zone settings, WAF rules, DNS records, or any other configuration.

= What API token permissions do I need? =

Minimum required: **Zone / Cache Purge / Purge** and **Zone / Zone / Read**. The Read permission is used only for the connection test. Do not use your Global API Key.

= Can I use the same token I created for WP Rocket? =

Yes, if your WP Rocket token has the same permissions (Cache Purge + Zone Read), you can use it here as well.

= Why does "Purge This URL" not appear sometimes? =

It only appears when the plugin can resolve a canonical URL for the current page. In wp-admin, this requires a post ID in the URL (i.e. you must be on the post editor screen, not the posts list). On the frontend it resolves for singular posts, pages, taxonomy archives, author archives, and the homepage.

= Does this work with custom post types and plugins like The Events Calendar? =

The "Purge This URL" button works for any URL WordPress can resolve a permalink for. For custom paths that don't correspond to a standard WordPress object (such as `/events/` in The Events Calendar), use the **Or enter any URL** field to type the URL directly.

= Is the manual URL field restricted to this site only? =

Yes. The plugin validates that any URL submitted matches the current site's domain. You cannot use it to purge URLs from other sites or zones.

== Screenshots ==

1. The CF Purge admin bar menu showing all three options
2. The Settings page where you enter your API Token and Zone ID
3. The connection test confirming a successful Cloudflare connection

== Changelog ==

= 1.0.0 =
* Initial release
* Context-aware single-URL purge from admin bar
* Full-site purge with confirmation dialog
* Manual URL entry field for arbitrary URLs
* Works in both wp-admin editor and frontend
* Scoped API token authentication
* Connection test on settings page
* Toast notification confirms success or failure after every purge action

== Upgrade Notice ==

= 1.0.0 =
Initial release.
