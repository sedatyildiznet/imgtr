<?php
require_once __DIR__.'/../includes/auth.php';
secure_session();
if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST')json_response(['success'=>false],405);
verify_csrf();
$user=require_login();
rate_limit('update-album-'.$user['id'],40);
$albumId=(int)($_POST['album_id']??0);
$title=trim((string)($_POST['title']??''));
$visibility=(string)($_POST['visibility']??'public');
$titleLength=function_exists('mb_strlen')?mb_strlen($title):strlen($title);
if($albumId<1)json_response(['success'=>false,'message'=>'Album not found.'],404);
if($titleLength<1||$titleLength>150)json_response(['success'=>false,'message'=>'Album title must be between 1 and 150 characters.'],422);
if(!in_array($visibility,['public','unlisted'],true))json_response(['success'=>false,'message'=>'Invalid album visibility.'],422);
$query=db()->prepare('UPDATE albums SET title=?,visibility=? WHERE id=? AND user_id=?');
$query->execute([$title,$visibility,$albumId,$user['id']]);
if($query->rowCount()===0){$check=db()->prepare('SELECT id FROM albums WHERE id=? AND user_id=?');$check->execute([$albumId,$user['id']]);if(!$check->fetchColumn())json_response(['success'=>false,'message'=>'Album not found.'],404);}
json_response(['success'=>true,'title'=>$title,'visibility'=>$visibility]);
