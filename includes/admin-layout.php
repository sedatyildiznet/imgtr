<?php
require_once __DIR__.'/auth.php';
require_once __DIR__.'/language.php';
require_once __DIR__.'/seo.php';
require_once __DIR__.'/layout.php';

function admin_start(string $title): array
{
    $user=admin_require();
    $active=basename($_SERVER['SCRIPT_NAME']??'index.php');
    header('X-Robots-Tag: noindex, nofollow, noarchive');
    $items=[
        'index.php'=>['fa-chart-line','Genel bakış'],
        'images.php'=>['fa-images','Görseller'],
        'users.php'=>['fa-users','Üyeler'],
        'relays.php'=>['fa-server','Depolama'],
        'reports.php'=>['fa-flag','Bildirimler'],
        'activity.php'=>['fa-clock-rotate-left','Kayıtlar'],
        'settings.php'=>['fa-gear','Sistem'],
    ];
    ?>
    <!doctype html>
    <html lang="<?=e(current_language())?>">
    <head>
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width,initial-scale=1">
      <meta name="csrf-token" content="<?=csrf_token()?>">
      <?php seo_head($title.' — IMGTR Admin','',['index'=>false,'full_title'=>$title.' — IMGTR Admin']);?>
      <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@7.2.0/css/all.min.css">
      <link rel="stylesheet" href="/assets/css/premium.css?v=20260831f">
      <link rel="stylesheet" href="/assets/css/dashboard-refine.css?v=20260831a">
      <link rel="stylesheet" href="/assets/css/admin-control.css?v=20260831a">
    </head>
    <body class="admin-body">
    <header class="site-header"><div class="header-inner">
      <a class="logo" href="/">IMGTR<span>.</span></a><span class="admin-badge">ADMIN</span>
      <div class="header-actions"><?php language_picker();?><button class="theme-button" id="theme" aria-label="<?=e(t('theme'))?>">◐</button><a class="login-link" href="/dashboard"><?=e(t('dashboard'))?></a><a class="button small" href="/logout"><?=e(t('logout'))?></a></div>
    </div></header>
    <div class="admin-shell"><aside class="admin-sidebar"><div><p>YÖNETİM</p>
      <?php foreach($items as $file=>$item):$route=$file==='index.php'?'/admin/':'/admin/'.basename($file,'.php');?><a class="<?=$active===$file?'active':''?>" href="<?=e($route)?>"><i class="fa-solid <?=e($item[0])?>" aria-hidden="true"></i><?=e($item[1])?></a><?php endforeach?>
    </div><small><?=e($user['email'])?><br>Yetkili hesap</small></aside><main class="admin-main">
    <?php
    return $user;
}

function admin_require(): array
{
    secure_session();$user=current_user();
    if(!$user||($user['role']??'user')!=='admin'){http_response_code(403);exit('Forbidden');}
    return $user;
}

function admin_title(string $eyebrow,string $title,string $description=''): void
{
    ?><div class="admin-heading"><div><p class="eyebrow"><?=e($eyebrow)?></p><h1><?=e($title)?></h1><?php if($description):?><p><?=e($description)?></p><?php endif?></div></div><?php
}

function admin_end(array $scripts=[]): void
{
    ?></main></div><?php site_footer(current_user());?><div class="toast" id="toast"></div><script src="/assets/js/app.js?v=20260828c"></script><script src="/assets/js/admin-control.js?v=20260831a"></script><?php foreach($scripts as $script):?><script src="<?=e($script)?>"></script><?php endforeach?></body></html><?php
}
