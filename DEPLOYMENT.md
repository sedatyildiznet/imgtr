# IMGTR deployment

This repository contains only application source. The production `includes/config.php`, MySQL database, and server-generated content must remain on the hosting account.

## Repository secrets (Settings > Secrets and variables > Actions)

- `IMGTR_FTP_HOST`: FTPS hostname (must match TLS certificate)
- `IMGTR_FTP_USER`: FTP login (prefer cPanel FTP account restricted to imgtr.xyz document root)
- `IMGTR_FTP_PASSWORD`: FTP password

## Repository variables

- `IMGTR_FTP_SERVER_DIR`: FTP-relative website root, normally `./` for an FTP subaccount scoped to the document root. Include trailing `/`.
- `IMGTR_DEPLOY_ENABLED`: Set `true` **only after dry-run verification**.

## First deployment

1. Ensure the production installation's `includes/config.php` and any server-generated files are backed up.
2. Run `Actions > Validate and deploy IMGTR > Run workflow` with `dry_run = true`. Review remote directory, proposed changes and the FTPS TLS handshake.
3. If correct, run again with `dry_run = false` (manual first deployment).
4. After verifying imgtr.xyz, set `IMGTR_DEPLOY_ENABLED = true`. Subsequent pushes to main automatically deploy.

Never commit runtime config, credentials, database dumps, private data, or user content.

## ShareX installer

`downloads/IMGTR-ShareX-Setup.bat` is versioned in Git, but the main FTPS sync excludes the entire `downloads/` directory to protect production content. A separate FTPS step uploads and SHA-256-verifies **only** that `.bat` file on real deploys. Manual dry runs skip all uploads, including the `.bat` file. No other contents of `downloads/` are touched.
