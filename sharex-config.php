<?php
require_once __DIR__.'/includes/auth.php';
require_once __DIR__.'/includes/language.php';

secure_session();
header('Cache-Control: no-store, private, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('Vary: Cookie');

if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST'){
    http_response_code(405);header('Allow: POST');exit(t('sharex_method_not_allowed'));
}
$user=current_user();
if(!$user){http_response_code(401);exit(t('sharex_auth_required'));}
verify_csrf();
rate_limit('sharex-config-user-'.$user['id'],5);

$pdo=db();
try{
    $pdo->beginTransaction();
    // Serialize configuration rotation per account so two concurrent downloads
    // cannot create multiple active ShareX credentials.
    $lock=$pdo->prepare('SELECT id FROM users WHERE id=? FOR UPDATE');
    $lock->execute([$user['id']]);
    if(!$lock->fetchColumn())throw new RuntimeException(t('sharex_config_failed'));
    $existing=$pdo->prepare('SELECT id FROM api_keys WHERE user_id=? AND name=? FOR UPDATE');
    $existing->execute([$user['id'],'IMGTR ShareX']);
    $existingIds=array_map('intval',$existing->fetchAll(PDO::FETCH_COLUMN));
    if($existingIds){
        $marks=implode(',',array_fill(0,count($existingIds),'?'));
        $pdo->prepare("DELETE FROM api_keys WHERE user_id=? AND id IN ($marks)")->execute(array_merge([$user['id']],$existingIds));
    }
    $count=$pdo->prepare('SELECT COUNT(*) FROM api_keys WHERE user_id=?');$count->execute([$user['id']]);
    if((int)$count->fetchColumn()>=10)throw new RuntimeException(t('sharex_key_limit'));
    $key='imgtr_'.bin2hex(random_bytes(24));
    $pdo->prepare('INSERT INTO api_keys(user_id,name,key_hash) VALUES(?,?,?)')->execute([$user['id'],'IMGTR ShareX',hash('sha256',$key)]);
    $pdo->commit();
}catch(Throwable $error){
    if($pdo->inTransaction())$pdo->rollBack();
    http_response_code(422);header('Content-Type: text/plain; charset=utf-8');exit($error instanceof RuntimeException?$error->getMessage():t('sharex_config_failed'));
}

$config=[
    'Version'=>'17.0.0',
    'Name'=>'IMGTR',
    'DestinationType'=>'ImageUploader',
    'RequestMethod'=>'POST',
    'RequestURL'=>'http://127.0.0.1:43191/upload',
    'Body'=>'MultipartFormData',
    'Headers'=>['Authorization'=>'Bearer '.$key],
    'FileFormName'=>'file',
    'URL'=>'{json:data.url}',
    'ErrorMessage'=>'{json:error.message}'
];

try{$json=json_encode($config,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n";}catch(JsonException){http_response_code(500);exit(t('sharex_config_failed'));}
header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="IMGTR.sxcu"');
header('Content-Length: '.strlen($json));
echo $json;
