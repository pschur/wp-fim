=== File Integrity Monitor ===
Contributors: pschur
Tags: security, file integrity, malware, monitoring, hashing
Requires at least: 6.4
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 1.0.0
License: Personal Use License
License URI: https://github.com/pschur/wp-fim/blob/main/LICENSE

Monitors WordPress core, plugins, and themes for unexpected file changes using MD5 hashes and alerts you immediately.

== Description ==

**File Integrity Monitor** creates a cryptographic fingerprint (MD5) of all PHP files in WordPress core, plugins, themes, and must-use plugins on activation, then checks on every page load whether the actually-loaded files still match the stored baseline.

=== Core Features ===

* **Real-time monitoring** — Only files loaded during the current request via `require`/`include` are checked. No full filesystem scan per request.
* **MD5 baseline** — All hashes are stored in a JSON file inside the plugin directory, protected from direct HTTP access via `.htaccess`.
* **Auto-repair** — Modified WordPress core files can optionally be restored automatically from `core.svn.wordpress.org`. The downloaded file is verified against the official WordPress.org checksum before it is written to disk.
* **Flexible alerting** — Admin notice in the dashboard, email alert (with 15-minute cooldown per file), and logging to `wp-content/fim-integrity.log`.
* **Deduplicated alerts** — Each modified file appears in the dashboard exactly once, with a hit counter plus first-seen and last-seen timestamps.
* **Exclusions** — Individual plugins or themes can be excluded from the automatic scan and from runtime checks.
* **Selective baseline** — Each area (core, individual plugins, individual themes, mu-plugins) can be re-indexed on its own. A "Scan All Areas" button is also available.
* **Automatic baseline updates** — After WordPress core updates, plugin updates, theme updates, and theme switches, the baseline for the affected area is rebuilt automatically.
* **Internationalized** — Admin UI available in English (default) and German (`de_DE`).

=== Response to Detected Changes ===

| Area | Response |
|---|---|
| WordPress Core | Optional: block request (HTTP 500) and/or auto-repair |
| Plugins / mu-plugins | Admin notice, email alert, log entry |
| Themes | Admin notice, email alert, log entry |

=== Privacy & Security ===

* `data/hashes.json` is protected from direct HTTP access via `.htaccess`
* All admin actions are guarded by nonces and `current_user_can('manage_options')`
* Paths are never constructed from user input; exclusion lists are validated against actually installed slugs
* During auto-repair, the downloaded file's MD5 is verified against the official WordPress.org checksum before the local copy is overwritten

== Installation ==

1. Upload the `file-integrity-monitor` folder to `wp-content/plugins/`.
2. Activate the plugin under **Plugins → Installed Plugins**.
3. On activation a full baseline scan of all areas is performed automatically.
4. The admin panel is available under **Tools → File Integrity**.

**System requirements for auto-repair:**
The web server needs outbound HTTPS access to `api.wordpress.org` and `core.svn.wordpress.org`.

== Frequently Asked Questions ==

= How much performance overhead is there per request? =

The plugin checks only files that were actually loaded during the current request (via PHP's `get_included_files()`). For a typical WordPress request that is around 200–400 files. `md5_file()` is a C-implemented PHP function and very fast; the overhead is approximately 10–30 ms per request.

= What happens when a core file is modified? =

By default the request is aborted with HTTP 500, and an admin notice plus an optional email are sent. If auto-repair is enabled, the file is restored from WordPress.org first — if the repair is successful, the request continues normally.

= Can I disable request blocking? =

Yes. Under **Settings → Detection Behavior** the "Block Requests" option can be unchecked. Changes are still logged and reported, but the request is not interrupted.

= How do I update the baseline after a plugin update? =

In the dashboard under **Tools → File Integrity** each area can be re-scanned individually. After WordPress updates, plugin updates, and theme updates the baseline for the affected area is also updated automatically.

= Why does auto-repair show "No official checksum available"? =

WordPress.org provides checksums for all official releases. This error appears when the installed WordPress version is not listed in the checksums API (e.g. development builds or unofficial packages) or when no outbound HTTPS connection to `api.wordpress.org` is possible.

= Can I exclude plugins or themes from monitoring? =

Yes. Under **Settings → Exclusions** individual plugins and themes can be excluded via checkbox. Excluded areas are skipped during both the automatic full scan and runtime checks. A manual scan from the Dashboard button remains possible at any time.

= Where are detected changes logged? =

All alerts are stored as a transient in the WordPress database (visible in the Dashboard) and written to `wp-content/fim-integrity.log` on the filesystem.

= Is the plugin compatible with SQLite-based WordPress installations? =

Yes. The plugin uses only standard WordPress APIs (`get_option`, transients, `wp_mail`) and is fully compatible with SQLite backends.

== Changelog ==

= 1.0.0 =
* Initial release
* Real-time monitoring via `get_included_files()` at `plugins_loaded` and `wp_loaded`
* MD5 baseline in JSON with atomic writes via temp file
* Auto-repair of core files via WordPress.org SVN with checksum verification
* Deduplicated alert view with hit counter, `first_seen`, and `last_seen`
* Per-plugin and per-theme exclusions
* Full-scan button and per-area manual scan
* Automatic baseline updates after updates and theme switches
* Email alert with 15-minute cooldown per file
* Log file at `wp-content/fim-integrity.log`
* Full i18n support (English default, German `de_DE` included)

== Upgrade Notice ==

= 1.0.0 =
Initial installation. On activation a full baseline scan is performed automatically — this may take a few seconds on sites with many plugins.
