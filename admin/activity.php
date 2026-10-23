<?php
require_once __DIR__.'/../includes/admin-layout.php';
admin_require();$schemaReady=control_tables_available();$type=in_array($_GET['type']??'auth',['auth','admin'],true)?$_GET['type']:'auth';$page=safe_admin_page();$perPage=50;$offset=($page-1)*$perPage;$rows=[];$total=0;$available=true;
try{
    if($type==='admin'){
        if(!$schemaReady)throw new RuntimeException('schema');
        $total=(int)db()->query('SELECT COUNT(*) FROM admin_audit_log')->fetchColumn();
        $rows=db()->query('SELECT al.*,u.email admin_email FROM admin_audit_log al LEFT JOIN users u ON u.id=al.admin_user_id ORDER BY al.id DESC LIMIT '.$perPage.' OFFSET '.$offset)->fetchAll();
    }else{
        $total=(int)db()->query('SELECT COUNT(*) FROM auth_activity')->fetchColumn();
        $rows=db()->query('SELECT aa.*,u.role FROM auth_activity aa LEFT JOIN users u ON u.id=aa.user_id ORDER BY aa.id DESC LIMIT '.$perPage.' OFFSET '.$offset)->fetchAll();
    }
}catch(Throwable){$available=false;}
$pages=max(1,(int)ceil($total/$perPage));
admin_start('Kayıtlar');admin_title('GÜVENLİK VE AUDIT','Sistem kayıtları','Giriş denemelerini ve yönetici değişikliklerini ayrı, sayfalanmış kayıtlar halinde inceleyin.');
?>
<div class="admin-toolbar"><nav><a class="<?=$type==='auth'?'active':''?>" href="?type=auth">Kimlik doğrulama</a><a class="<?=$type==='admin'?'active':''?>" href="?type=admin">Yönetici audit</a></nav><span><?=number_format($total)?> kayıt</span></div>
<?php if(!$available):?><div class="admin-schema-warning"><b>Kayıt tablosu hazır değil.</b><code>updates/2026-08-31-admin-control.sql</code></div><?php elseif(!$rows):?><div class="admin-empty">Henüz kayıt bulunmuyor.</div><?php elseif($type==='auth'):?>
<section class="admin-card table-card"><div class="admin-table-scroll"><table class="admin-table"><thead><tr><th>Olay</th><th>Kullanıcı</th><th>Yetki</th><th>IP özeti</th><th>Tarayıcı / cihaz</th><th>Zaman</th></tr></thead><tbody><?php foreach($rows as $r):?><tr><td><span class="table-status event-<?=e($r['event'])?>"><?=e($r['event'])?></span></td><td><b><?=e($r['email']?:'Bilinmeyen')?></b><small><?=isset($r['user_id'])?'#'.e($r['user_id']):'—'?></small></td><td><?=e($r['role']??'—')?></td><td><code><?=e(substr($r['ip_hash'],0,16))?>…</code></td><td class="user-agent"><?=e($r['user_agent']?:'—')?></td><td><?=e($r['created_at'])?></td></tr><?php endforeach?></tbody></table></div></section>
<?php else:?>
<section class="admin-card table-card"><div class="admin-table-scroll"><table class="admin-table"><thead><tr><th>İşlem</th><th>Yönetici</th><th>Hedef</th><th>Ayrıntı</th><th>IP özeti</th><th>Zaman</th></tr></thead><tbody><?php foreach($rows as $r):$details=json_decode($r['details_json']??'',true);?><tr><td><span class="table-status event-admin"><?=e($r['action'])?></span></td><td><b><?=e($r['admin_email']?:'Yönetici #'.$r['admin_user_id'])?></b></td><td><b><?=e($r['target_type'])?></b><small><?=e($r['target_id']??'—')?></small></td><td class="audit-details"><code><?=e($details?json_encode($details,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE):'—')?></code><small><?=e($r['user_agent']?:'—')?></small></td><td><code><?=e(substr($r['ip_hash'],0,16))?>…</code></td><td><?=e($r['created_at'])?></td></tr><?php endforeach?></tbody></table></div></section>
<?php endif?>
<?php if($available&&$rows):?><nav class="admin-pagination"><?php if($page>1):?><a href="?type=<?=e($type)?>&amp;page=<?=$page-1?>">← Önceki</a><?php endif?><span><?=$page?> / <?=$pages?></span><?php if($page<$pages):?><a href="?type=<?=e($type)?>&amp;page=<?=$page+1?>">Sonraki →</a><?php endif?></nav><?php endif?>
<?php admin_end();
