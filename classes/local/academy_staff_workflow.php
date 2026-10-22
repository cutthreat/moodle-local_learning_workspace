<?php
namespace local_learning_workspace\local;
defined('MOODLE_INTERNAL') || die();

/** Read models and guarded policy edits for the existing staff workspaces. */
final class academy_staff_workflow {
    public const LESSONS = ['L1','L2','L3','L4','L5','L6','L7','FINAL'];
    public static function text(string $key, $a=null): string {
        return get_string_manager()->get_string('ux_'.$key,'local_learning_workspace',$a,'ru');
    }

    /** Same authorized queue, freshly read after a successful immutable review. */
    public static function next_work(array $context, int $exclude=0): ?object {
        global $DB,$USER;
        $rows=academy_assessment_repository::reviewer_rows();
        $q=trim((string)($context['q']??''));
        if ($q!=='') {
            $ids=array_map(static fn($r)=>(int)$r->userid,$rows);
            $users=$ids?$DB->get_records_list('user','id',$ids,'','id,username'):[];
            $rows=array_filter($rows,static fn($r)=>mb_stripos($r->firstname.' '.$r->lastname.' '.
                $r->userid.' '.($users[$r->userid]->username??''),$q)!==false);
        }
        foreach(academy_review_queue::work_items($rows) as $item) {
            if((int)$item->id===$exclude||(int)$item->userid===(int)$USER->id){continue;}
            if(($context['status']??'')==='day_pending'&&$item->kind!=='day'){continue;}
            if(($context['status']??'')==='awaiting_review'&&$item->kind!=='final'){continue;}
            return $item;
        }
        return null;
    }

    /** Resolve an audience rule without impersonating a learner or changing memberships. */
    public static function policy_snapshot(int $scope, string $lesson): array {
        global $DB;
        academy_workspace::require_manager();
        if($scope<0||($scope&&!$DB->record_exists('cohort',['id'=>$scope]))||
                !in_array($lesson,array_merge(['ALL'],self::LESSONS),true)){
            throw new \DomainException(self::text('invalid_scope'));
        }
        $records=$DB->get_records('local_lworkspace_acpolicy',null,'id ASC');
        $states=[];
        foreach($lesson==='ALL'?self::LESSONS:[$lesson] as $target){
            $selected=null;$priority=-1;
            foreach($records as $r){
                if(!in_array((int)$r->scopeid,[0,$scope],true)||!in_array($r->lessonid,['ALL',$target],true)){continue;}
                $rank=($r->lessonid!=='ALL'?2:0)+((int)$r->scopeid>0?1:0);
                if($rank>=$priority){$selected=$r;$priority=$rank;}
            }
            $states[$target]=['limit'=>(int)($selected->attemptlimit??1),
                'feedback'=>(string)($selected->feedbackmode??'after_review'),
                'retakesenabled'=>(int)($selected->retakesenabled??0)];
        }
        $values=array_values($states);$first=$values[0];
        $uniform=[];foreach($first as $key=>$value){$uniform[$key]=count(array_unique(array_column($values,$key)))===1?$value:null;}
        $scopes=[$scope];
        if($scope===0&&$lesson==='ALL'){foreach($records as $r){if((int)$r->scopeid===0||$DB->record_exists('cohort',['id'=>$r->scopeid])){$scopes[]=(int)$r->scopeid;}}}
        $scopes=array_values(array_unique($scopes));
        $audience=$scope?(string)$DB->get_field('cohort','name',['id'=>$scope]):self::text('all_learners');
        return ['scope'=>$scope,'lesson'=>$lesson,'states'=>$states,'values'=>$uniform,'audience'=>$audience,
            'scopes'=>$scopes,'targets'=>count($scopes)*count($states),
            'version'=>hash('sha256',json_encode(array_values($records),JSON_THROW_ON_ERROR))];
    }

    public static function policy_proposal(array $input): array {
        $snapshot=self::policy_snapshot((int)($input['cohort']??-1),(string)($input['lesson']??''));
        $limit=filter_var($input['attemptlimit']??null,FILTER_VALIDATE_INT);
        $retakes=(string)($input['retakesenabled']??'');$feedback=(string)($input['feedbackmode']??'');
        if($limit===false||$limit<0||$limit>20||!in_array($retakes,['0','1'],true)||
                !in_array($feedback,['immediate','after_review'],true)){
            throw new \DomainException(self::text('choose_rules'));
        }
        if(!hash_equals($snapshot['version'],(string)($input['policyversion']??''))){
            throw new \DomainException(self::text('policy_stale'));
        }
        return $snapshot+['limit'=>$retakes==='0'?1:$limit,'retakes'=>(int)$retakes,'feedback'=>$feedback];
    }

    /** One lock binds fresh read, concurrency check and all append-only updates. */
    public static function apply_policy(array $input): void {
        global $DB;
        academy_workspace::require_manager();
        $lock=\core\lock\lock_config::get_lock_factory('local_learning_workspace')->get_lock('staff-policy-edit',10);
        if(!$lock){throw new \DomainException(self::text('policy_busy'));}
        try{
            $p=self::policy_proposal($input);
            if($p['targets']>1&&empty($input['confirmed'])){throw new \DomainException(self::text('confirm_required'));}
            $tx=$DB->start_delegated_transaction();
            foreach($p['scopes'] as $scope){foreach(array_keys($p['states']) as $lesson){
                academy_attempt_policy::configure($scope,$lesson,$p['limit'],$p['feedback'],(bool)$p['retakes']);
            }}
            // Preserve the established all-tests fallback as well as individual overrides.
            if($p['lesson']==='ALL'){foreach($p['scopes'] as $scope){
                academy_attempt_policy::configure($scope,'ALL',$p['limit'],$p['feedback'],(bool)$p['retakes']);
            }}
            $tx->allow_commit();
        }finally{$lock->release();}
    }

    public static function lesson_label(string $lesson): string {
        return $lesson==='ALL'?self::text('all_tests'):($lesson==='FINAL'?self::text('final_exam'):self::text('day',(int)substr($lesson,1)));
    }
    public static function waiting_time(int $created): string {
        $seconds=max(0,time()-$created);
        return $seconds>=86400?self::text('wait_days',(int)floor($seconds/86400)):
            ($seconds>=3600?self::text('wait_hours',(int)floor($seconds/3600)):self::text('wait_minutes',(int)floor($seconds/60)));
    }

    /** A reason and a useful next action; never equates an auto-score with practical acceptance. */
    public static function progress(object $row): array {
        if(!$row->enrolled){return ['state'=>'inactive','action'=>'history','attempt'=>0];}
        if($row->pendingdayreviews>0||$row->finalstatus==='awaiting_review'){
            return ['state'=>'waiting_review','action'=>'review','attempt'=>(int)$row->latestattemptid];
        }
        if(in_array($row->finalstatus,['rework','not_ready'],true)){
            return ['state'=>'needs_feedback','action'=>'history','attempt'=>0];
        }
        if($row->finalstatus==='ready'){return ['state'=>'exam_reviewed','action'=>'history','attempt'=>0];}
        if($row->submitteddaycount===0){return ['state'=>'not_started','action'=>'history','attempt'=>0];}
        if($row->passeddaycount<$row->submitteddaycount||$row->practicacceptedcount<$row->submitteddaycount){
            return ['state'=>'practice_gap','action'=>'history','attempt'=>0];
        }
        return ['state'=>$row->submitteddaycount<7?'learning':'final_next','action'=>'history','attempt'=>0];
    }
}
