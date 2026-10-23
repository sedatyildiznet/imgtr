<?php
require_once __DIR__.'/../includes/auth.php';
secure_session();
if($_SERVER['REQUEST_METHOD']!=='POST')json_response(['success'=>false],405);
verify_csrf();
$user=require_login();
rate_limit('delete-album',20);
$albumId=(int)($_POST['album_id']??0);
if($albumId<1)json_response(['success'=>false,'message'=>'Album not found.'],404);
$pdo=db();
$check=$pdo->prepare('SELECT id FROM albums WHERE id=? AND user_id=?');
$check->execute([$albumId,$user['id']]);
if(!$check->fetchColumn())json_response(['success'=>false,'message'=>'Album not found.'],404);
$pdo->beginTransaction();
try{
    $pdo->prepare('DELETE FROM album_images WHERE album_id=?')->execute([$albumId]);
    $delete=$pdo->prepare('DELETE FROM albums WHERE id=? AND user_id=?');
    $delete->execute([$albumId,$user['id']]);
    if($delete->rowCount()!==1)throw new RuntimeException('Album delete failed.');
    $pdo->commit();
    json_response(['success'=>true]);
}catch(Throwable $exception){
    if($pdo->inTransaction())$pdo->rollBack();
    app_log('Album delete error: '.get_class($exception));
    json_response(['success'=>false,'message'=>'Album could not be deleted.'],422);
}
