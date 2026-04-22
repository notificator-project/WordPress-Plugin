=== Notificator Companion ===
Contributors: eboxnet
Tags: notifications, hooks, monitoring, observability, developer-tools
Requires at least: 5.0
Tested up to: 7.0
Requires PHP: 7.2
Stable tag: 1.0.0
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Monitor selected WordPress hooks and send secure notifications to your Notificator endpoint.

== Description ==

Notificator Companion helps administrators monitor important WordPress events by attaching listeners to selected hooks (actions and filters) and sending notifications to an external endpoint.

It is built for debugging, observability, and operational monitoring across WordPress Core and plugins.

= Key Features =

* Hook scanner for WordPress Core and plugins
* Scenario-based monitoring (enable only what you need)
* Template-driven quick start plus custom scenarios
* Scenario conditions with operators (`=`, `!=`, `>`, `>=`, `<`, `<=`, `contains`, `not_contains`)
* Scenario notes with placeholder rendering (for example `{{order.total}}`)
* Severity levels (`info`, `warning`, `critical`)
* Per-scenario delivery controls (`sendPush`, `sendMqtt`)
* Multi-API-key support with optional key nicknames
* Per-key test button support in the admin UI
* Throttle controls to reduce noisy repeated notifications
* Notifications log (toggle on/off, pagination, row delete, clear all, CSV export)
* Optional wp-admin dashboard toasts (delivery mode, duration, position, dismiss mode)
* Import/export scenarios as JSON
* Background hook scans via WP-Cron (optional request mode)
* Concurrent-scan lock using transients
* Configurable scan hook limit per plugin (default `500`)
* Extensible API endpoint through `notificator_companion_api_endpoint` filter
* Extensible templates via `notificator_companion_register_templates` and `notificator_companion_templates`

= Quick Start =

1. Go to `Notificator Companion` in wp-admin.
2. Add one or more API keys (optionally add nicknames).
3. Save settings.
4. Run `Scan Plugins`.
5. Apply a template or create custom scenarios.
6. Keep only the scenarios you want enabled.

= Scanning Notes =

* Scans can include active plugins only or include inactive plugins.
* A transient lock prevents overlapping scans.
* When requested, scans can be queued to run in the background through WP-Cron.
* Discovered hook data is cached and compacted to keep storage smaller.
* If uploads storage is not writable, scanner cache handling falls back to plugin data paths.

= Security Notes =

* Admin-only access (`manage_options`) for settings and AJAX actions.
* Nonce validation for admin AJAX endpoints.
* Outbound requests include bearer auth and HMAC headers (`X-Timestamp`, `X-Signature`).
* Hook args are not sent by default as raw payload.

== External Services ==

This plugin sends outbound HTTPS requests to an external endpoint.

= Service Endpoint =

Default URL:
`https://api-wpnotificator.netlify.app/.netlify/functions/wpnotif-api`

Filterable via:
`notificator_companion_api_endpoint`

= Purpose =

Receive notification events generated from enabled WordPress scenarios.

= Data Sent =

* Notification envelope (type/title/body/severity/source)
* Scenario metadata (`hook_name`, `scenario_name`, `scenario_notes`)
* Site metadata (`site_url`, `site_name`, WordPress version, plugin version, timestamp)
* Delivery flags (`sendPush`, `sendMqtt`)

= Data Not Sent By Default =

* Raw hook argument payloads
* Database contents
* User account exports

Note: if you configure scenario notes with placeholders, rendered values can include runtime-derived fields. Configure scenarios responsibly.

== Privacy ==

Notificator Companion does not track end users for analytics.

Operational event metadata is stored in WordPress options for logging/toast features and may be sent to the configured external endpoint when scenarios are triggered.

Site owners are responsible for reviewing their scenario configuration and ensuring compliance with their privacy obligations.

== Installation ==

1. Upload this folder to `/wp-content/plugins/notificator-companion/`.
2. Activate the plugin from `Plugins`.
3. Open `Notificator Companion` in the left admin menu.
4. Add API keys and save.

== Frequently Asked Questions ==

= Who can configure this plugin? =

Users with the `manage_options` capability.

= Can I use more than one API key? =

Yes. You can store multiple keys, add nicknames, and test each key directly from its row.

= Are hook arguments transmitted externally? =

Raw hook arguments are not sent directly. Scenario notes can optionally render selected runtime values if you configure placeholders.

= Can I export/import scenarios? =

Yes. Export produces JSON. Import supports merge or replace.

= Does scanning block the admin? =

Direct scans run immediately. Background scan requests can be queued via WP-Cron. A transient lock prevents concurrent scan jobs.

= Can developers extend templates? =

Yes. Use `notificator_companion_register_templates` and `notificator_companion_templates`.

== Screenshots ==

1. Main admin page with API keys and action bar
2. Plugin scan modal and discovered hooks
3. Scenario templates and custom scenario editor
4. Notification log and tools actions

== Changelog ==

= 2.0.0 =
* Reworked admin experience with scenario-focused workflow
* Multi-key API support with nickname fields and per-key testing
* Improved scanner with compact cache and storage fallback behavior
* Import/export scenarios and richer log management tools
* Dashboard toast notifications and delivery preferences
* Scan safety improvements: transient lock, background queue support, configurable scan limit

== Upgrade Notice ==

= 2.0.0 =
Major admin and scanner update with scenarios, improved logging, and safer scan execution.
