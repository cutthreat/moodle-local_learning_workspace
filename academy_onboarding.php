<?php
require_once(__DIR__.'/../../config.php');
require_once(__DIR__.'/classes/local/academy_workspace_view.php');
require_once(__DIR__.'/classes/local/academy_onboarding_form.php');
require_once(__DIR__.'/classes/local/academy_onboarding_validation_exception.php');
use local_learning_workspace\local\academy_blended as b;
use local_learning_workspace\local\academy_workspace_view as v;
use local_learning_workspace\local\academy_onboarding_form as f;
require_login();\local_learning_workspace\local\academy_access::require_viewer();
$userid=optional_param('user',0,PARAM_INT)?:$USER->id;$own=(int)$userid===(int)$USER->id;
$error='';$fielderrors=[];try{$record=b::onboarding((int)$userid);}catch(DomainException $e){b::denied();return;}
$answers=$record?json_decode($record->answers,true):[];$revision=(int)($record->revision??0);
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
 require_sesskey();if(!$own){throw new moodle_exception('nopermissions','error');}$revision=required_param('revision',PARAM_INT);
 $answers=[];foreach(array_keys(b::options()) as $key){$answers[$key]=$key==='days'?optional_param_array('days',[],PARAM_ALPHA):optional_param($key,'',PARAM_TEXT);}
 foreach(['otherlanguage','timezone','timewindow','background','goal'] as $key){$answers[$key]=optional_param($key,'',PARAM_TEXT);}
 $multipleanswers=optional_param('multipleanswers',0,PARAM_BOOL);if($multipleanswers){
  foreach(f::MULTIPLE as $plural){$answers[$plural]=optional_param_array($plural,[],PARAM_ALPHA);}
  $languages=optional_param_array('languages',[],PARAM_ALPHA);$levels=optional_param_array('languagelevels',[],PARAM_ALPHA);$names=optional_param_array('languagenames',[],PARAM_TEXT);
  $answers['languages']=[];for($i=0;$i<max(count($languages),count($levels),count($names));$i++){$answers['languages'][]=['language'=>$languages[$i]??'','level'=>$levels[$i]??'','otherlanguage'=>$names[$i]??''];}
 }
 $add=optional_param('addlanguage',0,PARAM_BOOL);$remove=optional_param('removelanguage',-1,PARAM_INT);
 if($add||$remove>=0){
  $rows=f::languages($answers);if($remove>=0&&count($rows)>1&&isset($rows[$remove])){array_splice($rows,$remove,1);}
  if($add&&count($rows)<f::MAX_LANGUAGES){$rows[]=['language'=>'','level'=>'','otherlanguage'=>''];}
  $answers['languages']=$rows;
 }else{
  try{\local_learning_workspace\local\academy_workspace::consume_form('onboard');b::save_onboarding($answers,$revision,optional_param('finish',0,PARAM_BOOL));redirect(b::url('onboarding',['saved'=>1]));}catch(DomainException $e){$error=$e->getMessage();if($e instanceof \local_learning_workspace\local\academy_onboarding_validation_exception){$fielderrors=$e->field_errors();if(!$multipleanswers){foreach(['language','level','otherlanguage'] as $key){if(isset($fielderrors[$key])){$fielderrors['languages-0-'.$key]=$fielderrors[$key];unset($fielderrors[$key]);}}}}}
 }
}
$savednotice=($_SERVER['REQUEST_METHOD']??'GET')==='GET'&&$error===''&&optional_param('saved',0,PARAM_BOOL);
v::start('profile','onboarding',$fielderrors?'':$error,$savednotice?'Анкета сохранена. Вы можете уточнить её позже.':'',$own?'Подготовимся к обучению':'Учебная анкета кандидата');
echo f::summary($fielderrors);
$person=$DB->get_record('user',['id'=>$userid],'id,firstname,lastname',MUST_EXIST);
echo '<p>'.v::e(fullname($person)).' · '.($record&&$record->status==='complete'?'Анкета заполнена':'Короткая анкета перед первым занятием').'</p>';
if(!$own){echo '<p>Данные видны кандидату, назначенному тренеру и управляющему. Это подготовка к обучению, не автоматическая оценка пригодности.</p>';}
else{echo '<p>Помогите тренеру подобрать темп, язык и время занятий. Не указывайте данные клиентов, документы, здоровье или платёжные реквизиты.</p>';}
$labels=['experience'=>'Как долго вы работали с клиентами в переписке?','channel'=>f::text('question_channels'),'area'=>f::text('question_areas'),'language'=>f::text('question_languages'),'level'=>'Как вы оцениваете владение этим языком?','device'=>f::text('question_devices'),'meet'=>'Готовы ли вы подключаться к Google Meet?'];
if(!$own){echo '<section class="aw-panel"><dl>';foreach($labels as $key=>$label){if($key==='level'){continue;}echo '<dt><strong>'.v::e($label).'</strong></dt><dd>';echo $key==='language'?f::display_languages($answers,b::options()):(isset(f::MULTIPLE[$key])?v::e(f::display_choices($answers,$key,b::options()[$key])):v::e(b::options()[$key][$answers[$key]??'']??'Не указано'));echo '</dd>';}
 foreach(['timezone'=>'Часовой пояс','timewindow'=>'Доступное местное время','background'=>'Опыт и сильные стороны','goal'=>'Что хочется отработать'] as $key=>$label){echo '<dt><strong>'.$label.'</strong></dt><dd>'.nl2br(v::e($answers[$key]??'Не указано')).'</dd>';}
 echo '<dt><strong>Дни занятий</strong></dt><dd>'.v::e(implode(', ',array_map(static fn($d)=>b::options()['days'][$d]??'',(array)($answers['days']??[])))).'</dd></dl></section>';v::end();return;}
if(!\local_learning_workspace\local\academy_access::is_candidate()){echo '<p>Анкета предназначена приглашённым учащимся.</p>';v::end();return;}
$select=static function($key)use($answers,$labels,$fielderrors){echo '<label for="on-'.$key.'">'.v::e($labels[$key]).'</label><select id="on-'.$key.'" name="'.$key.'" required'.f::attributes($key,$fielderrors).'><option value="">Выберите вариант</option>';foreach(b::options()[$key] as $value=>$label){echo '<option value="'.$value.'" '.(($answers[$key]??'')===$value?'selected':'').'>'.v::e($label).'</option>';}echo '</select>'.f::error($key,$fielderrors);};
?>
<form method="post" action="<?=v::e(b::url('onboarding')->out(false))?>" class="ab-onboarding" data-aw-form="onboarding" data-aw-unsaved="<?=($_SERVER['REQUEST_METHOD']??'GET')==='POST'?'true':'false'?>" data-language-number="<?=v::e(f::text('language_number','{n}'))?>" data-days-error="<?=v::e(f::text('error_days_required'))?>" data-multiple-error="<?=v::e(f::text('error_multiple'))?>" data-language-removed="<?=v::e(f::text('language_removed'))?>" data-language-restored="<?=v::e(f::text('language_restored'))?>" data-language-undo="<?=v::e(f::text('language_undo','{language}'))?>" data-language-undo-unnamed="<?=v::e(f::text('language_undo_unnamed','{row}'))?>" data-language-undo-scope="<?=v::e(f::text('language_undo_scope'))?>" data-language-undo-limit="<?=v::e(f::text('language_undo_limit'))?>"><?=v::fields('onboard')?><input type="hidden" name="revision" value="<?=$revision?>"><input type="hidden" name="multipleanswers" value="1">
<section class="aw-panel"><h2>1. Ваш опыт</h2><?php $select('experience');foreach(['channel','area'] as $key){echo f::multiple($key,$labels[$key],$answers,b::options()[$key],$fielderrors);}?>
<details class="lw-details" <?=isset($fielderrors['background'])?'open':''?>><summary>Добавить контекст об опыте — необязательно</summary><label for="on-background">Какие задачи получались лучше всего? Без имён клиентов и конфиденциальных примеров.</label><textarea id="on-background" name="background" rows="3" maxlength="600"<?=f::attributes('background',$fielderrors)?>><?=v::e($answers['background']??'')?></textarea><?=f::error('background',$fielderrors)?></details></section>
<section class="aw-panel"><h2>2. Языки и время</h2><div id="on-language" tabindex="-1"<?=f::attributes('language',$fielderrors)?>><h3><?=v::e($labels['language'])?></h3><p class="aw-meta"><?=v::e(f::text('languages_hint'))?></p><div data-language-rows><?php $rows=array_slice(f::languages($answers),0,f::MAX_LANGUAGES);foreach($rows as $index=>$row){echo f::language_row(is_array($row)?$row:[],$index,b::options(),$fielderrors,count($rows)>1);}?></div><?=f::error('language',$fielderrors)?><button type="submit" name="addlanguage" value="1" formnovalidate class="lw-secondary" data-add-language data-max-languages="<?=f::MAX_LANGUAGES?>" <?=count($rows)>=f::MAX_LANGUAGES?'hidden':''?>><?=v::e(f::text('add_language'))?></button><p class="aw-meta" data-language-limit <?=count($rows)<f::MAX_LANGUAGES?'hidden':''?>><?=v::e(f::text('languages_limit',f::MAX_LANGUAGES))?></p><p class="ab-language-status" role="status" aria-live="polite" data-language-status></p>
<div data-language-undo-group role="group" aria-label="<?=v::e(f::text('removed_languages'))?>" hidden><div class="aw-form-actions" data-language-undo-list></div><p id="on-language-undo-help" class="aw-meta" data-language-undo-help><?=v::e(f::text('language_undo_scope'))?></p></div>
<template data-language-template><?=f::language_row(['language'=>'','level'=>'','otherlanguage'=>''],0,b::options(),[])?></template></div>
<label for="on-timezone">Ваш часовой пояс</label><select id="on-timezone" name="timezone" required<?=f::attributes('timezone',$fielderrors)?>><option value="">Выберите часовой пояс</option><?php foreach(core_date::get_list_of_timezones() as $zone=>$label):?><option value="<?=v::e($zone)?>" <?=($answers['timezone']??($USER->timezone==='99'?'':$USER->timezone))===$zone?'selected':''?>><?=v::e($label)?></option><?php endforeach;?></select><?=f::error('timezone',$fielderrors)?>
<fieldset id="on-days" tabindex="-1"<?=f::attributes('days',$fielderrors)?>><legend>В какие дни удобно заниматься?</legend><div class="ab-checks"><?php foreach(b::options()['days'] as $value=>$label):?><label><input type="checkbox" id="on-days-<?=$value?>" name="days[]" value="<?=$value?>"<?=f::attributes('days',$fielderrors)?> <?=in_array($value,(array)($answers['days']??[]),true)?'checked':''?>> <?=$label?></label><?php endforeach;?></div><?=f::error('days',$fielderrors)?></fieldset>
<label for="on-window"><?=v::e(f::text('question_timewindow'))?></label><input id="on-window" name="timewindow" maxlength="160" required value="<?=v::e($answers['timewindow']??'')?>"<?=f::attributes('timewindow',$fielderrors)?>><?=f::error('timewindow',$fielderrors)?><p class="aw-meta"><?=v::e(f::text('timewindow_hint'))?></p><p class="aw-meta">После завершения анкеты этот часовой пояс будет использоваться для расписания в вашем профиле.</p></section>
<section class="aw-panel"><h2>3. Подключение и цель</h2><?php echo f::multiple('device',$labels['device'],$answers,b::options()['device'],$fielderrors);$select('meet');?><label for="on-goal">Что особенно хочется отработать на занятиях? Необязательно.</label><textarea id="on-goal" name="goal" rows="3" maxlength="600"<?=f::attributes('goal',$fielderrors)?>><?=v::e($answers['goal']??'')?></textarea><?=f::error('goal',$fielderrors)?><p class="aw-meta">Ответы помогают организовать обучение. Заполнение анкеты и прохождение курса не означают автоматический допуск к работе.</p></section>
<div class="aw-form-actions"><?php if($record&&$record->status==='complete'):?><a class="lw-primary" href="course.php">Перейти к обучению →</a><?php endif;?><button class="<?=$record&&$record->status==='complete'?'lw-secondary':'lw-primary'?>" type="submit" name="finish" value="1"><?=$record&&$record->status==='complete'?'Сохранить изменения':'Сохранить и завершить анкету'?></button><button class="lw-secondary" type="submit" name="finish" value="0" formnovalidate>Сохранить черновик</button><p class="sa-form-state" data-aw-form-status role="status" aria-live="polite" hidden></p></div></form>
<?php v::end();
