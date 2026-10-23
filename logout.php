<?php
require_once __DIR__.'/includes/auth.php';
secure_session();
if ($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    $logoutUser=current_user();
    if($logoutUser) record_auth_activity('logout',(int)$logoutUser['id'],$logoutUser['email']);
    $_SESSION=[];
    session_destroy();
    json_response(['success'=>true]);
}
require_once __DIR__.'/includes/layout.php';
page_start(t('logout'),'',['index'=>false]);
?><main class="content-page"><h1>Sign out</h1><p>Confirm that you want to end this session.</p><form method="post"><input type="hidden" name="csrf" value="<?=csrf_token()?>"><button class="button" type="submit">Sign out</button></form></main><?php page_end();
