<?php
require_once(__DIR__.'/../../config.php');
require_once(__DIR__.'/classes/local/academy_workspace_view.php');
use local_learning_workspace\local\academy_workspace as w;
use local_learning_workspace\local\academy_workspace_view as v;
require_login();
\local_learning_workspace\local\academy_access::require_viewer();
$error='';$data=w::profile();
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
 require_sesskey();
 $data=array_merge($data,[
 'firstname'=>required_param('firstname',PARAM_RAW_TRIMMED),'lastname'=>required_param('lastname',PARAM_RAW_TRIMMED),
 'timezone'=>required_param('timezone',PARAM_TEXT)]);
 try{w::consume_form('profile');w::save_profile($data);redirect(w::url('profile',['saved'=>1]));}
 catch(DomainException $ex){$error=$ex->getMessage();}
}
$savednotice=($_SERVER['REQUEST_METHOD']??'GET')==='GET'&&$error===''&&optional_param('saved',0,PARAM_INT);
v::start('settings','profile',$error,$savednotice?w::text('profile_saved'):'');
?>
<div class="sa-profile-layout">
<section class="aw-panel sa-profile-editor"><div class="sa-profile-identity"><span class="aw-avatar" aria-hidden="true"><?=v::e(core_text::strtoupper(core_text::substr($data['firstname'],0,1).core_text::substr($data['lastname'],0,1)))?></span><div><h2><?=v::e(fullname($USER))?></h2><p><?=v::e(w::text($data['role']))?></p></div></div>
<form method="post" action="<?=v::e(w::url('profile')->out(false))?>" data-aw-form="profile" data-aw-unsaved="<?=$error!==''?'true':'false'?>"><?=v::fields('profile')?>
<fieldset><legend>Личные данные</legend>
<div class="sa-form-grid"><div><label for="aw-first"><?=v::e(w::text('firstname'))?></label><input id="aw-first" name="firstname" value="<?=v::e($data['firstname'])?>" maxlength="100" required autocomplete="given-name"></div>
<div><label for="aw-last"><?=v::e(w::text('lastname'))?></label><input id="aw-last" name="lastname" value="<?=v::e($data['lastname'])?>" maxlength="100" required autocomplete="family-name"></div></div>
</fieldset><fieldset><legend>Время обучения</legend>
<label for="aw-timezone"><?=v::e(w::text('timezone'))?></label><select id="aw-timezone" name="timezone" aria-describedby="aw-timezone-hint">
<optgroup label="<?=v::e(w::text('system_timezone'))?>"><option value="99" <?=$data['timezone']==='99'?'selected':''?>><?=v::e(\local_learning_workspace\local\academy_timezone::label(null, \core_date::get_server_timezone_object()))?></option></optgroup>
<optgroup label="Выбрать часовой пояс">
<?php foreach(\core_date::get_list_of_timezones() as $key=>$label):?><option value="<?=v::e($key)?>" <?=$data['timezone']===(string)$key?'selected':''?>><?=v::e($label)?></option><?php endforeach;?>
</optgroup>
</select>
<p class="aw-meta" id="aw-timezone-hint"><?=v::e(w::text('timezone_hint', \local_learning_workspace\local\academy_timezone::label()))?></p>
</fieldset>
<div class="aw-form-actions"><button><?=v::e(w::text('save'))?></button><p class="sa-form-state" data-aw-form-status role="status" aria-live="polite" hidden></p></div>
</form></section>
<aside class="sa-profile-aside"><section class="aw-panel"><h2>Учебный аккаунт</h2><dl class="sa-account-data"><div><dt><?=v::e(w::text('email'))?></dt><dd id="aw-email"><?=v::e($data['email'])?></dd></div><div><dt><?=v::e(w::text('language'))?></dt><dd>Русский</dd></div></dl></section>
<section class="aw-panel sa-account-help"><h2>Помощь с аккаунтом</h2><p>Чтобы изменить адрес входа, роль или восстановить доступ, напишите команде академии.</p><a class="lw-secondary" href="academy_help.php#support">Обратиться в поддержку</a><a class="sa-quiet-link" href="academy_help.php#requests">Мои обращения <span aria-hidden="true">→</span></a></section></aside>
</div>
<?php v::end();
