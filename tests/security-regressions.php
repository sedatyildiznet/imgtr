<?php
declare(strict_types=1);
// Pure checks: no database or network required.
require_once __DIR__.'/../includes/websocket.php';
function must(bool $ok,string $message): void { if(!$ok){fwrite(STDERR,"FAILED: ".$message."\n");exit(1);} }
$event=['pubkey'=>str_repeat('a',64),'created_at'=>1700000000,'kind'=>30304,'tags'=>[['d','abc:0']],'content'=>'ZGF0YQ=='];
$serialized=json_encode([0,$event['pubkey'],$event['created_at'],$event['kind'],$event['tags'],$event['content']],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_LINE_TERMINATORS);
$event['id']=hash('sha256',$serialized);
$event['sig']=str_repeat('b',128);
must(nostr_event_id_valid($event),'content hash and signature shape accepted');
$missing=$event;unset($missing['sig']);must(!nostr_event_id_valid($missing),'missing signature rejected');
$invalid=$event;$invalid['sig']='not-a-signature';must(!nostr_event_id_valid($invalid),'invalid signature syntax rejected');
$tampered=$event;$tampered['content']='tampered';must(!nostr_event_id_valid($tampered),'content hash mismatch rejected');
$stream=fopen('php://temp','w+');fwrite($stream,chr(0x81).chr(127).pack('NN',0,262145));rewind($stream);
must(ws_read_frame($stream)===false,'oversized WebSocket frame rejected before allocation');fclose($stream);
echo "Security regression checks passed.\n";

$handshake=fopen('php://temp','w+b');
$firstPayload='{"type":"first-frame"}';
$firstFrame=chr(0x81).chr(strlen($firstPayload)).$firstPayload;
$headers="HTTP/1.1 101 Switching Protocols\r\nUpgrade: websocket\r\nConnection: Upgrade\r\n\r\n";
fwrite($handshake,$headers.$firstFrame);
rewind($handshake);
$receivedHeaders=ws_read_http_headers($handshake);
must($receivedHeaders===$headers,'WebSocket handshake headers are read without consuming the first frame');
$receivedFrame=ws_read_frame($handshake);
must(is_array($receivedFrame)&&$receivedFrame['payload']===$firstPayload,'first WebSocket frame remains readable after handshake');
fclose($handshake);

$cacheBytes='IMGTR cache regression payload';
$cacheImage=['sha256'=>hash('sha256',$cacheBytes),'size'=>strlen($cacheBytes)];
must(relay_image_cache_store($cacheImage,$cacheBytes),'verified image cache write succeeds');
must(relay_image_cache_read($cacheImage)===$cacheBytes,'verified image cache returns the original bytes');
$cachePath=relay_image_cache_path($cacheImage['sha256']);
if($cachePath!==false)@unlink($cachePath);

