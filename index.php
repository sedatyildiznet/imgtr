<?php
require_once __DIR__.'/includes/layout.php';
secure_session();
$user=current_user();
header('Cache-Control: private, no-store, max-age=0');
header('Vary: Cookie, Accept-Language');
$clientI18n=[
    'copy'=>t('copy'),'copied'=>t('copied'),'viewer_link'=>t('viewer_link'),'direct_link'=>t('direct_link'),
    'preparing'=>t('preparing'),'creating_upload'=>t('creating_upload'),'publishing'=>t('publishing'),
    'publishing_replicas'=>t('publishing_replicas'),'preparing_link'=>t('preparing_link'),'complete'=>t('complete'),
    'upload_failed'=>t('upload_failed'),'file_too_large'=>t('file_too_large'),'relay_retry'=>t('relay_retry'),
    'relay_verification_failed'=>t('relay_verification_failed'),'server_error'=>t('server_error'),
    'upload_complete'=>t('upload_complete'),'multi_album_ready'=>t('multi_album_ready'),
    'multi_album_text'=>t('multi_album_text'),'album_link'=>t('album_link'),'open_album'=>t('open_album'),
    'album_create_failed'=>t('album_create_failed'),'exif_removed_title'=>t('exif_removed_title'),
    'exif_removed_text'=>t('exif_removed_text'),'verifying_storage'=>t('verifying_storage'),
    'url_cors_error'=>t('url_cors_error'),'background_replicating'=>t('background_replicating'),
    'distribution_complete'=>t('distribution_complete')
];
?><!doctype html>
<html lang="<?=e(current_language())?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="<?=csrf_token()?>">
  <?php seo_head(t('title'),t('seo_description_home'),['schema'=>[
    '@context'=>'https://schema.org','@graph'=>[
      ['@type'=>'WebSite','@id'=>base_url('#website'),'url'=>base_url(),'name'=>'IMGTR','alternateName'=>'IMGTR Image Infrastructure','description'=>t('seo_description_home'),'inLanguage'=>array_keys(supported_languages())],
      ['@type'=>'WebApplication','@id'=>base_url('#webapp'),'name'=>'IMGTR','url'=>base_url(),'description'=>t('seo_description_home'),'applicationCategory'=>'MultimediaApplication','operatingSystem'=>'Any','browserRequirements'=>'Requires a modern browser with JavaScript and WebSocket support','isPartOf'=>['@id'=>base_url('#website')]]
    ]
  ]]);?>
  <link rel="manifest" href="/manifest.json">
  <link rel="stylesheet" href="/assets/css/premium.css?v=20260831f">
</head>
<body class="home-body">
<header class="site-header">
  <div class="header-inner">
    <a class="logo" href="/" aria-label="IMGTR home">IMGTR<span>.</span></a>
    <nav class="main-nav" aria-label="Primary">
      <a href="#upload"><?=e(t('nav_upload'))?></a>
      <a href="#features"><?=e(t('features'))?></a>
      <a href="/faq"><?=e(t('nav_faq'))?></a>
      <a href="/app"><?=e(t('nav_app'))?></a>
      <a href="/api"><?=e(t('nav_api'))?></a>
    </nav>
    <div class="header-actions"><details class="mobile-nav"><summary aria-label="<?=e(t('nav_menu'))?>"><i></i><i></i></summary><div><a href="#upload"><?=e(t('nav_upload'))?></a><a href="#features"><?=e(t('features'))?></a><a href="/faq"><?=e(t('nav_faq'))?></a><a href="/app"><?=e(t('nav_app'))?></a><a href="/api"><?=e(t('nav_api'))?></a></div></details>
      <?php language_picker();?>
      <button class="theme-button" id="theme" aria-label="<?=e(t('theme'))?>"><span aria-hidden="true">◐</span></button>
      <?php if($user):?>
        <details class="account-menu"><summary><span><?=e(strtoupper(substr($user['email'],0,1)))?></span><b><?=e($user['email'])?></b><i aria-hidden="true"></i></summary><div><p><?=e($user['email'])?></p><a href="/dashboard"><?=e(t('dashboard'))?></a><?php if(is_admin()):?><a href="/admin/"><?=e(t('admin'))?></a><?php endif?><a href="/logout"><?=e(t('logout'))?></a></div></details>
      <?php else:?>
        <a class="login-link" href="/login"><?=e(t('login'))?></a>
        <a class="header-cta" href="/register"><?=e(t('register'))?></a>
      <?php endif?>
    </div>
  </div>
</header>

<main class="home-main">
  <section class="opening" id="upload" aria-labelledby="home-title">
    <div class="opening-copy">
      <p class="eyebrow">NOSTR-POWERED IMAGE SHARING</p>
      <h1 id="home-title"><?=e(t('tagline'))?></h1>
      <p class="opening-intro"><?=e(t('home_sub'))?></p>
      <div class="opening-signal"><span>NOSTR</span><i></i><small>PREPARE · PUBLISH · VERIFY · DELIVER</small></div>
    </div>

    <div class="upload-workbench">
      <div class="workbench-head">
        <span>IMGTR / UPLOAD</span>
        <span class="workbench-status"><i></i><?=e(t('operational'))?></span>
      </div>
      <div class="uploader" id="dropzone">
        <input id="fileInput" type="file" accept="image/jpeg,image/png,image/webp,image/gif" multiple hidden>
        <svg class="upload-mark" viewBox="0 0 48 48" aria-hidden="true"><path d="M24 33V11m0 0-8 8m8-8 8 8M9 31v8h30v-8" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="square"/></svg>
        <div class="upload-copy"><h2><?=e(t('drop'))?></h2><p><?=e(t('formats'))?></p></div>
        <button class="button upload-primary" id="choose" type="button"><?=e(t('choose'))?></button>
        <div class="upload-secondary"><button id="paste" type="button"><?=e(t('paste'))?></button><button id="urlButton" type="button"><?=e(t('url'))?></button></div>
        <div class="privacy-default"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6.5 12 3.3 3.3L17.8 7" fill="none" stroke="currentColor" stroke-width="1.8"/></svg><span><b><?=e(t('exif_removed_title'))?></b><small>LOCAL / BEFORE PUBLISH</small></span></div>
      </div>
      <div id="queue" class="queue" aria-live="polite"></div>
      <div id="results" class="results" aria-live="polite"></div>
    </div>
  </section>

  <section class="nostr-system" id="features" aria-labelledby="system-title" data-nostr-demo>
    <header class="nostr-heading"><div><p class="index-label">NOSTR NETWORK</p><p class="eyebrow"><?=e(t('how_label'))?></p></div><h2 id="system-title"><?=e(t('how_title'))?></h2></header>
    <div class="relay-mesh" aria-label="IMGTR Nostr relay distribution diagram" aria-live="polite">
      <svg class="mesh-lines" viewBox="0 0 1000 420" preserveAspectRatio="none" aria-hidden="true">
        <path class="network-link" d="M500 210 120 70"/><path class="network-link" d="M500 210 880 70"/><path class="network-link" d="M500 210 120 350"/><path class="network-link" d="M500 210 880 350"/>
        <path class="data-signal signal-a" pathLength="100" d="M500 210 120 70"/><path class="data-signal signal-b" pathLength="100" d="M500 210 880 70"/><path class="data-signal signal-c" pathLength="100" d="M500 210 120 350"/><path class="data-signal signal-d" pathLength="100" d="M500 210 880 350"/>
        <path class="verify-signal signal-a" pathLength="100" d="M120 70 500 210"/><path class="verify-signal signal-b" pathLength="100" d="M880 70 500 210"/><path class="verify-signal signal-c" pathLength="100" d="M120 350 500 210"/><path class="verify-signal signal-d" pathLength="100" d="M880 350 500 210"/>
      </svg>
      <div class="file-input-token" aria-hidden="true"><svg viewBox="0 0 20 24"><path d="M2 1h10l6 6v16H2zM12 1v6h6"/></svg><span>IMAGE</span></div>
      <div class="mesh-core">
        <small class="core-kicker">IMGTR CLIENT</small><strong>IMGTR</strong>
        <span class="core-state state-idle">READY</span><span class="core-state state-prepare">LOCAL PREP / EXIF WHEN SUPPORTED</span><span class="core-state state-distribute">SIGNED NOSTR EVENTS</span><span class="core-state state-verify">READ-BACK / SHA-256</span><span class="core-state state-deliver"><?=RELAY_MINIMUM?>+ COPIES VERIFIED</span>
      </div>
      <?php foreach(['a'=>'A','b'=>'B','c'=>'C','d'=>'D'] as $relayClass=>$relayLabel):?>
      <div class="relay-node relay-<?=$relayClass?>"><i></i><span>RELAY / <?=$relayLabel?></span><small class="node-wait">WAITING</small><small class="node-store">EVENT STORED</small><small class="node-verified">✓ VERIFIED</small></div>
      <?php endforeach?>
      <div class="delivery-output" aria-hidden="true"><svg viewBox="0 0 30 24"><path d="M1 1h28v22H1zM4 18l6-6 4 4 4-5 8 7"/><circle cx="22" cy="7" r="2"/></svg><span>SHARE LINK READY</span></div>
    </div>
    <ol class="relay-steps"><li><button type="button" data-nostr-stage="prepare"><?=e(t('step1_title'))?><small>LOCAL / EXIF</small></button></li><li><button type="button" data-nostr-stage="distribute"><?=e(t('step2_title'))?><small>NOSTR EVENTS</small></button></li><li><button type="button" data-nostr-stage="verify"><?=e(t('step3_title'))?><small>READ-BACK / HASH</small></button></li><li><button type="button" data-nostr-stage="deliver"><?=e(t('step4_title'))?><small>MEMORY / LINK</small></button></li></ol>
    <p class="system-note"><span>SHA-256 / NOSTR EVENTS / INDEPENDENT RELAYS</span><?=e(t('infra_note'))?></p>
  </section>

  <section class="capabilities" aria-labelledby="capabilities-title">
    <header class="capabilities-head"><p class="index-label">DETAILS</p><div><p class="eyebrow"><?=e(t('feature_label'))?></p><?php $featureTitle=explode('|',t('feature_title'));?><h2 id="capabilities-title"><?=e($featureTitle[0])?></h2></div></header>
    <div class="feature-composition">
      <article class="feature-lead"><span>PRIVACY</span><h3><?=e(t('f3_title'))?></h3><div class="privacy-readout"><i></i><b>EXIF</b><small>CAMERA · DEVICE · LOCATION</small><strong>REMOVED LOCALLY</strong></div></article>
      <article class="feature-links"><span>OUTPUT</span><h3><?=e(t('f2_title'))?></h3><ul><li>DIRECT URL</li><li>MARKDOWN</li><li>BBCODE</li><li>HTML</li></ul></article>
      <article class="feature-quiet"><span>ORIGIN</span><h3><?=e(t('f4_title'))?></h3><strong>0 <small>persistent image binaries</small></strong></article>
      <article class="feature-library"><span>WORKSPACE</span><div><h3><?=e(t('f1_title'))?></h3></div><div><h3><?=e(t('f5_title'))?></h3></div><div><h3><?=e(t('f6_title'))?></h3></div></article>
    </div>
  </section>

  <section class="final-action"><p class="index-label">SHARE</p><div><h2><?=e(t('cta_title'))?></h2></div><a href="#upload"><?=e(t('cta_button'))?><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14m-5-5 5 5-5 5"/></svg></a></section>
</main>

<?php site_footer($user);?>

<dialog id="urlDialog"><form method="dialog"><button id="urlDialogClose" class="dialog-close" type="button" aria-label="Close">×</button><h2><?=e(t('url_title'))?></h2><p><?=e(t('url_help'))?></p><input id="urlInput" type="url" placeholder="https://example.com/image.jpg" required><button id="urlUpload" class="button" value="default"><?=e(t('upload'))?></button></form></dialog>
<div id="dragOverlay"><span><?=e(t('drop_overlay'))?></span></div><div class="toast" id="toast"></div>
<script>window.IMGTR_I18N=<?=json_encode($clientI18n,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>;</script>
<script src="/assets/js/app.js?v=20260830b"></script>
<script type="module" src="/assets/js/upload.js?v=20260830h"></script>
<script src="/assets/js/nostr-diagram.js?v=20260830a" defer></script>
</body></html>
