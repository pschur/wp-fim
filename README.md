# File Integrity Monitor

A WordPress security plugin that monitors core files, plugins, and themes for unexpected changes using MD5 hashes and optionally restores them automatically.

## Features

- **Real-time monitoring** — Checks only files actually loaded during the current request via `get_included_files()`. No full filesystem scan per request.
- **MD5 baseline** — All hashes are stored in `data/hashes.json`, protected from direct HTTP access via `.htaccess`.
- **Auto-repair** — Modified core files can optionally be restored automatically from `core.svn.wordpress.org`. The downloaded file's MD5 is verified against the official WordPress.org checksum before it is written to disk.
- **Deduplicated alerts** — Each modified file appears once in the dashboard, with a hit counter plus first-seen and last-seen timestamps.
- **Exclusions** — Individual plugins or themes can be excluded from the automatic scan and runtime checks.
- **Selective baseline** — Each area can be re-indexed individually; a "Scan All Areas" button is also available.
- **Automatic baseline updates** — After core, plugin, and theme updates as well as theme switches, the baseline for the affected area is updated automatically.
- **Internationalized** — Admin UI available in English (default) and German (`de_DE`).

## Response to Detected Changes

| Area | Response |
|---|---|
| WordPress Core | Optional: block request (HTTP 500) and/or auto-repair |
| Plugins / mu-plugins | Admin notice, email alert, log entry |
| Themes | Admin notice, email alert, log entry |

## Installation

1. Copy the `file-integrity-monitor` folder into `wp-content/plugins/`.
2. Activate the plugin under **Plugins → Installed Plugins**.
3. On activation a full baseline scan of all areas is performed automatically.
4. Admin panel: **Tools → File Integrity**

> **Note for auto-repair:** The web server needs outbound HTTPS access to `api.wordpress.org` and `core.svn.wordpress.org`.

## File Structure

```
file-integrity-monitor/
├── file-integrity-monitor.php   # Bootstrap, hooks, activation
├── includes/
│   ├── class-fim-scanner.php    # MD5 hashing, scanning, JSON persistence
│   ├── class-fim-checker.php    # Runtime checking via get_included_files()
│   ├── class-fim-alerts.php     # Admin notice, email, log
│   ├── class-fim-repair.php     # Auto-repair via WordPress.org SVN
│   └── class-fim-admin.php      # Admin panel, Settings API, AJAX
├── admin/
│   └── views/
│       ├── page-dashboard.php   # Status, repair log, alert list
│       └── page-settings.php    # Settings, exclusions
├── languages/
│   ├── file-integrity-monitor-de_DE.po
│   └── file-integrity-monitor-de_DE.mo
├── data/
│   ├── .htaccess                # Deny from all
│   └── hashes.json              # Baseline (created at runtime)
└── readme.txt
```

## Requirements

| | |
|---|---|
| WordPress | ≥ 6.4 |
| PHP | ≥ 8.1 |
| Database | MySQL or SQLite (fully compatible) |

## Security

- `data/hashes.json` is protected from direct HTTP access via `.htaccess`
- All admin actions are guarded by nonces and `current_user_can('manage_options')`
- Exclusion lists are validated against actually installed slugs (allowlist)
- During auto-repair, the downloaded file's MD5 is verified against the official WordPress.org checksum before the local copy is overwritten

## FAQ

**How much performance overhead is there per request?**
About 10–30 ms. `md5_file()` is a C-implemented PHP function; a typical WordPress request loads 200–400 files.

**What happens when a core file is modified?**
By default the request is aborted with HTTP 500. With auto-repair enabled, the file is restored from WordPress.org first — if successful, the request continues normally.

**Can I disable request blocking?**
Yes, under **Settings → Detection Behavior**. Changes are still logged and reported but the request is not interrupted.

**How do I update the baseline after an update?**
Use the Scan button in the Dashboard. After WordPress, plugin, and theme updates the baseline for the affected area is also updated automatically.

**Where are alerts logged?**
As a transient in the WordPress database (visible in the Dashboard) and in `wp-content/fim-integrity.log` on the filesystem.

## Changelog

### 1.0.0
- Initial release
- Real-time monitoring via `get_included_files()` at `plugins_loaded` and `wp_loaded`
- Auto-repair via WordPress.org SVN with checksum verification
- Deduplicated alert view with hit counter, `first_seen`, and `last_seen`
- Per-plugin and per-theme exclusions
- Full-scan button and per-area manual scan
- Email alert with 15-minute cooldown per file
- Full i18n support (English default, German `de_DE` included)

## License

[Personal Use License](LICENSE) — Personal, non-commercial use only.
For commercial licensing: git@paul-plus.de
