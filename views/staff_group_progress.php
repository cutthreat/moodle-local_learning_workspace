<?php
use local_learning_workspace\local\academy_blended as b;
use local_learning_workspace\local\academy_workspace_view as v;
use local_learning_workspace\local\academy_staff_workflow as ux;
use local_learning_workspace\local\academy_assessment_repository as assessments;
use local_learning_workspace\local\academy_review_queue as queue;
defined('MOODLE_INTERNAL') || die();
if(!$groups){echo '<p class="ac-empty">'.v::e(ux::text('no_groups')).'</p>';return;}
$groupid=optional_param('groupid',(int)array_key_first($groups),PARAM_INT);
if(!isset($groups[$groupid])){echo '<p class="lw-alert lw-error" role="alert">'.v::e(ux::text('group_denied')).'</p>';return;}
b::require_group($groupid,true);
$members=b::members($groupid);$search=trim(optional_param('q','',PARAM_TEXT));
$rows=array_values(array_filter(assessments::reviewer_rows(),static fn($r)=>isset($members[$r->userid])));
$active=count(array_filter($rows,static fn($r)=>$r->enrolled));
$rows=array_values(array_filter($rows,static fn($r)=>$search===''||mb_stripos($r->firstname.' '.$r->lastname.' '.$members[$r->userid]->username,$search)!==false));
$waiting=[];foreach(queue::work_items($rows) as $item){if(!isset($waiting[$item->userid])){$waiting[$item->userid]=$item;}}
usort($rows,static fn($a,$c)=>strcmp($a->lastname.' '.$a->firstname,$c->lastname.' '.$c->firstname));
$paging=queue::page($rows,optional_param('page',1,PARAM_INT));
?>
<section class="aw-panel" id="group-progress"><h2><?=v::e(ux::text('group_progress'))?></h2>
<form method="get" class="as-scope"><input type="hidden" name="view" value="progress"><div><label for="progress-group"><?=v::e(ux::text('group'))?></label><select name="groupid" id="progress-group"><?php foreach($groups as $group):?><option value="<?=(int)$group->id?>" <?=$groupid===(int)$group->id?'selected':''?>><?=v::e($group->name)?></option><?php endforeach;?></select></div><div><label for="progress-search"><?=v::e(ux::text('find_candidate'))?></label><input name="q" id="progress-search" type="search" value="<?=v::e($search)?>" maxlength="100"></div><button class="aw-secondary"><?=v::e(ux::text('show'))?></button></form>
<p class="aw-meta" role="status"><?=v::e(ux::text('active_members',$active))?> · <?=v::e(ux::text('found_members',$paging['total']))?></p>
<?php if(!$rows):?><p class="ac-empty"><?=v::e(ux::text('no_members'))?></p><?php else:?>
<div class="as-progress-scroll"><table class="as-progress-table"><caption class="lw-sr-only"><?=v::e($groups[$groupid]->name)?></caption><thead><tr><th scope="col"><?=v::e(ux::text('candidate'))?></th><th scope="col"><?=v::e(ux::text('tests_submitted'))?></th><th scope="col"><?=v::e(ux::text('objective_passed'))?></th><th scope="col"><?=v::e(ux::text('practice_accepted'))?></th><th scope="col"><?=v::e(ux::text('stage_next'))?></th></tr></thead><tbody>
<?php foreach($paging['rows'] as $person):$stage=ux::progress($person);?><tr><td data-label="<?=v::e(ux::text('candidate'))?>"><strong><?=v::e(fullname($person))?></strong><small><?=v::e($members[$person->userid]->username)?></small></td><td data-label="<?=v::e(ux::text('tests_submitted'))?>"><?=$person->submitteddaycount?>/7</td><td data-label="<?=v::e(ux::text('objective_passed'))?>"><?=$person->passeddaycount?>/7</td><td data-label="<?=v::e(ux::text('practice_accepted'))?>"><?=$person->practicacceptedcount?>/7</td><td data-label="<?=v::e(ux::text('stage_next'))?>"><span><?=v::e(ux::text('progress_'.$stage['state']))?></span><?php if(isset($waiting[$person->userid])&&$person->enrolled):?><a href="academy_review.php?status=pending&amp;attempt=<?=(int)$waiting[$person->userid]->id?>"><?=v::e(ux::text('review_action'))?> →</a><?php else:?><a href="academy_candidate.php?userid=<?=(int)$person->userid?>"><?=v::e(ux::text('open_history'))?> →</a><?php endif;?></td></tr><?php endforeach;?></tbody></table></div>
<?php if($paging['pagecount']>1):?><nav class="aw-list-tools" aria-label="<?=v::e(ux::text('pages'))?>"><?php foreach(['previous'=>-1,'next'=>1] as $label=>$delta):$page=$paging['page']+$delta;if($page<1||$page>$paging['pagecount']){continue;}?><a href="<?=v::e((new moodle_url('/local/learning_workspace/academy_teaching.php',['view'=>'progress','groupid'=>$groupid,'q'=>$search,'page'=>$page]))->out(false))?>"><?=v::e(ux::text($label))?></a><?php endforeach;?><span><?=$paging['page']?>/<?=$paging['pagecount']?></span></nav><?php endif;endif;?>
</section>
