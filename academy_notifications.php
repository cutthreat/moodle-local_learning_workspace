<?php
require_once(__DIR__.'/../../config.php');
require_once(__DIR__.'/classes/local/academy_workspace_view.php');
use local_learning_workspace\local\academy_workspace as w;
use local_learning_workspace\local\academy_workspace_view as v;
require_login();\local_learning_workspace\local\academy_access::require_viewer();
$error='';
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
 require_sesskey();
 try{
  $action=required_param('action',PARAM_ALPHA);
  if(!in_array($action,['read','readall'],true)){throw new DomainException(w::text('invalid'));}
  w::consume_form($action);
  if($action==='readall'){w::mark_all_read();}else{w::mark_read(required_param('eventkey',PARAM_RAW_TRIMMED));}
  redirect(w::url('notifications',['filter'=>optional_param('filter','all',PARAM_ALPHA)]));
 }
 catch(DomainException $ex){$error=$ex->getMessage();}
}
v::start('notifications','notifications',$error);
$events=w::feed();
$filter=optional_param('filter','all',PARAM_ALPHA)==='unread'?'unread':'all';
$unread=count(array_filter($events,static fn($event)=>!$event['read']));
?>
<p class="aw-meta">Последние 50 уведомлений: занятия, ответы тренера, результаты проверки и объявления. Прочитанные уведомления остаются в списке.</p>
<div class="aw-list-tools"><nav aria-label="Фильтр уведомлений"><a href="?filter=all" <?=$filter==='all'?'aria-current="page"':''?>>Все (<?=count($events)?>)</a><a href="?filter=unread" <?=$filter==='unread'?'aria-current="page"':''?>>Непрочитанные (<?=$unread?>)</a></nav>
<?php if($unread):?><form method="post"><?=v::fields('readall')?><input type="hidden" name="filter" value="<?=$filter?>"><button class="aw-button">Отметить все прочитанными</button></form><?php endif;?></div>
<?php
if($filter==='unread'){$events=array_filter($events,static fn($event)=>!$event['read']);}
if(!$events){echo '<section class="aw-panel">'.v::e($filter==='unread'?'Нет непрочитанных уведомлений.':w::text('empty')).'</section>';}
foreach($events as $event):?>
<article id="<?=v::e(w::notification_anchor($event['key']))?>" class="aw-panel aw-event <?=$event['read']?'':'is-unread'?>" tabindex="-1">
<span class="aw-status"><?=v::e(w::text($event['read']?'read':'unread'))?></span>
<h2><?=v::e($event['title'])?></h2><?=\local_learning_workspace\local\academy_timezone::render((int)$event['time'],'%d.%m.%Y · %H:%M','aw-meta')?><p><?=v::e($event['body'])?></p>
<?php if(!str_starts_with($event['key'],'notice.')): ?><a href="<?=v::e($event['url'])?>"><?=v::e(w::text('open'))?> →</a><?php endif; ?>
<?php if(!$event['read']):?><form method="post"><?=v::fields('read')?><input type="hidden" name="filter" value="<?=$filter?>"><input type="hidden" name="eventkey" value="<?=v::e($event['key'])?>"><button class="aw-secondary"><?=v::e(w::text('mark_read'))?></button></form><?php endif;?>
</article>
<?php endforeach;v::end();
