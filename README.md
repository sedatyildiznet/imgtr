# IMGTR

Framework-free PHP 8.1 image host designed for ordinary cPanel accounts. It uses MySQL/MariaDB, PDO, PHP sessions, cURL, browser-side JavaScript, and public Nostr relays—no Composer, Node, Redis, queue worker, Docker, or daemon.

## Deployment

This repository is for the already-installed IMGTR instance. The public web installer has been removed for security. Upload changed files to `public_html`, preserve the production database credentials in `includes/config.php`, and ensure `logs/` is writable by PHP. Image binaries are not persisted on the origin server. Use HTTPS in production.

Import `updates/2026-08-31-admin-control.sql` once through phpMyAdmin after deploying this version. It creates the account restriction, administrator audit and privacy-preserving traffic analytics tables; the statements are idempotent. Traffic charts begin collecting data after this migration is installed.

The production database is expected to already include the previously deployed authentication activity, multi-upload album and relay-deletion tables. New uploads retain an uploader-signed NIP-09 deletion request while the ephemeral uploader key still exists. Older uploads cannot produce a valid NIP-09 event after the key has been discarded; they are still blocked locally but are reported as `legacy_unavailable` rather than falsely reported as deleted from relays.

## Cron

Replace `/home/USER/public_html` with the actual absolute path:

```text
*/15 * * * * php -q /home/USER/public_html/cron/check-relays.php >> /home/USER/public_html/logs/cron-relays.log 2>&1
*/10 * * * * php -q /home/USER/public_html/cron/process-deletions.php >> /home/USER/public_html/logs/cron-deletions.log 2>&1
```

Both jobs use non-blocking file locks, so a slow run cannot overlap the next scheduled run. Import the relay-deletion SQL update before enabling `process-deletions.php`.

## Required PHP extensions

`pdo`, `pdo_mysql`, `fileinfo`, `curl`, `openssl`, `mbstring`, and `gd` (or ImageMagick) are recommended. `allow_url_fopen` is needed for the lightweight relay health check.

## Configuration

All operational constants live in `includes/config.php`: `MAX_FILE_SIZE`, `MAX_FILES`, `MAX_IMAGE_PIXELS`, `NOSTR_CHUNK_SIZE`, `RELAY_TARGET`, and `RELAY_MINIMUM`.

Default relay records include `wss://relay.damus.io`, `wss://nos.lol`, `wss://relay.primal.net`, `wss://relay.nostr.band`, `wss://relay.snort.social`, `wss://nostr.wine`, `wss://relay.nostr.net`, and `wss://nostr.mom`. Relay availability and publishing rules change frequently; use `/admin/relays` to review them.

## Nostr design and limitations

The browser generates an ephemeral key and signs image manifest/chunk events itself. IMGTR stores no Nostr private key. This is what keeps upload bandwidth off your cPanel host.

The browser publishes signed manifest and uniquely addressed chunk events through bounded acknowledgement windows. It returns as soon as a safe relay quorum is available instead of waiting for every slow endpoint. The origin then reads those exact event IDs back from configured relays before marking an image ready. Direct image requests reconstruct verified chunks in memory through the built-in read-only WebSocket client; no image file is written to origin disk. The `check-relays.php` job also uses NIP-11 limits to exclude relays that explicitly require authentication/payment or cannot accept IMGTR event sizes. A zero-storage deployment cannot republish an older upload after its ephemeral uploader key has been discarded.

## SDK, CLI and API v1

The official Node.js SDK and CLI live in `sdk/js` and require Node.js 20+. They decode/re-encode JPG, PNG and WEBP locally, create and sign the Nostr manifest/chunks with an ephemeral key, publish directly to the configured relays, and ask IMGTR to read back and verify the exact event ids. GIF retains the website's current pass-through behavior.

Run `npm install && npm link` inside `sdk/js` for repository development. The deployable package is generated with `pnpm pack` and can be installed from `/sdk/js/imgtr-sdk-1.2.0.tgz`. Use `imgtr config set-key <key>` or the `IMGTR_API_KEY` environment variable. The SDK exports `ImgtrClient` for uploads, image listing/detail/deletion, account lookup and relay inspection. If npm reports that the `sharp` install script was blocked, rerun the installation with the exact `--allow-scripts=sharp` command npm prints.

The versioned API is under `/api/v1`; its OpenAPI 3.1 document is `/api/openapi.json`. All authenticated endpoints use `Authorization: Bearer imgtr_...`, return a request id, and use a consistent `{success,data,meta,request_id}` or `{success:false,error,request_id}` envelope. Traditional multipart upload remains disabled because image bytes must never pass through origin storage.

## ShareX desktop integration

The public setup page is `/app`. For ordinary Windows users, `/downloads/IMGTR-ShareX-Setup.bat` installs Node.js LTS when needed, verifies the fallback MSI checksum, installs SDK/CLI 1.2.0 and enables the background integration in one run. ShareX custom uploaders can only submit an HTTP request; they cannot perform IMGTR's client-side Nostr signing and multi-relay publication. IMGTR therefore does not expose an origin multipart upload endpoint. `imgtr sharex install` installs a hidden per-user Windows Startup launcher and starts the loopback bridge immediately, without an open terminal. The official `.sxcu` sends the screenshot to `http://127.0.0.1:43191/upload`, where the bridge reuses the SDK pipeline and returns `{success:true,data:{url,direct_url,...}}` for ShareX to parse after the required relay quorum is verified. Remaining relay publication continues in the local bridge process. Use `imgtr sharex status`, `start`, `stop`, or `uninstall` to manage it.

An authenticated POST to `/account/integrations/sharex/download` creates or replaces one dedicated `IMGTR ShareX` API key and downloads `IMGTR.sxcu`. Only the key hash is persisted. The secret exists in the downloaded file, is never rendered into page HTML and can be revoked from the API access panel. The response is private and non-cacheable. The local bridge accepts loopback requests only, rejects browser `Origin` requests, requires the embedded Bearer key and never logs it.

## Security

The browser decodes supported images before publication and rebuilds JPG, PNG and WEBP files to remove embedded metadata. URL imports use the browser's CORS boundary and never pass through the origin. Relay events are accepted only after event-ID, manifest, each chunk hash, and the reconstructed file's total size and SHA-256 are independently read back and verified. Database access uses PDO prepared statements; browser form requests use CSRF tokens; sessions are `HttpOnly`/`SameSite=Lax`; and rate limits use MySQL. Set restrictive permissions on `includes/config.php` after installing.
