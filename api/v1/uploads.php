<?php define('IMGTR_API_V1',true);
require_once __DIR__.'/../../includes/api.php';
api_method('POST');$user=api_require_key();if((int)($_SERVER['CONTENT_LENGTH']??0)>131072)api_error('payload_too_large','Upload metadata is too large.',413);define('IMGTR_API_AUTH_USER',$user);
require __DIR__.'/../image.php';
