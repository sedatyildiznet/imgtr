<?php
require_once __DIR__.'/../includes/auth.php';
secure_session(); verify_csrf(); $user=require_login();
$stmt=db()->prepare('DELETE FROM api_keys WHERE id=? AND user_id=?');
$stmt->execute([(int)($_POST['id']??0),$user['id']]);
json_response(['success'=>$stmt->rowCount()===1,'message'=>$stmt->rowCount()===1?'':'API key not found.']);
