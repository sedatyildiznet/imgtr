<?php
require_once __DIR__.'/../includes/auth.php';
secure_session();
if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST')json_response(['success'=>false],405);
verify_csrf();
$user=require_login();
rate_limit('create-album-'.$user['id'],20);
$title=trim((string)($_POST['title']??''));
$visibility=(string)($_POST['visibility']??'public');
$titleLength=function_exists('mb_strlen')?mb_strlen($title):strlen($title);
if($titleLength<1||$titleLength>150)json_response(['success'=>false,'message'=>'Album title must be between 1 and 150 characters.'],422);
if(!in_array($visibility,['public','unlisted'],true))json_response(['success'=>false,'message'=>'Invalid album visibility.'],422);
$ascii=function_exists('iconv')?(iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$title)?:$title):$title;
$base=strtolower(trim((string)preg_replace('/[^a-z0-9]+/i','-',$ascii),'-'));
if($base==='')$base='album';
$base=substr($base,0,72);$slug=$base;
$exists=db()->prepare('SELECT 1 FROM albums WHERE slug=? LIMIT 1');
for($attempt=0;$attempt<6;$attempt++){$exists->execute([$slug]);if(!$exists->fetchColumn())break;$slug=$base.'-'.substr(bin2hex(random_bytes(4)),0,6);}
try{
    db()->prepare('INSERT INTO albums(user_id,slug,title,visibility) VALUES(?,?,?,?)')->execute([$user['id'],$slug,$title,$visibility]);
    json_response(['success'=>true,'url'=>base_url('a/'.$slug),'slug'=>$slug]);
}catch(Throwable $error){
    app_log('Album creation failed: '.get_class($error));
    json_response(['success'=>false,'message'=>'The album could not be created.'],422);
}
