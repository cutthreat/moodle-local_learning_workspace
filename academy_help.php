<?php
require_once(__DIR__.'/../../config.php');
require_once(__DIR__.'/classes/local/academy_workspace_view.php');
use local_learning_workspace\local\academy_workspace as w;
use local_learning_workspace\local\academy_workspace_view as v;
$signed=isloggedin()&&!isguestuser();
if($signed){\local_learning_workspace\local\academy_access::require_viewer();}
$error='';$subject='';$message='';
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
 require_login();\local_learning_workspace\local\academy_access::require_viewer();require_sesskey();
 $subject=required_param('subject',PARAM_RAW_TRIMMED);$message=required_param('message',PARAM_RAW_TRIMMED);
 try{w::consume_form('help');$requestid=w::request_help($subject,$message);w::remember_help_sent($requestid);redirect(w::url('help',['sent'=>$requestid,'request'=>$requestid]));}
 catch(DomainException $ex){$error=$ex->getMessage();}
}
$sentrequest=max(0,optional_param('sent',0,PARAM_INT));
$sentnotice=($_SERVER['REQUEST_METHOD']??'GET')==='GET'&&$signed&&$error===''&&w::consume_help_sent($sentrequest);
v::start('help','help',$error,$sentnotice?w::text('help_sent',$sentrequest):'');
?>
<section class="aw-panel" id="role-start"><h2>Как работать в Learning Workspace</h2>
<?php if($signed):?><p><a class="lw-primary" href="course.php?quickstart=1"><?=v::e(\local_learning_workspace\local\academy_quickstart::text('restart'))?></a></p><?php endif;?>
<?php $helprole=$signed?w::role():'candidate'; ?>
<details <?=$helprole==='candidate'?'open':''?>><summary>Учащемуся · пройти обучение</summary><ol>
<li><a href="course.php">Откройте программу</a> и выберите урок.</li>
<li>Изучите материал и пример. Свободная практика — черновик для самопроверки, не контрольная отправка.</li>
<li>Пройдите контрольный тест дня и отправьте ответы один раз.</li>
<li>В <a href="academy.php">«Моих результатах»</a> посмотрите решение и замечания тренера. Если повторы выключены, разберите ошибки с тренером без новой отправки.</li>
<li>После подготовки откройте <a href="academy.php?view=final">итоговый экзамен</a>.</li></ol></details>
<?php if($signed&&w::can_review()): ?><details <?=$helprole==='examiner'?'open':''?>><summary>Тренеру · проверить работу</summary><ol>
<li><a href="academy_review.php">Откройте очередь</a>. Выберите дневную практику или итоговый экзамен конкретного кандидата.</li>
<li>Сверьте день, номер попытки и дату отправки; прочитайте ответ рядом с критериями.</li>
<li>Оцените критерии. Для сниженной оценки выделите точную цитату в ответе и опишите исправление.</li>
<li>Сохраните черновик или отправьте окончательное решение. Только сообщение «Решение сохранено» подтверждает отправку обратной связи ученику.</li>
<li>Разберите ошибки с учеником. Разрешение на новую попытку действует только при включённых пересдачах; оно не обходит запрет управляющего.</li></ol></details><?php endif; ?>
<?php if($signed&&w::manager()): ?><details open><summary>Управляющему · посмотреть результаты группы</summary><ol>
<li><a href="academy_review.php?status=all">Откройте список кандидатов</a>. Поиск работает по имени, логину и ID; статус применяется вместе с поиском.</li>
<li>Откройте «Вся история» у кандидата: текущие результаты и замечания видны отдельно от старых попыток.</li>
<li>Сверьте даты отправки и проверки. «Ожидает проверки» означает, что тренер ещё не сохранил решение.</li>
<li>Используйте <a href="academy_manage.php">«Управление»</a> для правил попыток, материалов встреч, объявлений и ответов на обращения.</li></ol></details><?php endif; ?>
</section>
<section class="aw-panel" id="faq"><h2><?=v::e(w::text('faq'))?></h2>
<?php foreach(['save','review','notices'] as $topic):?><details><summary><?=v::e(w::text('faq_'.$topic))?></summary><p><?=v::e(w::text('faq_'.$topic.'_body'))?></p></details><?php endforeach;?>
</section>
<section class="aw-panel" id="glossary"><h2><?=v::e(w::text('glossary'))?></h2><dl>
<?php foreach(['FRT'=>'frt','ART'=>'art','Таймфиллер'=>'timefiller','Энгейджмент'=>'engagement','Персональная сессия'=>'session'] as $term=>$key):?><dt><?=v::e($term)?></dt><dd><?=v::e(w::text('terms_'.$key))?></dd><?php endforeach;?>
</dl><p><?=v::e(w::text('terms_notice'))?></p></section>
<section class="aw-panel" id="support"><h2><?=v::e(w::text('support'))?></h2>
<?php if($signed):?><form method="post" action="<?=v::e(w::url('help')->out(false))?>" data-aw-form="help" data-aw-unsaved="<?=$error!==''?'true':'false'?>"><?=v::fields('help')?><label for="aw-subject"><?=v::e(w::text('subject'))?></label><input name="subject" id="aw-subject" maxlength="160" value="<?=v::e($subject)?>" required><label for="aw-message"><?=v::e(w::text('message'))?></label><textarea name="message" id="aw-message" maxlength="4000" required aria-describedby="aw-help-hint"><?=v::e($message)?></textarea><small id="aw-help-hint"><?=v::e(w::text('help_hint'))?></small><div class="aw-form-actions"><button><?=v::e(w::text('send'))?></button><p class="sa-form-state" data-aw-form-status role="status" aria-live="polite" hidden></p></div></form>
<?php else:?><a href="academy_profile.php"><?=v::e(w::text('login'))?></a><?php endif;?>
</section>
<?php if($signed):?><section class="aw-panel" id="requests"><h2><?=v::e(w::text('my_requests'))?></h2>
<?php $conditions=['userid'=>(int)$USER->id];$total=$DB->count_records('local_lworkspace_help',$conditions);
$requestpage=min(max(0,optional_param('requestpage',0,PARAM_INT)),max(0,(int)ceil($total/20)-1));
$requested=optional_param('request',0,PARAM_INT);
if($requested&&($target=$DB->get_record('local_lworkspace_help',['id'=>$requested,'userid'=>(int)$USER->id]))){
 $newer=$DB->count_records_select('local_lworkspace_help','userid=:owner AND (timecreated>:created OR (timecreated=:same AND id>:target))',
 ['owner'=>(int)$USER->id,'created'=>$target->timecreated,'same'=>$target->timecreated,'target'=>$target->id]);
 $requestpage=(int)floor($newer/20);
}
$requests=$DB->get_records('local_lworkspace_help',$conditions,'timecreated DESC,id DESC','*',$requestpage*20,20);
if(!$requests){echo '<p>'.v::e(w::text('no_requests')).'</p>';}
foreach($requests as $r):?><article id="request-<?=$r->id?>"><h3>#<?=$r->id?> · <?=v::e($r->subject)?></h3><p class="aw-meta"><?=v::e(w::text($r->status==='answered'?'answered':'pending'))?> · <?=v::e(userdate($r->timecreated,'%d.%m.%Y · %H:%M'))?></p><p><?=nl2br(v::e($r->message))?></p><?php if($r->reply!==''):?><strong><?=v::e(w::text('reply'))?></strong><p><?=nl2br(v::e($r->reply))?></p><?php endif;?></article><?php endforeach;?></section><?php endif;?>
<?php if($signed&&$total>20):?><nav class="aw-list-tools" aria-label="Страницы моих обращений"><?php if($requestpage>0):?><a href="?requestpage=<?=$requestpage-1?>#requests">← Предыдущие</a><?php endif;?><span>Страница <?=$requestpage+1?> из <?=(int)ceil($total/20)?></span><?php if(($requestpage+1)*20<$total):?><a href="?requestpage=<?=$requestpage+1?>#requests">Следующие →</a><?php endif;?></nav><?php endif;?>
<?php v::end();
