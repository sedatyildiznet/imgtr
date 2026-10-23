<?php
require_once __DIR__.'/../includes/auth.php';
secure_session();
if ($_SERVER['REQUEST_METHOD']!=='POST') json_response(['success'=>false],405);
verify_csrf();
rate_limit('upload-album',current_user()?30:10);
$payload=json_decode(file_get_contents('php://input'),true);
$raw=is_array($payload['images']??null)?$payload['images']:[];
$requested=[];
foreach ($raw as $item) {
    if (!is_array($item)) continue;
    $id=preg_replace('/[^A-Za-z0-9]/','',(string)($item['id']??''));
    $token=(string)($item['verification_token']??'');
    if ($id!=='' && preg_match('/^[a-f0-9]{64}$/',$token)) $requested[$id]=$token;
}
if (count($requested)<2 || count($requested)>MAX_FILES) json_response(['success'=>false,'message'=>'A multi-upload album requires 2 to '.MAX_FILES.' verified images.'],422);
$ids=array_keys($requested);
$marks=implode(',',array_fill(0,count($ids),'?'));
$q=db()->prepare("SELECT id,short_id,user_id,manage_token_hash FROM images WHERE status=\"ready\" AND short_id IN ($marks)");
$q->execute($ids);
$user=current_user();
$ordered=[];
foreach ($q->fetchAll() as $image) {
    $token=$requested[$image['short_id']]??'';
    $owned=$user && (int)$image['user_id']===(int)$user['id'];
    if ($owned || ($token!=='' && hash_equals($image['manage_token_hash'],hash('sha256',$token)))) $ordered[$image['short_id']]=(int)$image['id'];
}
if (count($ordered)!==count($ids)) json_response(['success'=>false,'message'=>'One or more images are not authorized for this album.'],403);
try {
    do {$slug=strtolower(random_id(10));$check=db()->prepare('SELECT id FROM upload_albums WHERE slug=?');$check->execute([$slug]);} while($check->fetchColumn());
    $pdo=db();$pdo->beginTransaction();$title='IMGTR Upload · '.gmdate('Y-m-d H:i');
    $pdo->prepare('INSERT INTO upload_albums(user_id,slug,title,created_at) VALUES(?,?,?,UTC_TIMESTAMP())')->execute([$user['id']??null,$slug,$title]);
    $albumId=(int)$pdo->lastInsertId();$insert=$pdo->prepare('INSERT INTO upload_album_images(album_id,image_id,sort_order) VALUES(?,?,?)');
    foreach ($ids as $order=>$shortId) $insert->execute([$albumId,$ordered[$shortId],$order]);
    $pdo->commit();json_response(['success'=>true,'url'=>base_url('c/'.$slug),'slug'=>$slug,'count'=>count($ordered)]);
} catch(Throwable $e) {
    if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();app_log('Multi-upload album error: '.get_class($e));
    json_response(['success'=>false,'message'=>'Multi-upload album could not be created. Import the required database update.'],503);
}
