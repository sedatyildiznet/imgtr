<?php
require_once __DIR__.'/includes/db.php';
require_once __DIR__.'/includes/functions.php';
require_once __DIR__.'/includes/control.php';
require_once __DIR__.'/includes/websocket.php';
require_once __DIR__.'/includes/not-found.php';

$id=$_GET['id']??'';
$stmt=db()->prepare('SELECT * FROM images WHERE short_id=? AND status="ready"');
$stmt->execute([$id]);
$image=$stmt->fetch();
if(!$image)render_image_not_found();

record_public_hit('/'.$id.'.'.$image['extension']);
$etag='"'.$image['sha256'].'"';
header('ETag: '.$etag);
header('X-Content-Type-Options: nosniff');

if(trim($_SERVER['HTTP_IF_NONE_MATCH']??'')===$etag){
    http_response_code(304);
    header('Cache-Control: public, no-cache, must-revalidate');
    exit;
}

$binary=relay_image_cache_read($image);
if($binary===false){
    $binary=reconstruct_image_from_relays($image);
    if($binary===false){
        http_response_code(503);
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: no-store, max-age=0');
        header('Retry-After: 3');
        exit('Image is temporarily unavailable from the distributed storage network.');
    }
    relay_image_cache_store($image,$binary);
}

header('Content-Type: '.$image['mime']);
header('Content-Length: '.strlen($binary));
header('Cache-Control: public, no-cache, must-revalidate');
echo $binary;
