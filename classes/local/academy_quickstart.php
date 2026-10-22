<?php
namespace local_learning_workspace\local;
defined('MOODLE_INTERNAL') || die();
require_once(__DIR__.'/academy_workspace.php');
require_once(__DIR__.'/academy_blended.php');

/** Native Moodle User Tours with explicit consent and account-bound lifecycle. Never grades work. */
final class academy_quickstart {
    public const VERSION = 1;
    public const ASSETS = '20261015-qs4';
    public const ROLES = ['candidate', 'examiner', 'manager'];
    public static function text(string $key): string {
        return get_string_manager()->get_string('qs_'.$key, 'local_learning_workspace', null, 'ru');
    }
    public static function definitions(string $role, string $segment): array {
        if (!in_array($role, self::ROLES, true)) { throw new \InvalidArgumentException('role'); }
        $course = [
            'candidate'=>[['#program li:first-child .lw-secondary','begin'],['[data-aq-nav="sessions"]','sessions'],['[data-aq-nav="results"]','results'],['.aw-help','help']],
            'examiner'=>[['[data-aq-nav="sessions"]','trainer_sessions'],['[data-aq-nav="review"]','review'],['#program','reference'],['.aw-help','help']],
            'manager'=>[['[data-aq-nav="manage"]','manage'],['[data-aq-nav="review"]','overview'],['[data-aq-nav="sessions"]','manager_sessions'],['.aw-help','help']],
        ];
        $segments = ['course'=>$course[$role]];
        if ($role === 'candidate') {
            $segments['lesson'] = [['.lw-stepnav','route'],['.lw-workbenchbar','practice'],['[data-aq-nav="results"]','feedback']];
        } else {
            $segments['teaching'] = [['#workspace-main h1','group'],['#session-title','meeting'],['#session-meet','meet'],['#session-editor details summary','recording']];
            $segments['review'] = [['#ac-candidate-search, #ac-answers','queue'],['#ac-candidate-list, #review-feedback','review_feedback']];
            if ($role === 'manager') { $segments['manage'] = [['#test-policy','policy'],['#teaching','members'],['#announcements','announcements']]; }
        }
        if (!isset($segments[$segment])) { throw new \InvalidArgumentException('segment'); }
        return $segments[$segment];
    }
    public static function segment(string $script, string $role): ?string {
        $page=basename($script);
        if ($page==='course.php') { return 'course'; }
        if ($role==='candidate' && $page==='lesson_one.php') { return 'lesson'; }
        if ($role!=='candidate' && $page==='academy_teaching.php') { return 'teaching'; }
        if ($role!=='candidate' && $page==='academy_review.php') { return 'review'; }
        if ($role==='manager' && $page==='academy_manage.php') { return 'manage'; }
        return null;
    }
    /** Seed only missing tours. Existing administrator edits and unrelated tours are preserved. */
    public static function install(): void {
        global $DB;
        foreach (self::ROLES as $role) {
            foreach (['course','lesson','teaching','review','manage'] as $segment) {
                try { $steps=self::definitions($role,$segment); } catch (\InvalidArgumentException $e) { continue; }
                $key='quickstart_tour_'.$role.'_'.$segment;
                $existing=(int)get_config('local_learning_workspace',$key);
                if ($existing && $DB->record_exists('tool_usertours_tours',['id'=>$existing])) {
                    $tour=\tool_usertours\tour::instance($existing);
                    if (self::is_owned_tour($tour,$role,$segment)) { continue; }
                    // A stale or manually repointed setting must never make another component's tour ours.
                    unset_config($key,'local_learning_workspace');
                }
                $tour=new \tool_usertours\tour();
                $tour->set_name('Learning Workspace · '.$role.' · '.$segment.' · v'.self::VERSION);
                $tour->set_description('Learning Workspace quick start. Launch and role checks are owned by the academy; edit steps here without changing the reserved path.');
                // A non-page route prevents native automatic launch competing with the consent card.
                $tour->set_pathmatch('/local/learning_workspace/__quickstart_'.$role.'_'.$segment.'__');
                $tour->set_enabled(true);
                $tour->set_endtourlabel('qs_close,local_learning_workspace');
                $tour->set_display_step_numbers(false);
                $tour->persist();
                foreach ($steps as $i=>[$target,$copy]) {
                    $step=new \tool_usertours\step();
                    $step->set_tourid($tour->get_id());
                    $step->set_title('qs_'.$copy.'_title,local_learning_workspace');
                    $step->set_content('qs_'.$copy.'_body,local_learning_workspace',FORMAT_HTML);
                    $step->set_targettype(\tool_usertours\target::TARGET_SELECTOR);
                    $step->set_targetvalue($target);
                    $step->set_sortorder($i);
                    foreach (['placement'=>'bottom','orphan'=>false,'backdrop'=>true,'reflex'=>false] as $k=>$v) { $step->set_config($k,$v); }
                    $step->persist();
                }
                set_config($key,$tour->get_id(),'local_learning_workspace');
            }
        }
    }
    /** Remove only tours referenced by this component and still carrying its reserved identity markers. */
    public static function uninstall(): void {
        global $DB;
        foreach (self::ROLES as $role) {
            foreach (['course','lesson','teaching','review','manage'] as $segment) {
                try { self::definitions($role,$segment); } catch (\InvalidArgumentException $e) { continue; }
                $key='quickstart_tour_'.$role.'_'.$segment;
                $id=(int)get_config('local_learning_workspace',$key);
                if ($id && $DB->record_exists('tool_usertours_tours',['id'=>$id])) {
                    $tour=\tool_usertours\tour::instance($id);
                    if (self::is_owned_tour($tour,$role,$segment)) {
                        // tool_usertours::remove() removes only this tour, its steps and its per-user tour state.
                        $tour->remove();
                    }
                }
                unset_config($key,'local_learning_workspace');
            }
        }
    }
    private static function is_owned_tour(\tool_usertours\tour $tour,string $role,string $segment): bool {
        $prefix='Learning Workspace · '.$role.' · '.$segment.' · v';
        $name=$tour->get_name();
        $version=substr($name,strlen($prefix));
        return str_starts_with($name,$prefix) && preg_match('/\A[1-9][0-9]*\z/',$version)===1
            && $tour->get_pathmatch()==='/local/learning_workspace/__quickstart_'.$role.'_'.$segment.'__';
    }
    public static function tour(string $role, string $segment): ?\tool_usertours\tour {
        global $DB;
        self::definitions($role,$segment);
        $id=(int)get_config('local_learning_workspace','quickstart_tour_'.$role.'_'.$segment);
        if (!$id || !$DB->record_exists('tool_usertours_tours',['id'=>$id])) { return null; }
        $tour=\tool_usertours\tour::instance($id);
        if (!self::is_owned_tour($tour,$role,$segment)) { return null; }
        return $tour->is_enabled() ? $tour : null;
    }
    public static function configure(array $enabled): void {
        global $DB;
        academy_workspace::require_manager();$tx=$DB->start_delegated_transaction();
        foreach(self::ROLES as $role){foreach(['course','lesson','teaching','review','manage'] as $segment){
            try{self::definitions($role,$segment);}catch(\InvalidArgumentException $e){continue;}
            $id=(int)get_config('local_learning_workspace','quickstart_tour_'.$role.'_'.$segment);
            if(!$id||!$DB->record_exists('tool_usertours_tours',['id'=>$id])){continue;}
            $tour=\tool_usertours\tour::instance($id);
            if(!self::is_owned_tour($tour,$role,$segment)){throw new \DomainException('owned tour');}
            $tour->set_enabled(!empty($enabled[$role]))->persist();
        }}
        $tx->allow_commit();
    }
    private static function key(string $role,string $segment): string { return 'local_lworkspace_qs_'.$role.'_'.$segment.'_v'.self::VERSION; }
    /** Saved native content, including administrator edits. No preference writes. */
    public static function preview(string $role): array {
        global $DB;
        academy_workspace::require_manager();
        if(!in_array($role,self::ROLES,true)){throw new \InvalidArgumentException('role');}
        $result=[];
        $language=current_language();force_current_language('ru');
        try{
        foreach(['course','lesson','teaching','review','manage'] as $segment){
            try{self::definitions($role,$segment);}catch(\InvalidArgumentException $e){continue;}
            $id=(int)get_config('local_learning_workspace','quickstart_tour_'.$role.'_'.$segment);
            if(!$id||!$DB->record_exists('tool_usertours_tours',['id'=>$id])){continue;}
            foreach(\tool_usertours\tour::instance($id)->get_steps() as $step){
                $result[$segment][]=['title'=>html_to_text(format_string(\tool_usertours\helper::get_string_from_input($step->get_title()))),
                    'body'=>format_text(\tool_usertours\helper::get_string_from_input($step->get_content()),$step->get_contentformat(),['context'=>\context_system::instance()])];
            }
        }
        }finally{force_current_language($language);}
        return $result;
    }
    public static function state(string $role,string $segment): array {
        self::definitions($role,$segment);
        $state=json_decode((string)get_user_preferences(self::key($role,$segment),''),true);
        if (!is_array($state) || !in_array($state['status']??'', ['active','skipped','completed'],true)) {
            return ['status'=>'new','step'=>0,'revision'=>0];
        }
        return ['status'=>$state['status'],'step'=>max(0,(int)$state['step']),'revision'=>max(0,(int)$state['revision'])];
    }
    /** Optimistic revision + per-user lock prevent stale tabs resurrecting a dismissed tour. */
    public static function change(string $role,string $segment,string $action,int $revision,int $step=0,bool $manual=false): array {
        global $USER;
        academy_access::require_viewer();
        if ($role!==academy_workspace::role()) { throw new \DomainException('role'); }
        self::definitions($role,$segment);
        $tour=self::tour($role,$segment);
        if (!$tour || !($stepcount=$tour->count_steps())) { throw new \DomainException('disabled'); }
        if (!in_array($action,['start','move','skip','complete'],true) || $step<0 || (in_array($action,['move','complete'],true) && $step>=$stepcount)) { throw new \InvalidArgumentException('action'); }
        $lock=\core\lock\lock_config::get_lock_factory('local_lworkspace_quickstart')->get_lock($USER->id.':'.$role.':'.$segment,5);
        if (!$lock) { throw new \DomainException('busy'); }
        try {
            // Force a fresh database read under lock rather than another tab's cached preference.
            unset($USER->preference);
            $state=self::state($role,$segment);
            if ($state['revision']!==$revision) { throw new \DomainException('stale'); }
            // Native administrators may add/remove steps without changing the account-state version.
            $state['step']=min($state['step'],$stepcount-1);
            if ($action==='start') {
                if (in_array($state['status'],['skipped','completed'],true) && !$manual) { throw new \DomainException('manual'); }
                $state['step']=$state['status']==='active'&&!$manual ? $state['step'] : 0;
                $state['status']='active';
            } elseif ($action==='skip') { $state['status']='skipped'; }
            elseif ($state['status']!=='active') { throw new \DomainException('inactive'); }
            elseif ($action==='complete') { $state['status']='completed';$state['step']=$step; }
            else { $state['step']=$step; }
            $state['revision']++;
            set_user_preference(self::key($role,$segment),json_encode($state));
            return $state;
        } finally { $lock->release(); }
    }
    public static function boot(): string {
        global $PAGE,$SCRIPT,$OUTPUT,$USER;
        if (!isloggedin() || isguestuser() || empty($USER->id) || basename((string)$SCRIPT)==='lesson_one_preview.php') { return ''; }
        $role=academy_workspace::role();$segment=self::segment((string)$SCRIPT,$role);
        if (!$segment || !($tour=self::tour($role,$segment))) { return ''; }
        $PAGE->requires->css(new \moodle_url('/local/learning_workspace/assets/academy-quickstart.css',['v'=>self::ASSETS]));
        $PAGE->requires->js(new \moodle_url('/local/learning_workspace/assets/academy-quickstart.js',['v'=>self::ASSETS]));
        // Preserve native text formatting/sanitising and the academy's existing Russian teaching shell.
        $language=current_language();force_current_language('ru');
        try { $config=(new \tool_usertours\output\tour($tour))->export_for_template($PAGE->get_renderer('core')); }
        finally { force_current_language($language); }
        $strings=[];foreach(['title','invite_'.$role,'start','resume','later','restart','close','next','previous','done','progress','error','unavailable','finished','skipped'] as $key){$strings[$key]=self::text($key);}
        $data=['role'=>$role,'segment'=>$segment,'state'=>self::state($role,$segment),'tour'=>$config,'strings'=>$strings,'sesskey'=>sesskey(),
            'endpoint'=>(new \moodle_url('/local/learning_workspace/academy_quickstart_action.php'))->out(false),
            'manual'=>(bool)optional_param('quickstart',0,PARAM_BOOL)];
        return '<script type="application/json" id="academy-quickstart-config">'.json_encode($data,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE).'</script>';
    }
    public static function card(): string {
        $role=academy_workspace::role();$state=self::state($role,'course');
        if (!self::tour($role,'course')) { return ''; }
        $fresh=in_array($state['status'],['new','active'],true);
        return '<section class="aq-card" data-aq-card aria-labelledby="aq-title">'.
            '<div data-aq-invite'.($fresh?'':' hidden').'><h2 id="aq-title">'.s(self::text('title')).'</h2><p>'.s(self::text('invite_'.$role)).'</p>'.
            '<div class="aq-actions"><button type="button" class="lw-primary" data-aq-start>'.s(self::text($state['status']==='active'?'resume':'start')).'</button>'.
            '<button type="button" class="aq-link" data-aq-skip>'.s(self::text('later')).'</button></div></div>'.self::checklist($role).
            '<p class="aq-status" data-aq-status role="status" aria-live="polite"></p></section>';
    }
    private static function checklist(string $role): string {
        global $DB,$USER;
        $groups=academy_blended::groups();$ids=array_keys($groups);
        $session=false;if($ids){[$sql,$params]=$DB->get_in_or_equal($ids,SQL_PARAMS_NAMED);$session=$DB->record_exists_select('local_lworkspace_session','groupid '.$sql,$params);}
        if($role==='candidate'){
            $onboard=academy_blended::onboarding((int)$USER->id);
            $items=[['academy_onboarding.php','check_profile',($onboard->status??'')==='complete'],
                ['lesson_one.php?step=3','check_practice',$DB->record_exists_sql('SELECT a.id FROM {local_lworkspace_lanswer} a JOIN {local_lworkspace_lesson} l ON l.id=a.lessonid WHERE l.userid=:userid',['userid'=>$USER->id])],
                ['academy.php','check_result',$DB->record_exists('local_lworkspace_acattempt',['userid'=>$USER->id])]];
        } elseif($role==='examiner') {
            $items=[['academy_teaching.php','check_group',(bool)$groups],['academy_teaching.php','check_session',$session],['academy_review.php','check_review',$DB->record_exists('local_lworkspace_acreview',['reviewerid'=>$USER->id])]];
        } else {
            $assigned=false;
            foreach($ids as $id){$trainer=false;$learner=false;foreach($DB->get_records('groups_members',['groupid'=>$id],'','id,userid') as $member){$trainer=$trainer||academy_blended::trainer((int)$member->userid);$learner=$learner||academy_access::is_candidate((int)$member->userid);}if($trainer&&$learner){$assigned=true;break;}}
            $items=[['academy_teaching.php#groups','check_groups',(bool)$groups],['academy_teaching.php#groups','check_members',$assigned],['academy_manage.php#test-policy','check_policy',$DB->count_records('local_lworkspace_acpolicy',['scopeid'=>0])>=8]];
        }
        $html='<details class="aq-checklist"><summary>'.s(self::text('first_steps')).'</summary><ul>';
        foreach($items as [$url,$label,$done]){$html.='<li><span class="aq-check" aria-label="'.s(self::text($done?'checked':'todo')).'">'.($done?'✓':'○').'</span><a href="'.s($url).'">'.s(self::text($label)).'</a></li>';}
        return $html.'</ul></details>';
    }
}
