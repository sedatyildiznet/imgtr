<?php
require_once __DIR__.'/../../includes/api.php';
api_method('GET');$user=api_require_key();$limit=api_limit();$cursor=api_decode_cursor(isset($_GET['cursor'])?(string)$_GET['cursor']:null);
$where='i.user_id=? AND i.status!="deleted"';$params=[(int)$user['id']];
if($cursor){$where.=' AND (i.created_at<? OR (i.created_at=? AND i.id<?))';array_push($params,$cursor[0],$cursor[0],$cursor[1]);}
$query=db()->prepare('SELECT i.*,(SELECT COUNT(*) FROM image_relays ir WHERE ir.image_id=i.id AND ir.status="stored") replica_count FROM images i WHERE '.$where.' ORDER BY i.created_at DESC,i.id DESC LIMIT '.($limit+1));
$query->execute($params);$rows=$query->fetchAll();$hasMore=count($rows)>$limit;if($hasMore)array_pop($rows);$next=null;
if($hasMore&&$rows){$last=end($rows);$next=api_encode_cursor($last['created_at'],(int)$last['id']);}
api_success(['images'=>array_map('api_image_resource',$rows)],200,['limit'=>$limit,'next_cursor'=>$next,'has_more'=>$hasMore]);
