# Security policy

Please disclose security vulnerabilities privately through GitHub's **Report a vulnerability** feature when the public repository is available. Never publish passwords, exploit details, private user content, or production credentials in an issue or pull request.

**Production is separate from open source.** A fork or pull request must never receive production API tokens or deployment permissions.

## Hosting recommendations

- Keep `includes/config.php`, backup archives, logs, cron and database exports outside publicly accessible paths.
- Use minimal database and API-token permissions, unique credentials, HTTPS and supported PHP versions.
- Review changes before promoting them to production. Do not run untrusted PR content using privileged workflow credentials.
- Nostr relay events are publicly readable unless independently encrypted; signed does not mean private. Deletion is not a guaranteed purge across all relays.

Provide affected versions, reproducible steps and potential impact in any private report.
