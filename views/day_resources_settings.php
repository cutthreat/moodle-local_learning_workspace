<?php
use local_learning_workspace\local\academy_workspace_view as v;
defined('MOODLE_INTERNAL') || die();
require_once(__DIR__.'/../classes/local/academy_day_resources.php');
$resourceid=optional_param('resource_lesson','L1',PARAM_ALPHANUM);
if (!preg_match('/^L[1-7]$/D',$resourceid)) { $resourceid='L1'; }
$resources=\local_learning_workspace\local\academy_day_resources::read($resourceid);
?>
<section class="aw-panel" id="day-resources"><h2>Встречи и записи</h2><details><summary>Настроить дополнительные материалы дня</summary>
<form method="get"><label for="resource-lesson">День</label><select id="resource-lesson" name="resource_lesson"><?php for($i=1;$i<=7;$i++): ?><option value="L<?=$i?>" <?=$resourceid==='L'.$i?'selected':''?>>День <?=$i?></option><?php endfor; ?></select><button class="aw-secondary">Выбрать</button></form>
<form method="post"><?=v::fields('dayresources')?><input type="hidden" name="resource_lesson" value="<?=v::e($resourceid)?>">
<label for="resource-topic">Тема встречи · <?=v::e($resourceid)?></label><input id="resource-topic" name="resource_topic" maxlength="160" value="<?=v::e($resources['topic']??'')?>">
<label for="resource-meeting">Ссылка на встречу (HTTPS, необязательно)</label><input id="resource-meeting" name="resource_meeting" type="url" maxlength="2000" value="<?=v::e($resources['meeting']??'')?>">
<label for="resource-recording">Ссылка на запись (HTTPS, необязательно)</label><input id="resource-recording" name="resource_recording" type="url" maxlength="2000" value="<?=v::e($resources['recording']??'')?>">
<small>Без ссылок блок в уроке не показывается. Встреча не блокирует доступ к материалу и контрольному тесту.</small>
<div class="aw-form-actions"><button>Сохранить материалы дня</button></div></form></details></section>
