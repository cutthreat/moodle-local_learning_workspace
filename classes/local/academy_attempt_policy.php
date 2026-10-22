<?php
namespace local_learning_workspace\local;
defined('MOODLE_INTERNAL') || die();

/** Append-only policy, per-attempt snapshots and explicit one-use trainer permits. */
final class academy_attempt_policy {
    public static function upgrade(): void {
        global $DB;
        $manager = $DB->get_manager();
        $tables = [
            'local_lworkspace_acpolicy' => ['scopeid'=>'int','lessonid'=>8,'attemptlimit'=>'int',
                'feedbackmode'=>16,'authorid'=>'int','timecreated'=>'int'],
            'local_lworkspace_acpermit' => ['userid'=>'int','kind'=>16,'lessonid'=>8,'attemptno'=>'int',
                'reviewerid'=>'int','reason'=>'text','timecreated'=>'int'],
            'local_lworkspace_acstart' => ['userid'=>'int','kind'=>16,'lessonid'=>8,'attemptno'=>'int',
                'policyjson'=>'text','timecreated'=>'int'],
        ];
        foreach ($tables as $name=>$fields) {
            $table = new \xmldb_table($name);
            if ($manager->table_exists($table)) { continue; }
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            foreach ($fields as $field=>$type) {
                if ($type==='int') { $table->add_field($field, XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0); }
                elseif ($type==='text') { $table->add_field($field, XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL); }
                else { $table->add_field($field, XMLDB_TYPE_CHAR, (string)$type, null, XMLDB_NOTNULL); }
            }
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            if ($name!=='local_lworkspace_acpolicy') {
                $table->add_index('user_test_attempt_uix', XMLDB_INDEX_UNIQUE, ['userid','kind','lessonid','attemptno']);
            }
            $manager->create_table($table);
        }
        $table = new \xmldb_table('local_lworkspace_acattempt');
        $field = new \xmldb_field('policyjson', XMLDB_TYPE_TEXT);
        if (!$manager->field_exists($table,$field)) { $manager->add_field($table,$field); }
        $table = new \xmldb_table('local_lworkspace_acpolicy');
        $field = new \xmldb_field('retakesenabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, 1);
        if (!$manager->field_exists($table,$field)) { $manager->add_field($table,$field); }
    }

    public static function effective(int $userid, string $lessonid): array {
        global $DB;
        $scopes = [0];
        foreach ($DB->get_records('cohort_members',['userid'=>$userid]) as $member) {
            $scopes[] = (int)$member->cohortid;
        }
        $rows = $DB->get_records('local_lworkspace_acpolicy',null,'id ASC');
        $selected = null;
        $priority = -1;
        foreach ($rows as $row) {
            if (!in_array((int)$row->scopeid,$scopes,true) ||
                    !in_array($row->lessonid,['ALL',$lessonid],true)) { continue; }
            $rank = ($row->lessonid!=='ALL' ? 2 : 0) + ((int)$row->scopeid>0 ? 1 : 0);
            // Ties resolve to the latest explicit setting, including overlapping cohorts.
            if ($rank >= $priority) { $priority=$rank; $selected=$row; }
        }
        return ['version'=>(int)($selected->id??0), 'limit'=>(int)($selected->attemptlimit??1),
            'feedback'=>(string)($selected->feedbackmode??'after_review'),
            'retakesenabled'=>(bool)($selected->retakesenabled??false)];
    }

    public static function configure(int $scopeid, string $lessonid, int $limit, string $feedback, bool $retakesenabled=true): object {
        global $DB,$USER;
        academy_workspace::require_manager();
        if ($scopeid<0 || ($scopeid && !$DB->record_exists('cohort',['id'=>$scopeid])) ||
                !preg_match('/^(ALL|FINAL|L[1-7])$/D',$lessonid) || $limit<0 || $limit>20 ||
                !in_array($feedback,['immediate','after_review'],true)) {
            throw new \DomainException('Проверьте настройки контрольных тестов.');
        }
        $record=(object)['scopeid'=>$scopeid,'lessonid'=>$lessonid,'attemptlimit'=>$limit,
            'feedbackmode'=>$feedback,'retakesenabled'=>(int)$retakesenabled,'authorid'=>(int)$USER->id,'timecreated'=>time()];
        $record->id=$DB->insert_record('local_lworkspace_acpolicy',$record);
        return $record;
    }

    public static function state(int $userid, string $kind, string $lessonid): array {
        global $DB;
        if (!in_array($kind,['day','final'],true) ||
                !preg_match('/^(FINAL|L[1-7])$/D',$lessonid) ||
                (($kind === 'final') !== ($lessonid === 'FINAL'))) { throw new \InvalidArgumentException('Unknown test'); }
        $history=array_values($DB->get_records('local_lworkspace_acattempt',
            ['userid'=>$userid,'kind'=>$kind,'lessonid'=>$lessonid],'attemptno DESC'));
        $next=count($history)+1;
        $key=['userid'=>$userid,'kind'=>$kind,'lessonid'=>$lessonid,'attemptno'=>$next];
        $start=$DB->get_record('local_lworkspace_acstart',$key);
        $policy=$start ? json_decode($start->policyjson,true,512,JSON_THROW_ON_ERROR) : self::effective($userid,$lessonid);
        $reviewed=!$history || $DB->record_exists('local_lworkspace_acreview',['attemptid'=>$history[0]->id]);
        $permit=$DB->record_exists('local_lworkspace_acpermit',$key);
        // Administrative stop is live: an old permit or started retry cannot bypass it.
        $retakesenabled=self::effective($userid,$lessonid)['retakesenabled'];
        return ['allowed'=>$reviewed && ($next===1 || $retakesenabled) && ($policy['limit']===0 || $next<=$policy['limit'] || $permit),
            'awaiting_review'=>!$reviewed,'next'=>$next,'policy'=>$policy,'started'=>(bool)$start,'permit'=>$permit,
            'retakesenabled'=>$retakesenabled];
    }

    /** Caller submitting holds the assessment lock; GET starts use the same lock. */
    public static function begin(int $userid, string $kind, string $lessonid, bool $locked=false): array {
        global $DB,$USER;
        academy_access::require_candidate();
        if ((int)$USER->id!==$userid) { throw new \moodle_exception('assessmentreviewforbidden','local_learning_workspace'); }
        $lock=null;
        if (!$locked) {
            $lock=\core\lock\lock_config::get_lock_factory('local_learning_workspace')
                ->get_lock('academy-attempt-'.$userid.'-'.$kind.'-'.$lessonid,10);
            if (!$lock) { throw new \moodle_exception('concurrencyconflict','local_learning_workspace'); }
        }
        try {
            $state=self::state($userid,$kind,$lessonid);
            if (!$state['allowed']) {
                throw new \moodle_exception($state['awaiting_review']?'assessmentawaitingreview':'assessmentattemptlocked','local_learning_workspace');
            }
            if (!$state['started']) {
                $DB->insert_record('local_lworkspace_acstart',(object)['userid'=>$userid,'kind'=>$kind,
                    'lessonid'=>$lessonid,'attemptno'=>$state['next'],'policyjson'=>self::encode($state['policy']),
                    'timecreated'=>time()]);
            }
            return $state['policy'];
        } finally { if ($lock) { $lock->release(); } }
    }

    public static function permit(int $attemptid, string $reason): object {
        global $DB,$USER;
        require_capability('local/learning_workspace:reviewassessment',\context_system::instance());
        $attempt=$DB->get_record('local_lworkspace_acattempt',['id'=>$attemptid],'*',MUST_EXIST);
        $reason=trim(clean_param($reason,PARAM_TEXT));
        if ((int)$attempt->userid===(int)$USER->id || \core_text::strlen($reason)<10 || \core_text::strlen($reason)>1000) {
            throw new \DomainException('Укажите причину повтора от 10 до 1000 символов. Самопроверка недоступна.');
        }
        $lock=\core\lock\lock_config::get_lock_factory('local_learning_workspace')
            ->get_lock('academy-attempt-'.$attempt->userid.'-'.$attempt->kind.'-'.$attempt->lessonid,10);
        if (!$lock) { throw new \DomainException('Попробуйте ещё раз: тест сейчас обновляется.'); }
        try {
            $state=self::state((int)$attempt->userid,$attempt->kind,$attempt->lessonid);
            if (!$state['retakesenabled']) { throw new \DomainException('Повторные отправки отключены управляющим для этой работы.'); }
            if ($state['awaiting_review']) { throw new \DomainException('Сначала сохраните решение по текущей попытке.'); }
            if ($state['next'] !== (int)$attempt->attemptno+1) { throw new \DomainException('Разрешение выдаётся из последней попытки.'); }
            $key=['userid'=>(int)$attempt->userid,'kind'=>$attempt->kind,'lessonid'=>$attempt->lessonid,'attemptno'=>$state['next']];
            $existing=$DB->get_record('local_lworkspace_acpermit',$key);
            if ($existing) { return $existing; }
            $record=(object)($key+['reviewerid'=>(int)$USER->id,'reason'=>$reason,'timecreated'=>time()]);
            $record->id=$DB->insert_record('local_lworkspace_acpermit',$record);
            return $record;
        } finally { $lock->release(); }
    }

    public static function feedback_visible(object $attempt, bool $reviewed): bool {
        $policy=!empty($attempt->policyjson) ? json_decode($attempt->policyjson,true,512,JSON_THROW_ON_ERROR) : [];
        return $reviewed || ($policy['feedback']??'immediate')==='immediate';
    }

    public static function encode(array $policy): string {
        return json_encode($policy,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    }

    public static function retry_message(int $userid,string $lessonid): string {
        return self::effective($userid,$lessonid)['retakesenabled']
            ? 'Новую контрольную попытку открывает тренер после проверки.'
            : 'Для этой работы предусмотрена одна отправка. Ошибки разберите с тренером.';
    }
}
