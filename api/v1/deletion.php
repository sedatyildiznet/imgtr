<?php
require_once __DIR__.'/../../includes/api.php';require_once __DIR__.'/../../includes/deletion.php';
$method=strtoupper($_SERVER['REQUEST_METHOD']??'GET');api_method('GET','POST');$user=api_require_key();$id=(string)($_GET['id']??'');$image=api_find_owned_image((int)$user['id'],$id,true);
if($image['status']!=='deleted')api_error('image_not_deleted','Delete the image before requesting deletion status.',409);
if($method==='POST')$result=dispatch_image_deletion($image,12);else $result=image_deletion_status((int)$image['id']);
api_success(['id'=>$id,'relay_deletion'=>$result]);
