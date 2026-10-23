<?php
if(!defined('IMGTR_API_V1'))define('IMGTR_API_V1',true);
require_once __DIR__.'/auth.php';

function api_request_id(): string {
    static $id;
    if ($id) return $id;
    $incoming=trim((string)($_SERVER['HTTP_X_REQUEST_ID']??''));
    $id=preg_match('/^[A-Za-z0-9._-]{8,80}$/',$incoming)?$incoming:bin2hex(random_bytes(12));
    return $id;
}

function api_headers(): void {
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');
    header('X-IMGTR-API-Version: 1');
    header('X-Request-ID: '.api_request_id());
}

function api_success(array $data=[],int $status=200,array $meta=[]): never {
    http_response_code($status); api_headers();
    $body=['success'=>true,'data'=>$data];
    if($meta)$body['meta']=$meta;
    $body['request_id']=api_request_id();
    echo json_encode($body,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); exit;
}

function api_error(string $code,string $message,int $status,array $details=[]): never {
    http_response_code($status); api_headers();
    $error=['code'=>$code,'message'=>$message];
    if($details)$error['details']=$details;
    echo json_encode(['success'=>false,'error'=>$error,'request_id'=>api_request_id()],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); exit;
}

function api_method(string ...$allowed): void {
    $method=strtoupper($_SERVER['REQUEST_METHOD']??'GET');
    if(in_array($method,$allowed,true))return;
    header('Allow: '.implode(', ',$allowed));
    api_error('method_not_allowed','This endpoint does not accept '.$method.'.',405,['allowed'=>$allowed]);
}

function api_authorization(): string {
    $header=(string)($_SERVER['HTTP_AUTHORIZATION']??$_SERVER['REDIRECT_HTTP_AUTHORIZATION']??'');
    if(trim($header)===''){
        foreach(function_exists('getallheaders')?(getallheaders()?:[]):[] as $name=>$value){
            if(strcasecmp((string)$name,'Authorization')===0){$header=(string)$value;break;}
        }
    }
    return trim($header);
}

function api_require_key(): array {
    secure_session();
    $authorization=api_authorization();
    if(!preg_match('/^Bearer\s+(imgtr_[A-Fa-f0-9]{48})$/',$authorization,$match)){
        header('WWW-Authenticate: Bearer realm="IMGTR API"');
        api_error('authentication_required','Use an IMGTR API key in the Bearer authorization header.',401);
    }
    $hash=hash('sha256',$match[1]);
    $query=db()->prepare('SELECT u.*,k.id api_key_id FROM api_keys k JOIN users u ON u.id=k.user_id WHERE k.key_hash=? AND u.status="active" LIMIT 1');
    $query->execute([$hash]); $user=$query->fetch();
    if(!$user){header('WWW-Authenticate: Bearer realm="IMGTR API", error="invalid_token"');api_error('invalid_api_key','The supplied API key is invalid or has been revoked.',401);}
    if($ban=active_user_ban((int)$user['id']))api_error('account_restricted','This account is restricted.',403,['reason'=>$ban['reason'],'expires_at'=>$ban['expires_at']]);
    db()->prepare('UPDATE api_keys SET last_used=UTC_TIMESTAMP() WHERE id=?')->execute([$user['api_key_id']]);
    rate_limit('api-v1-user-'.$user['id'],180);
    return $user;
}

function api_json(int $maximumBytes=65536): array {
    $length=(int)($_SERVER['CONTENT_LENGTH']??0);
    if($length>$maximumBytes)api_error('payload_too_large','The JSON request body is too large.',413);
    $raw=file_get_contents('php://input',false,null,0,$maximumBytes+1);
    if($raw===false||strlen($raw)>$maximumBytes)api_error('payload_too_large','The JSON request body is too large.',413);
    if(trim($raw)==='')return [];
    try{$value=json_decode($raw,true,64,JSON_THROW_ON_ERROR);}catch(JsonException $exception){api_error('invalid_json','The request body is not valid JSON.',400);}
    if(!is_array($value))api_error('invalid_json','The JSON request body must be an object.',400);
    return $value;
}

function api_limit(int $default=25,int $maximum=100): int {
    $raw=$_GET['limit']??$default;
    if(filter_var($raw,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>$maximum]])===false)
        api_error('invalid_limit','limit must be an integer between 1 and '.$maximum.'.',422);
    return (int)$raw;
}

function api_encode_cursor(string $createdAt,int $id): string {
    return rtrim(strtr(base64_encode($createdAt.'|'.$id),'+/','-_'),'=');
}

function api_decode_cursor(?string $cursor): ?array {
    if($cursor===null||$cursor==='')return null;
    if(!preg_match('/^[A-Za-z0-9_-]{8,160}$/',$cursor))api_error('invalid_cursor','The pagination cursor is invalid.',422);
    $decoded=base64_decode(strtr($cursor,'-_','+/'),true);
    if($decoded===false||!preg_match('/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\|(\d+)$/',$decoded,$match))
        api_error('invalid_cursor','The pagination cursor is invalid.',422);
    return [$match[1],(int)$match[2]];
}

function api_image_resource(array $image): array {
    $extension=(string)$image['extension']; $short=(string)$image['short_id'];
    return [
        'id'=>$short,'filename'=>$image['original_filename'],'mime'=>$image['mime'],'extension'=>$extension,
        'size'=>(int)$image['size'],'width'=>(int)$image['width'],'height'=>(int)$image['height'],
        'status'=>$image['status'],'views'=>(int)($image['views']??0),'replicas'=>(int)($image['replica_count']??0),
        'created_at'=>gmdate('c',strtotime($image['created_at'].' UTC')),
        'url'=>base_url($short),'direct_url'=>base_url($short.'.'.$extension)
    ];
}

function api_find_owned_image(int $userId,string $shortId,bool $includeDeleted=false): array {
    if(!preg_match('/^[A-Za-z0-9]{4,32}$/',$shortId))api_error('invalid_image_id','The image id is invalid.',422);
    $sql='SELECT i.*,(SELECT COUNT(*) FROM image_relays ir WHERE ir.image_id=i.id AND ir.status="stored") replica_count FROM images i WHERE i.user_id=? AND i.short_id=?'.($includeDeleted?'':' AND i.status!="deleted"').' LIMIT 1';
    $query=db()->prepare($sql);$query->execute([$userId,$shortId]);$image=$query->fetch();
    if(!$image)api_error('image_not_found','The requested image was not found.',404);
    return $image;
}
