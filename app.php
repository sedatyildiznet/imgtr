<?php
require_once __DIR__.'/includes/layout.php';
secure_session();
$appUser=current_user();
$sharexReady=false;
if($appUser){
    $query=db()->prepare('SELECT 1 FROM api_keys WHERE user_id=? AND name=? LIMIT 1');
    $query->execute([$appUser['id'],'IMGTR ShareX']);
    $sharexReady=(bool)$query->fetchColumn();
}
page_start(t('sharex_page_title'),t('sharex_meta'),['schema'=>['@context'=>'https://schema.org','@type'=>'SoftwareApplication','name'=>'IMGTR for ShareX','description'=>t('seo_description_app'),'url'=>seo_language_url(current_language(),'/app'),'applicationCategory'=>'UtilitiesApplication','operatingSystem'=>'Windows','softwareRequirements'=>'ShareX and Node.js 20 or newer','isPartOf'=>['@type'=>'WebSite','@id'=>base_url('#website')]]]);
?>
<main class="app-page">
  <section class="app-hero">
    <div class="app-hero-copy"><p class="eyebrow"><?=e(t('sharex_eyebrow'))?></p><h1><?=e(t('sharex_page_title'))?></h1><h2><?=e(t('sharex_hero_title'))?></h2><p><?=e(t('sharex_hero_intro'))?></p>
      <div class="app-actions">
        <?php if($appUser):?>
          <form method="post" action="/account/integrations/sharex/download"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><button class="button" type="submit"><?=e(t($sharexReady?'sharex_regenerate_config':'sharex_download_config'))?></button></form>
          <a class="button button-secondary" href="/dashboard?tab=integration"><?=e(t('sharex_open_settings'))?></a>
        <?php else:?><a class="button" href="/register"><?=e(t('sharex_create_account'))?></a><?php endif?>
        <a class="text-button" href="https://getsharex.com/downloads" target="_blank" rel="noopener noreferrer"><?=e(t('sharex_download'))?> ↗</a>
      </div>
    </div>
    <aside class="app-instrument"><header><span>SHAREX → IMGTR</span><i class="status-dot"></i></header><div class="app-keystroke"><small><?=e(t('sharex_hotkey'))?></small><strong>PRINT<br>SCREEN</strong></div><div class="app-route"><span>LOCALHOST</span><b>127.0.0.1:43191</b><span>NOSTR / WSS</span></div><footer><span><?=e(t('sharex_origin_files'))?></span><strong>0 B</strong></footer></aside>
  </section>

  <section class="app-workflow"><header><p class="eyebrow"><?=e(t('sharex_flow_label'))?></p><h2><?=e(t('sharex_flow_title'))?></h2></header><ol><li><span>PRTSC</span><b><?=e(t('sharex_capture'))?></b></li><li><span>CROP</span><b><?=e(t('sharex_select'))?></b></li><li><span>EDIT</span><b><?=e(t('sharex_annotate'))?></b></li><li><span>WSS</span><b><?=e(t('sharex_upload'))?></b></li><li><span>URL</span><b><?=e(t('sharex_link_copied'))?></b></li></ol></section>

  <section class="app-architecture"><div><p class="eyebrow"><?=e(t('sharex_arch_label'))?></p><h2><?=e(t('sharex_arch_title'))?></h2></div><div><p><?=e(t('sharex_arch_text'))?></p><pre><code>SHAREX
  └─ HTTP / LOOPBACK
      └─ IMGTR CLI
          ├─ <?=e(strtoupper(t('sharex_metadata_clean')))?>
          ├─ <?=e(strtoupper(t('sharex_local_sign')))?>
          ├─ <?=e(strtoupper(t('sharex_relay_publish')))?>
          └─ <?=e(strtoupper(t('sharex_api_verify')))?> → IMGTR URL</code></pre></div></section>

  <section class="app-capabilities"><header><p class="eyebrow"><?=e(t('sharex_features_label'))?></p><h2><?=e(t('sharex_features_title'))?></h2></header><div><p><?=e(t('sharex_feature_capture'))?></p><p><?=e(t('sharex_feature_edit'))?></p><p><?=e(t('sharex_feature_keys'))?></p><p><?=e(t('sharex_feature_upload'))?></p></div></section>

  <section class="app-setup" id="setup"><header><p class="eyebrow"><?=e(t('sharex_setup_label'))?></p><h2><?=e(t('sharex_setup_title'))?></h2></header><div class="app-setup-steps">
    <article><b><?=e(t('sharex_step_install'))?></b><a href="https://getsharex.com/downloads" target="_blank" rel="noopener noreferrer"><?=e(t('sharex_download'))?> ↗</a></article>
    <article><b><?=e(t('sharex_step_cli'))?></b><a class="button small" href="https://raw.githubusercontent.com/sedatyildiznet/imgtr/main/downloads/IMGTR-ShareX-Setup.bat" target="_blank" rel="noopener noreferrer"><?=e(t('sharex_download_setup'))?> ↓</a></article>
    <article><b><?=e(t('sharex_step_bridge'))?></b><code>IMGTR-ShareX-Setup.bat</code></article>
    <article><b><?=e(t('sharex_step_config'))?></b><?php if($appUser):?><form method="post" action="/account/integrations/sharex/download"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><button class="text-button" type="submit"><?=e(t($sharexReady?'sharex_regenerate_config':'sharex_download_config'))?> ↓</button></form><?php else:?><a href="/login"><?=e(t('login'))?> →</a><?php endif?></article>
    <article><b><?=e(t('sharex_step_import'))?></b><code>IMGTR.sxcu</code></article>
    <article><b><?=e(t('sharex_step_tasks'))?></b></article>
  </div><aside><h3><?=e(t('sharex_recommended'))?></h3><p><?=e(t('sharex_after_capture'))?></p><p><?=e(t('sharex_after_upload'))?></p><p><?=e(t('sharex_editor_optional'))?></p></aside></section>

  <section class="app-security"><div><p class="eyebrow"><?=e(t('sharex_security_label'))?></p><h2><?=e(t('sharex_security_title'))?></h2></div><div><p><?=e(t('sharex_security_text'))?></p><small><?=e(t('sharex_config_sensitive'))?></small></div></section>
</main>
<?php page_end();
