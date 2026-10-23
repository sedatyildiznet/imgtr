<?php
require_once __DIR__.'/../includes/admin-layout.php';
require_once __DIR__.'/../includes/deletion.php';
$admin=admin_require();
$schemaReady=control_tables_available();

if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    verify_csrf();
    $action=(string)($_POST['action']??'');$shortId=(string)($_POST['id']??'');
    $query=db()->prepare('SELECT * FROM images WHERE short_id=? LIMIT 1');$query->execute([$shortId]);$image=$query->fetch();
    if(!$image){http_response_code(404);exit('Görsel bulunamadı.');}
    if($action==='delete'){
        $result=delete_image_everywhere($image,12);
        admin_audit((int)$admin['id'],'image.delete','image',$shortId,['owner_id'=>$image['user_id'],'relay_deletion'=>$result]);
        header('Location: /admin/images?notice=deleted');exit;
    }
    if($action==='retry_deletion'){
        $result=dispatch_image_deletion($image,12);
        admin_audit((int)$admin['id'],'image.deletion_retry','image',$shortId,['relay_deletion'=>$result]);
        header('Location: /admin/images?notice=retry');exit;
    }
    http_response_code(422);exit('Geçersiz işlem.');
}

$page=safe_admin_page();$perPage=40;$offset=($page-1)*$perPage;
$total=(int)db()->query('SELECT COUNT(*) FROM images')->fetchColumn();$pages=max(1,(int)ceil($total/$perPage));
$rows=db()->query('SELECT i.*,u.email,(SELECT COUNT(*) FROM image_relays ir WHERE ir.image_id=i.id AND ir.status="stored") replica_count FROM images i LEFT JOIN users u ON u.id=i.user_id ORDER BY i.id DESC LIMIT '.$perPage.' OFFSET '.$offset)->fetchAll();
$relayMap=[];$deletionMap=[];
if($rows){
    $ids=array_map('intval',array_column($rows,'id'));$marks=implode(',',array_fill(0,count($ids),'?'));
    $relayQuery=db()->prepare("SELECT ir.*,r.url FROM image_relays ir JOIN relays r ON r.id=ir.relay_id WHERE ir.image_id IN ($marks) ORDER BY r.url");$relayQuery->execute($ids);
    foreach($relayQuery->fetchAll() as $relay)$relayMap[(int)$relay['image_id']][]=$relay;
    if(deletion_tables_available()){$deletionQuery=db()->prepare("SELECT * FROM image_deletion_requests WHERE image_id IN ($marks)");$deletionQuery->execute($ids);foreach($deletionQuery->fetchAll() as $item)$deletionMap[(int)$item['image_id']]=$item;}
}
admin_start('Görseller');
admin_title('İÇERİK ENVANTERİ','Yüklenen görseller','IMGTR bağlantılarını, relay manifestlerini, parça event’lerini ve NIP-09 silme durumlarını yönetin.');
?>
<?php if(!$schemaReady):?><div class="admin-schema-warning"><b>Admin kontrol şeması kurulmamış.</b><code>updates/2026-08-31-admin-control.sql</code></div><?php endif?>
<?php if(isset($_GET['notice'])):?><div class="admin-notice"><?=$_GET['notice']==='retry'?'Relay silme talebi yeniden gönderildi.':'Görsel bağlantısı kapatıldı ve relay silme süreci başlatıldı.'?></div><?php endif?>
<div class="admin-toolbar"><span><?=number_format($total)?> kayıt · sayfa <?=$page?> / <?=$pages?></span><a class="button small" href="/admin/images">Yenile</a></div>
<section class="admin-image-list">
<?php foreach($rows as $row):$relays=$relayMap[(int)$row['id']]??[];$deletion=$deletionMap[(int)$row['id']]??null;?>
<article class="admin-image-record">
  <div class="admin-image-summary">
    <?php if($row['status']==='ready'):?><a href="/<?=e($row['short_id'])?>"><img src="/<?=e($row['short_id'])?>.<?=e($row['extension'])?>" alt="" loading="lazy"></a><?php else:?><div class="admin-thumb-placeholder"><?=e(strtoupper($row['status']))?></div><?php endif?>
    <div><b><?=e($row['original_filename'])?></b><small><?=e($row['email']?:'Anonim yükleme')?> · #<?=e((string)($row['user_id']?:'—'))?></small><small><?=e($row['mime'])?> · <?=e($row['width'])?>×<?=e($row['height'])?> · <?=e(format_bytes((int)$row['size']))?></small><code title="<?=e($row['sha256'])?>"><?=e(substr($row['sha256'],0,20))?>…</code></div>
    <div><span class="table-status status-<?=e($row['status'])?>"><?=e($row['status'])?></span><small><?=e($row['created_at'])?> · <?=number_format((int)$row['views'])?> hit</small><small><?=count($relays)?> relay / <?=number_format((int)$row['replica_count'])?> doğrulanmış</small><?php if($deletion):?><small>Silme: <?=e($deletion['status'])?> · <?=number_format((int)$deletion['acknowledged'])?>/<?=number_format((int)$deletion['target_count'])?></small><?php endif?></div>
    <div class="admin-image-actions"><?php if($row['status']==='ready'):?><a class="button small button-secondary" href="/<?=e($row['short_id'])?>" target="_blank">Görüntüle ↗</a><?php endif?><button class="admin-copy-mini" type="button" data-copy-text="<?=e(base_url($row['short_id'].'.'.$row['extension']))?>">Direkt URL</button><?php if($row['status']!=='deleted'):?><form method="post" data-confirm="Bu IMGTR bağlantısı kapatılsın ve relay’lere NIP-09 silme talebi gönderilsin mi?"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=e($row['short_id'])?>"><button class="admin-danger-button" type="submit">Görseli sil</button></form><?php elseif($deletion&&in_array($deletion['status'],['prepared','pending','partial'],true)):?><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="retry_deletion"><input type="hidden" name="id" value="<?=e($row['short_id'])?>"><button class="admin-danger-button" type="submit">Silme talebini yinele</button></form><?php endif?></div>
  </div>
  <details class="admin-image-relays"><summary>Relay kayıtları ve özgün Nostr event’leri</summary><div class="admin-relay-list">
  <?php if(!$relays):?><small>Bu görsel için relay kaydı yok.</small><?php else:foreach($relays as $relay):$chunks=json_decode($relay['chunk_event_ids']??'[]',true);if(!is_array($chunks))$chunks=[];?>
    <div class="admin-relay-row"><div><header><i class="status-dot <?=$relay['status']==='stored'?'':'warning'?>"></i><code><?=e($relay['url'])?></code><span class="table-status"><?=e($relay['status'])?></span></header><small><?=e($relay['stored_at']??'—')?> · <?=count($chunks)?> parça</small><button class="admin-copy-mini" type="button" data-copy-text="<?=e($relay['url'])?>">Relay adresini kopyala</button></div><div class="admin-relay-events"><label>MANIFEST EVENT / NOSTR URI</label><?php if(!empty($relay['manifest_event_id'])):?><code><?=e('nostr:'.$relay['manifest_event_id'])?></code><button class="admin-copy-mini" type="button" data-copy-text="<?=e('nostr:'.$relay['manifest_event_id'])?>">Nostr URI kopyala</button><?php else:?><code>Manifest event ID kaydedilmemiş.</code><?php endif?><details><summary>Chunk event ID’leri (<?=count($chunks)?>)</summary><code><?=e($chunks?implode("\n",$chunks):'Event ID kaydedilmemiş.')?></code><?php if($chunks):?><button class="admin-copy-mini" type="button" data-copy-text="<?=e(implode("\n",$chunks))?>">Tüm event ID’lerini kopyala</button><?php endif?></details></div></div>
  <?php endforeach;endif?></div></details>
</article>
<?php endforeach?>
</section>
<nav class="admin-pagination"><?php if($page>1):?><a href="?page=<?=$page-1?>">← Önceki</a><?php endif?><span><?=$page?> / <?=$pages?></span><?php if($page<$pages):?><a href="?page=<?=$page+1?>">Sonraki →</a><?php endif?></nav>
<?php admin_end();
