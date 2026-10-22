<?php
require_once(__DIR__.'/../../config.php');require_login();
try{$file=\local_learning_workspace\local\academy_blended::recording(required_param('id',PARAM_INT),(bool)optional_param('caption',0,PARAM_BOOL));}
catch(DomainException|dml_missing_record_exception $e){http_response_code(403);header('Content-Type: text/plain; charset=utf-8');header('Cache-Control: private, no-store');echo 'Запись недоступна вашему аккаунту или ещё не опубликована.';exit;}
\core\session\manager::write_close();send_stored_file($file,0,0,false,['cacheability'=>'private','dontdie'=>false]);
