<?php
require_once __DIR__.'/../../includes/api.php';
api_method('GET'); $user=api_require_key();
api_success(['user'=>['email'=>$user['email'],'language'=>$user['language'],'theme'=>$user['theme']]]);
