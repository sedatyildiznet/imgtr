<?php
require_once __DIR__.'/../../includes/api.php';
api_method('GET');
api_success(['name'=>'IMGTR API','version'=>'v1','documentation'=>base_url('api'),'openapi'=>base_url('api/openapi.json'),'authentication'=>'Bearer API key']);
