# Sam Anti Spam - Architecture

This document outlines the file and folder structure for the Sam Anti Spam WordPress plugin, as requested in Step 1.

## Proposed Directory Structure

```text
sam-anti-spam/
├── sam-anti-spam.php           # Main plugin file: headers, autoloader, activation hooks, init.
├── readme.txt                  # Standard WordPress plugin readme.
├── architecture.md             # This document.
├── includes/                   # All core PHP logic and classes.
│   ├── Core/                   # The Invisible Anti-Spam Engine.
│   │   ├── Engine.php          # Main engine orchestrator.
│   │   ├── JSInjector.php      # Handles injecting hidden JS and setting the verification cookie.
│   │   ├── Honeypot.php        # Generates and validates hidden form fields.
│   │   └── AjaxHandler.php     # Manages the 'wp_ajax_sam_check_spam' endpoint for async validation.
│   ├── SFW/                    # Spam FireWall (Early Access Interception).
│   │   └── Interceptor.php     # Logic for early IP blocking (to be included in wp-config.php or similar).
│   ├── Integration/            # Deep WordPress Integration hooks.
│   │   ├── Comments.php        # Hooks into 'pre_comment_approved'.
│   │   ├── Registrations.php   # Hooks into 'registration_errors'.
│   │   ├── Forms.php           # Integration hooks for CF7, WPForms, etc.
│   │   ├── WooCommerce.php     # Hooks for Woo checkout, registration, reviews.
│   │   └── Trackbacks.php      # Logic for filtering/disabling trackbacks and pingbacks.
│   ├── TrafficControl/         # Rate Limiting and Bot Management.
│   │   ├── RateLimiter.php     # Manages IP-based rate limits via WP Transients API.
│   │   └── BotManager.php      # Handles Good Bot whitelisting and manual White/Blacklists.
│   └── Admin/                  # Admin Dashboard & UI.
│       ├── Dashboard.php       # Handles the main settings pages and UI logic.
│       ├── Statistics.php      # Logic for the stats widget (using WP Transients).
│       └── SpamLogTable.php    # Extends WP_List_Table to display the log of blocked attempts.
└── assets/                     # Static files.
    ├── css/
    │   ├── admin.css           # Styles for the admin dashboard.
    │   └── frontend.css        # Minimal styles (e.g., hiding honeypot).
    └── js/
        ├── admin.js            # JS for admin settings interactions.
        └── sam-check.js        # The client-side JS injected by JSInjector.php.
```

## File Responsibilities

*   **`sam-anti-spam.php`**: The entry point. Registers the autoloader, handles plugin activation/deactivation, and initializes the core modules.
*   **`includes/Core/`**: Contains the frontend validation logic (JS cookie injection, client-side timing, honeypot generation) and the AJAX endpoint handler.
*   **`includes/SFW/Interceptor.php`**: Contains the standalone logic for server-level IP blocking before WordPress loads entirely.
*   **`includes/Integration/`**: Each file here encapsulates the specific hooks and logic needed to integrate the anti-spam engine with WordPress core features (comments, registrations) and third-party plugins (WooCommerce, CF7).
*   **`includes/TrafficControl/`**: Manages the rate limiting logic (using Transients for fast IP checks) and the bot whitelisting/blacklisting logic.
*   **`includes/Admin/`**: Manages all backend UI interfaces, including the settings page, the statistics widget, and the `WP_List_Table` implementation for the spam log.
*   **`assets/`**: Contains the minimal necessary CSS and JS files for both the frontend (honeypot hiding, cookie generation) and the backend (dashboard styling).
