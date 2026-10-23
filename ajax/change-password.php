<?php
require_once __DIR__.'/../includes/auth.php';
secure_session(); verify_csrf(); $user=require_login(); rate_limit('password-change',5);
$current=$_POST['current_password']??''; $new=$_POST['new_password']??'';
if(!password_verify($current,$user['password_hash'])) json_response(['success'=>false,'message'=>'Current password is incorrect.'],422);
if(strlen($new)<10) json_response(['success'=>false,'message'=>'New password must be at least 10 characters.'],422);
db()->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($new,PASSWORD_DEFAULT),$user['id']]);
session_regenerate_id(true); json_response(['success'=>true]);
