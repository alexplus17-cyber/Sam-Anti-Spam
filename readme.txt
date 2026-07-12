=== Sam Anti Spam ===
Contributors: alex
Tags: spam, anti-spam, firewall, security, comments, registration
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A premium WordPress anti-spam plugin with Invisible Anti-Spam Engine, Deep Integrations, and Traffic Control.

== Description ==

Sam Anti Spam is a comprehensive security and anti-spam solution for WordPress. It protects your site from malicious bots, spam comments, fake registrations, and brute-force attacks without requiring your users to solve annoying CAPTCHAs.

Our multi-layered defense system includes:
*   **Invisible Heuristics:** We use honeypots, JS token verification, and submission timing checks to silently catch bots.
*   **Spam FireWall (SFW):** An early-interception script that blocks known bad IPs at the server level via `.htaccess` before WordPress even loads, saving you precious server resources.
*   **Cloud API Validation:** A robust fallback to our Cloud Backend to verify IPs and emails against a global reputation database.
*   **Deep Integrations:** Native support for WooCommerce checkouts, Contact Form 7, default WP comments, and user registrations.
*   **Traffic Control:** Built-in rate limiting to prevent form abuse and DoS attempts.

== Installation ==

1. Upload the `sam-anti-spam` folder to your `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to **Settings > Sam Anti Spam**.
4. In the **General** tab, click **Connect to Sam Anti Spam Cloud** to provision your API key.
5. (Optional) Enable the **Spam FireWall (SFW)** for server-level protection. *Note: Ensure your host supports .htaccess auto_prepend_file.*
6. Configure your desired integrations in the **Integrations** tab.

== Frequently Asked Questions ==

= Does this require a CAPTCHA? =
No. The plugin operates entirely invisibly using JavaScript tokens, honeypots, and cloud verification.

= Will the Spam FireWall work on Nginx/FastCGI? =
The SFW currently relies on `.htaccess` rules (`php_value auto_prepend_file`), which are primarily supported by Apache. If you are on an exclusive Nginx or strict PHP-FPM environment without Apache `.htaccess` parsing, the SFW feature may not function. However, the core plugin heuristics will still protect your forms.

= What if I get locked out by the FireWall? =
You can use the **Restore .htaccess** button in the settings, or manually edit your `.htaccess` file via FTP to remove the `auto_prepend_file` rule.

== Screenshots ==

1. The clean, tabbed Settings dashboard.
2. The Spam FireWall configuration and status.
3. Integration settings for WooCommerce and Contact Form 7.
4. The comprehensive Spam Log view.

== Changelog ==

= 1.0.0 =
* Initial Release.
* Added Phase 1: Core Heuristics (JS, Honeypot, Rate Limiting).
* Added Phase 2: Admin UI and Bot Management.
* Added Phase 3: Cloud API connectivity and DB logging.
* Added Phase 4: Spam FireWall (SFW) early interception.
* Added Phase 5: Security hardening and QA fixes.
* Added Phase 6: Asynchronous Cloud reporting.
* Added Phase 7: Dynamic onboarding and registration.

== Upgrade Notice ==

= 1.0.0 =
Initial release. No upgrade necessary.