<div align="center">

# IMGTR

**Images, distributed over Nostr.**

A lightweight PHP image-publishing app that signs and distributes images across Nostr relays without permanently storing image binaries on the origin server.

[**Website**](https://imgtr.xyz) · [**API**](https://imgtr.xyz/api) · [**JavaScript SDK**](sdk/js/README.md) · [**Windows Setup**](https://raw.githubusercontent.com/sedatyildiznet/imgtr/main/downloads/IMGTR-ShareX-Setup.bat) · [**Türkçe**](README.tr.md)

![PHP 8.1+](https://img.shields.io/badge/PHP-8.1%2B-777BB4)
![Nostr](https://img.shields.io/badge/Protocol-Nostr-6941C6)
[![License: MIT](https://img.shields.io/badge/License-MIT-111827)](LICENSE)

</div>

---

## Why IMGTR?

Most image hosts keep files on their own disks. IMGTR processes supported images client-side, signs Nostr manifests and chunks with ephemeral keys, and publishes those events to multiple relays. The origin keeps account and image metadata in MySQL/MariaDB, then verifies relay content when images are read.

**Open source does not mean encrypted storage.** Nostr relays can be public, and independently retained copies may remain available.

## Features

- **Nostr-native:** signed manifests, event-based chunks and relay replication.
- **Verification:** independently check event IDs, content hashes, reconstructed size and relay acknowledgements.
- **Zero permanent image binary storage on the origin.**
- **Built for ordinary hosting:** PHP, MySQL/MariaDB, Apache; no Docker or background Node server required.
- **Full web interface:** accounts, albums, collections, admin tools, translated UI.
- **Developer-friendly:** REST API v1, OpenAPI 3.1, Node.js SDK/CLI and ShareX bridge.

## Architecture

```text
Browser / SDK
    │ Prepare image, sign manifest & chunks
    ▼
Public Nostr relays ◄── signed publications
    │
    ▼
IMGTR PHP API ──────── MySQL / MariaDB
    │                  users, metadata, albums
    ▼
Verified viewer / image reconstruction
```

See [Architecture](docs/ARCHITECTURE.md).

## Getting started

**Requirements:** PHP 8.1+, Apache/mod_rewrite, MySQL/MariaDB, HTTPS, PDO MySQL, cURL, OpenSSL, mbstring and fileinfo. Node.js 20+ is required for SDK/CLI usage only.

```bash
cp includes/config.example.php includes/config.php
# Enter your own database credentials and site URL.
```

**Important:** IMGTR's public code currently comes from an existing production installation. The complete *fresh-install SQL bootstrap is not yet available*. SQL scripts under `updates/` are incremental changes, not a full installation schema. Do not deploy to an empty database expecting a complete installer. See [Self-hosting notes](docs/SELF_HOSTING.md).

## SDK and API

```bash
cd sdk/js
npm install
npm test
npm link
```

```js
import { ImgtrClient } from '@imgtr/sdk';

const client = new ImgtrClient({ apiKey: process.env.IMGTR_API_KEY });
const image = await client.upload('./photo.jpg');
console.log(image.direct_url);
```

Read the [SDK documentation](sdk/js/README.md) or [API specification](api/openapi.json).

## Privacy & security

- Relay-published content is **not encrypted by default**; signatures prove event provenance, not confidentiality.
- Public relay operators or other users may mirror content, regardless of deletion requests.
- This source release includes **no live database, user media, production configuration or deploy credentials**.
- Forks and pull requests **do not have access** to the production hosting environment.

For responsible disclosure, see [SECURITY.md](SECURITY.md).

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines. Translations, accessibility, relay compatibility, tests and a reproducible fresh-install database schema are welcome.

## License

IMGTR application code is licensed under [MIT](LICENSE); `sdk/js/` retains its own [MIT license](sdk/js/LICENSE). Trademark and end-user uploaded content rights are separate from the software license.

---

<div align="center">Built for an open, interoperable web. · <a href="https://imgtr.xyz">imgtr.xyz</a></div>
