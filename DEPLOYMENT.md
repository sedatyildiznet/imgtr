# IMGTR — cPanel API Token deployment

GitHub Actions deploys application files over **HTTPS 2083**, with cPanel API Tokens.
There is no SSH or FTP dependency.

## GitHub Repository Secrets

In Settings > Secrets and variables > Actions > Secrets, add:

- `CPANEL_HOST`: cPanel *server hostname*, for example `server.example.net`, without `https://`, port, or path; the hostname must have a trusted TLS certificate
- `CPANEL_USERNAME`: the cPanel account login (not the FTP username)
- `CPANEL_API_TOKEN`: create in cPanel > Security > Manage API Tokens (name: `imgtr-github`)

**Do not send the token in chat or commit it.**

## Repository Variables

In Settings > Secrets and variables > Actions > Variables, add:

- `CPANEL_DEPLOY_DIR`: IMGTR document root relative to cPanel home, e.g. `public_html` or `imgtr.xyz`. Check cPanel > Domains before choosing.
- `IMGTR_DEPLOY_ENABLED`: initially absent/false. Set to `true` only after the first manual production run is verified.

## First deploy

1. Create a full website + database backup on cPanel.
2. Set three GitHub Secrets and `CPANEL_DEPLOY_DIR`.
3. Run Actions > **Validate and deploy IMGTR** > **Run workflow** with `dry_run=true`.
4. Check the log: must find the existing `index.php`, `assets/`, `includes/`, `.htaccess`, and `includes/config.php` in the selected root; it must only PREVIEW changes.
5. Once confirmed, run again with `dry_run=false`. This overwrites *only* application code files, and does not delete remote files.
6. Check the site. Set `IMGTR_DEPLOY_ENABLED=true` to enable auto-deploy on `main` pushes.

## Protected production paths

The deployment explicitly excludes `.htaccess`, `includes/config.php`, `downloads/`, `uploads/`, `logs/`, `cache/`, `storage/`, `sessions/`, `.well-known/`, `updates/`, SQL dumps, project secrets, and GitHub workflow files.

No database migrations or deletions are performed automatically.
The ShareX BAT file is **not** deployed by this workflow.

GitHub Actions uses UAPI `Fileman::list_files` (read-only check) and `Fileman::upload_files` for files. If a source-code directory is missing, it uses cPanel API 2 `Fileman::mkdir`. Your hosting provider must allow these API functions over TLS port 2083.

For push-triggered runs, the script selects changed tracked files; manual deploys preview/upload all eligible application files.
