<?php
/** Minimal read-only WebSocket client for retrieving already-signed events from configured relays. */
function ws_read_exact($stream,int $length): string|false {$data='';while(strlen($data)<$length&&!feof($stream)){$part=fread($stream,$length-strlen($data));if($part===false||$part==='')return false;$data.=$part;}return strlen($data)===$length?$data:false;}
function ws_frame(string $payload,int $opcode=1): string {$length=strlen($payload);$head=chr(0x80|$opcode);if($length<126)$head.=chr(0x80|$length);elseif($length<=65535)$head.=chr(0x80|126).pack('n',$length);else{$head.=chr(0x80|127).pack('NN',0,$length);}$mask=random_bytes(4);$masked='';for($i=0;$i<$length;$i++)$masked.=$payload[$i]^$mask[$i%4];return $head.$mask.$masked;}
function ws_read_frame($stream): array|false {$first=ws_read_exact($stream,2);if($first===false)return false;$b1=ord($first[0]);$b2=ord($first[1]);$opcode=$b1&0x0f;$length=$b2&0x7f;if($length===126){$raw=ws_read_exact($stream,2);if($raw===false)return false;$length=unpack('n',$raw)[1];}elseif($length===127){$raw=ws_read_exact($stream,8);if($raw===false)return false;$length=0;for($i=0;$i<8;$i++)$length=$length*256+ord($raw[$i]);if($length>16777216)return false;}if($length>262144)return false;$masked=($b2&0x80)!==0;$mask=$masked?ws_read_exact($stream,4):'';$payload=$length?ws_read_exact($stream,$length):'';if($payload===false)return false;if($masked)for($i=0;$i<$length;$i++)$payload[$i]=$payload[$i]^$mask[$i%4];return ['opcode'=>$opcode,'final'=>(bool)($b1&0x80),'payload'=>$payload];}
function relay_open(string $url,int $timeout=8){$parts=parse_url($url);if(!$parts||!in_array(strtolower($parts['scheme']??''),['ws','wss'],true))return false;$host=$parts['host']??'';$secure=strtolower($parts['scheme'])==='wss';$port=(int)($parts['port']??($secure?443:80));$path=($parts['path']??'/').(isset($parts['query'])?'?'.$parts['query']:'');$context=stream_context_create(['ssl'=>['verify_peer'=>true,'verify_peer_name'=>true,'peer_name'=>$host,'SNI_enabled'=>true]]);$stream=@stream_socket_client(($secure?'tls':'tcp').'://'.$host.':'.$port,$errno,$error,$timeout,STREAM_CLIENT_CONNECT,$context);if(!$stream)return false;stream_set_timeout($stream,$timeout);$key=base64_encode(random_bytes(16));$hostHeader=$host.(($secure&&$port===443)||(!$secure&&$port===80)?'':':'.$port);$request="GET $path HTTP/1.1\r\nHost: $hostHeader\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Key: $key\r\nSec-WebSocket-Version: 13\r\nOrigin: ".rtrim(SITE_URL,'/')."\r\nUser-Agent: IMGTR-Relay-Reader/1.0\r\n\r\n";fwrite($stream,$request);$response='';while(!str_contains($response,"\r\n\r\n")&&strlen($response)<16384){$part=fread($stream,1024);if($part===false||$part==='')break;$response.=$part;}if(!preg_match('#^HTTP/1\.[01] 101\b#',$response)||!preg_match('/^Sec-WebSocket-Accept:\s*([^\r\n]+)/im',$response,$accept)||!hash_equals(base64_encode(sha1($key.'258EAFA5-E914-47DA-95CA-C5AB0DC85B11',true)),trim($accept[1]))){fclose($stream);return false;}return $stream;}
function relay_fetch_events(string $url,array $ids,int $timeout=10): array {
  $ids=array_values(array_unique(array_filter($ids,fn($id)=>is_string($id)&&preg_match('/^[a-f0-9]{64}$/',$id))));
  if(!$ids)return [];
  $stream=relay_open($url,min($timeout,6));
  if(!$stream)return [];
  $events=[];$deadline=microtime(true)+$timeout;
  foreach(array_chunk($ids,60) as $batch){
    if(microtime(true)>=$deadline)break;
    $sub=bin2hex(random_bytes(6));$buffer='';
    $request=['REQ',$sub,['ids'=>$batch,'limit'=>count($batch)]];
    if(@fwrite($stream,ws_frame(json_encode($request,JSON_UNESCAPED_SLASHES)))===false)break;
    while(microtime(true)<$deadline){
      $missing=array_diff($batch,array_keys($events));
      if(!$missing)break;
      stream_set_timeout($stream,max(1,(int)ceil($deadline-microtime(true))));
      $frame=ws_read_frame($stream);
      if($frame===false)break 2;
      if($frame['opcode']===9){@fwrite($stream,ws_frame($frame['payload'],10));continue;}
      if($frame['opcode']===8)break 2;
      if($frame['opcode']===1)$buffer=$frame['payload'];elseif($frame['opcode']===0)$buffer.=$frame['payload'];else continue;
      if(strlen($buffer)>262144)break 2;if(!$frame['final'])continue;
      $message=json_decode($buffer,true);$buffer='';
      if(($message[0]??'')==='EVENT'&&($message[1]??'')===$sub&&is_array($message[2]??null)){
        $event=$message[2];$id=$event['id']??'';
        if(in_array($id,$batch,true)&&nostr_event_id_valid($event))$events[$id]=$event;
      }
      if(in_array(($message[0]??''),['EOSE','CLOSED'],true)&&($message[1]??'')===$sub)break;
    }
    @fwrite($stream,ws_frame(json_encode(['CLOSE',$sub]),1));
  }
  @fclose($stream);
  return $events;
}
function nostr_event_id_valid(array $event): bool {
  if(!isset($event['id'],$event['pubkey'],$event['created_at'],$event['kind'],$event['tags'],$event['content']))return false;
  if(!is_string($event['id'])||!preg_match('/^[a-f0-9]{64}$/',$event['id'])||!is_string($event['pubkey'])||!preg_match('/^[a-f0-9]{64}$/',$event['pubkey'])||!is_string($event['sig']??null)||!preg_match('/^[a-f0-9]{128}$/',$event['sig']))return false;
  $canonical=json_encode([0,$event['pubkey'],(int)$event['created_at'],(int)$event['kind'],$event['tags'],(string)$event['content']],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_LINE_TERMINATORS);
  return $canonical!==false&&hash_equals($event['id'],hash('sha256',$canonical));
}
function nostr_tag_value(array $event,string $name): ?string {foreach(($event['tags']??[]) as $tag)if(is_array($tag)&&($tag[0]??null)===$name&&is_string($tag[1]??null))return $tag[1];return null;}
function relay_image_events_valid(array $events,string $manifestId,array $chunkIds,array $image,array $chunkHashes): bool {
  $manifest=$events[$manifestId]??null;
  if(!is_array($manifest)||(int)($manifest['kind']??0)!==30303)return false;
  $data=json_decode($manifest['content']??'',true);
  if(!is_array($data)||($data['protocol']??'')!=='imgtr'||(int)($data['version']??0)<2||($data['sha256']??'')!==$image['sha256']||(int)($data['size']??-1)!==(int)$image['size']||(int)($data['chunks']??-1)!==count($chunkHashes)||nostr_tag_value($manifest,'d')!==($image['short_id']??null))return false;
  $totalSize=0;$imageHash=hash_init('sha256');
  foreach($chunkIds as $index=>$eventId){
    $event=$events[$eventId]??null;
    if(!is_array($event)||(int)($event['kind']??0)!==30304||($event['pubkey']??'')!==($manifest['pubkey']??null)||nostr_tag_value($event,'d')!==($image['short_id'].':'.$index)||nostr_tag_value($event,'chunk')!==(string)$index||nostr_tag_value($event,'e')!==$manifestId)return false;
    $part=base64_decode($event['content']??'',true);
    if($part===false||!isset($chunkHashes[$index])||!hash_equals($chunkHashes[$index],hash('sha256',$part)))return false;
    $totalSize+=strlen($part);if($totalSize>(int)$image['size'])return false;hash_update($imageHash,$part);
  }
  return $totalSize===(int)$image['size']&&hash_equals($image['sha256'],hash_final($imageHash));
}
function reconstruct_image_from_relays(array $image): string|false {$q=db()->prepare('SELECT ir.manifest_event_id,ir.chunk_event_ids,r.url FROM image_relays ir JOIN relays r ON r.id=ir.relay_id WHERE ir.image_id=? AND ir.status="stored" AND r.enabled=1 AND r.read_allowed=1 ORDER BY r.status="healthy" DESC,r.latency ASC');$q->execute([$image['id']]);$locations=$q->fetchAll();$chunksStmt=db()->prepare('SELECT chunk_index,chunk_hash FROM image_chunks WHERE image_id=? ORDER BY chunk_index');$chunksStmt->execute([$image['id']]);$chunkRows=$chunksStmt->fetchAll();if(!$locations||count($chunkRows)!==(int)$image['chunk_count'])return false;$needed=[];$chunkEventIds=[];foreach($locations as $location){$ids=json_decode($location['chunk_event_ids']??'[]',true);if(!is_array($ids)||count($ids)!==count($chunkRows))continue;$chunkEventIds[]=$ids;$needed[]=$location['manifest_event_id'];foreach($ids as $id)$needed[]=$id;}$events=[];foreach($locations as $location){$ids=json_decode($location['chunk_event_ids']??'[]',true);if(!is_array($ids))continue;$request=array_merge([$location['manifest_event_id']],$ids);$events+=relay_fetch_events($location['url'],$request,12);if(count(array_intersect($request,array_keys($events)))===count($request))break;}$selectedIds=null;$manifest=null;foreach($locations as $location){$ids=json_decode($location['chunk_event_ids']??'[]',true);if(!is_array($ids))continue;if(isset($events[$location['manifest_event_id']])&&count(array_intersect($ids,array_keys($events)))===count($ids)){$selectedIds=$ids;$manifest=$events[$location['manifest_event_id']];break;}}if(!$selectedIds||!$manifest)return false;$manifestData=json_decode($manifest['content']??'',true);if(!is_array($manifestData)||($manifestData['sha256']??'')!==$image['sha256'])return false;$binary='';foreach($chunkRows as $index=>$chunkRow){$event=$events[$selectedIds[$index]]??null;if(!$event)return false;$part=base64_decode($event['content']??'',true);if($part===false||hash('sha256',$part)!==$chunkRow['chunk_hash'])return false;$binary.=$part;if(strlen($binary)>MAX_FILE_SIZE)return false;}if(strlen($binary)!==(int)$image['size']||hash('sha256',$binary)!==$image['sha256'])return false;return $binary;}
