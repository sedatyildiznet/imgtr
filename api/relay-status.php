<?php
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../includes/websocket.php';
require_once __DIR__.'/../includes/deletion.php';
secure_session();
if($_SERVER['REQUEST_METHOD']!=='POST')json_response(['success'=>false],405);
verify_csrf();
rate_limit('relay-verify-ip',90);
$raw=file_get_contents('php://input',false,null,0,1048577);
if($raw===false||strlen($raw)>1048576)json_response(['success'=>false,'message'=>'Relay verification data is too large.'],413);
$value=json_decode($raw,true);
if(!is_array($value)||!is_array($value['relays']??null)||count($value['relays'])>RELAY_TARGET)json_response(['success'=>false,'message'=>'Invalid relay verification data.'],422);
$stmt=db()->prepare('SELECT id,short_id,chunk_count,status,sha256,size,manage_token_hash FROM images WHERE short_id=?');
$stmt->execute([$value['id']??'']);
$image=$stmt->fetch();
if(!$image)json_response(['success'=>false,'message'=>'Image not found.'],404);
$token=$value['verification_token']??'';
if(!is_string($token)||!preg_match('/^[a-f0-9]{64}$/',$token)||!hash_equals($image['manage_token_hash'],hash('sha256',$token)))json_response(['success'=>false,'message'=>'Upload verification token is invalid.'],403);
rate_limit('relay-verify-image-'.(int)$image['id'],24,false);
if(!in_array($image['status'],['pending','ready'],true))json_response(['success'=>false,'message'=>'Image is not available for verification.'],409);
$storedStmt=db()->prepare('SELECT relay_id FROM image_relays WHERE image_id=? AND status="stored"');
$storedStmt->execute([$image['id']]);
$storedRelayIds=array_fill_keys(array_map('intval',$storedStmt->fetchAll(PDO::FETCH_COLUMN)),true);
$stored=count($storedRelayIds);
$chunkStmt=db()->prepare('SELECT chunk_hash FROM image_chunks WHERE image_id=? ORDER BY chunk_index');
$chunkStmt->execute([$image['id']]);
$chunkHashes=$chunkStmt->fetchAll(PDO::FETCH_COLUMN);
$save=db()->prepare('INSERT INTO image_relays(image_id,relay_id,manifest_event_id,chunk_event_ids,status,stored_at) VALUES(?,?,?,?,?,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE status=VALUES(status),manifest_event_id=VALUES(manifest_event_id),chunk_event_ids=VALUES(chunk_event_ids),stored_at=UTC_TIMESTAMP()');
$relayLookup=db()->prepare('SELECT url FROM relays WHERE id=? AND enabled=1 AND write_allowed=1');
$seen=[];$seenUrls=[];
$verificationTimeout=min(18,max(6,(int)ceil((int)$image['size']/1048576)*2));
$verifyDeadline=microtime(true)+60;
foreach($value['relays'] as $reported){
  if(!is_array($reported)||microtime(true)>=$verifyDeadline)break;
  if($image['status']==='pending'&&$stored>=RELAY_MINIMUM)break;
  $relayId=(int)($reported['relay_id']??0);
  if($relayId<1||isset($seen[$relayId]))continue;
  $seen[$relayId]=true;
  if(isset($storedRelayIds[$relayId]))continue;
  $manifest=(string)($reported['manifest_event_id']??'');
  $chunks=is_array($reported['chunk_event_ids']??null)?$reported['chunk_event_ids']:[];
  $relayLookup->execute([$relayId]);
  $url=$relayLookup->fetchColumn();
  if(!$url||isset($seenUrls[$url]))continue;
  $seenUrls[$url]=true;
  $validIds=count($chunks)===(int)$image['chunk_count']&&preg_match('/^[a-f0-9]{64}$/',$manifest)&&!array_filter($chunks,fn($id)=>!is_string($id)||!preg_match('/^[a-f0-9]{64}$/',$id));
  $verified=false;
  if($url&&$validIds&&!empty($reported['ok'])){
    $ids=array_merge([$manifest],$chunks);
    $events=[];
    foreach([0,250000,750000] as $delay){
      if(microtime(true)>=$verifyDeadline-1)break;
      if($delay)usleep($delay);
      $budget=max(1,min($verificationTimeout,(int)ceil($verifyDeadline-microtime(true))));
      $events+=relay_fetch_events($url,array_values(array_diff($ids,array_keys($events))),$budget);
      if(count($events)===count($ids))break;
    }
    $verified=count($events)===count($ids)&&relay_image_events_valid($events,$manifest,$chunks,$image,$chunkHashes);
  }
  $save->execute([(int)$image['id'],$relayId,$manifest,json_encode($chunks),$verified?'stored':'failed']);
  $deletions=is_array($reported['deletion_events']??null)?$reported['deletion_events']:[];
  if(!$deletions&&is_array($reported['deletion_event']??null))$deletions=[$reported['deletion_event']];
  if($verified&&$deletions&&isset($events[$manifest])&&nostr_deletion_events_match($deletions,$events[$manifest],$chunks))store_deletion_events((int)$image['id'],$deletions);
  if($verified){$stored++;$storedRelayIds[$relayId]=true;}
}
$ready=$image['status']==='ready'||$stored>=RELAY_MINIMUM;
if($ready&&$image['status']!=='ready')db()->prepare('UPDATE images SET status="ready" WHERE id=?')->execute([$image['id']]);
if(!$ready)json_response(['success'=>false,'code'=>'relay_verification_failed','message'=>'Distributed copies could not be verified. Please try again.','replicas'=>$stored,'minimum'=>RELAY_MINIMUM],422);
json_response(['success'=>true,'replicas'=>$stored,'minimum'=>RELAY_MINIMUM]);
