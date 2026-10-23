<?php
require_once __DIR__.'/../includes/admin-layout.php';
$admin=admin_require();$schemaReady=control_tables_available();

if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    verify_csrf();if(!$schemaReady){http_response_code(503);exit('Önce updates/2026-08-31-admin-control.sql güncellemesini çalıştırın.');}$action=(string)($_POST['action']??'');$userId=(int)($_POST['user_id']??0);
    $query=db()->prepare('SELECT id,email,role FROM users WHERE id=?');$query->execute([$userId]);$target=$query->fetch();
    if(!$target){http_response_code(404);exit('Üye bulunamadı.');}
    if((int)$target['id']===(int)$admin['id']||$target['role']==='admin'){http_response_code(422);exit('Yönetici hesapları bu ekrandan kısıtlanamaz.');}
    if($action==='ban'){
        $reason=trim((string)($_POST['reason']??''));$reasonLength=function_exists('mb_strlen')?mb_strlen($reason):strlen($reason);if($reasonLength<3||$reasonLength>500){http_response_code(422);exit('Yasaklama sebebi 3–500 karakter olmalıdır.');}
        $duration=(string)($_POST['duration']??'permanent');$expiresAt=null;
        $seconds=['1d'=>86400,'7d'=>604800,'30d'=>2592000];
        if(isset($seconds[$duration]))$expiresAt=gmdate('Y-m-d H:i:s',time()+$seconds[$duration]);
        elseif($duration==='custom'){$raw=(string)($_POST['expires_at']??'');try{$date=new DateTimeImmutable($raw);$date=$date->setTimezone(new DateTimeZone('UTC'));if($date->getTimestamp()<=time())throw new RuntimeException();$expiresAt=$date->format('Y-m-d H:i:s');}catch(Throwable){http_response_code(422);exit('Geçerli bir gelecek tarihi girin.');}}
        elseif($duration!=='permanent'){http_response_code(422);exit('Geçersiz süre.');}
        $banId=create_user_ban($userId,$reason,$expiresAt,(int)$admin['id']);
        admin_audit((int)$admin['id'],'user.ban','user',(string)$userId,['ban_id'=>$banId,'email'=>$target['email'],'reason'=>$reason,'expires_at'=>$expiresAt]);
        header('Location: /admin/users?notice=banned');exit;
    }
    if($action==='unban'){
        $changed=revoke_user_ban($userId,(int)$admin['id']);
        admin_audit((int)$admin['id'],'user.unban','user',(string)$userId,['email'=>$target['email'],'records'=>$changed]);
        header('Location: /admin/users?notice=unbanned');exit;
    }
    http_response_code(422);exit('Geçersiz işlem.');
}

$page=safe_admin_page();$perPage=40;$offset=($page-1)*$perPage;$total=(int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn();$pages=max(1,(int)ceil($total/$perPage));
$rows=db()->query('SELECT u.*,(SELECT COUNT(*) FROM images i WHERE i.user_id=u.id AND i.status!="deleted") image_count,(SELECT COUNT(*) FROM albums a WHERE a.user_id=u.id) album_count,(SELECT COALESCE(SUM(i.size),0) FROM images i WHERE i.user_id=u.id AND i.status!="deleted") used_bytes,(SELECT MAX(i.created_at) FROM images i WHERE i.user_id=u.id) last_upload FROM users u ORDER BY u.id DESC LIMIT '.$perPage.' OFFSET '.$offset)->fetchAll();
$authMap=[];$banMap=[];
try{if($rows){$ids=array_map('intval',array_column($rows,'id'));$marks=implode(',',array_fill(0,count($ids),'?'));$authQuery=db()->prepare("SELECT user_id,MAX(CASE WHEN event='login' THEN created_at END) last_auth_login,MAX(CASE WHEN event='logout' THEN created_at END) last_logout FROM auth_activity WHERE user_id IN ($marks) GROUP BY user_id");$authQuery->execute($ids);foreach($authQuery->fetchAll() as $eventRow)$authMap[(int)$eventRow['user_id']]=$eventRow;if($schemaReady){$banQuery=db()->prepare("SELECT * FROM user_bans WHERE user_id IN ($marks) AND active=1 AND starts_at<=UTC_TIMESTAMP() AND (expires_at IS NULL OR expires_at>UTC_TIMESTAMP()) ORDER BY id DESC");$banQuery->execute($ids);foreach($banQuery->fetchAll() as $ban)if(!isset($banMap[(int)$ban['user_id']]))$banMap[(int)$ban['user_id']]=$ban;}}}catch(Throwable $ignored){}
admin_start('Üyeler');admin_title('ÜYE YÖNETİMİ','Kullanıcılar','Hesap kullanımını inceleyin; gerekçeli, süreli veya kalıcı erişim kısıtlamaları uygulayın.');
?>
<?php if(!$schemaReady):?><div class="admin-schema-warning"><b>Yasaklama ve audit tabloları kurulmamış.</b><code>updates/2026-08-31-admin-control.sql</code></div><?php endif?>
<?php if(isset($_GET['notice'])):?><div class="admin-notice"><?=$_GET['notice']==='unbanned'?'Üye kısıtlaması kaldırıldı.':'Üye kısıtlandı; açık oturumu sonraki istekte sonlandırılacak.'?></div><?php endif?>
<div class="admin-toolbar"><span><?=number_format($total)?> üye · sayfa <?=$page?> / <?=$pages?></span></div>
<section class="admin-user-list">
<?php foreach($rows as $u):$auth=$authMap[(int)$u['id']]??[];$ban=$banMap[(int)$u['id']]??null;?>
<article class="admin-user-record"><div><span class="member-cell"><i><?=e(strtoupper(substr($u['email'],0,1)))?></i><span><b><?=e($u['email'])?></b><small>Kullanıcı #<?=e($u['id'])?> · <?=e($u['role'])?> · <?=e($u['status'])?></small></span></span><div class="admin-user-facts"><div><span>İçerik</span><b><?=number_format((int)$u['image_count'])?> görsel</b><small><?=number_format((int)$u['album_count'])?> albüm · <?=e(format_bytes((int)$u['used_bytes']))?></small></div><div><span>Son yükleme</span><b><?=e($u['last_upload']??'—')?></b></div><div><span>Son giriş</span><b><?=e(($u['last_login']??null)?:($auth['last_auth_login']??'—'))?></b></div><div><span>Son çıkış</span><b><?=e($auth['last_logout']??'—')?></b></div></div><?php if($ban):?><div class="admin-ban-state"><b>Kısıtlı · <?=e($ban['expires_at']?:'Süresiz')?></b><span><?=e($ban['reason'])?></span><small><?=e($ban['created_at'])?> tarihinde uygulandı</small></div><?php endif?></div><div>
<?php if(!$schemaReady):?><div class="admin-ban-form"><h3>SQL güncellemesi gerekli</h3><p>Üye kısıtlama araçları migration çalıştırıldıktan sonra açılır.</p></div><?php elseif($u['role']==='admin'):?><div class="admin-ban-form"><h3>Yönetici hesabı</h3><p>Yönetici hesapları güvenlik nedeniyle bu ekrandan kısıtlanamaz.</p></div><?php elseif($ban):?><form class="admin-ban-form" method="post" data-confirm="Bu üyenin erişim kısıtlaması kaldırılsın mı?"><h3>Kısıtlamayı kaldır</h3><p>Üye yeniden giriş yapabilir ve API anahtarlarını kullanabilir.</p><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="unban"><input type="hidden" name="user_id" value="<?=$u['id']?>"><button class="button button-secondary" type="submit">Yasağı kaldır</button></form><?php else:?><form class="admin-ban-form" method="post" data-confirm="Bu üyenin web ve API erişimi kısıtlansın mı?"><h3>Üyeyi kısıtla</h3><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="ban"><input type="hidden" name="user_id" value="<?=$u['id']?>"><label>Sebep<textarea name="reason" maxlength="500" required placeholder="Üye giriş yaptığında bu açıklamayı görecek."></textarea></label><label>Süre<select name="duration" data-ban-duration><option value="permanent">Süresiz</option><option value="1d">1 gün</option><option value="7d">7 gün</option><option value="30d">30 gün</option><option value="custom">Özel tarih</option></select></label><label data-ban-until hidden>Bitiş tarihi (yerel saat)<input type="datetime-local" name="expires_at"></label><button class="admin-danger-button" type="submit">Üyeyi yasakla</button></form><?php endif?></div></article>
<?php endforeach?>
</section>
<nav class="admin-pagination"><?php if($page>1):?><a href="?page=<?=$page-1?>">← Önceki</a><?php endif?><span><?=$page?> / <?=$pages?></span><?php if($page<$pages):?><a href="?page=<?=$page+1?>">Sonraki →</a><?php endif?></nav>
<?php admin_end();
