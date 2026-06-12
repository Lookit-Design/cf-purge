=== Lookit Cloudflare Cache Purge ===
Contributors: lookitdesign
Tags: cloudflare, cache, purge, cdn, admin bar
Requires at least: 5.8
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Granular Cloudflare cache control from the admin bar — purge a single page, any URL, or the entire site, right from wp-admin.

== Description ==

Most Cloudflare integrations for WordPress give you only one option: purge everything. That is the nuclear option, and it is more expensive than it looks.

= What happens when you purge everything =

When you purge the entire cache in Cloudflare, every cached page is cleared at once — but Cloudflare does not rebuild those caches for you. A page is only re-cached *after* the next time someone requests it. That first request following a purge is a cache miss: it travels all the way back to your origin server, which is slow to render, and that visitor waits for the full render. Only the *next* visitor to the same page gets the fast, cached copy.

The catch is traffic. Busy pages re-cache almost immediately. But pages that are visited infrequently can sit uncached for hours or even days, and the first person to land on each one pays the slow-render penalty. There is no way inside Cloudflare to warm the cache ahead of time or speed this up — re-caching happens only on demand, page by page, as real traffic trickles in.

**Lookit&reg; Cloudflare Cache Purge gives you granular control.** Instead of wiping everything and forcing your whole site back to origin speed, you clear only the page you actually changed — and every other page keeps its cache intact.

From any page in WordPress — whether you are editing a post in wp-admin or viewing the live site while logged in — a lightweight **CF Purge** menu (☁) appears in the admin bar with three options:

* **Purge This URL** — clears only the page you are currently on or editing
* **Purge Entire Site** — full cache purge when you genuinely need it (with a confirmation dialog so it is never accidental)
* **Or enter any URL** — type any URL on the site and purge it directly, without navigating to that page first

The manual URL field is especially useful for URLs that do not correspond to a standard WordPress post or page — such as custom archive paths, Events Calendar URLs, or other custom post type archives.

= Why this plugin exists =

If you manage WordPress sites on Cloudflare with a caching plugin like WP Rocket, you have likely run into this problem: WP Rocket's Cloudflare integration only supports full-site purge. The official Cloudflare WordPress plugin is heavyweight and touches your zone settings. Neither gives you a simple, context-aware button to clear just one URL.

This plugin does exactly that — nothing more, nothing less. It connects to Cloudflare via a scoped API token, adds a clean menu to the admin bar, and stays completely out of your Cloudflare zone configuration.

= Features =

* Context-aware: automatically detects the URL of the post or page you are editing or viewing
* Works in both the wp-admin editor and the frontend (when logged in)
* Manual URL field for purging arbitrary URLs without navigating to them
* Full-site purge option with confirmation dialog
* Lightweight — no zone settings, no DNS, no SSL toggles, no bloat
* Secure — uses a scoped API token with minimum required permissions
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

= Documentation and support =

* Full documentation: https://lookitdesign.com/software/cloudflare-cache-purge/
* Support: https://lookitdesign.com/cloudflare-purge-support-form/

_Lookit&reg; is a registered trademark of ZENOVA CORP. Cloudflare is a registered trademark of Cloudflare, Inc.; this plugin is an independent integration and is not affiliated with, sponsored by, or endorsed by Cloudflare._

== Installation ==

1. Upload the `lookit-cf-purge` folder to `/wp-content/plugins/`
2. Activate the plugin through the **Plugins** menu in WordPress
3. Go to **Settings → CF Purge Settings** and enter your Cloudflare API Token and Zone ID
4. Use the **Test Connection** button to confirm everything is working

== Frequently Asked Questions ==

= Do I need the official Cloudflare WordPress plugin? =

No. This plugin connects directly to the Cloudflare API and does not depend on any other plugin. It works alongside WP Rocket, the official Cloudflare plugin, or any other setup.

= After a full-site purge, why is my site slow for a while? =

That is Cloudflare's normal behavior, not a fault in the plugin. A full purge empties every cached page, and each page is only re-cached the next time it is requested. Until then, requests go back to your origin server and render at full (slow) speed, and pages with little traffic stay uncached the longest. There is no way within Cloudflare to pre-warm the cache. This is exactly why the plugin defaults to single-URL purging — so you clear only what you changed and leave the rest of your cache warm.

= Will this affect my Cloudflare zone settings? =

No. This plugin only calls Cloudflare's cache purge endpoint. It cannot read or modify any zone settings, WAF rules, DNS records, or any other configuration.

= What API token permissions do I need? =

Minimum required: **Zone / Cache Purge / Purge** and **Zone / Zone / Read**. The Read permission is used only for the connection test. Do not use your Global API Key.

= Can I use the same token I created for WP Rocket? =

Yes, if your WP Rocket token has the same permissions (Cache Purge + Zone Read), you can use it here as well.

= Can I use this alongside WP Rocket? =

Yes. The two work together fine, and you do not need WP Rocket's Cloudflare add-on just to clear the Cloudflare cache — this plugin does that on its own, with the per-URL control WP Rocket does not offer.

There is also a reason some people prefer to leave WP Rocket's Cloudflare add-on switched off. Its "Optimal Settings" option writes changes into your Cloudflare account — it sets the Caching Level, forces a Browser Cache TTL, and turns off Rocket Loader — and it does not reliably restore your previous values if you turn it back off. If you have tuned those caching settings by hand in Cloudflare, the add-on can overwrite them. Lookit Cloudflare Cache Purge never touches any Cloudflare settings; it only calls the cache-purge endpoint, so your zone configuration is left exactly as you set it.

= Why does "Purge This URL" not appear sometimes? =

It only appears when the plugin can resolve a canonical URL for the current page. In wp-admin, this requires a post ID in the URL (i.e. you must be on the post editor screen, not the posts list). On the frontend it resolves for singular posts, pages, taxonomy archives, author archives, and the homepage.

= Does this work with custom post types and plugins like The Events Calendar? =

The "Purge This URL" button works for any URL WordPress can resolve a permalink for. For custom paths that do not correspond to a standard WordPress object (such as `/events/` in The Events Calendar), use the **Or enter any URL** field to type the URL directly.

= Is the manual URL field restricted to this site only? =

Yes. The plugin validates that any URL submitted matches the current site's domain. You cannot use it to purge URLs from other sites or zones.

= Where can I get help or report a problem? =

You can reach us through the support form at https://lookitdesign.com/cloudflare-purge-support-form/, or post in the WordPress.org support forum for this plugin. Full documentation is available at https://lookitdesign.com/software/cloudflare-cache-purge/.

== External Services ==

This plugin connects to the Cloudflare API to purge cached files from the Cloudflare CDN. This is required for the core functionality of the plugin — without it, no cache purging can occur.

**What data is sent and when:**

When you trigger a cache purge (manually or via the Test Connection button), this plugin sends the following data to Cloudflare's API:

* Your Cloudflare Zone ID (to identify which zone to purge)
* Your Cloudflare API Token (for authentication)
* The specific URL(s) to purge, or a full-site purge flag

No visitor data, personal information, or site content is ever transmitted. API credentials are stored in your WordPress database and are only sent directly to Cloudflare's API endpoint (`https://api.cloudflare.com/client/v4`).

This service is provided by Cloudflare, Inc.:

* Terms of Service: https://www.cloudflare.com/terms/
* Privacy Policy: https://www.cloudflare.com/privacypolicy/

== Screenshots ==

1. The CF Purge admin bar menu showing all three options
2. The Settings page where you enter your API Token and Zone ID
3. The connection test confirming a successful Cloudflare connection
4. The confirmation dialog shown before a purge, with the exact URL to be cleared
5. The toast notification confirming a successful purge

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
