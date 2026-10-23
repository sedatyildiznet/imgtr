<?php
require_once __DIR__.'/includes/layout.php';
require_once __DIR__.'/includes/not-found.php';
secure_session();
$id = $_GET['id'] ?? '';
$stmt = db()->prepare('SELECT * FROM images WHERE short_id = ? AND status = "ready"');
$stmt->execute([$id]);
$image = $stmt->fetch();
if (!$image) render_image_not_found();
db()->prepare('UPDATE images SET views = views + 1 WHERE id = ?')->execute([$image['id']]);
$directUrl = base_url($id.'.'.$image['extension']);
$viewerUrl = base_url($id);
$markdownCode = '![image]('.$directUrl.')';
$bbcodeCode = '[img]'.$directUrl.'[/img]';
$htmlCode = '<img src="'.$directUrl.'" alt="image">';
$seoDescription=t('seo_image_description',['name'=>$image['original_filename']]);
$imageSchema=['@context'=>'https://schema.org','@type'=>'ImageObject','name'=>$image['original_filename'],'description'=>$seoDescription,'url'=>seo_language_url(current_language(),'/'.$id),'contentUrl'=>$directUrl,'encodingFormat'=>$image['mime'],'width'=>(int)$image['width'],'height'=>(int)$image['height'],'contentSize'=>(int)$image['size'],'representativeOfPage'=>true,'inLanguage'=>current_language()];
page_start($image['original_filename'],$seoDescription,['canonical_path'=>'/'.$id,'image'=>$directUrl,'image_alt'=>$image['original_filename'],'image_width'=>(int)$image['width'],'image_height'=>(int)$image['height'],'image_type'=>$image['mime'],'og_type'=>'article','schema'=>$imageSchema,'keywords'=>t('seo_keywords_general').', '.pathinfo($image['original_filename'],PATHINFO_FILENAME)]);
?>
<main class="viewer-shell">
  <section class="viewer-card">
    <div class="viewer-stage">
      <img id="viewerImage" src="/<?=e($id)?>.<?=e($image['extension'])?>" alt="<?=e($image['original_filename'])?>">
      <div id="imageLoading" class="image-loading"><span></span><?=e(t('loading_image'))?></div>
    </div>
    <div class="viewer-content">
      <div class="viewer-heading"><div><p class="eyebrow"><?=e(t('shared_image'))?></p><h1><?=e($image['original_filename'])?></h1></div><span class="status-pill"><?=e(t('available'))?></span></div>
      <div class="image-meta"><span><?=e($image['width'])?> × <?=e($image['height'])?></span><span><?=format_bytes((int)$image['size'])?></span><span><?=number_format((int)$image['views'] + 1)?> <?=e(t('views'))?></span></div>
      <label class="field-label" for="shareUrl"><?=e(t('viewer_link'))?></label>
      <div class="copy-field"><input id="shareUrl" readonly value="<?=e($viewerUrl)?>"><button type="button" data-copy="#shareUrl"><?=e(t('copy'))?></button></div>
      <label class="field-label" for="directUrl"><?=e(t('direct_link'))?></label>
      <div class="copy-field"><input id="directUrl" readonly value="<?=e($directUrl)?>"><button type="button" data-copy="#directUrl"><?=e(t('copy'))?></button></div>
      <section class="embed-panel" aria-labelledby="shareCodesTitle">
        <h2 id="shareCodesTitle" class="sr-only"><?=e(t('share_codes_title'))?></h2>
        <label class="field-label" for="markdownCode"><?=e(t('markdown_code'))?></label>
        <div class="copy-field"><input id="markdownCode" readonly value="<?=e($markdownCode)?>"><button type="button" data-copy="#markdownCode"><?=e(t('copy'))?></button></div>
        <label class="field-label" for="bbcodeCode"><?=e(t('bbcode_code'))?></label>
        <div class="copy-field"><input id="bbcodeCode" readonly value="<?=e($bbcodeCode)?>"><button type="button" data-copy="#bbcodeCode"><?=e(t('copy'))?></button></div>
        <label class="field-label" for="htmlCode"><?=e(t('html_code'))?></label>
        <div class="copy-field"><input id="htmlCode" readonly value="<?=e($htmlCode)?>"><button type="button" data-copy="#htmlCode"><?=e(t('copy'))?></button></div>
      </section>
      <div class="viewer-actions"><a class="button" href="/<?=e($id)?>.<?=e($image['extension'])?>" download><?=e(t('download'))?></a><button class="button button-secondary" id="nativeShare" type="button"><?=e(t('share'))?></button><button class="text-button" id="openReport" type="button"><?=e(t('report_image'))?></button></div>
    </div>
  </section>
</main>
<dialog id="reportDialog"><form id="reportForm"><button class="dialog-close" type="button" data-close>×</button><h2><?=e(t('report_image'))?></h2><p class="muted"><?=e(t('report_help'))?></p><input type="hidden" name="id" value="<?=e($id)?>"><label><?=e(t('reason'))?><select name="reason" required><option value="spam">Spam</option><option value="malware"><?=e(t('malware'))?></option><option value="copyright"><?=e(t('copyright'))?></option><option value="illegal"><?=e(t('illegal'))?></option><option value="other"><?=e(t('other'))?></option></select></label><label><?=e(t('details'))?><textarea name="details" maxlength="1000" placeholder="<?=e(t('optional_details'))?>"></textarea></label><button class="button" type="submit"><?=e(t('send_report'))?></button></form></dialog>
<script>window.IMGTR_I18N=<?=json_encode(['copied'=>t('copied'),'copy'=>t('copy'),'done'=>t('done'),'load_error'=>t('load_error'),'try_later'=>t('try_later')],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>;</script>
<?php page_end(['/assets/js/view.js?v=20260828d']);
