<?php
/** Nostr event encoding shared with browser publisher. Events are signed client-side. */
function manifest_event(string $imageId,array $meta): array { return ['kind'=>30303,'tags'=>[['d',$imageId],['t','imgtr'],['sha256',$meta['sha256']]],'content'=>json_encode($meta,JSON_UNESCAPED_SLASHES)]; }
