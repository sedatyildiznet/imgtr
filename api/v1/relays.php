<?php
require_once __DIR__.'/../../includes/api.php';require_once __DIR__.'/../../includes/relay.php';
api_method('GET');api_require_key();$rows=healthy_relays(true);
api_success(['relays'=>array_map(fn($relay)=>['id'=>(int)$relay['id'],'url'=>$relay['url'],'status'=>$relay['status'],'latency_ms'=>isset($relay['latency'])?(int)$relay['latency']:null,'write_allowed'=>(bool)$relay['write_allowed']],$rows),'target'=>RELAY_TARGET,'minimum'=>RELAY_MINIMUM]);
