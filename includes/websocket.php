<?php
/** Minimal read-only WebSocket client and verified image cache. */
function ws_read_exact($stream,int $length): string|false {$data='';while(strlen($data)<$length&&!feof($stream)){$part=fread($stream,$length-strlen($data));if($part===false||$part==='')return false;$data.=$part;}return strlen($data)===$length?$data:false;}
function ws_frame(string $payload,int $opcode=1): string {$length=strlen($payload);$head=chr(0x80|$opcode);if($length<126)$head.=chr(0x80|$length);elseif($length<=65535)$head.=chr(0x80|126).pack('n',$length);else{$head.=chr(0x80|127).pack('NN',0,$length);}$mask=random_bytes(4);$masked='';for($i=0;$i<$length;$i++)$masked.=$payload[$i]^$mask[$i%4];return $head.$mask.$masked;}
function ws_read_frame($stream): array|false {$first=ws_read_exact($stream,2);if($first===false)return false;$b1=ord($first[0]);$b2=ord($first[1]);$opcode=$b1&0x0f;$length=$b2&0x7f;if($length===126){$raw=ws_read_exact($stream,2);if($raw===false)return false;$length=unpack('n',$raw)[1];}elseif($length===127){$raw=ws_read_exact($stream,8);if($raw===false)return false;$length=0;for($i=0;$i<8;$i++)$length=$length*256+ord($raw[$i]);if($length>16777216)return false;}if($length>262144)return false;$masked=($b2&0x80)!==0;$mask=$masked?ws_read_exact($stream,4):'';$payload=$length?ws_read_exact($stream,$length):'';if($payload===false)return false;if($masked)for($i=0;$i<$length;$i++)$payload[$i]=$payload[$i]^$mask[$i%4];return ['opcode'=>$opcode,'final'=>(bool)($b1&0x80),'payload'=>$payload];}
function ws_read_http_headers($stream,int $maxBytes=16384): string|false {
  $headers='';
  while(strlen($headers)<$maxBytes){
    // Read one byte at a time so bytes belonging to the first WebSocket frame remain available.
    $byte=fread($stream,1);
    if($byte===false||$byte==='')return false;
    $headers.=$byte;
    if(str_ends_with($headers,"\r\n\r\n"))return $headers;
  }
  return false;
}
function ws_write_all($stream,string $payload): bool {
  $offset=0;$length=strlen($payload);
  while($offset<$length){
    $written=@fwrite($stream,substr($payload,$offset));
    if($written===false||$written===0)return false;
    $offset+=$written;
  }
  return true;
}
function relay_open(string $url,int $timeout=8){
  $parts=parse_url($url);
  if(!$parts||!in_array(strtolower($parts['scheme']??''),['ws','wss'],true))return false;
  $host=$parts['host']??'';
  if($host==='')return false;
  $secure=strtolower($parts['scheme'])==='wss';
  $port=(int)($parts['port']??($secure?443:80));
  $path=($parts['path']??'/').(isset($parts['query'])?'?'.$parts['query']:'');
  $context=stream_context_create(['ssl'=>['verify_peer'=>true,'verify_peer_name'=>true,'peer_name'=>$host,'SNI_enabled'=>true]]);
  $stream=@stream_socket_client(($secure?'tls':'tcp').'://'.$host.':'.$port,$errno,$error,max(1,$timeout),STREAM_CLIENT_CONNECT,$context);
  if(!$stream)return false;
  stream_set_timeout($stream,max(1,$timeout));
  $key=base64_encode(random_bytes(16));
  $hostHeader=$host.(($secure&&$port===443)||(!$secure&&$port===80)?'':':'.$port);
  $request="GET $path HTTP/1.1\r\nHost: $hostHeader\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Key: $key\r\nSec-WebSocket-Version: 13\r\nOrigin: ".rtrim(SITE_URL,'/')."\r\nUser-Agent: IMGTR-Relay-Reader/1.1\r\n\r\n";
  if(!ws_write_all($stream,$request)){
    fclose($stream);
    return false;
  }
  $response=ws_read_http_headers($stream);
  $valid=$response!==false
    &&preg_match('#^HTTP/1\.[01] 101\b#',$response)
    &&preg_match('/^Upgrade:\s*websocket\s*$/im',$response)
    &&preg_match('/^Connection:.*\bupgrade\b/im',$response)
    &&preg_match('/^Sec-WebSocket-Accept:\s*([^\r\n]+)/im',$response,$accept)
    &&hash_equals(base64_encode(sha1($key.'258EAFA5-E914-47DA-95CA-C5AB0DC85B11',true)),trim($accept[1]));
  if(!$valid){
    fclose($stream);
    return false;
  }
  return $stream;
}
function relay_fetch_events(string $url,array $ids,int $timeout=10): array {
  $ids=array_values(array_unique(array_filter($ids,fn($id)=>is_string($id)&&preg_match('/^[a-f0-9]{64}$/',$id))));
  if(!$ids)return [];
  // The deadline includes DNS, TCP/TLS and the WebSocket handshake.
  $timeout=max(1,$timeout);
  $deadline=microtime(true)+$timeout;
  $stream=relay_open($url,min($timeout,6));
  if(!$stream)return [];
  $events=[];
  foreach(array_chunk($ids,60) as $batch){
    if(microtime(true)>=$deadline)break;
    $sub=bin2hex(random_bytes(6));
    $buffer='';
    $request=['REQ',$sub,['ids'=>$batch,'limit'=>count($batch)]];
    $encoded=json_encode($request,JSON_UNESCAPED_SLASHES);
    if(!is_string($encoded)||!ws_write_all($stream,ws_frame($encoded)))break;
    while(microtime(true)<$deadline){
      $missing=array_diff($batch,array_keys($events));
      if(!$missing)break;
      $remaining=$deadline-microtime(true);
      if($remaining<=0)break;
      stream_set_timeout($stream,max(1,(int)ceil($remaining)));
      $frame=ws_read_frame($stream);
      if($frame===false)break 2;
      if($frame['opcode']===9){
        ws_write_all($stream,ws_frame($frame['payload'],10));
        continue;
      }
      if($frame['opcode']===8)break 2;
      if($frame['opcode']===1)$buffer=$frame['payload'];
      elseif($frame['opcode']===0)$buffer.=$frame['payload'];
      else continue;
      if(strlen($buffer)>262144)break 2;
      if(!$frame['final'])continue;
      $message=json_decode($buffer,true);
      $buffer='';
      if(($message[0]??'')==='EVENT'&&($message[1]??'')===$sub&&is_array($message[2]??null)){
        $event=$message[2];
        $id=$event['id']??'';
        if(in_array($id,$batch,true)&&nostr_event_id_valid($event))$events[$id]=$event;
      }
      if(in_array(($message[0]??''),['EOSE','CLOSED'],true)&&($message[1]??'')===$sub)break;
    }
    ws_write_all($stream,ws_frame(json_encode(['CLOSE',$sub]),1));
  }
  fclose($stream);
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
function relay_image_cache_dir(): string {
  return rtrim(sys_get_temp_dir(),DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'imgtr-image-cache-'.substr(hash('sha256',__DIR__),0,12);
}
function relay_image_cache_path(string $sha256): string|false {
  if(!preg_match('/^[a-f0-9]{64}$/',$sha256))return false;
  return relay_image_cache_dir().DIRECTORY_SEPARATOR.$sha256.'.bin';
}
function relay_image_cache_ttl(): int {
  return defined('IMGTR_IMAGE_CACHE_TTL')?max(0,(int)IMGTR_IMAGE_CACHE_TTL):600;
}
function relay_image_cache_max_bytes(): int {
  return defined('IMGTR_IMAGE_CACHE_MAX_BYTES')?max(1048576,(int)IMGTR_IMAGE_CACHE_MAX_BYTES):268435456;
}
function relay_image_cache_read(array $image): string|false {
  $ttl=relay_image_cache_ttl();
  $path=relay_image_cache_path((string)($image['sha256']??''));
  if($ttl<1||$path===false||!is_file($path))return false;
  clearstatcache(true,$path);
  $modified=@filemtime($path);
  if($modified===false||time()-$modified>$ttl){
    @unlink($path);
    return false;
  }
  $binary=@file_get_contents($path);
  if(!is_string($binary)||(int)($image['size']??-1)!==strlen($binary)||!hash_equals((string)$image['sha256'],hash('sha256',$binary))){
    @unlink($path);
    return false;
  }
  @touch($path);
  return $binary;
}
function relay_image_cache_prune(string $dir,string $keep,int $ttl,int $maxBytes): void {
  $now=time();
  $entries=[];
  $total=0;
  foreach(glob($dir.DIRECTORY_SEPARATOR.'*.bin')?:[] as $path){
    clearstatcache(true,$path);
    $modified=@filemtime($path);
    $size=@filesize($path);
    if($modified===false||$size===false)continue;
    if($path!==$keep&&$now-$modified>$ttl){
      @unlink($path);
      continue;
    }
    $entries[]=['path'=>$path,'mtime'=>$modified,'size'=>$size];
    $total+=$size;
  }
  if($total<=$maxBytes)return;
  usort($entries,fn($a,$b)=>$a['mtime']<=>$b['mtime']);
  foreach($entries as $entry){
    if($total<=$maxBytes)break;
    if($entry['path']===$keep)continue;
    if(@unlink($entry['path']))$total-=$entry['size'];
  }
}
function relay_image_cache_store(array $image,string $binary): bool {
  $sha256=(string)($image['sha256']??'');
  $size=(int)($image['size']??-1);
  $ttl=relay_image_cache_ttl();
  $maximum=defined('MAX_FILE_SIZE')?(int)MAX_FILE_SIZE:10485760;
  if($ttl<1||$size<0||$size>$maximum||strlen($binary)!==$size||!preg_match('/^[a-f0-9]{64}$/',$sha256)||!hash_equals($sha256,hash('sha256',$binary)))return false;
  $dir=relay_image_cache_dir();
  if(!is_dir($dir)&&!@mkdir($dir,0700,true)&&!is_dir($dir))return false;
  @chmod($dir,0700);
  $path=relay_image_cache_path($sha256);
  if($path===false)return false;
  $temporary=@tempnam($dir,'.imgtr-');
  if($temporary===false)return false;
  $written=@file_put_contents($temporary,$binary,LOCK_EX);
  if($written!==$size){
    @unlink($temporary);
    return false;
  }
  @chmod($temporary,0600);
  if(!@rename($temporary,$path)){
    @unlink($temporary);
    return is_file($path);
  }
  relay_image_cache_prune($dir,$path,$ttl,relay_image_cache_max_bytes());
  return true;
}
function reconstruct_image_from_relays(array $image): string|false {
  $query=db()->prepare('SELECT ir.manifest_event_id,ir.chunk_event_ids,r.url FROM image_relays ir JOIN relays r ON r.id=ir.relay_id WHERE ir.image_id=? AND ir.status="stored" AND r.enabled=1 AND r.read_allowed=1 ORDER BY r.status="healthy" DESC,r.latency ASC');
  $query->execute([$image['id']]);
  $locations=$query->fetchAll();
  $chunkQuery=db()->prepare('SELECT chunk_index,chunk_hash FROM image_chunks WHERE image_id=? ORDER BY chunk_index');
  $chunkQuery->execute([$image['id']]);
  $chunkRows=$chunkQuery->fetchAll();
  if(!$locations||count($chunkRows)!==(int)$image['chunk_count'])return false;

  $candidates=[];
  foreach($locations as $location){
    $ids=json_decode($location['chunk_event_ids']??'[]',true);
    $manifestId=(string)($location['manifest_event_id']??'');
    if(!is_array($ids)||!array_is_list($ids)||count($ids)!==count($chunkRows)||!preg_match('/^[a-f0-9]{64}$/',$manifestId))continue;
    $valid=true;
    foreach($ids as $id){
      if(!is_string($id)||!preg_match('/^[a-f0-9]{64}$/',$id)){$valid=false;break;}
    }
    if($valid)$candidates[]=['url'=>(string)$location['url'],'manifest'=>$manifestId,'chunks'=>$ids];
  }
  if(!$candidates)return false;

  $events=[];
  $hashes=array_map(fn($row)=>(string)$row['chunk_hash'],$chunkRows);
  $deadline=microtime(true)+min(24,max(8,4+(int)ceil((int)$image['size']/1048576)*2));
  foreach($candidates as $candidate){
    $remaining=$deadline-microtime(true);
    if($remaining<=0)break;
    $requested=array_merge([$candidate['manifest']],$candidate['chunks']);
    $missing=array_values(array_diff($requested,array_keys($events)));
    if($missing){
      $budget=max(1,min(6,(int)ceil($remaining)));
      $events+=relay_fetch_events($candidate['url'],$missing,$budget);
    }
    $complete=isset($events[$candidate['manifest']])&&count(array_intersect($candidate['chunks'],array_keys($events)))===count($candidate['chunks']);
    if($complete&&relay_image_events_valid($events,$candidate['manifest'],$candidate['chunks'],$image,$hashes))break;
  }

  foreach($candidates as $candidate){
    if(!isset($events[$candidate['manifest']])||count(array_intersect($candidate['chunks'],array_keys($events)))!==count($candidate['chunks']))continue;
    if(!relay_image_events_valid($events,$candidate['manifest'],$candidate['chunks'],$image,$hashes))continue;
    $binary='';
    foreach($candidate['chunks'] as $eventId){
      $part=base64_decode($events[$eventId]['content']??'',true);
      if($part===false)return false;
      $binary.=$part;
    }
    if(strlen($binary)===(int)$image['size']&&hash_equals((string)$image['sha256'],hash('sha256',$binary)))return $binary;
  }
  return false;
}
