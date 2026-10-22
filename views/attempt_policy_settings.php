<?php
use local_learning_workspace\local\academy_workspace_view as v;
use local_learning_workspace\local\academy_staff_workflow as ux;
defined('MOODLE_INTERNAL') || die();
$scope=optional_param('cohort',optional_param('scope',0,PARAM_INT),PARAM_INT);
$test=optional_param('lesson',optional_param('test','ALL',PARAM_ALPHANUM),PARAM_ALPHANUM);
try{$snapshot=ux::policy_snapshot($scope,$test);}catch(DomainException $ex){
    if($error===''){echo '<p class="lw-alert lw-error" role="alert">'.v::e($ex->getMessage()).'</p>';}
    echo '<p><a href="?panel=policy">'.v::e(ux::text('show_rules')).'</a></p>';return;
}
$values=$snapshot['values'];
if($error!==''){
    $values=['limit'=>optional_param('attemptlimit',$values['limit']??1,PARAM_INT),
        'retakesenabled'=>optional_param('retakesenabled',$values['retakesenabled']??0,PARAM_INT),
        'feedback'=>optional_param('feedbackmode',$values['feedback']??'after_review',PARAM_ALPHAEXT)];
}
?>
<section class="aw-panel" id="test-policy"><h2><?=v::e(ux::text('assessment_rules'))?></h2>
<form method="get" class="as-scope" data-as-scope><input type="hidden" name="panel" value="policy">
<div><label for="policy-cohort"><?=v::e(ux::text('audience'))?></label><select name="scope" id="policy-cohort"><option value="0"><?=v::e(ux::text('all_learners'))?></option><?php foreach($DB->get_records('cohort',null,'name ASC') as $cohort):?><option value="<?=(int)$cohort->id?>" <?=$scope===(int)$cohort->id?'selected':''?>><?=v::e($cohort->name)?></option><?php endforeach;?></select></div>
<div><label for="policy-lesson"><?=v::e(ux::text('assessment'))?></label><select name="test" id="policy-lesson"><?php foreach(array_merge(['ALL'],ux::LESSONS) as $lesson):?><option value="<?=$lesson?>" <?=$test===$lesson?'selected':''?>><?=v::e(ux::lesson_label($lesson))?></option><?php endforeach;?></select></div><button type="submit" class="aw-secondary"><?=v::e(ux::text('show_rules'))?></button></form>
<div class="as-scope-title"><strong><?=v::e($snapshot['audience'])?> · <?=v::e(ux::lesson_label($test))?></strong><span><?=v::e(ux::text('current_rules'))?></span></div>
<div class="as-policy-grid"><?php foreach($snapshot['states'] as $lesson=>$state):?><div class="as-policy-item"><strong><?=v::e(ux::lesson_label($lesson))?></strong><span><?=v::e(ux::text($state['retakesenabled']?'retakes_on':'retakes_off'))?></span><small><?=v::e($state['retakesenabled']?ux::text('attempts',$state['limit']?:ux::text('unlimited')):ux::text('one_submission'))?></small></div><?php endforeach;?></div>
<?php if($policyproposal):?>
<section class="as-confirm" aria-labelledby="policy-confirm-title"><h3 id="policy-confirm-title" tabindex="-1" data-as-confirm><?=v::e(ux::text('confirm_rules'))?></h3><dl><dt><?=v::e(ux::text('audience'))?></dt><dd><?=v::e($policyproposal['audience'])?></dd><dt><?=v::e(ux::text('assessment'))?></dt><dd><?=v::e(ux::lesson_label($policyproposal['lesson']))?></dd><dt><?=v::e(ux::text('new_rules'))?></dt><dd><?=v::e(ux::text($policyproposal['retakes']?'retakes_on':'retakes_off'))?> · <?=v::e(ux::text('attempts',$policyproposal['limit']?:ux::text('unlimited')))?> · <?=v::e(ux::text($policyproposal['feedback']==='immediate'?'feedback_now':'feedback_review'))?></dd></dl>
<?php if($scope===0&&$test==='ALL'):?><p><?=v::e(ux::text('replaces_exceptions'))?></p><?php endif;?><p><?=v::e(ux::text('practice_unchanged'))?></p>
<form method="post"><?=v::fields('policy')?><?php foreach(['cohort'=>$scope,'lesson'=>$test,'attemptlimit'=>$policyproposal['limit'],'retakesenabled'=>$policyproposal['retakes'],'feedbackmode'=>$policyproposal['feedback'],'policyversion'=>$policyproposal['version'],'confirmed'=>1] as $key=>$value):?><input type="hidden" name="<?=$key?>" value="<?=v::e($value)?>"><?php endforeach;?><div class="aw-form-actions"><button><?=v::e(ux::text('apply_confirmed'))?></button><a class="lw-secondary" href="?panel=policy&amp;scope=<?=$scope?>&amp;test=<?=$test?>"><?=v::e(ux::text('cancel'))?></a></div></form></section>
<?php else:?>
<details class="as-form-block" <?=$error!==''?'open':''?>><summary><?=v::e(ux::text('edit_rules'))?></summary>
<form method="post" data-as-policy><?=v::fields('policy')?><input type="hidden" name="cohort" value="<?=$scope?>"><input type="hidden" name="lesson" value="<?=$test?>"><input type="hidden" name="policyversion" value="<?=v::e($snapshot['version'])?>">
<div class="as-form-grid"><div><label for="policy-retakes"><?=v::e(ux::text('retakes'))?></label><select name="retakesenabled" id="policy-retakes" required><?php if($values['retakesenabled']===null):?><option value=""><?=v::e(ux::text('mixed_choose'))?></option><?php endif;?><?php foreach([0=>'retakes_off',1=>'retakes_on'] as $value=>$key):?><option value="<?=$value?>" <?=$values['retakesenabled']===$value?'selected':''?>><?=v::e(ux::text($key))?></option><?php endforeach;?></select></div>
<div data-as-limit><label for="policy-limit"><?=v::e(ux::text('submission_count'))?></label><select name="attemptlimit" id="policy-limit" required><?php if($values['limit']===null):?><option value=""><?=v::e(ux::text('mixed_choose'))?></option><?php endif;?><?php $limits=array_unique(array_merge([1,2,3,5,10,20,0],$values['limit']!==null?[$values['limit']]:[]));foreach($limits as $value):?><option value="<?=$value?>" <?=$values['limit']===$value?'selected':''?>><?=v::e($value?ux::text('attempts',$value):ux::text('unlimited'))?></option><?php endforeach;?></select></div>
<div><label for="policy-feedback"><?=v::e(ux::text('feedback_timing'))?></label><select name="feedbackmode" id="policy-feedback" required><?php if($values['feedback']===null):?><option value=""><?=v::e(ux::text('mixed_choose'))?></option><?php endif;?><?php foreach(['after_review'=>'feedback_review','immediate'=>'feedback_now'] as $value=>$key):?><option value="<?=$value?>" <?=$values['feedback']===$value?'selected':''?>><?=v::e(ux::text($key))?></option><?php endforeach;?></select></div></div>
<p class="aw-meta"><?=v::e(ux::text('practice_unchanged'))?></p><details class="as-review-help"><summary><?=v::e(ux::text('rule_details'))?></summary><p><?=v::e(ux::text('rule_effect'))?></p></details><div class="aw-form-actions"><button><?=v::e(ux::text($snapshot['targets']>1?'review_changes':'save_rules'))?></button></div></form></details>
<?php endif;?>
<details class="lw-details"><summary><?=v::e(ux::text('policy_history'))?></summary><?php $policies=$DB->get_records('local_lworkspace_acpolicy',null,'id DESC','*',0,30);if(!$policies):?><p><?=v::e(ux::text('no_changes'))?></p><?php else:?><ul><?php foreach($policies as $policy):?><li><?=v::e(userdate($policy->timecreated,'%d.%m.%Y %H:%M'))?> · <?=v::e(ux::lesson_label($policy->lessonid))?> · <?=v::e($policy->scopeid?($DB->get_field('cohort','name',['id'=>$policy->scopeid])?:ux::text('past_audience')):ux::text('all_learners'))?> · <?=v::e(ux::text($policy->retakesenabled?'retakes_on':'retakes_off'))?></li><?php endforeach;?></ul><?php endif;?></details>
</section>
