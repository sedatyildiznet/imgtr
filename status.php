<?php
require_once __DIR__.'/includes/layout.php';

$relays=[];
$databaseReady=false;
try{
    $relays=db()->query('SELECT url,status,latency,last_checked,read_allowed,write_allowed FROM relays WHERE enabled=1 ORDER BY FIELD(status,"healthy","slow","offline"),latency ASC,url')->fetchAll();
    $databaseReady=true;
}catch(Throwable $ignored){}

$total=count($relays);
$healthy=count(array_filter($relays,fn($relay)=>$relay['status']==='healthy'));
$slow=count(array_filter($relays,fn($relay)=>$relay['status']==='slow'));
$offline=count(array_filter($relays,fn($relay)=>$relay['status']==='offline'));
$usable=array_values(array_filter($relays,fn($relay)=>in_array($relay['status'],['healthy','slow'],true)&&!empty($relay['read_allowed'])&&!empty($relay['write_allowed'])));
$usableCount=count($usable);
$networkReady=$usableCount>=RELAY_MINIMUM;
$overallReady=$databaseReady&&$networkReady;
$latencies=array_values(array_filter(array_map(fn($relay)=>isset($relay['latency'])?(int)$relay['latency']:null,$usable),fn($latency)=>$latency!==null));
$averageLatency=$latencies?(int)round(array_sum($latencies)/count($latencies)):null;
$lastChecks=array_values(array_filter(array_column($relays,'last_checked')));
$lastCheck=$lastChecks?max($lastChecks):null;
$readiness=$total>0?(int)round(($usableCount/$total)*100):0;
$generated=gmdate('Y-m-d H:i:s').' UTC';

page_start(t('footer_status'),t('status_intro'));
?>
<main class="subpage status-page">
  <section class="status-console-head">
    <div><p class="eyebrow"><?=e(t('status_live'))?></p><h1><?=e(t($overallReady?'status_nominal':'status_degraded'))?></h1><p><?=e(t('status_intro'))?></p></div>
    <aside class="status-snapshot"><span><?=e(t('status_snapshot'))?></span><time datetime="<?=e(gmdate('c'))?>"><?=e($generated)?></time><small>UTC</small></aside>
  </section>

  <section class="status-overview" aria-label="<?=e(t('status_overview'))?>">
    <article class="status-overall <?=$overallReady?'is-ready':'is-warning'?>"><div><i class="status-beacon"></i><span><?=e(t('status_overall'))?></span></div><strong><?=e(t($overallReady?'operational':'regional'))?></strong><small>HTTP 200 · <?=$usableCount?>/<?=RELAY_MINIMUM?></small></article>
    <article><span><?=e(t('status_ready_nodes'))?></span><strong><?=$usableCount?><small>/<?=$total?></small></strong><code><?=$readiness?>%</code></article>
    <article><span><?=e(t('status_quorum'))?></span><strong><?=$usableCount?><small>/<?=RELAY_MINIMUM?></small></strong><code><?=e(t($networkReady?'operational':'regional'))?></code></article>
    <article><span><?=e(t('status_probe_latency'))?></span><strong><?=$averageLatency!==null?number_format($averageLatency):'—'?><small><?=$averageLatency!==null?' ms':''?></small></strong><code><?=e(t('status_mean'))?></code></article>
  </section>

  <section class="status-service-stack" aria-label="<?=e(t('status_services'))?>">
    <div><span class="status-dot <?=$databaseReady?'':'offline'?>"></span><b><?=e(t('web_app'))?></b><small>HTTPS</small><em><?=e(t($databaseReady?'operational':'status_unavailable'))?></em></div>
    <div><span class="status-dot <?=$databaseReady?'':'offline'?>"></span><b><?=e(t('status_database'))?></b><small>METADATA / SHA-256</small><em><?=e(t($databaseReady?'status_connected':'status_unavailable'))?></em></div>
    <div><span class="status-dot <?=$networkReady?'':'warning'?>"></span><b><?=e(t('image_delivery'))?></b><small>RELAY → RAM → HTTP</small><em><?=e(t($networkReady?'operational':'regional'))?></em></div>
    <div><span class="status-dot <?=$networkReady?'':'warning'?>"></span><b><?=e(t('relay_network'))?></b><small>NOSTR / WSS / NIP-11</small><em><?=$usableCount?> <?=e(t('status_ready_short'))?></em></div>
  </section>

  <div class="status-technical-grid">
    <section class="relay-telemetry">
      <header><div><p class="eyebrow"><?=e(t('status_relay_telemetry'))?></p><h2><?=e(t('status_relay_fabric'))?></h2></div><dl><div><dt><?=e(t('status_healthy'))?></dt><dd><?=$healthy?></dd></div><div><dt><?=e(t('status_slow'))?></dt><dd><?=$slow?></dd></div><div><dt><?=e(t('status_offline'))?></dt><dd><?=$offline?></dd></div></dl></header>
      <div class="relay-table-head"><span><?=e(t('status_node'))?></span><span><?=e(t('status_access'))?></span><span><?=e(t('status_latency'))?></span><span><?=e(t('status_state'))?></span></div>
      <div class="relay-table">
        <?php if(!$relays):?><p class="relay-empty"><?=e(t('status_no_telemetry'))?></p><?php endif?>
        <?php foreach($relays as $relay):$state=in_array($relay['status'],['healthy','slow','offline'],true)?$relay['status']:'unknown';?>
          <div class="relay-telemetry-row"><span class="relay-address"><i class="status-dot <?=$state==='healthy'?'':($state==='slow'?'warning':'offline')?>"></i><code><?=e($relay['url'])?></code></span><span class="relay-access"><?=!empty($relay['read_allowed'])?'R':'—'?> / <?=!empty($relay['write_allowed'])?'W':'—'?></span><span class="relay-latency"><?=isset($relay['latency'])?number_format((int)$relay['latency']).' ms':'—'?></span><span class="relay-state status-<?=$state?>"><?=e(t('status_'.$state))?></span></div>
        <?php endforeach?>
      </div>
    </section>

    <aside class="status-protocol">
      <p class="eyebrow"><?=e(t('status_protocol'))?></p><h2><?=e(t('status_runtime_profile'))?></h2>
      <dl>
        <div><dt><?=e(t('status_origin_storage'))?></dt><dd><?=e(t('status_disabled'))?></dd><small>0 B</small></div>
        <div><dt><?=e(t('status_event_transport'))?></dt><dd>NOSTR / WSS</dd><small>KIND 1 + KIND 5</small></div>
        <div><dt><?=e(t('status_integrity'))?></dt><dd>SHA-256</dd><small>MANIFEST + CHUNK</small></div>
        <div><dt><?=e(t('status_reconstruction'))?></dt><dd><?=e(t('status_memory_only'))?></dd><small>RAM → HTTP</small></div>
        <div><dt><?=e(t('status_relay_target'))?></dt><dd><?=RELAY_TARGET?></dd><small><?=RELAY_MINIMUM?> / <?=$usableCount?></small></div>
        <div><dt><?=e(t('status_last_probe'))?></dt><dd><?=$lastCheck?e($lastCheck.' UTC'):'—'?></dd><small>CRON · */15</small></div>
      </dl>
    </aside>
  </div>
  <p class="status-note"><?=e(t('status_note'))?></p>
</main>
<?php page_end();
