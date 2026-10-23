<?php
require_once __DIR__.'/db.php';
require_once __DIR__.'/functions.php';

function control_tables_available(): bool {
    static $available=null;
    if($available!==null)return $available;
    try{
        db()->query('SELECT 1 FROM user_bans LIMIT 1');
        db()->query('SELECT 1 FROM admin_audit_log LIMIT 1');
        db()->query('SELECT 1 FROM traffic_hourly LIMIT 1');
        return $available=true;
    }catch(Throwable $error){
        app_log('Admin control schema unavailable: '.$error->getMessage());
        return $available=false;
    }
}

function active_user_ban(int $userId): ?array {
    if($userId<1)return null;
    try{
        $query=db()->prepare('SELECT * FROM user_bans WHERE user_id=? AND active=1 AND starts_at<=UTC_TIMESTAMP() AND (expires_at IS NULL OR expires_at>UTC_TIMESTAMP()) ORDER BY id DESC LIMIT 1');
        $query->execute([$userId]);
        return $query->fetch()?:null;
    }catch(Throwable $error){return null;}
}

function create_user_ban(int $userId,string $reason,?string $expiresAt,int $adminId): int {
    if(!control_tables_available())throw new RuntimeException('Admin control SQL update is required.');
    db()->prepare('UPDATE user_bans SET active=0,revoked_by=?,revoked_at=UTC_TIMESTAMP() WHERE user_id=? AND active=1')->execute([$adminId,$userId]);
    $query=db()->prepare('INSERT INTO user_bans(user_id,reason,expires_at,created_by) VALUES(?,?,?,?)');
    $query->execute([$userId,$reason,$expiresAt,$adminId]);
    return (int)db()->lastInsertId();
}

function revoke_user_ban(int $userId,int $adminId): int {
    if(!control_tables_available())throw new RuntimeException('Admin control SQL update is required.');
    $query=db()->prepare('UPDATE user_bans SET active=0,revoked_by=?,revoked_at=UTC_TIMESTAMP() WHERE user_id=? AND active=1');
    $query->execute([$adminId,$userId]);
    return $query->rowCount();
}

function admin_audit(int $adminId,string $action,string $targetType,?string $targetId=null,array $details=[]): void {
    if(!control_tables_available())return;
    try{
        $encoded=$details?json_encode($details,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE):null;
        $ip=hash_hmac('sha256',client_ip(),DB_PASS);
        $ua=substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,500);
        $query=db()->prepare('INSERT INTO admin_audit_log(admin_user_id,action,target_type,target_id,details_json,ip_hash,user_agent) VALUES(?,?,?,?,?,?,?)');
        $query->execute([$adminId,substr($action,0,80),substr($targetType,0,40),$targetId?substr($targetId,0,100):null,$encoded,$ip,$ua]);
    }catch(Throwable $error){app_log('Admin audit write failed: '.$error->getMessage());}
}

function record_public_hit(string $path): void {
    if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET')return;
    try{
        $ua=substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,500);
        if(preg_match('/(?:bot|spider|crawler|preview|slurp)/i',$ua))return;
        $visitor=hash_hmac('sha256',client_ip().'|'.$ua,DB_PASS);
        $imageHit=preg_match('#^/[A-Za-z0-9]{6,16}(?:\.[A-Za-z0-9]{2,5})?$#',$path)?1:0;
        $query=db()->prepare('INSERT INTO traffic_hourly(bucket_hour,visitor_hash,hits,image_hits,last_seen_at) VALUES(DATE_FORMAT(UTC_TIMESTAMP(),"%Y-%m-%d %H:00:00"),?,1,?,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE hits=hits+1,image_hits=image_hits+VALUES(image_hits),last_seen_at=UTC_TIMESTAMP()');
        $query->execute([$visitor,$imageHit]);
    }catch(Throwable $error){app_log('Traffic metric write failed: '.$error->getMessage());}
}

function safe_admin_page(int $default=1): int {
    $page=filter_input(INPUT_GET,'page',FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>100000]]);
    return $page?:$default;
}
