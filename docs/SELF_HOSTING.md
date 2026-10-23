# Self-hosting notes

IMGTR currently publishes source from an existing installation, not a complete fresh-install distribution. **The initial database schema is not included.** Migrations in `updates/` extend existing installations; they cannot bootstrap an empty database.

Requirements: PHP 8.1+, MySQL/MariaDB, Apache with mod_rewrite, HTTPS, PDO MySQL, curl, OpenSSL, mbstring and fileinfo.

Copy `includes/config.example.php` to `includes/config.php` only on your own server, then configure site URL, credentials and runtime limits. Protect configuration, logs, backups, cron and administrative files from HTTP access.

The public source contains no live deployment credentials or production workflow. Keep those in a separate private repository.
