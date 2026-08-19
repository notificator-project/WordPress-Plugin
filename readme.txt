=== Notificator – Alerts & Notifications ===
Contributors: eboxnet
Donate link: https://buymeacoffee.com/vagelis
Tags: notifications, alerts, hooks, monitoring, mqtt
Requires at least: 5.0
Tested up to: 7.0
Requires PHP: 7.2
Stable tag: 1.2
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Turn WordPress events into dashboard alerts, with optional mobile push and MQTT delivery.

== Description ==

Notificator is the official WordPress plugin of the Notificator Project. It helps site administrators notice important WordPress events without watching logs or writing a complete monitoring integration.

Discover events exposed by WordPress and installed plugins, choose the events that matter, and decide how each notification should be delivered. Dashboard notifications work entirely inside WordPress and do not require an account or API key.

For remote delivery, connect an optional Notificator API key. The Notificator mobile app can then receive push alerts and display notification details on your phone, while MQTT can deliver events to connected devices through your own HiveMQ Cloud cluster.

Download **Notificator Project** for iPhone or iPad from the [Apple App Store](https://apps.apple.com/app/notificator-project/id6758410275). The Android app is coming to Google Play and is not publicly available yet.

Optional email alerts are controlled as an account preference from the mobile app. Mobile push and MQTT remain the per-notification remote delivery choices in WordPress.

= What you can do =

* Discover actions and filters in WordPress Core and installed plugins.
* Review likely events in a ranked inbox with source, confidence, and noise information.
* Start from ready-made templates or create a notification from scratch.
* Deliver each event to the WordPress dashboard, mobile push, MQTT, or a combination.
* Add conditions so a notification is sent only when its event data matches your rules.
* Add placeholders, priority, and throttling to make alerts more useful and less noisy.
* Export notification configurations as JSON and reuse them across WordPress sites.
* Manage notifications and review delivery status from one activity workspace.
* Register well-described events and templates from another plugin or theme.

= Events, templates, and notifications =

These three terms describe different stages:

* An **event** is something that can happen in WordPress, such as a user signing in or an order changing status. Discovering or registering an event does not start monitoring it.
* A **template** is a ready-made starting configuration for an event. Applying a template opens a draft that you can review and change.
* A **notification** is the saved rule that listens for an event. Only enabled notifications can create alerts.

= Safer hook discovery =

Scanning runs locally in resumable background batches, processes one plugin at a time, limits per-plugin file and execution work, reuses unchanged results, and prevents overlapping jobs. Existing discoveries remain available until a new scan finishes. Discovery separates likely events from registrations and dynamic patterns. Optional observation samples traffic and batches database writes; it records approximate counts, argument types, and execution context, but not argument values.

== Installation ==

1. Install and activate Notificator.
2. Open **Notificator > Overview** in wp-admin.
3. Select **Scan plugins** to discover available site events.
4. Apply a template or create a notification from a discovered event.
5. Keep **Dashboard** enabled to receive alerts inside WordPress.
6. Optional: get the Notificator mobile app, create an account and API key, then add and enable that key in Settings.
7. Enable Mobile push or MQTT on the notifications that need remote delivery.
8. Optional: under Settings > MQTT broker, connect your own HiveMQ Cloud cluster and use the same topic prefix in your device firmware.

No API key is required for setup, discovery, templates, activity, or dashboard notifications.

== Frequently Asked Questions ==

= Do I need a Notificator account or API key? =

No. Dashboard delivery and the plugin's setup and management features work without one. An API key is needed only for mobile push or MQTT delivery.

= What is the difference between a template and a notification? =

A template is an editable starting point. It does not listen for an event by itself. A notification is the saved, enabled rule that listens for an event and delivers an alert through your selected channels.

= Does scanning send my plugin code to an external service? =

No. Hook discovery runs on your WordPress installation. Optional observation stores metadata such as execution counts and argument types, not argument values.

= Are hook arguments sent to Notificator? =

The plugin does not send raw hook arguments as a complete payload. It sends the notification content and metadata described under External Services.

Rendered placeholder values become part of the notification and may contain personal or sensitive information. Review each remote notification before enabling it.

= How do I get a Notificator account? =

Download **Notificator Project** for iPhone or iPad from the [Apple App Store](https://apps.apple.com/app/notificator-project/id6758410275). Install the app and select the option to create an account. Registration is completed in the mobile app. Notificator is also coming to Google Play for Android, but that release is not publicly available yet.

= How do I receive notifications in the mobile app? =

Install and sign in to the Notificator mobile app, create an API key from the app, and add that key in the plugin's Settings tab. Enable Mobile push on the notifications you want on your phone and make sure the app has notification permission.

= Can I use my own HiveMQ Cloud account? =

Yes. HiveMQ Cloud is the only MQTT provider supported by the current release. HiveMQ offers a free Serverless plan with no credit card required, subject to HiveMQ's current limits and terms.

Create an account at https://console.hivemq.cloud/, choose Create Serverless Cluster, and open the cluster overview. Copy the cluster URL and enter only its hostname in Notificator. Under Access Management, create a Publish Only username and password for WordPress and a separate Publish and Subscribe credential for the device.

Enable MQTT delivery under Settings > MQTT broker, then enter the hostname, publisher username, password, and topic prefix. New configurations default to `notificator-project`. The device must use the same cluster and topic prefix.

The password is encrypted locally using keys derived from this WordPress installation. It is excluded from exports, logs, diagnostics, and saved retry payloads. For an MQTT-enabled delivery, the plugin decrypts it only while preparing the HTTPS request to the Notificator API.

= Is there a Pro or paid version? =

No. Notificator is free and open source, and we intend to keep its current features free. We may introduce optional new features or hosted services in the future, but they will not remove or place the functionality available today behind a paywall.

= Can another plugin register an event or template? =

Yes. Integrations can register documented events with `notificator_companion_register_event()` and provide templates through `notificator_companion_register_templates`. See the repository README and integration guide for complete examples.

= Can I reuse my notification setup on another site? =

Yes. Export configured notifications as JSON from Tools and import them on another site. You can merge with the destination setup or replace it. Review plugin availability, hooks, placeholders, API keys, and delivery channels before enabling imported notifications.

= What happens to plugin data when I uninstall it? =

Deactivation keeps the configuration. Deleting the plugin through WordPress removes all plugin-owned settings, notifications, activity, scan caches, scheduled jobs, user metadata, and transients, including across multisite.

The Tools dialog also provides a test reset that keeps saved API keys and the custom MQTT connection.

== Support Development ==

If Notificator is useful to you, you can support its continued development through [Buy Me a Coffee](https://buymeacoffee.com/vagelis) or [GitHub Sponsors](https://github.com/sponsors/vagelisp). Donations are optional and do not unlock features or priority access.

For other ways to contribute or support the platform, [get in touch](https://notificator-project.com/contact/).

== External Services ==

Notificator can connect to the Notificator service, operated by Notificator Project, to deliver notifications outside WordPress. This connection is optional. Dashboard-only notifications do not use the service.

The plugin connects to the Notificator API at `https://wpnotif.notificator-project.com` only when an administrator tests an API key, an enabled notification requests Mobile push or MQTT delivery, or a previously configured website monitor is sent to the service. The plugin does not load executable code, fonts, stylesheets, images, or other assets from this or any other remote service.

= Data sent to the service =

For authentication and request security, the request includes the enabled API key, site origin, timestamp, nonce, and HMAC signature.

For a triggered notification, the request can include:

* Notification title, body, priority, source, rendered notes, hook name, and notification name.
* Mobile push and MQTT delivery choices.
* Site URL and name, WordPress and plugin versions, and event timestamp.
* Administrator-configured placeholder values.
* For a configured website monitor, its name, URL, HTTP method, and enabled state.
* If a HiveMQ Cloud connection is enabled, its hostname, WSS port and path, publisher username and password, and topic prefix. These values are sent only for MQTT-enabled requests so the API can connect to that broker.

Raw hook arguments, WordPress database contents, and user exports are not sent wholesale.

= How the service uses the data =

The service validates the API key and allowed domains. Notification content may be encrypted with the account's public key and stored in Supabase for the Notificator app. Depending on account settings, it can use Expo for push, the administrator's HiveMQ cluster for MQTT, and Resend for email. Push previews may be generic while full content remains encrypted. HiveMQ credentials are used for the current request and are not stored in the Notificator account database.

Use of the remote service is subject to the information published by [Notificator Project](https://notificator-project.com/), its [documentation](https://docs.notificator-project.com/), and its [privacy policy](https://notificator-project.com/privacy/).

HiveMQ Cloud is an independent third-party service. Its plans, limits, terms, and availability are controlled by HiveMQ. See https://www.hivemq.com/products/mqtt-cloud-broker/ and https://docs.hivemq.com/hivemq-cloud/quick-start-guide.html.

== Privacy ==

Notificator does not add analytics or advertising tracking.

The plugin stores configuration, discovery metadata, activity, dashboard-toast data, and temporary delivery jobs in the WordPress database. A custom MQTT password is encrypted in a separate, non-autoloaded option. Scan caches may be stored in uploads. Observation stores counts and type metadata, not argument values.

Remote delivery is opt-in per notification and requires an enabled API key. Administrators control the notification text and placeholders and are responsible for avoiding unnecessary personal or sensitive information.

Deleting the plugin through WordPress removes plugin data as described in the FAQ.

== Development ==

Human-readable TypeScript, SCSS, and PHP source code, along with build scripts and development instructions, is available in the [Notificator GitHub repository](https://github.com/notificator-project/WordPress-Plugin).

The minified files in `assets/dist` are generated from `assets/js` and `assets/src`. Run `npm ci` followed by `npm run build` to reproduce them.

== Screenshots ==

1. Overview with setup progress, delivery health, and recent events.
2. Ready-made templates for popular WordPress plugins and common events.
3. Discovery inbox with ranked events, plain-language descriptions, and quick creation.
4. Guided notification editor for choosing a source, event, message, and delivery channels.

== Changelog ==

= 1.2 =

* Adds first-class support for connecting a user-owned HiveMQ Cloud cluster.
* Encrypts the MQTT password locally and excludes it from exports, diagnostics, logs, and saved retry jobs.
* Sends MQTT credentials only in memory for an enabled MQTT delivery or explicit connection test.
* Adds an MQTT connection test and clearer delivery-readiness feedback.
* Reorganizes Settings into consistent, more readable sections and tabs.
* Improves settings typography, contrast, responsive behavior, and in-place controls.
* Removes the implicit default MQTT broker and defaults new topic prefixes to `notificator-project`.

= 1.1.16 =

* Prevents saved field metadata from selecting or invoking runtime object methods.
* Resolves supported WooCommerce and Contact Form 7 object fields through explicit server-side handlers.
* Removes obsolete getter-method metadata from built-in templates and adds a trusted PHP filter for custom integrations.

= 1.1.5 =

* Prompts for a new discovery scan when a plugin is activated after the last successful scan.
* Identifies newly activated plugins directly in the Overview scan step.
* Hides the first-time scan prompt immediately after event discovery completes, without requiring a page reload.
* Marks the Overview discovery step complete and refreshes its scan summary immediately after a successful scan.
* Refreshes the Discovery inbox, event cards, filters, and totals in place when a scan finishes.

= 1.1 =
* Initial public release of Notificator.
* Includes local dashboard alerts, optional mobile push and MQTT, event discovery, ready-made templates, conditions, activity history, integration APIs, and complete uninstall cleanup.
