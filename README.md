# Lookit Cloudflare Cache Purge

[![WordPress Plugin Version](https://img.shields.io/wordpress/plugin/v/lookit-cf-purge.svg)](https://wordpress.org/plugins/lookit-cf-purge/)
[![License](https://img.shields.io/badge/license-GPLv2-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![Lint](https://github.com/Lookit-Design/cf-purge/actions/workflows/lint.yml/badge.svg)](../../actions/workflows/lint.yml)
[![Coding Standards](https://github.com/Lookit-Design/cf-purge/actions/workflows/coding-standards.yml/badge.svg)](../../actions/workflows/coding-standards.yml)
[![Plugin Check](https://github.com/Lookit-Design/cf-purge/actions/workflows/plugin-check.yml/badge.svg)](../../actions/workflows/plugin-check.yml)
[![Tests](https://github.com/Lookit-Design/cf-purge/actions/workflows/test.yml/badge.svg)](../../actions/workflows/test.yml)

Granular Cloudflare cache control from the WordPress admin bar — purge a single page, any URL, or the entire site, right from wp-admin.

Supports `WordPress >= 5.9` on `PHP >= 7.4`.

## Features

* **Single-URL purge**: Clear only the page you changed and leave the rest of your cache warm.
* **Context-aware**: Detects the URL of the post or page you are editing or viewing — in both the wp-admin editor and on the frontend (when logged in).
* **Manual URL field**: Purge arbitrary URLs (custom archives, Events Calendar paths, etc.) without navigating to them first.
* **Full-site purge**: Available when you genuinely need it, behind a confirmation dialog so it is never accidental.
* **Stays out of your zone**: Calls only Cloudflare's cache-purge endpoint — never reads or changes DNS, SSL, WAF, or any other zone settings.
* **Secure by design**: Uses a scoped API token, never echoes the saved token back to the browser, keeps it out of autoloaded options, and removes it on uninstall.
* **Compatible**: Works alongside WP Rocket, the official Cloudflare plugin, or any other caching setup.

## Table of Contents

- [Getting Started](#getting-started)
  - [Installation](#installation)
  - [Creating a Scoped Cloudflare API Token](#creating-a-scoped-cloudflare-api-token)
  - [Configuration](#configuration)
- [Usage](#usage)
- [Why Single-URL Purging](#why-single-url-purging)
- [Compatibility](#compatibility)
- [Security and Privacy](#security-and-privacy)
- [Frequently Asked Questions](#frequently-asked-questions)
- [Development](#development)
  - [Setup](#setup)
  - [Running the Test Suite](#running-the-test-suite)
  - [Coding Standards](#coding-standards)
  - [Continuous Integration](#continuous-integration)
- [Deployment](#deployment)
- [Contributing](#contributing)
- [License](#license)

## Getting Started

### Installation

From the WordPress.org plugin directory:

1. In wp-admin, go to **Plugins → Add New** and search for "Lookit Cloudflare Cache Purge".
2. Click **Install Now**, then **Activate**.

Or install manually:

1. Download the plugin and upload the `lookit-cf-purge` folder to `/wp-content/plugins/`.
2. Activate it through the **Plugins** menu in WordPress.

### Creating a Scoped Cloudflare API Token

In your Cloudflare dashboard: **My Profile → API Tokens → Create Token → Create Custom Token**.

Set these permissions:

* Zone / Cache Purge / Purge
* Zone / Zone / Read

Set **Zone Resources** to the specific zone for this site to keep the token scoped. The Read permission is used only by the connection test.

> **Do not use your Global API Key.** A scoped token is always the correct approach.

You will also need your **Zone ID**, found on the domain's Overview page in the Cloudflare dashboard.

### Configuration

1. Go to **Settings → CF Purge Settings**.
2. Paste your Cloudflare **API Token** and **Zone ID**.
3. Click **Test Cloudflare Connection** to verify the credentials.
4. The **☁ CF Purge** menu now appears in your admin bar.

## Usage

From the **☁ CF Purge** admin bar menu:

* **Purge This URL** — clears only the page you are currently on or editing. It appears whenever the plugin can resolve a canonical URL for the current view (singular posts, pages, taxonomy and author archives, the homepage, and the post editor screen).
* **Purge Entire Site** — a full cache purge, behind a confirmation dialog.
* **Or enter any URL** — type any URL on this site and purge it directly. The plugin validates that the URL matches the current site's domain.

A toast notification confirms success or failure after every purge.

## Why Single-URL Purging

When you purge the entire cache in Cloudflare, every cached page is cleared at once — but Cloudflare does not rebuild those caches for you. A page is only re-cached *after* the next request for it. That first request is a cache miss: it goes all the way back to your origin server and the visitor waits for the full render. Busy pages re-cache almost immediately, but low-traffic pages can sit uncached for hours or days, and Cloudflare offers no way to warm the cache ahead of time.

Single-URL purging avoids this: you clear only the page you actually changed, and every other page keeps its warm cache.

## Compatibility

This plugin connects directly to the Cloudflare API and does not depend on any other plugin. It works alongside WP Rocket, the official Cloudflare plugin, or any other caching setup.

You do **not** need WP Rocket's Cloudflare add-on just to clear the Cloudflare cache — this plugin does that on its own, with the per-URL control WP Rocket does not offer. It also never touches your Cloudflare zone configuration, unlike add-ons whose "optimal settings" rewrite your caching level, browser cache TTL, and Rocket Loader options.

## Security and Privacy

* The API Token and Zone ID are stored in a single WordPress option and are **never** rendered back into the settings form — the token field is always blank, and submitting it blank keeps the saved value.
* The credentials option is **not autoloaded**, so the token is not pulled into memory on every front-end request.
* On uninstall, the stored credentials are **removed from the database** (including across a multisite network).

The plugin sends data to Cloudflare only when you trigger a purge or the connection test. Each request includes your Zone ID, your API Token (for authentication), and the URL(s) to purge (or a full-site purge flag), sent directly to `https://api.cloudflare.com/client/v4`. No visitor data, personal information, or site content is ever transmitted.

See Cloudflare's [Terms of Service](https://www.cloudflare.com/terms/) and [Privacy Policy](https://www.cloudflare.com/privacypolicy/).

## Frequently Asked Questions

A full FAQ is available on the [WordPress.org plugin page](https://wordpress.org/plugins/lookit-cf-purge/). A few common questions:

* **Do I need the official Cloudflare plugin?** No. This plugin connects directly to the Cloudflare API and works independently.
* **Will it change my Cloudflare zone settings?** No. It only calls the cache-purge endpoint and cannot read or modify any zone configuration.
* **Why is my site slow for a while after a full-site purge?** That is Cloudflare's normal on-demand re-caching behavior, not a fault in the plugin — see [Why Single-URL Purging](#why-single-url-purging).
* **Does it work with custom post types and The Events Calendar?** "Purge This URL" works for any URL WordPress can resolve a permalink for; for other paths, use the manual URL field.

## Development

### Setup

Install the development dependencies with [Composer](https://getcomposer.org/):

```bash
composer install
```

### Running the Test Suite

The integration tests run against a real WordPress test install and a MySQL database. Install the test suite once, then run PHPUnit:

```bash
# bin/install-wp-tests.sh <db-name> <db-user> <db-pass> <db-host> <wp-version>
bin/install-wp-tests.sh wordpress_test root '' 127.0.0.1 latest

composer test
```

The AJAX handler tests run in a separate process group and must be invoked explicitly:

```bash
vendor/bin/phpunit --group ajax
```

### Coding Standards

This project follows the WordPress Coding Standards and checks PHP cross-version compatibility:

```bash
composer phpcs    # check coding standards
composer phpcbf   # auto-fix what can be fixed
composer compat   # check PHP 7.4+ compatibility
composer lint     # php -l syntax check on all files
```

### Continuous Integration

Every push and pull request runs the following GitHub Actions workflows:

| Workflow | Purpose |
| --- | --- |
| [Lint](../../actions/workflows/lint.yml) | `php -l` syntax check across the supported PHP versions |
| [Coding Standards](../../actions/workflows/coding-standards.yml) | WordPress Coding Standards (PHPCS) |
| [Plugin Check](../../actions/workflows/plugin-check.yml) | Official WordPress Plugin Check, including readme validation |
| [Tests](../../actions/workflows/test.yml) | PHPUnit across a broad WordPress × PHP matrix |

A scheduled [Version Monitor](../../actions/workflows/version-monitor.yml) workflow watches for new PHP and WordPress releases so compatibility can be reviewed proactively.

## Deployment

See [DEPLOY.md](DEPLOY.md) for the release and WordPress.org deployment process.

## Contributing

Bug reports and pull requests are welcome on [GitHub](../../issues).

## License

This plugin is available as open source under the terms of the [GPL-2.0-or-later License](https://www.gnu.org/licenses/gpl-2.0.html).

---

_Lookit&reg; is a registered trademark of ZENOVA CORP. Cloudflare is a registered trademark of Cloudflare, Inc.; this plugin is an independent integration and is not affiliated with, sponsored by, or endorsed by Cloudflare._
