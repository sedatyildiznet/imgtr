<?php
require_once __DIR__.'/../../includes/api.php';
require_once __DIR__.'/../../includes/deletion.php';
$method=strtoupper($_SERVER['REQUEST_METHOD']??'GET');$user=api_require_key();$id=(string)($_GET['id']??'');
if($method==='GET')api_success(['image'=>api_image_resource(api_find_owned_image((int)$user['id'],$id))]);
if($method==='DELETE'){$image=api_find_owned_image((int)$user['id'],$id);$deletion=delete_image_everywhere($image);api_success(['deleted'=>true,'id'=>$id,'relay_deletion'=>$deletion]);}
api_method('GET','DELETE');
