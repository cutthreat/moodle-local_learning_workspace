<?php
namespace local_learning_workspace\local;
defined('MOODLE_INTERNAL') || die();

require_once(__DIR__.'/academy_access.php');

/** Server-backed account, in-platform notices and support. No credential fields. */
final class academy_workspace {
    public static function staff_text(string $key): string {
        return get_string_manager()->get_string('staff_'.$key, 'local_learning_workspace', null, 'ru');
    }
    public static function text(string $key, $a=null): string {
        // The academy currently teaches in Russian; keep one language across its shell.
        return get_string_manager()->get_string('ws_'.$key, 'local_learning_workspace', $a, 'ru');
    }
    public static function url(string $page, array $params=[]): \moodle_url {
        $pages=['profile','notifications','help','manage','materials','onboarding','sessions','session','teaching'];
        if (!in_array($page,$pages,true)) { throw new \InvalidArgumentException('Unknown workspace page'); }
        return new \moodle_url('/local/learning_workspace/academy_'.$page.'.php',$params);
    }
    public static function manager(): bool {
        return has_capability('local/learning_workspace:approveadmission', \context_system::instance());
    }
    public static function can_review(): bool {
        return has_capability('local/learning_workspace:reviewassessment', \context_system::instance());
    }
    public static function role(): string {
        if (self::manager()) { return 'manager'; }
        if (has_capability('local/learning_workspace:reviewassessment',\context_system::instance())) { return 'examiner'; }
        return 'candidate';
    }
    public static function require_manager(): void {
        if(!self::manager()){throw new \moodle_exception('ws_management_forbidden','local_learning_workspace');}
    }
    public static function ready(): bool {
        global $DB;
        return $DB->get_manager()->table_exists('local_lworkspace_notice');
    }
    public static function clean_text(string $text, int $max): string {
        $text=trim(clean_param($text,PARAM_TEXT));
        if ($text==='' || \core_text::strlen($text)>$max) { throw new \DomainException(self::text('invalid')); }
        return $text;
    }
    public static function form_token(string $action): string {
        global $SESSION;
        $forms=(array)($SESSION->academy_workspace_forms??[]);
        $forms=array_filter($forms,static fn($f)=>$f['time']>time()-7200);
        $key=bin2hex(random_bytes(16));
        $forms[$key]=['action'=>$action,'time'=>time()];
        $SESSION->academy_workspace_forms=array_slice($forms,-200,null,true);
        return $key;
    }
    public static function consume_form(string $action): void {
        global $SESSION;
        $key=required_param('workspaceform',PARAM_ALPHANUM);
        $form=$SESSION->academy_workspace_forms[$key]??null;
        if(!$form||$form['action']!==$action||$form['time']<time()-7200){throw new \DomainException(self::text('try_again'));}
        unset($SESSION->academy_workspace_forms[$key]);
    }
    public static function profile(): array {
        global $USER;
        return ['firstname'=>$USER->firstname,'lastname'=>$USER->lastname,'email'=>$USER->email,
            'timezone'=>$USER->timezone,'lang'=>$USER->lang ?: current_language(),'role'=>self::role()];
    }
    public static function save_profile(array $data): void {
        global $CFG,$USER;
        $first=self::clean_text((string)$data['firstname'],100);
        $last=self::clean_text((string)$data['lastname'],100);
        $zones=\core_date::get_list_of_timezones();
        $zone=(string)$data['timezone'];
        if ($zone!=='99' && !array_key_exists($zone,$zones)) { throw new \DomainException(self::text('invalid')); }
        require_once($CFG->dirroot.'/user/lib.php');
        $record=(object)['id'=>(int)$USER->id,'firstname'=>$first,'lastname'=>$last,'timezone'=>$zone];
        user_update_user($record,false,true);
        foreach (['firstname','lastname','timezone'] as $key) { $USER->$key=$record->$key; }
    }
    /** Dismissal belongs to this Moodle login session, never to localStorage. */
    public static function dismissed(): array {
        global $SESSION;
        return array_values($SESSION->academy_dismissed_notices ?? []);
    }
    public static function dismiss(string $key): void {
        global $SESSION;
        if (!preg_match('/^[a-f0-9]{64}$/D',$key)) { throw new \DomainException(self::text('invalid')); }
        $keys=self::dismissed();
        if (!in_array($key,$keys,true)) { $keys[]=$key; }
        $SESSION->academy_dismissed_notices=array_slice($keys,-100);
    }
    public static function notice_key(string $text): string {
        return hash('sha256',preg_replace('/\s+/u',' ',trim($text)));
    }
    /** The server enforces audience and owns all event links. */
    public static function feed(): array {
        global $DB,$USER;
        if (!self::ready()) { return []; }
        $role=self::role();
        $rows=$DB->get_records_select('local_lworkspace_notice','active = 1 AND (audience = :all OR audience = :role)',
            ['all'=>'all','role'=>$role],'timecreated DESC','*',0,50);
        $events=[];
        foreach ($rows as $r) {
            $events[]=['key'=>'notice.'.$r->id,'title'=>$r->title,'body'=>$r->body,
                'time'=>(int)$r->timecreated,'url'=>self::url('notifications')->out(false)];
        }
        $sql="SELECT r.id,r.feedback,r.decision,r.timecreated,a.kind,a.lessonid
                FROM {local_lworkspace_acreview} r JOIN {local_lworkspace_acattempt} a ON a.id=r.attemptid
               WHERE a.userid=:userid ORDER BY r.timecreated DESC";
        foreach ($DB->get_records_sql($sql,['userid'=>(int)$USER->id],0,30) as $r) {
            $events[]=['key'=>'review.'.$r->id,'title'=>self::text('review_ready'),
                'body'=>($r->kind==='day'?$r->lessonid.': ':'').$r->feedback,
                'time'=>(int)$r->timecreated,'url'=>(new \moodle_url('/local/learning_workspace/academy.php',
                    $r->kind==='day'?['view'=>'day','lesson'=>$r->lessonid]:['view'=>'final']))->out(false)];
        }
        foreach ($DB->get_records_select('local_lworkspace_help','userid=:user AND reply<>:empty',
                ['user'=>(int)$USER->id,'empty'=>''],'timemodified DESC','*',0,20) as $r) {
            $events[]=['key'=>'help.'.$r->id.'.'.$r->timemodified,'title'=>self::text('support_reply'),
                'body'=>$r->reply,'time'=>(int)$r->timemodified,'url'=>self::url('help',['request'=>(int)$r->id])->out(false).'#request-'.$r->id];
        }
        foreach ($DB->get_records('local_lworkspace_acpermit',['userid'=>(int)$USER->id],'timecreated DESC','*',0,20) as $permit) {
            if (!academy_attempt_policy::effective((int)$USER->id,$permit->lessonid)['retakesenabled']) { continue; }
            $events[]=['key'=>'permit.'.$permit->id,'title'=>'Тренер разрешил повторную попытку',
                'body'=>($permit->kind==='day'?$permit->lessonid.': ':'').$permit->reason,
                'time'=>(int)$permit->timecreated,'url'=>(new \moodle_url('/local/learning_workspace/academy.php',
                    $permit->kind==='day'?['view'=>'day','lesson'=>$permit->lessonid]:['view'=>'final']))->out(false)];
        }
        if(class_exists(academy_blended::class)){$events=array_merge($events,academy_blended::feed());}
        usort($events,static fn($a,$b)=>$b['time']<=>$a['time']);
        $read=$DB->get_records('local_lworkspace_nread',['userid'=>(int)$USER->id],'','eventkey');
        foreach ($events as &$event) { $event['read']=isset($read[$event['key']]); }
        return array_slice($events,0,50);
    }
    public static function mark_read(string $key): void {
        global $DB,$USER;
        if (!preg_match('/\A(?:notice\.[1-9][0-9]*|(?:review|permit|teaching)\.[1-9][0-9]*|help\.[1-9][0-9]*\.[0-9]+)\z/D',$key)) {
            throw new \DomainException(self::text('invalid'));
        }
        $allowed=array_column(self::feed(),'key');
        if (!in_array($key,$allowed,true)) { throw new \DomainException(self::text('invalid')); }
        $record=(object)['userid'=>(int)$USER->id,'eventkey'=>$key,'timecreated'=>time()];
        if (!$DB->record_exists('local_lworkspace_nread',['userid'=>$record->userid,'eventkey'=>$key])) {
            // A unique index plus named lock makes repeated clicks idempotent.
            $factory=\core\lock\lock_config::get_lock_factory('local_learning_workspace');
            $lock=$factory->get_lock('notice-read-'.$USER->id,5);
            if (!$lock) { throw new \DomainException(self::text('try_again')); }
            try {
                if (!$DB->record_exists('local_lworkspace_nread',['userid'=>$record->userid,'eventkey'=>$key])) {
                    $DB->insert_record('local_lworkspace_nread',$record);
                }
            } finally { $lock->release(); }
        }
    }
    /** Compact header view of the same audience-filtered feed; opening it has no write effects. */
    public static function notification_digest(array $events): array {
        $unread=array_values(array_filter($events,static fn($event)=>!$event['read']));
        $items=array_slice($unread,0,5);
        foreach($items as &$item){
            $body=preg_replace('/\s+/u',' ',trim($item['body']));
            $item['excerpt']=\core_text::strlen($body)>120?\core_text::substr($body,0,120).'…':$body;
        }
        unset($item);
        return ['count'=>count($unread),'items'=>$items];
    }
    public static function notification_anchor(string $key): string {
        if(!preg_match('/\A(?:notice\.[1-9][0-9]*|(?:review|permit|teaching)\.[1-9][0-9]*|help\.[1-9][0-9]*\.[0-9]+)\z/D',$key)){
            throw new \InvalidArgumentException('Invalid notification key');
        }
        return 'notification-'.str_replace('.','-',$key);
    }
    public static function mark_all_read(): void {
        // The visible, audience-filtered feed is the only permitted batch scope.
        foreach(self::feed() as $event) {
            if(!$event['read']) { self::mark_read($event['key']); }
        }
    }
    public static function announce(string $title,string $body,string $audience): int {
        global $DB,$USER;
        self::require_manager();
        if (!in_array($audience,['all','candidate','examiner','manager'],true)) { throw new \DomainException(self::text('invalid')); }
        return (int)$DB->insert_record('local_lworkspace_notice',(object)['authorid'=>$USER->id,'audience'=>$audience,
            'title'=>self::clean_text($title,160),'body'=>self::clean_text($body,4000),
            'active'=>1,'timecreated'=>time(),'timemodified'=>time()]);
    }
    public static function archive(int $id, bool $active=false): void {
        global $DB;
        self::require_manager();
        $record=$DB->get_record('local_lworkspace_notice',['id'=>$id],'*',MUST_EXIST);
        $record->active=(int)$active; $record->timemodified=time();
        $DB->update_record('local_lworkspace_notice',$record);
    }
    public static function request_help(string $subject,string $message): int {
        global $DB,$USER;
        // Bound accidental double submits and abuse; only own requests are counted.
        if ($DB->count_records_select('local_lworkspace_help','userid=:u AND timecreated>:t',
                ['u'=>$USER->id,'t'=>time()-3600])>=5) { throw new \DomainException(self::text('help_limit')); }
        return (int)$DB->insert_record('local_lworkspace_help',(object)['userid'=>$USER->id,
            'subject'=>self::clean_text($subject,160),'message'=>self::clean_text($message,4000),
            'pagepath'=>'','status'=>'open','reply'=>'','handledby'=>0,'timecreated'=>time(),'timemodified'=>time()]);
    }
    /** A successful help POST owns this short-lived, per-request feedback. */
    public static function remember_help_sent(int $id): void {
        global $SESSION,$USER;
        if($id<=0 || (int)$USER->id<=0){throw new \InvalidArgumentException('Positive request and signed user required');}
        $now=time();
        $pending=array_filter((array)($SESSION->academy_help_sent??[]),static fn($entry)=>
            is_array($entry) && (int)($entry['time']??0)>$now-600 && (int)($entry['time']??0)<=$now);
        $pending[$id]=['userid'=>(int)$USER->id,'time'=>$now];
        $SESSION->academy_help_sent=array_slice($pending,-20,null,true);
    }
    /** Query parameters alone never establish a successful submission. */
    public static function consume_help_sent(int $id): bool {
        global $SESSION,$USER,$DB;
        if($id<=0 || (int)$USER->id<=0){return false;}
        $pending=(array)($SESSION->academy_help_sent??[]);
        $entry=$pending[$id]??null;
        unset($pending[$id]);
        $SESSION->academy_help_sent=$pending;
        $now=time();
        return is_array($entry) && (int)($entry['userid']??0)===(int)$USER->id
            && (int)($entry['time']??0)>$now-600 && (int)($entry['time']??0)<=$now
            && $DB->record_exists('local_lworkspace_help',['id'=>$id,'userid'=>(int)$USER->id]);
    }
    public static function reply(int $id,string $text): void {
        global $DB,$USER;
        self::require_manager();
        $record=$DB->get_record('local_lworkspace_help',['id'=>$id],'*',MUST_EXIST);
        $record->reply=self::clean_text($text,4000); $record->status='answered';
        $record->handledby=$USER->id; $record->timemodified=max(time(),$record->timemodified+1);
        $DB->update_record('local_lworkspace_help',$record);
    }
}
