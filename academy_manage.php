<?php
require_once(__DIR__.'/../../config.php');
require_once(__DIR__.'/classes/local/academy_workspace_view.php');
require_once(__DIR__.'/classes/local/academy_access_denied_view.php');
require_once(__DIR__.'/classes/local/academy_staff_workflow.php');
require_once(__DIR__.'/classes/local/academy_assessment_repository.php');
require_once(__DIR__.'/classes/local/academy_review_queue.php');
use local_learning_workspace\local\academy_staff_workflow as ux;
use local_learning_workspace\local\academy_workspace as w;
use local_learning_workspace\local\academy_workspace_view as v;
require_login();
if (!w::manager()) {
 \local_learning_workspace\local\academy_access_denied_view::page(
  \local_learning_workspace\local\academy_access::is_candidate(), true);
 return;
}
w::require_manager();
$error='';$policyproposal=null;
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
 require_sesskey();
 try{
  w::consume_form(required_param('action',PARAM_ALPHA));
  switch(required_param('action',PARAM_ALPHA)){
   case 'quickstart':\local_learning_workspace\local\academy_quickstart::configure(['candidate'=>optional_param('qs_candidate',0,PARAM_BOOL),'examiner'=>optional_param('qs_examiner',0,PARAM_BOOL),'manager'=>optional_param('qs_manager',0,PARAM_BOOL)]);break;
   case 'announce':w::announce(required_param('title',PARAM_RAW_TRIMMED),required_param('body',PARAM_RAW_TRIMMED),required_param('audience',PARAM_ALPHA));break;
   case 'archive':w::archive(required_param('id',PARAM_INT));break;
   case 'restore':w::archive(required_param('id',PARAM_INT),true);break;
   case 'reply':w::reply(required_param('id',PARAM_INT),required_param('reply',PARAM_RAW_TRIMMED));break;
   case 'policy':
    $input=['cohort'=>required_param('cohort',PARAM_INT),'lesson'=>required_param('lesson',PARAM_ALPHANUM),
      'attemptlimit'=>required_param('attemptlimit',PARAM_INT),'feedbackmode'=>required_param('feedbackmode',PARAM_ALPHAEXT),
      'retakesenabled'=>required_param('retakesenabled',PARAM_INT),'policyversion'=>required_param('policyversion',PARAM_ALPHANUM),
      'confirmed'=>optional_param('confirmed',0,PARAM_BOOL)];
    $proposal=ux::policy_proposal($input);
    if($proposal['targets']>1&&!$input['confirmed']){$policyproposal=$proposal;}
    else{ux::apply_policy($input);}break;
   default:throw new DomainException(w::text('invalid'));
  }
  $returnpanel=['quickstart'=>'quickstart','policy'=>'policy','reply'=>'support','announce'=>'announcements','archive'=>'announcements','restore'=>'announcements'][required_param('action',PARAM_ALPHA)];
  if(!$policyproposal){redirect(w::url('manage',['saved'=>1,'panel'=>$returnpanel,'scope'=>optional_param('cohort',0,PARAM_INT),'test'=>optional_param('lesson','ALL',PARAM_ALPHANUM),'status'=>optional_param('status','open',PARAM_ALPHA),'notices'=>required_param('action',PARAM_ALPHA)==='announce'?'active':optional_param('notices','active',PARAM_ALPHA)]));}
 }catch(DomainException $ex){$error=$ex->getMessage();}
}
$panel=optional_param('panel','overview',PARAM_ALPHA);
if(!in_array($panel,['overview','policy','support','announcements','quickstart'],true)){$panel='overview';}
if($error!==''){$panel=['quickstart'=>'quickstart','policy'=>'policy','reply'=>'support','announce'=>'announcements','archive'=>'announcements','restore'=>'announcements'][optional_param('action','',PARAM_ALPHA)]??$panel;}
if($policyproposal){$panel='policy';}
$st=static fn($key)=>w::staff_text($key);
v::start('manage','manage',$error,optional_param('saved',0,PARAM_INT)?w::text('saved'):'');
$filter=optional_param('status','open',PARAM_ALPHA);
if(!in_array($filter,['all','open','answered'],true)){$filter='open';}
$noticefilter=optional_param('notices','active',PARAM_ALPHA)==='archive'?'archive':'active';
$openrequests=$DB->count_records('local_lworkspace_help',['status'=>'open']);
$requested=optional_param('request',0,PARAM_INT);$requestedrow=$panel==='support'&&$requested?$DB->get_record('local_lworkspace_help',['id'=>$requested]):null;
if($requestedrow&&$filter!=='all'){$filter=$requestedrow->status==='answered'?'answered':'open';}
$conditions=$filter==='all'?[]:['status'=>$filter];
$total=$DB->count_records('local_lworkspace_help',$conditions);
$page=min(max(0,optional_param('page',0,PARAM_INT)),max(0,(int)ceil($total/20)-1));
if($requestedrow){$where='(timecreated > :created OR (timecreated = :samecreated AND id > :requestid))';$params=['created'=>$requestedrow->timecreated,'samecreated'=>$requestedrow->timecreated,'requestid'=>$requested];if($filter!=='all'){$where.=' AND status=:status';$params['status']=$filter;}$page=(int)floor($DB->count_records_select('local_lworkspace_help',$where,$params)/20);}
?>
<div data-as-manage>
<nav class="as-tabs" aria-label="<?=v::e($st('manage_sections'))?>">
<?php foreach(['overview'=>'overview','policy'=>'test_rules','support'=>'support','announcements'=>'announcements','quickstart'=>'quickstart'] as $key=>$label):?><a href="<?=v::e(w::url('manage',['panel'=>$key])->out(false))?>" <?=$panel===$key?'aria-current="page"':''?>><?=v::e($st($label))?><?=$key==='support'&&$openrequests?' · '.$openrequests:''?></a><?php endforeach;?></nav>

<?php if($panel==='overview'):
$staffrows=\local_learning_workspace\local\academy_assessment_repository::reviewer_rows();
$pending=\local_learning_workspace\local\academy_review_queue::awaiting_count($staffrows)+array_sum(array_map(static fn($row)=>(int)($row->pendingdayreviews??0),$staffrows));
$groups=\local_learning_workspace\local\academy_blended::groups();
$work=\local_learning_workspace\local\academy_review_queue::work_items($staffrows);
$pending=count($work);
$meeting=\local_learning_workspace\local\academy_blended::next_session();
?>
<div class="as-stats" aria-label="<?=v::e($st('overview'))?>">
<?php foreach([['academy_review.php',$pending,'waiting'],['academy_review.php?status=all',count($staffrows),'candidates'],['academy_teaching.php#groups',count($groups),'groups'],['academy_manage.php?panel=support',$openrequests,'open_requests']] as [$url,$count,$label]):?><a class="as-stat" href="<?=v::e($url)?>"><strong><?=(int)$count?></strong><span><?=v::e($st($label))?></span></a><?php endforeach;?>
</div>
<div class="sa-operations-grid">
<section class="aw-panel as-tasks"><div class="as-heading-action"><h2><?=v::e(ux::text('attention'))?></h2><?php if($work): $firstwork=reset($work);?><a class="aw-button" href="academy_review.php?status=pending&amp;attempt=<?=(int)$firstwork->id?>">Начать проверку →</a><?php else:?><a href="academy_review.php"><?=v::e($st('open_queue'))?> →</a><?php endif;?></div>
<?php if(!$work&&!$openrequests):?><p class="as-empty-inline"><?=v::e(ux::text('no_tasks'))?></p><?php endif;?>
<?php foreach(array_slice($work,0,4) as $item):?><article class="as-task"><div><h3><?=v::e(fullname($item))?> · <?=v::e(ux::lesson_label($item->lessonid))?></h3><p><?=v::e(ux::text('waiting_since',userdate($item->timecreated,'%d.%m.%Y · %H:%M')))?> · <?=v::e(ux::waiting_time((int)$item->timecreated))?></p></div><a href="academy_review.php?status=pending&amp;attempt=<?=(int)$item->id?>"><?=v::e(ux::text('review_action'))?> →</a></article><?php endforeach;?>
<?php foreach($DB->get_records('local_lworkspace_help',['status'=>'open'],'timecreated ASC,id ASC','id,subject,timecreated',0,3) as $request):?><article class="as-task"><div><h3><?=v::e($request->subject)?></h3><p><?=v::e(ux::text('request_since',userdate($request->timecreated,'%d.%m.%Y · %H:%M')))?></p></div><a href="?panel=support&amp;request=<?=(int)$request->id?>#request-<?=(int)$request->id?>"><?=v::e(ux::text('reply_action'))?> →</a></article><?php endforeach;?></section>
<section class="aw-panel" id="teaching"><div class="as-heading-action"><h2><?=v::e(ux::text('next_session'))?></h2><a href="academy_teaching.php"><?=v::e($st('open_teaching'))?> →</a></div><?php if($meeting):?><article class="as-task"><div><h3><?=v::e($meeting->title)?></h3><p><?=v::e($groups[$meeting->groupid]->name)?> · <?=v::e(userdate($meeting->startsat,'%d.%m.%Y · %H:%M'))?></p></div><a href="academy_session.php?id=<?=(int)$meeting->id?>"><?=v::e(ux::text('open_session'))?> →</a></article><?php else:?><p class="as-empty-inline"><?=v::e(ux::text('no_upcoming'))?></p><?php endif;?></section>
</div>
<nav class="as-shortcuts" aria-label="<?=v::e(ux::text('settings'))?>"><a id="test-policy" href="?panel=policy"><?=v::e($st('test_rules'))?></a><a id="announcements" href="?panel=announcements"><?=v::e($st('announcements'))?></a><a href="academy_teaching.php?view=progress#group-progress"><?=v::e(ux::text('group_progress'))?></a></nav>

<?php elseif($panel==='quickstart'):?>
<section class="aw-panel" id="quickstart"><h2><?=v::e(\local_learning_workspace\local\academy_quickstart::text('control'))?></h2><p><?=v::e(ux::text('tour_audience'))?></p><form method="post"><?=v::fields('quickstart')?>
<?php foreach(\local_learning_workspace\local\academy_quickstart::ROLES as $qsrole): $qsenabled=(bool)\local_learning_workspace\local\academy_quickstart::tour($qsrole,'course');?><div class="as-tour-row"><div><strong id="qs-role-<?=$qsrole?>"><?=v::e(w::text($qsrole))?></strong><span><?=v::e(ux::text('tour_for_'.$qsrole))?></span></div><label class="as-toggle"><input type="checkbox" name="qs_<?=$qsrole?>" value="1" aria-labelledby="qs-role-<?=$qsrole?> qs-enabled-<?=$qsrole?>" <?=$qsenabled?'checked':''?>><span id="qs-enabled-<?=$qsrole?>"><?=v::e(ux::text('enabled'))?></span></label><button type="button" class="aw-secondary" data-as-preview="tour-<?=$qsrole?>"><?=v::e(ux::text('preview'))?><span class="lw-sr-only"> · <?=v::e(w::text($qsrole))?></span></button></div><?php endforeach;?><div class="aw-form-actions"><button><?=v::e(w::text('save'))?></button></div></form>
<?php foreach(\local_learning_workspace\local\academy_quickstart::ROLES as $qsrole):?><dialog class="as-preview" id="tour-<?=$qsrole?>" aria-labelledby="tour-title-<?=$qsrole?>"><div class="as-heading-action"><h2 id="tour-title-<?=$qsrole?>"><?=v::e(ux::text('preview'))?> · <?=v::e(w::text($qsrole))?></h2><button type="button" class="aw-secondary" data-as-close><?=v::e(ux::text('close'))?></button></div><p class="aw-meta"><?=v::e(ux::text('preview_safe'))?></p><?php foreach(\local_learning_workspace\local\academy_quickstart::preview($qsrole) as $segment=>$steps):?><h3><?=v::e(ux::text('segment_'.$segment))?> · <?=count($steps)?></h3><ol><?php foreach($steps as $step):?><li><strong><?=v::e($step['title'])?></strong><div><?=$step['body']?></div></li><?php endforeach;?></ol><?php endforeach;?></dialog><?php endforeach;?></section>

<?php elseif($panel==='policy'): require(__DIR__.'/views/attempt_policy_settings.php'); endif; ?>

<?php if($panel==='support'): ?>
<section class="aw-panel" id="support">
<div class="as-heading-action"><h2><?=v::e(w::text('support'))?></h2><span class="aw-meta"><?=v::e($st('open_requests'))?>: <?=$openrequests?></span></div>
<nav class="as-tabs" aria-label="Статус обращения"><?php foreach(['open'=>'Ожидают ответа','answered'=>'С ответом','all'=>'Все'] as $key=>$label):?><a href="<?=v::e(w::url('manage',['panel'=>'support','status'=>$key])->out(false))?>" <?=$filter===$key?'aria-current="page"':''?>><?=$label?> · <?=$DB->count_records('local_lworkspace_help',$key==='all'?[]:['status'=>$key])?></a><?php endforeach;?></nav>
<?php $requests=$DB->get_records('local_lworkspace_help',$conditions,'timecreated DESC,id DESC','*',$page*20,20);
if(!$requests):?><p class="ac-empty"><?=v::e($filter==='open'?'Нет обращений, ожидающих ответа.':($filter==='answered'?'Пока нет обращений с ответом.':w::text('no_requests')))?></p><?php endif;
foreach($requests as $r):$owner=$DB->get_record('user',['id'=>$r->userid],'id,firstname,lastname');?>
<details class="as-request" id="request-<?=(int)$r->id?>" <?=($error!==''&&optional_param('id',0,PARAM_INT)===(int)$r->id)||optional_param('request',0,PARAM_INT)===(int)$r->id?'open':''?>><summary><strong>#<?=$r->id?> · <?=v::e($r->subject)?></strong><span><?=v::e(w::text($r->status==='answered'?'answered':'pending'))?></span></summary><p class="aw-meta"><?=v::e($owner?fullname($owner):'#'.$r->userid)?> · <?=v::e(userdate($r->timecreated,'%d.%m.%Y · %H:%M'))?></p><p><?=nl2br(v::e($r->message))?></p>
<form method="post"><?=v::fields('reply')?><input type="hidden" name="status" value="<?=$filter?>"><input type="hidden" name="id" value="<?=$r->id?>"><label for="reply-<?=$r->id?>"><?=v::e(w::text('reply'))?></label><textarea id="reply-<?=$r->id?>" name="reply" maxlength="4000" required><?=v::e($error!==''&&optional_param('id',0,PARAM_INT)===(int)$r->id?optional_param('reply','',PARAM_RAW):$r->reply)?></textarea><div class="aw-form-actions"><button><?=$r->reply===''?v::e(w::text('answer')):'Сохранить уточнение'?></button></div></form></details>
<?php endforeach; if($total>20):?><nav class="aw-list-tools" aria-label="Страницы обращений"><?php if($page>0):?><a href="<?=v::e(w::url('manage',['panel'=>'support','status'=>$filter,'page'=>$page-1])->out(false))?>">← Предыдущие</a><?php endif;?><span>Страница <?=$page+1?> из <?=(int)ceil($total/20)?></span><?php if(($page+1)*20<$total):?><a href="<?=v::e(w::url('manage',['panel'=>'support','status'=>$filter,'page'=>$page+1])->out(false))?>">Следующие →</a><?php endif;?></nav><?php endif;?></section>

<?php elseif($panel==='announcements'):?>
<section class="aw-panel" id="announcements"><h2><?=v::e(w::text('announcements'))?></h2>
<details class="as-form-block" <?=$error!==''&&optional_param('action','',PARAM_ALPHA)==='announce'?'open':''?>><summary><?=v::e($st('new_announcement'))?></summary><form method="post"><?=v::fields('announce')?>
<label for="aw-title"><?=v::e(w::text('notice_title'))?></label><input id="aw-title" name="title" maxlength="160" required value="<?=v::e($error!==''?optional_param('title','',PARAM_RAW):'')?>">
<label for="aw-body"><?=v::e(w::text('notice_body'))?></label><textarea id="aw-body" name="body" maxlength="4000" required><?=v::e($error!==''?optional_param('body','',PARAM_RAW):'')?></textarea>
<label for="aw-audience"><?=v::e(w::text('audience'))?></label><select id="aw-audience" name="audience"><?php foreach(['all','candidate','examiner','manager'] as $role):?><option value="<?=$role?>" <?=$error!==''&&optional_param('audience','all',PARAM_ALPHA)===$role?'selected':''?>><?=v::e(w::text($role))?></option><?php endforeach;?></select>
<div class="aw-form-actions"><button><?=v::e(w::text('publish'))?></button></div></form></details>
<nav class="as-tabs" aria-label="Состояние объявлений"><a href="?panel=announcements&amp;notices=active" <?=$noticefilter==='active'?'aria-current="page"':''?>>Опубликованные</a><a href="?panel=announcements&amp;notices=archive" <?=$noticefilter==='archive'?'aria-current="page"':''?>>Архив</a></nav>
<?php $noticeconditions=['active'=>(int)($noticefilter==='active')];$noticetotal=$DB->count_records('local_lworkspace_notice',$noticeconditions);$noticepage=min(max(0,optional_param('noticepage',0,PARAM_INT)),max(0,(int)ceil($noticetotal/20)-1));$notices=$DB->get_records('local_lworkspace_notice',$noticeconditions,'timecreated DESC,id DESC','*',$noticepage*20,20);
if(!$notices):?><p class="ac-empty"><?=v::e($st('no_announcements'))?></p><?php endif;
foreach($notices as $r):?><article class="as-announcement"><h3><?=v::e($r->title)?></h3><p class="aw-meta"><?=v::e(w::text($r->audience))?> · <?=v::e(userdate($r->timecreated,'%d.%m.%Y · %H:%M'))?></p><p><?=nl2br(v::e($r->body))?></p><form method="post"><?=v::fields($r->active?'archive':'restore')?><input type="hidden" name="notices" value="<?=$noticefilter?>"><input type="hidden" name="id" value="<?=$r->id?>"><button class="aw-secondary"><?=$r->active?v::e(w::text('archive')):'Вернуть в опубликованные'?></button></form></article><?php endforeach;?>
<?php if($noticetotal>20):?><nav class="aw-list-tools" aria-label="Страницы объявлений"><?php if($noticepage>0):?><a href="<?=v::e(w::url('manage',['panel'=>'announcements','notices'=>$noticefilter,'noticepage'=>$noticepage-1])->out(false))?>">← Предыдущие</a><?php endif;?><span>Страница <?=$noticepage+1?> из <?=(int)ceil($noticetotal/20)?></span><?php if(($noticepage+1)*20<$noticetotal):?><a href="<?=v::e(w::url('manage',['panel'=>'announcements','notices'=>$noticefilter,'noticepage'=>$noticepage+1])->out(false))?>">Следующие →</a><?php endif;?></nav><?php endif;?></section>
<?php endif; ?>
</div>
<?php v::end();
