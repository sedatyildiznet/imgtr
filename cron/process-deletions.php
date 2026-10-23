<?php
if(PHP_SAPI!=='cli')exit(1);
require_once __DIR__.'/../includes/deletion.php';
if(!deletion_tables_available()){fwrite(STDERR,"Required relay deletion database tables are missing; see docs/SELF_HOSTING.md.\n");exit(1);}
$lock=fopen(sys_get_temp_dir().'/imgtr-process-deletions.lock','c');
if($lock===false||!flock($lock,LOCK_EX|LOCK_NB)){fwrite(STDERR,"Deletion processing is already running.\n");exit(0);}
$query=db()->query('SELECT i.* FROM images i JOIN image_deletion_requests d ON d.image_id=i.id WHERE i.status="deleted" AND d.status IN ("prepared","pending","partial") ORDER BY d.updated_at ASC LIMIT 20');
$processed=0;foreach($query->fetchAll() as $image){$result=dispatch_image_deletion($image,18);$processed++;echo $image['short_id'].' '.$result['status'].' '.$result['acknowledged'].'/'.$result['targets'].PHP_EOL;}
echo 'Processed '.$processed.' deletion request(s).'.PHP_EOL;
flock($lock,LOCK_UN);fclose($lock);
