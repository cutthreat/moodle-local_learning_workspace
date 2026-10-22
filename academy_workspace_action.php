<?php
define('AJAX_SCRIPT',true);
require_once(__DIR__.'/../../config.php');
require_once(__DIR__.'/classes/local/academy_workspace.php');
use local_learning_workspace\local\academy_workspace as w;
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
try {
    if (($_SERVER['REQUEST_METHOD']??'GET')!=='POST') { http_response_code(405); throw new DomainException('POST required'); }
    require_sesskey();
    if (required_param('action',PARAM_ALPHA)!=='dismiss') { throw new DomainException('Unknown action'); }
    w::dismiss(required_param('key',PARAM_ALPHANUM));
    echo json_encode(['ok'=>true]);
} catch (Throwable $error) {
    if (http_response_code()===200) { http_response_code(400); }
    echo json_encode(['ok'=>false,'message'=>w::text('session_failed')]);
}
