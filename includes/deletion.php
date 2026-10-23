<?php
require_once __DIR__.'/websocket.php';

function deletion_tables_available(): bool {
    static $available=null;if($available!==null)return $available;
    try{db()->query('SELECT 1 FROM image_deletion_requests LIMIT 1');db()->query('SELECT 1 FROM image_deletion_relays LIMIT 1');return $available=true;}catch(Throwable $error){app_log('Deletion tables unavailable: '.$error->getMessage());return $available=false;}
}

function nostr_deletion_event_valid(array $event,array $manifest): bool {
    if((int)($event['kind']??0)!==5||!nostr_event_id_valid($event)||!is_string($event['sig']??null)||!preg_match('/^[a-f0-9]{128}$/',$event['sig']))return false;
    if(($event['pubkey']??'')!==($manifest['pubkey']??null))return false;
    $created=(int)($event['created_at']??0);$manifestCreated=(int)($manifest['created_at']??0);
    if($created<$manifestCreated||$created>$manifestCreated+3600)return false;
    return count($event['tags']??[])<=64&&strlen((string)($event['content']??''))<=500;
}

function nostr_deletion_events_match(array $deletions,array $manifest,array $chunkIds): bool {
    if(!$deletions||count($deletions)>32)return false;$references=[];
    foreach($deletions as $event){if(!is_array($event)||!nostr_deletion_event_valid($event,$manifest))return false;foreach(($event['tags']??[]) as $tag)if(is_array($tag)&&($tag[0]??null)==='e'&&is_string($tag[1]??null))$references[$tag[1]]=true;}
    foreach(array_merge([$manifest['id']],$chunkIds) as $id)if(!isset($references[$id]))return false;
    return true;
}

function store_deletion_events(int $imageId,array $events): bool {
    if(!deletion_tables_available())return false;
    $encoded=json_encode(array_values($events),JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    if($encoded===false||strlen($encoded)>1000000)return false;
    $query=db()->prepare('INSERT IGNORE INTO image_deletion_requests(image_id,event_id,deletion_event,status) VALUES(?,?,?,"prepared")');
    $query->execute([$imageId,$events[0]['id'],$encoded]);return $query->rowCount()>0||deletion_event_exists($imageId);
}

function deletion_event_exists(int $imageId): bool {
    if(!deletion_tables_available())return false;$query=db()->prepare('SELECT 1 FROM image_deletion_requests WHERE image_id=?');$query->execute([$imageId]);return (bool)$query->fetchColumn();
}

function relay_publish_event(string $url,array $event,int $timeout=4): array {
    $stream=relay_open($url,min(4,$timeout));if(!$stream)return ['ok'=>false,'reason'=>'connection'];
    $id=(string)($event['id']??'');$deadline=microtime(true)+$timeout;$reason='timeout';
    if(@fwrite($stream,ws_frame(json_encode(['EVENT',$event],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)))===false){@fclose($stream);return ['ok'=>false,'reason'=>'write'];}
    while(microtime(true)<$deadline){stream_set_timeout($stream,max(1,(int)ceil($deadline-microtime(true))));$frame=ws_read_frame($stream);if($frame===false)break;if($frame['opcode']===9){@fwrite($stream,ws_frame($frame['payload'],10));continue;}if($frame['opcode']===8)break;if($frame['opcode']!==1)continue;$message=json_decode($frame['payload'],true);if(($message[0]??'')==='OK'&&($message[1]??'')===$id){$ok=(bool)($message[2]??false);$reason=$ok?'':substr((string)($message[3]??'rejected'),0,240);@fclose($stream);return ['ok'=>$ok,'reason'=>$reason];}}
    @fclose($stream);return ['ok'=>false,'reason'=>$reason];
}

function relay_publish_events(string $url,array $events,int $timeout=6): array {
    if(!$events)return ['ok'=>false,'reason'=>'empty'];$stream=relay_open($url,min(4,$timeout));if(!$stream)return ['ok'=>false,'reason'=>'connection'];
    $pending=[];foreach($events as $event){$pending[$event['id']]=true;if(@fwrite($stream,ws_frame(json_encode(['EVENT',$event],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)))===false){@fclose($stream);return ['ok'=>false,'reason'=>'write'];}}
    $deadline=microtime(true)+$timeout;$reason='timeout';
    while($pending&&microtime(true)<$deadline){stream_set_timeout($stream,max(1,(int)ceil($deadline-microtime(true))));$frame=ws_read_frame($stream);if($frame===false)break;if($frame['opcode']===9){@fwrite($stream,ws_frame($frame['payload'],10));continue;}if($frame['opcode']===8)break;if($frame['opcode']!==1)continue;$message=json_decode($frame['payload'],true);$id=(string)($message[1]??'');if(($message[0]??'')!=='OK'||!isset($pending[$id]))continue;if(!($message[2]??false)){$reason=substr((string)($message[3]??'rejected'),0,240);@fclose($stream);return ['ok'=>false,'reason'=>$reason];}unset($pending[$id]);}
    @fclose($stream);return $pending?['ok'=>false,'reason'=>$reason]:['ok'=>true,'reason'=>''];
}

function dispatch_image_deletion(array $image,int $budgetSeconds=12): array {
    if(!deletion_tables_available())return ['status'=>'schema_required','acknowledged'=>0,'targets'=>0];
    $request=db()->prepare('SELECT * FROM image_deletion_requests WHERE image_id=?');$request->execute([$image['id']]);$row=$request->fetch();
    if(!$row)return ['status'=>'legacy_unavailable','acknowledged'=>0,'targets'=>0];
    $events=json_decode($row['deletion_event'],true);if(!is_array($events))return ['status'=>'invalid_request','acknowledged'=>0,'targets'=>0];if(isset($events['kind']))$events=[$events];
    db()->prepare('INSERT IGNORE INTO image_deletion_relays(image_id,relay_id,status) SELECT image_id,relay_id,"pending" FROM image_relays WHERE image_id=? AND status="stored"')->execute([$image['id']]);
    $targets=db()->prepare('SELECT dr.relay_id,dr.status,r.url FROM image_deletion_relays dr JOIN relays r ON r.id=dr.relay_id WHERE dr.image_id=? AND dr.status!="acknowledged" AND r.enabled=1 AND r.write_allowed=1 ORDER BY dr.attempts ASC,r.latency ASC');$targets->execute([$image['id']]);
    $deadline=microtime(true)+max(3,$budgetSeconds);$lastError='';
    foreach($targets->fetchAll() as $target){if(microtime(true)>=$deadline)break;$result=relay_publish_events($target['url'],$events,min(6,max(2,(int)ceil($deadline-microtime(true)))));$state=$result['ok']?'acknowledged':'failed';$lastError=$result['ok']?'':$result['reason'];$update=db()->prepare('UPDATE image_deletion_relays SET status=?,attempts=attempts+1,last_error=?,last_attempt_at=UTC_TIMESTAMP(),acknowledged_at=IF(?="acknowledged",UTC_TIMESTAMP(),acknowledged_at) WHERE image_id=? AND relay_id=?');$update->execute([$state,$lastError,$state,$image['id'],$target['relay_id']]);}
    $count=db()->prepare('SELECT COUNT(*) total,SUM(status="acknowledged") acknowledged FROM image_deletion_relays WHERE image_id=?');$count->execute([$image['id']]);$counts=$count->fetch();$total=(int)($counts['total']??0);$ack=(int)($counts['acknowledged']??0);$status=$total>0&&$ack===$total?'complete':($ack>0?'partial':'pending');
    db()->prepare('UPDATE image_deletion_requests SET status=?,attempts=attempts+1,acknowledged=?,target_count=?,last_error=?,completed_at=IF(?="complete",UTC_TIMESTAMP(),NULL) WHERE image_id=?')->execute([$status,$ack,$total,$lastError,$status,$image['id']]);
    return ['status'=>$status,'acknowledged'=>$ack,'targets'=>$total];
}

function image_deletion_status(int $imageId): array {
    if(!deletion_tables_available())return ['status'=>'schema_required','acknowledged'=>0,'targets'=>0];
    $query=db()->prepare('SELECT status,attempts,acknowledged,target_count,last_error,created_at,updated_at,completed_at FROM image_deletion_requests WHERE image_id=?');$query->execute([$imageId]);$row=$query->fetch();
    if(!$row)return ['status'=>'legacy_unavailable','acknowledged'=>0,'targets'=>0];
    return ['status'=>$row['status'],'acknowledged'=>(int)$row['acknowledged'],'targets'=>(int)$row['target_count'],'attempts'=>(int)$row['attempts'],'last_error'=>$row['last_error']?:null,'created_at'=>$row['created_at'],'updated_at'=>$row['updated_at'],'completed_at'=>$row['completed_at']?:null];
}

function delete_image_everywhere(array $image,int $budgetSeconds=12): array {
    $update=db()->prepare('UPDATE images SET status="deleted" WHERE id=? AND status!="deleted"');$update->execute([$image['id']]);
    try{return dispatch_image_deletion($image,$budgetSeconds);}catch(Throwable $error){app_log('Image deletion dispatch failed for '.$image['short_id'].': '.$error->getMessage());return ['status'=>'pending','acknowledged'=>0,'targets'=>0];}
}
