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
