<?php
require_once __DIR__.'/includes/auth.php';
require_once __DIR__.'/includes/layout.php';
secure_session();
if (current_user()) { header('Location: /dashboard'); exit; }
$error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
  try {
    verify_csrf();
    $now=time(); $window=$_SESSION['register_window']??['started'=>$now,'hits'=>0];
    if($now-(int)$window['started']>3600) $window=['started'=>$now,'hits'=>0];
    if((int)$window['hits']>=5) throw new RuntimeException(t('too_many_register'));
    $email=strtolower(trim($_POST['email']??'')); $password=$_POST['password']??'';
    if(!filter_var($email,FILTER_VALIDATE_EMAIL)) throw new RuntimeException(t('invalid_email'));
    rate_limit('register-ip',8);
    if(strlen($password)<10) throw new RuntimeException(t('password_short'));
    $exists=db()->prepare('SELECT id FROM users WHERE email=? LIMIT 1'); $exists->execute([$email]);
    if($exists->fetchColumn()) throw new RuntimeException(t('email_exists'));
    db()->prepare('INSERT INTO users(email,password_hash) VALUES(?,?)')->execute([$email,password_hash($password,PASSWORD_DEFAULT)]);
    $_SESSION['user_id']=db()->lastInsertId();record_auth_activity('register',(int)$_SESSION['user_id'],$email);unset($_SESSION['register_window']); session_regenerate_id(true);
    header('Location: /dashboard'); exit;
  } catch(Throwable $exception) { $window['hits']=((int)($window['hits']??0))+1; $_SESSION['register_window']=$window; $error=$exception->getMessage(); app_log('Register handled error: '.get_class($exception)); }
}
page_start(t('register'),t('auth_register_text'),['index'=>false]);
?><main class="auth-shell"><section class="auth-card"><p class="eyebrow"><?=e(t('auth_free'))?></p><h1><?=e(t('auth_register_title'))?></h1><p class="muted"><?=e(t('auth_register_text'))?></p><?php if($error):?><div class="form-error"><?=e($error)?></div><?php endif?><form method="post"><input type="hidden" name="csrf" value="<?=csrf_token()?>"><label><?=e(t('email'))?><input name="email" type="email" autocomplete="email" value="<?=e($_POST['email']??'')?>" required></label><label><?=e(t('password'))?><input name="password" type="password" minlength="10" autocomplete="new-password" required><small><?=e(t('password_hint'))?></small></label><button class="button" type="submit"><?=e(t('register'))?></button></form><p class="auth-switch"><?=e(t('auth_has_account'))?> <a href="/login"><?=e(t('login'))?></a></p></section></main><?php page_end(); ?>
