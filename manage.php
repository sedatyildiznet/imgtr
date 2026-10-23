<?php
require_once __DIR__.'/includes/layout.php';
require_once __DIR__.'/includes/deletion.php';
secure_session();
$token=$_GET['token']??'';
$hash=hash('sha256',$token);
$stmt=db()->prepare('SELECT * FROM images WHERE manage_token_hash=? LIMIT 1');
$stmt->execute([$hash]);
$image=$stmt->fetch();
if(!$image){http_response_code(404);exit(e(t('invalid_manage')));}
$message='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  verify_csrf();
  delete_image_everywhere($image);
  $message=t('removed_notice');
  $image['status']='deleted';
}
page_start(t('image_management'),t('manage_help'),['index'=>false]);
?><main class="content-page"><p class="eyebrow"><?=e(t('image_management'))?></p><h1><?=e($image['original_filename'])?></h1><div class="setting-card"><p><?=e($message?:t('manage_help'))?></p><?php if(!$message&&$image['status']!=='deleted'):?><form method="post"><input type="hidden" name="csrf" value="<?=csrf_token()?>"><button class="button danger-button" type="submit"><?=e(t('remove_link'))?></button></form><?php endif?></div></main><?php page_end();
