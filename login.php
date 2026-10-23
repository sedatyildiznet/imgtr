<?php
require_once __DIR__.'/includes/auth.php';
require_once __DIR__.'/includes/layout.php';
secure_session();
if (current_user()) { header('Location: /dashboard'); exit; }
$error='';
$banNotice=$_SESSION['account_ban_notice']??null;unset($_SESSION['account_ban_notice']);
if(is_array($banNotice))$error=t('account_banned_message',['reason'=>$banNotice['reason']??'—','until'=>($banNotice['expires_at']??null)?:t('ban_permanent')]);
if ($_SERVER['REQUEST_METHOD']==='POST') {
  try {
    verify_csrf();
    $now=time(); $window=$_SESSION['login_window']??['started'=>$now,'hits'=>0];
    if($now-(int)$window['started']>900) $window=['started'=>$now,'hits'=>0];
    if((int)$window['hits']>=8) throw new RuntimeException(t('too_many_login'));
    $email=strtolower(trim($_POST['email']??'')); $password=$_POST['password']??'';
    if(!filter_var($email,FILTER_VALIDATE_EMAIL)) throw new RuntimeException(t('invalid_email'));
    rate_limit('login-ip',25);
    rate_limit('login-account-'.hash('sha256',$email),8,false);
    $stmt=db()->prepare('SELECT * FROM users WHERE email=? LIMIT 1'); $stmt->execute([$email]); $user=$stmt->fetch();
    if(!$user||!password_verify($password,$user['password_hash'])||(($user['status']??'active')!=='active')) {
      record_auth_activity('login_failed',null,$email);$window['hits']++; $_SESSION['login_window']=$window; throw new RuntimeException(t('invalid_login'));
    }
    if($ban=active_user_ban((int)$user['id'])){record_auth_activity('login_blocked',(int)$user['id'],$user['email']);throw new RuntimeException(t('account_banned_message',['reason'=>$ban['reason'],'until'=>$ban['expires_at']?:t('ban_permanent')]));}
    $_SESSION['user_id']=$user['id']; unset($_SESSION['login_window']); session_regenerate_id(true);
    try { db()->prepare('UPDATE users SET last_login=UTC_TIMESTAMP() WHERE id=?')->execute([$user['id']]); } catch(Throwable $ignored) {}record_auth_activity('login',(int)$user['id'],$user['email']);
    header('Location: /dashboard'); exit;
  } catch(Throwable $exception) { $error=$exception->getMessage(); app_log('Login handled error: '.get_class($exception)); }
}
page_start(t('login'),t('auth_login_text'),['index'=>false]);
?><main class="auth-shell"><section class="auth-card"><p class="eyebrow"><?=e(t('auth_welcome'))?></p><h1><?=e(t('auth_login_title'))?></h1><p class="muted"><?=e(t('auth_login_text'))?></p><?php if($error):?><div class="form-error"><?=e($error)?></div><?php endif?><form method="post"><input type="hidden" name="csrf" value="<?=csrf_token()?>"><label><?=e(t('email'))?><input name="email" type="email" autocomplete="email" value="<?=e($_POST['email']??'')?>" required></label><label><?=e(t('password'))?><input name="password" type="password" autocomplete="current-password" required></label><button class="button" type="submit"><?=e(t('login'))?></button></form><p class="auth-switch"><?=e(t('auth_no_account'))?> <a href="/register"><?=e(t('auth_create_free'))?></a></p></section></main><?php page_end(); ?>
