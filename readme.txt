=== Sam Anti Spam ===
Contributors: alex
Tags: spam, anti-spam, firewall, security, comments, registration
Requires at least: 5.0
Tested up to: 6.7
Stable tag: 1.1.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sam Anti Spam is a lightweight anti-spam plugin for WordPress. It protects comments, registrations, contact forms, and other user-submitted content with invisible heuristics, optional cloud verification, and a configurable Spam FireWall.

== Description ==

Sam Anti Spam adds layered protection without forcing visitors through CAPTCHAs. It includes:

* Invisible heuristics such as honeypots, JavaScript verification, and timing checks.
* Optional cloud verification for IP and email reputation checks.
* Local rate limiting to reduce abuse and bot traffic.
* Optional Spam FireWall support using `.htaccess` rules on compatible Apache servers.

This plugin does not require Composer to run after installation. The core plugin package is a normal WordPress plugin and is designed for installation from the WordPress.org ZIP package without any post-install build step.

The optional cloud service is hosted at https://api.samantispam.com and is used only when the site is configured to use cloud verification. The plugin still provides local heuristic protection even when cloud verification is not configured.

== Installation ==

1. Upload the `sam-anti-spam` folder to your `/wp-content/plugins/` directory.
2. Activate the plugin through the Plugins menu in WordPress.
3. Go to Settings > Sam Anti Spam.
4. Optionally connect to the Sam Anti Spam cloud service for reputation checks.
5. Optionally enable the Spam FireWall (SFW). This feature depends on Apache `.htaccess` support and may not work on Nginx or other server setups that do not process the relevant directives.
6. Configure the desired integrations and review the spam log.

== External Services ==

This plugin can optionally send request metadata to the hosted cloud service at https://api.samantispam.com when cloud verification is enabled.

Data sent may include:

* Visitor IP address
* User agent string
* Email address (when available)
* Submission action type
* Site URL and administrative email when registering the site
* Spam-related metadata used to evaluate whether a submission should be blocked

The cloud service is optional. It is not required for the plugin to operate with local heuristics, but enables optional reputation-based checks. Users can disable the service by disconnecting the API key from the plugin settings or leaving the cloud settings blank.

== Frequently Asked Questions ==

= Does this require a CAPTCHA? =
No. The plugin operates invisibly using JavaScript verification, honeypots, and timing checks.

= Will the Spam FireWall work on Nginx/FastCGI? =
The SFW feature relies on Apache `.htaccess` directives such as `php_value auto_prepend_file`. This is not universally supported on Nginx, some FastCGI setups, or environments without Apache `.htaccess` parsing. The core plugin protections still work without the firewall layer, but the server-side SFW may not be active in unsupported environments.

= What if I get locked out by the FireWall? =
Use the Restore .htaccess button in the plugin settings or remove the plugin markers from your `.htaccess` file manually.

== Screenshots ==

1. Settings dashboard.
2. Spam FireWall configuration.
3. Integration settings.
4. Spam log screen.

== Changelog ==

= 1.1.0 =
* Hardened cloud request handling and release packaging.
* Added safer firewall behavior and clearer Apache-only documentation.
* Improved settings and AJAX reliability for production use.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.1.0 =
This release improves cloud safety, packaging, and firewall compatibility checks. No manual migration is required for standard installations.