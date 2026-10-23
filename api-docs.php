<?php require_once __DIR__.'/includes/layout.php';page_start(t('dev_page_title'),t('dev_page_description'),['schema'=>['@context'=>'https://schema.org','@type'=>'TechArticle','headline'=>t('seo_title_api'),'description'=>t('seo_description_api'),'url'=>seo_language_url(current_language(),'/api'),'inLanguage'=>current_language(),'author'=>['@type'=>'Organization','name'=>'IMGTR','url'=>base_url()],'about'=>['IMGTR API','Nostr','JavaScript SDK','CLI']]]);?>
<main class="subpage developer-page">
  <section class="subpage-hero compact"><p class="eyebrow"><?=e(t('dev_eyebrow'))?></p><h1><?=e(t('dev_hero_title'))?></h1><p><?=e(t('dev_hero_intro'))?></p></section>
  <nav class="developer-nav" aria-label="<?=e(t('dev_nav_label'))?>"><a href="#quickstart"><?=e(t('dev_nav_start'))?></a><a href="#sdk">JavaScript SDK</a><a href="#cli">CLI</a><a href="#api">API v1</a><a href="/api/openapi.json">OpenAPI ↗</a></nav>
  <section id="quickstart" class="developer-section"><div><p class="eyebrow">QUICKSTART</p><h2><?=e(t('dev_quick_title'))?></h2></div><div><p><?=e(t('dev_quick_text'))?></p><pre><code>Authorization: Bearer imgtr_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx</code></pre><p class="api-note"><?=e(t('dev_quick_note'))?></p></div></section>
  <section id="sdk" class="developer-section"><div><p class="eyebrow">JAVASCRIPT SDK</p><h2><?=e(t('dev_sdk_title'))?></h2></div><div><pre><code>npm install https://raw.githubusercontent.com/sedatyildiznet/imgtr/main/sdk/js/imgtr-sdk-1.2.0.tgz

import { ImgtrClient } from '@imgtr/sdk';

const imgtr = new ImgtrClient({
  apiKey: process.env.IMGTR_API_KEY
});

const image = await imgtr.upload('./photo.jpg', {
  waitForDistribution: true,
  onProgress: event =&gt; console.log(event.phase)
});

console.log(image.direct_url);</code></pre><ul class="developer-facts"><li><?=e(t('dev_sdk_fact_1'))?></li><li><?=e(t('dev_sdk_fact_2'))?></li><li><?=e(t('dev_sdk_fact_3'))?></li><li><?=e(t('dev_sdk_fact_4'))?></li></ul></div></section>
  <section id="cli" class="developer-section"><div><p class="eyebrow">COMMAND LINE</p><h2><?=e(t('dev_cli_title'))?></h2></div><div><pre><code>npm install -g https://raw.githubusercontent.com/sedatyildiznet/imgtr/main/sdk/js/imgtr-sdk-1.2.0.tgz
imgtr config set-key imgtr_your_key
imgtr upload photo.jpg
imgtr upload one.png two.webp --json
imgtr images --limit 50
imgtr image Ab12Cd
imgtr delete Ab12Cd --yes
imgtr deletion Ab12Cd
imgtr deletion Ab12Cd --retry
imgtr relays
imgtr sharex install
imgtr sharex status
imgtr sharex stop
imgtr sharex uninstall</code></pre><p><?=e(t('dev_cli_text'))?></p></div></section>
  <section id="api" class="developer-section api-reference"><div><p class="eyebrow">REST API / V1</p><h2><?=e(t('dev_api_title'))?></h2></div><div><p><?=e(t('dev_base_url'))?>: <code><?=e(base_url('api/v1'))?></code></p><div class="endpoint-list">
    <article><span>GET</span><code>/user</code><p><?=e(t('dev_ep_user'))?></p></article>
    <article><span>GET</span><code>/images?limit=25&amp;cursor=…</code><p><?=e(t('dev_ep_images'))?></p></article>
    <article><span>GET</span><code>/image?id=Ab12Cd</code><p><?=e(t('dev_ep_image'))?></p></article>
    <article><span>DELETE</span><code>/image?id=Ab12Cd</code><p><?=e(t('dev_ep_delete'))?></p></article>
    <article><span>GET</span><code>/deletion?id=Ab12Cd</code><p><?=e(t('dev_ep_deletion_get'))?></p></article>
    <article><span>POST</span><code>/deletion?id=Ab12Cd</code><p><?=e(t('dev_ep_deletion_retry'))?></p></article>
    <article><span>GET</span><code>/relays</code><p><?=e(t('dev_ep_relays'))?></p></article>
    <article><span>POST</span><code>/uploads</code><p><?=e(t('dev_ep_uploads'))?></p></article>
    <article><span>POST</span><code>/uploads-verify</code><p><?=e(t('dev_ep_verify'))?></p></article>
  </div><p class="api-note"><?=e(t('dev_deletion_status_note'))?></p><h3><?=e(t('dev_response_format'))?></h3><pre><code>{
  "success": true,
  "data": { "images": [] },
  "meta": { "next_cursor": null, "has_more": false },
  "request_id": "e7c8…"
}</code></pre><pre><code>{
  "success": false,
  "error": {
    "code": "invalid_api_key",
    "message": "The supplied API key is invalid or has been revoked."
  },
  "request_id": "e7c8…"
}</code></pre><p class="api-note"><?=e(t('dev_http_note'))?></p></div></section>
</main>
<?php page_end();
