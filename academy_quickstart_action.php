<?php
define('AJAX_SCRIPT',true);
require_once(__DIR__.'/../../config.php');
require_once(__DIR__.'/classes/local/academy_quickstart.php');
use local_learning_workspace\local\academy_quickstart as q;
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
try {
    if (($_SERVER['REQUEST_METHOD']??'GET')!=='POST') { http_response_code(405);throw new InvalidArgumentException('method'); }
    if (!isloggedin()||isguestuser()) { http_response_code(401);throw new DomainException('login'); }
    require_sesskey();
    $state=q::change(required_param('role',PARAM_ALPHA),required_param('segment',PARAM_ALPHA),required_param('action',PARAM_ALPHA),
        required_param('revision',PARAM_INT),optional_param('step',0,PARAM_INT),(bool)optional_param('manual',0,PARAM_BOOL));
    echo json_encode(['ok'=>true,'state'=>$state]);
} catch (Throwable $error) {
    if(http_response_code()===200){http_response_code($error instanceof DomainException?409:400);}
    echo json_encode(['ok'=>false,'message'=>q::text('error')]);
}
