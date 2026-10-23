# IMGTR JavaScript SDK and CLI

The SDK processes the image locally, removes metadata from JPG/PNG/WEBP by decoding and re-encoding it, signs the IMGTR Nostr manifest/chunks locally, publishes them to the relays returned by IMGTR, and asks the API to verify the stored events. The private Nostr key is ephemeral and never leaves the process. GIF files follow the website's current pass-through behavior.

## Install

```bash
npm install https://imgtr.xyz/sdk/js/imgtr-sdk-1.2.0.tgz
npm install -g https://imgtr.xyz/sdk/js/imgtr-sdk-1.2.0.tgz
imgtr config set-key imgtr_your_key
```

Repository contributors can run `npm install && npm link` in this directory instead.

Node.js 20 or newer is required. If npm reports that the `sharp` install script was blocked, rerun the global installation with the exact `--allow-scripts=sharp` command npm prints.

## SDK

```js
import { ImgtrClient } from '@imgtr/sdk';

const imgtr = new ImgtrClient({ apiKey: process.env.IMGTR_API_KEY });
const image = await imgtr.upload('./photo.jpg', {
  waitForDistribution: true,
  onProgress: event => console.log(event)
});

console.log(image.direct_url);
```

`upload()` resolves after IMGTR has verified the required relay quorum. Its `distribution` promise tracks the remaining relay work when `waitForDistribution` is false.

New uploads also prepare a signed NIP-09 deletion request while the ephemeral key is still in memory. `deleteImage(id)` immediately disables IMGTR delivery and dispatches that request. Use `deletionStatus(id)` or `retryDeletion(id)` to inspect/retry relay acknowledgements; relay deletion is explicitly reported as `complete`, `partial`, `pending` or `legacy_unavailable` and is never silently assumed.

## CLI

```bash
imgtr upload photo.jpg
imgtr upload one.png two.webp --json
imgtr images --limit 50
imgtr image Ab12Cd
imgtr delete Ab12Cd --yes
imgtr deletion Ab12Cd
imgtr deletion Ab12Cd --retry
imgtr relays
```

Use `IMGTR_API_KEY` in CI instead of storing a local key. All commands support `--json` for scripting.

## ShareX local bridge

ShareX custom uploaders cannot sign Nostr events or publish one image to multiple relays. Install the official loopback-only bridge once:

```bash
imgtr sharex install
```

The command starts the bridge invisibly and adds a per-user Windows Startup launcher, so no administrator access or open terminal is required. The personalized `IMGTR.sxcu` from `https://imgtr.xyz/app` posts the screenshot to `http://127.0.0.1:43191/upload`. The bridge uses the same `ImgtrClient.upload()` pipeline as the CLI: supported metadata is removed locally, events are signed locally and the IMGTR API verifies the required relay quorum before the viewer URL is returned. Publication to the remaining relays continues in the local bridge process.

```bash
imgtr sharex status
imgtr sharex stop
imgtr sharex start
imgtr sharex uninstall
```

`stop` ends the current bridge process while keeping automatic startup installed. `uninstall` stops the bridge and removes the Startup launcher. `imgtr sharex serve` remains available for foreground diagnostics.
