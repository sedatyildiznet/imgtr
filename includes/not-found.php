<?php
require_once __DIR__.'/layout.php';
function render_image_not_found(): never {
    http_response_code(404);header('Cache-Control: private, no-store, max-age=0');header('X-Robots-Tag: noindex, nofollow');
    page_start(t('image_missing_title'),t('image_missing_text'),['index'=>false]);
    ?><main class="not-found-shell"><section class="not-found-panel"><div class="not-found-code" aria-hidden="true">404</div><div><p class="eyebrow"><?=e(t('image_missing_label'))?></p><h1><?=e(t('image_missing_title'))?></h1><p><?=e(t('image_missing_text'))?></p><a class="button" href="/"><?=e(t('image_missing_action'))?></a></div></section></main><?php
    page_end();exit;
}
