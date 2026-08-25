# Deploying a release to WordPress.org

This plugin is published to the [WordPress.org plugin directory](https://wordpress.org/plugins/lookit-cf-purge/)
automatically whenever a **GitHub Release is published**. The release tag drives
the version, tests must pass, and the version numbers must line up before
anything reaches WordPress.org.

> This guide covers **subsequent releases**. The one-time setup (the
> [deploy workflow](.github/workflows/deploy.yml) on `main`, the
> `SVN_USERNAME` / `SVN_PASSWORD` [repository secrets](../../settings/secrets/actions#repository-secrets),
> and the initial WordPress.org submission) is already done.

## Versioning rules

- Use [semantic versioning](https://semver.org/): `MAJOR.MINOR.PATCH`.
  - `PATCH` (`1.0.0` → `1.0.1`) — bug fixes only.
  - `MINOR` (`1.0.1` → `1.1.0`) — new, backward-compatible functionality.
  - `MAJOR` (`1.1.0` → `2.0.0`) — backward-incompatible changes.
- The tag must be a **bare** version of three integers: `1.2.3`.
  - No `v` prefix (`v1.2.3` is rejected), no suffixes (`1.2.3-beta` is rejected).
  - This matches WordPress.org's SVN tag and the readme `Stable tag`. A mismatch
    breaks which version the directory serves, so the deploy gate enforces the
    format and fails fast on anything else.
- Versions only ever move **forward**. A version that has already shipped cannot
  be re-published — fix forward with a higher version.

## Release steps

### 1. Bump the version in the code

The release tag must match **all three** of these exactly, or the deploy is
rejected:

| Location | File |
| --- | --- |
| Plugin header `Version:` | [`lookit-cf-purge.php`](lookit-cf-purge.php) |
| `LOOKIT_CF_PURGE_VERSION` constant | [`lookit-cf-purge.php`](lookit-cf-purge.php) |
| `Stable tag:` | [`readme.txt`](readme.txt) |

Also add a matching entry under `== Changelog ==` in [`readme.txt`](readme.txt).

### 2. Merge the bump into `main`

Open a PR with the version bump and changelog, get CI green, and merge it into
`main`. The release is cut from `main`, so the new version numbers must be there
first.

### 3. Confirm `main` is ready

- CI is green on `main`.
- The three version values all read the new version.

### 4. Draft the GitHub Release

Go to [**Releases → Draft a new release**](../../releases/new):

- **Tag:** type the bare version (e.g. `1.2.3`) and choose
  *Create new tag: `1.2.3` on publish*. **Do not** prefix with `v`.
- **Target:** `main`.
- **Title:** the version (e.g. `1.2.3`).
- **Notes:** describe the release (the changelog entry works well).
- Leave *Set as the latest release* checked. Do **not** mark it as a pre-release.

### 5. Publish

Click **Publish release**. Publishing is the trigger — a saved *draft* does
nothing.

### 6. Watch the deploy

Open the [**Deploy to WordPress.org**](../../actions/workflows/deploy.yml) workflow
under **Actions**. The run will, in order:

1. Run the test suite (plus the AJAX group) on a real WordPress + MySQL stack.
2. Validate the tag format and confirm it matches the three version values.
3. Deploy the code to SVN `trunk/` and `tags/<version>`.
4. Sync [`.wordpress-org/`](.wordpress-org) assets (icon, banners, screenshots) to SVN `assets/`.

A failure in any earlier step stops the deploy, so nothing partial reaches
WordPress.org.

### 7. Verify

Within a few minutes, the [plugin page](https://wordpress.org/plugins/lookit-cf-purge/)
should show the new version, the updated readme, and the current assets. To
confirm the tag from the command line:

```bash
svn ls https://plugins.svn.wordpress.org/lookit-cf-purge/tags/
```

## Troubleshooting

| Situation | What to do |
| --- | --- |
| Test or version-check failed (nothing deployed) | If transient, re-run from the [workflow run](../../actions/workflows/deploy.yml) (**Re-run failed jobs**). If it's a real bug, fix on `main` and cut a new release. |
| Bad/expired SVN credentials | Update the `SVN_USERNAME` / `SVN_PASSWORD` [secrets](../../settings/secrets/actions#repository-secrets), then **Re-run failed jobs** (SVN was untouched). |
| `tag already exists` on SVN | That version already shipped. Bump to a higher version and release again. |
| Code deployed but **assets** failed | The code is live; only the asset sync needs re-running. Re-run the asset sync (see below) — do **not** re-run the whole deploy job, because it would try to recreate the existing SVN tag and fail. |

### Updating readme or assets without a release

Changes such as **Tested up to**, screenshots, banners, or icons do **not**
require a version bump or GitHub Release. Merge the change to `main`, then either:

- let the [**Sync readme to WordPress.org**](.github/workflows/sync-readme.yml)
  workflow run automatically when `readme.txt` or `.wordpress-org/` changes, or
- trigger it manually under **Actions → Sync readme to WordPress.org → Run workflow**.

That workflow pushes only `readme.txt` and `.wordpress-org/` to SVN (trunk and the
current `Stable tag` readme). The downloadable plugin version stays the same.

### Re-syncing assets only

Updating the icon, banners, or screenshots does **not** require a release.
Update the files in `.wordpress-org/`, merge to `main`, and the
[sync-readme workflow](.github/workflows/sync-readme.yml) will push them to SVN
`assets/`.

### A note on immutable releases

Published releases and their tags **cannot be edited**. If you publish the wrong
tag, you must delete the release (and its tag) and create a new one — so
double-check the tag before clicking publish.
