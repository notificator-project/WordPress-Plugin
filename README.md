# Notificator Companion (WordPress Plugin)

Notificator Companion helps WordPress administrators monitor selected hooks and send secure notifications to an external Notificator endpoint.

It is designed for observability workflows: scan hooks, create scenario-based rules, and receive notifications when important events happen.

## Direct Download

[Download v1.1.0-beta.3 ZIP](https://github.com/notificator-project/WordPress-Plugin/releases/download/v1.1.0-beta.3/notificator-companion-1.1.0-beta.3.zip)

## Feedback and Bug Reports

Found a bug or have a feature request? Please open an issue here:

[Notificator Companion Issues](https://github.com/notificator-project/WordPress-Plugin/issues)

When reporting a bug, it helps to include:

- WordPress version
- PHP version
- Plugin version
- Steps to reproduce
- Expected behavior vs actual behavior
- Error logs or screenshots (if available)

## Highlights

- Hook scanner for WordPress core and plugins
- Scenario-driven monitoring with templates and custom rules
- Condition operators: `=`, `!=`, `>`, `>=`, `<`, `<=`, `contains`, `not_contains`
- Scenario notes with placeholder rendering (for example `{{order.total}}`)
- Severity levels: `info`, `warning`, `critical`
- Per-scenario delivery flags (`sendPush`, `sendMqtt`)
- Multi-key API support with optional key nicknames and per-key test actions
- Notification log with pagination, row delete, clear all, and CSV export
- Optional wp-admin toast notifications
- JSON import/export for scenarios
- Optional background scans via WP-Cron

## Requirements

- WordPress 5.0+
- PHP 7.2+
- Node.js + npm (only for frontend/admin asset development)

## Installation (WordPress)

1. Upload the plugin to `/wp-content/plugins/notificator-companion/`.
2. Activate it from **Plugins** in wp-admin.
3. Open **Notificator Companion** in the admin menu.
4. Add one or more API keys and save settings.
5. Run a plugin scan, then enable the scenarios you need.

## External Service

By default, notifications are sent to:

`https://api-wpnotificator.netlify.app/.netlify/functions/wpnotif-api`

You can override this using the `notificator_companion_api_endpoint` filter.

### Payload Notes

Typical outbound data includes:

- Notification envelope (`type`, `title`, `body`, `severity`, `source`)
- Scenario metadata (`hook_name`, `scenario_name`, `scenario_notes`)
- Site metadata (`site_url`, `site_name`, WordPress version, plugin version, timestamp)
- Delivery flags (`sendPush`, `sendMqtt`)

Raw hook argument payloads are not sent by default.

## Security Notes

- Admin capability checks (`manage_options`) for settings and AJAX actions
- Nonce validation for admin AJAX endpoints
- Bearer + HMAC request signing headers (`X-Timestamp`, `X-Signature`)

## Developer Setup

### Install dependencies

```bash
npm install
```

### Development/build commands

```bash
npm run dev
npm run build
npm run build:debug
npm run watch
```

### Release package

```bash
npm run build:release
```

Creates a zip at:

`dist/notificator-companion.zip`

To skip asset build and package current files directly:

```bash
npm run build:release:skip-assets
```

### Automated GitHub release (tag-based)

This repository is configured to build and publish a release zip automatically when you push a semantic version tag:

```bash
git tag v1.0.1
git push origin v1.0.1
```

For pre-releases:

```bash
git tag v1.0.2-rc.1
git push origin v1.0.2-rc.1
```

Supported pre-release suffixes:

- `-alpha.N` (example `v1.0.2-alpha.1`)
- `-beta.N` (example `v1.0.2-beta.2`)
- `-rc.N` (example `v1.0.2-rc.1`)

The GitHub release title automatically reflects these stages (Alpha, Beta, Release Candidate).

One-command beta helper:

```bash
npm run release:beta -- 1.1.0-beta.1
```

To also push branch and tag automatically:

```bash
npm run release:beta -- 1.1.0-beta.1 --push
```

This helper updates both version declarations in `notificator-companion.php`, creates a release commit, and creates the matching `v...` tag.

What happens in CI:

- Validates that tag version matches `Version:` in `notificator-companion.php`
- Validates that tag version matches `Stable tag:` in `readme.txt`
- Builds assets and runs release packaging
- Publishes a GitHub Release with `dist/notificator-companion-<version>.zip`

Pre-release tags (for example `v1.0.2-rc.1`) publish a GitHub Pre-release and keep stable releases separate.

## Build Output

Vite builds admin assets into:

- `assets/dist/admin.js`
- `assets/dist/admin.css`
- `assets/dist/admin-toast.js`
- `assets/dist/admin-toast.css`

Note: the top-level `dist/` release-packaging directory is generated during build/release steps and is intentionally excluded from source control.

## Extensibility

### Filters

- `notificator_companion_api_endpoint`
- `notificator_companion_register_templates`
- `notificator_companion_templates`

### Helper functions

- `notificator_companion_register_template( array $template )`
- `notificator_companion_get_registered_templates()`

## Project Structure

```text
admin/               # Admin page class and UI partials
assets/src/          # TypeScript + styles source for admin UI
assets/dist/         # Built assets loaded by WordPress
includes/            # Scanner and backend support classes
languages/           # Translation files
scripts/             # Release/build scripts
notificator-companion.php
readme.txt           # WordPress.org-style readme
README.md            # Repository-facing documentation
```

## Versioning Note

This repository currently declares plugin version `1.0.0` in the main plugin file.

## License

GPL-3.0-or-later
