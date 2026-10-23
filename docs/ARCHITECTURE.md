# IMGTR architecture

A client processes an image locally and signs Nostr manifest/chunk events with an ephemeral key. Public relays receive these events. The PHP API reads them back, verifies event and content identifiers, and stores metadata in MySQL/MariaDB. On retrieval, image data is verified and reconstructed in memory; the original binary is not permanently stored on the origin server.

**Limits:** Relays may refuse or prune events. Signed is not encrypted. Deletion requests are not guaranteed to remove all externally retained copies.

See `includes/nostr.php`, `includes/websocket.php`, `api/v1/`, `assets/js/upload.js` and `sdk/js/`.
