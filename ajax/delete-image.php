<?php
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../includes/deletion.php';
secure_session(); verify_csrf(); $user=require_login();
$stmt=db()->prepare('SELECT * FROM images WHERE short_id=? AND user_id=? AND status!="deleted"');
$stmt->execute([$_POST['id']??'',$user['id']]); $image=$stmt->fetch();
if(!$image) json_response(['success'=>false,'message'=>'Image not found.'],404);
$deletion=delete_image_everywhere($image);
json_response(['success'=>true,'deletion'=>$deletion]);
