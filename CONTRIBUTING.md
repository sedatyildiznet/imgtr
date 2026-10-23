# Contributing

- Fork the public source repository and open a focused pull request.
- Do not commit secrets, user data, production configuration or database exports.
- Keep PHP 8.1 compatibility, validate inputs and use prepared SQL statements.
- Run `find . -type f -name '*.php' -print0 | xargs -0 -r -n1 php -l`.
- Run `node --test sdk/js/tests/*.test.js`; Windows-specific ShareX checks need Windows.
- Open vulnerabilities privately as described in [SECURITY.md](SECURITY.md).

Helpful contributions include reproducible database initialization, Nostr compatibility, translations, accessibility and tests.
