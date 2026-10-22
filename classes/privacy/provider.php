<?php
namespace local_learning_workspace\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\metadata\provider as metadata_provider;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\core_userlist_provider;
use core_privacy\local\request\plugin\provider as plugin_provider;
use core_privacy\local\request\user_preference_provider;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

defined('MOODLE_INTERNAL') || die();

/** Privacy API for learner records and authored workspace data. */
final class provider implements metadata_provider, plugin_provider, core_userlist_provider, user_preference_provider {
    /** Quick-start preferences persisted by academy_quickstart. */
    private const PREFERENCE_NAMES = [
        'local_lworkspace_qs_candidate_course_v1',
        'local_lworkspace_qs_candidate_lesson_v1',
        'local_lworkspace_qs_examiner_course_v1',
        'local_lworkspace_qs_examiner_teaching_v1',
        'local_lworkspace_qs_examiner_review_v1',
        'local_lworkspace_qs_manager_course_v1',
        'local_lworkspace_qs_manager_teaching_v1',
        'local_lworkspace_qs_manager_review_v1',
        'local_lworkspace_qs_manager_manage_v1',
        'local_lworkspace_start_candidate',
        'local_lworkspace_start_examiner',
        'local_lworkspace_start_manager',
    ];

    /**
     * Describe all plugin tables and preferences which contain personal data.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $field = static fn(string $name): string => 'privacy:metadata:' . $name;
        $tables = [
            'local_lworkspace_lesson' => ['id' => 'reference', 'userid' => 'userid', 'edition' => 'reference',
                'contenthash' => 'reference', 'contentjson' => 'content', 'statejson' => 'content',
                'revision' => 'reference', 'timemodified' => 'time'],
            'local_lworkspace_lanswer' => ['id' => 'reference', 'lessonid' => 'reference', 'stage' => 'classification',
                'revision' => 'reference', 'answertext' => 'content', 'timecreated' => 'time'],
            'local_lworkspace_acattempt' => ['id' => 'reference', 'userid' => 'userid', 'kind' => 'assessment',
                'lessonid' => 'assessment', 'bankversion' => 'reference', 'bankhash' => 'reference',
                'attemptno' => 'reference', 'status' => 'status', 'hasdraft' => 'status', 'score' => 'score',
                'passmark' => 'score', 'answersjson' => 'content', 'questionsjson' => 'content',
                'feedbackjson' => 'feedback', 'policyjson' => 'policy', 'answershash' => 'reference',
                'idemkey' => 'reference', 'timecreated' => 'time'],
            'local_lworkspace_acdraft' => ['id' => 'reference', 'userid' => 'userid', 'kind' => 'assessment',
                'variant' => 'reference', 'attemptno' => 'reference', 'bankversion' => 'reference',
                'bankhash' => 'reference', 'answersjson' => 'content', 'timemodified' => 'time'],
            'local_lworkspace_acreview' => ['id' => 'reference', 'attemptid' => 'reference',
                'reviewerid' => 'reviewerid', 'decision' => 'status', 'rubricjson' => 'feedback',
                'feedback' => 'feedback', 'idemkey' => 'reference', 'timecreated' => 'time'],
            'local_lworkspace_notice' => ['id' => 'reference', 'authorid' => 'authorid', 'audience' => 'classification',
                'title' => 'title', 'body' => 'content', 'active' => 'status', 'timecreated' => 'time',
                'timemodified' => 'time'],
            'local_lworkspace_nread' => ['id' => 'reference', 'userid' => 'userid', 'eventkey' => 'reference',
                'timecreated' => 'time'],
            'local_lworkspace_help' => ['id' => 'reference', 'userid' => 'userid', 'subject' => 'title',
                'message' => 'content', 'pagepath' => 'reference', 'status' => 'status', 'reply' => 'feedback',
                'handledby' => 'authorid', 'timecreated' => 'time', 'timemodified' => 'time'],
            'local_lworkspace_acpolicy' => ['id' => 'reference', 'scopeid' => 'reference',
                'lessonid' => 'assessment', 'attemptlimit' => 'policy', 'feedbackmode' => 'policy',
                'retakesenabled' => 'policy', 'authorid' => 'authorid', 'timecreated' => 'time'],
            'local_lworkspace_acpermit' => ['id' => 'reference', 'userid' => 'userid', 'kind' => 'assessment',
                'lessonid' => 'assessment', 'attemptno' => 'reference', 'reviewerid' => 'reviewerid',
                'reason' => 'feedback', 'timecreated' => 'time'],
            'local_lworkspace_acstart' => ['id' => 'reference', 'userid' => 'userid', 'kind' => 'assessment',
                'lessonid' => 'assessment', 'attemptno' => 'reference', 'policyjson' => 'policy',
                'timecreated' => 'time'],
            'local_lworkspace_acrdraft' => ['id' => 'reference', 'attemptid' => 'reference',
                'reviewerid' => 'reviewerid', 'revision' => 'reference', 'timemodified' => 'time',
                'payloadjson' => 'content'],
            'local_lworkspace_onboard' => ['id' => 'reference', 'userid' => 'userid', 'answers' => 'content',
                'status' => 'status', 'revision' => 'reference', 'timecreated' => 'time', 'timemodified' => 'time'],
            'local_lworkspace_session' => ['id' => 'reference', 'groupid' => 'reference', 'lessonid' => 'assessment',
                'title' => 'title', 'startsat' => 'sessiontime', 'duration' => 'duration', 'timezone' => 'reference',
                'meeturl' => 'reference', 'agenda' => 'content', 'summary' => 'content', 'recordurl' => 'reference',
                'recordname' => 'filename', 'captionname' => 'filename', 'published' => 'status',
                'recordnotice' => 'status', 'status' => 'status', 'revision' => 'reference', 'authorid' => 'authorid',
                'timecreated' => 'time', 'timemodified' => 'time'],
            'local_lworkspace_discuss' => ['id' => 'reference', 'sessionid' => 'reference', 'parentid' => 'reference',
                'userid' => 'userid', 'body' => 'content', 'active' => 'status', 'pinned' => 'status',
                'timecreated' => 'time', 'timemodified' => 'time'],
            'local_lworkspace_attend' => ['id' => 'reference', 'sessionid' => 'reference', 'userid' => 'userid',
                'status' => 'status', 'watched' => 'status', 'note' => 'content', 'actorid' => 'authorid',
                'timemodified' => 'time'],
            'local_lworkspace_bevent' => ['id' => 'reference', 'userid' => 'userid', 'sessionid' => 'reference',
                'eventkey' => 'reference', 'title' => 'title', 'body' => 'content', 'timecreated' => 'time'],
        ];

        foreach ($tables as $table => $fields) {
            $descriptions = [];
            foreach ($fields as $name => $description) {
                $descriptions[$name] = $field($description);
            }
            $collection->add_database_table($table, $descriptions, 'privacy:metadata:table:' . substr($table, strlen('local_lworkspace_')));
        }
        foreach (self::PREFERENCE_NAMES as $name) {
            $collection->add_user_preference($name, 'privacy:metadata:preference');
        }
        $collection->add_subsystem_link('tool_usertours', [], 'privacy:metadata:usertours');

        return $collection;
    }

    /**
     * Find the system context when any data or quick-start preference is held.
     * Local plugin records do not carry a course or activity context.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $contextlist = new contextlist();
        $contextlist->set_component('local_learning_workspace');
        if ($userid > 0) {
            [$union, $params] = self::user_data_union_sql();
            $params['targetuserid'] = $userid;
            if ($DB->record_exists_sql("SELECT 1 FROM ({$union}) localdata WHERE localdata.userid = :targetuserid", $params)) {
                $contextlist->add_system_context();
            }
        }
        return $contextlist;
    }

    /**
     * Find all users represented by plugin data in the system context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist) {
        if (!($userlist->get_context() instanceof \context_system)) {
            return;
        }
        [$sql, $params] = self::user_data_union_sql();
        $userlist->add_from_sql('userid', $sql, $params);
    }

    /**
     * Export plugin records for each approved system context.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        $userid = (int)$contextlist->get_user()->id;
        if ($userid <= 0) {
            return;
        }
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof \context_system) {
                self::export_system_data($context, $userid);
            }
        }
    }

    /**
     * Delete all plugin personal data in the system context.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if (!($context instanceof \context_system)) {
            return;
        }
        [$union, $params] = self::user_data_union_sql();
        $userids = $DB->get_fieldset_sql("SELECT DISTINCT localdata.userid FROM ({$union}) localdata WHERE localdata.userid > 0", $params);
        foreach (array_unique(array_map('intval', $userids)) as $userid) {
            self::delete_user_data($userid);
        }
    }

    /**
     * Delete one user's data in approved system contexts.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        $userid = (int)$contextlist->get_user()->id;
        if ($userid <= 0) {
            return;
        }
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof \context_system) {
                self::delete_user_data($userid);
                return;
            }
        }
    }

    /**
     * Delete data for users selected in the system context.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        if (!($userlist->get_context() instanceof \context_system)) {
            return;
        }
        foreach (array_unique(array_map('intval', $userlist->get_userids())) as $userid) {
            self::delete_user_data($userid);
        }
    }

    /**
     * Export the user's saved guided-practice state preferences.
     *
     * @param int $userid
     */
    public static function export_user_preferences(int $userid) {
        foreach (self::PREFERENCE_NAMES as $name) {
            $value = get_user_preferences($name, null, $userid);
            if ($value !== null) {
                writer::export_user_preference('local_learning_workspace', $name, (string)$value,
                    get_string('privacy:metadata:preference', 'local_learning_workspace'));
            }
        }
    }

    /**
     * Build the same data-source query for context and user-list discovery.
     * Each identity field is included, including authors/reviewers on shared rows.
     *
     * @return array{0:string,1:array}
     */
    private static function user_data_union_sql(): array {
        global $DB;
        $queries = [
            'SELECT userid AS userid FROM {local_lworkspace_lesson} WHERE userid > 0',
            'SELECT l.userid AS userid FROM {local_lworkspace_lanswer} a JOIN {local_lworkspace_lesson} l ON l.id = a.lessonid WHERE l.userid > 0',
            'SELECT userid AS userid FROM {local_lworkspace_acattempt} WHERE userid > 0',
            'SELECT userid AS userid FROM {local_lworkspace_acdraft} WHERE userid > 0',
            'SELECT reviewerid AS userid FROM {local_lworkspace_acreview} WHERE reviewerid > 0',
            'SELECT a.userid AS userid FROM {local_lworkspace_acreview} r JOIN {local_lworkspace_acattempt} a ON a.id = r.attemptid WHERE a.userid > 0',
            'SELECT authorid AS userid FROM {local_lworkspace_notice} WHERE authorid > 0',
            'SELECT userid AS userid FROM {local_lworkspace_nread} WHERE userid > 0',
            'SELECT userid AS userid FROM {local_lworkspace_help} WHERE userid > 0',
            'SELECT handledby AS userid FROM {local_lworkspace_help} WHERE handledby > 0',
            'SELECT authorid AS userid FROM {local_lworkspace_acpolicy} WHERE authorid > 0',
            'SELECT userid AS userid FROM {local_lworkspace_acpermit} WHERE userid > 0',
            'SELECT reviewerid AS userid FROM {local_lworkspace_acpermit} WHERE reviewerid > 0',
            'SELECT userid AS userid FROM {local_lworkspace_acstart} WHERE userid > 0',
            'SELECT reviewerid AS userid FROM {local_lworkspace_acrdraft} WHERE reviewerid > 0',
            'SELECT a.userid AS userid FROM {local_lworkspace_acrdraft} d JOIN {local_lworkspace_acattempt} a ON a.id = d.attemptid WHERE a.userid > 0',
            'SELECT userid AS userid FROM {local_lworkspace_onboard} WHERE userid > 0',
            'SELECT authorid AS userid FROM {local_lworkspace_session} WHERE authorid > 0',
            'SELECT gm.userid AS userid FROM {groups_members} gm JOIN {local_lworkspace_session} s ON s.groupid = gm.groupid WHERE gm.userid > 0',
            'SELECT userid AS userid FROM {local_lworkspace_discuss} WHERE userid > 0',
            'SELECT userid AS userid FROM {local_lworkspace_attend} WHERE userid > 0',
            'SELECT actorid AS userid FROM {local_lworkspace_attend} WHERE actorid > 0',
            'SELECT userid AS userid FROM {local_lworkspace_bevent} WHERE userid > 0',
        ];
        [$prefs, $prefparams] = $DB->get_in_or_equal(self::PREFERENCE_NAMES, SQL_PARAMS_NAMED, 'quickstart');
        $queries[] = "SELECT userid AS userid FROM {user_preferences} WHERE name {$prefs}";
        return [implode("\nUNION ALL\n", $queries), $prefparams];
    }

    /** Export user-owned and authored records without including other users' identity fields. */
    private static function export_system_data(\context_system $context, int $userid): void {
        global $DB;
        $writer = writer::with_context($context);
        $directtables = [
            'local_lworkspace_lesson', 'local_lworkspace_acattempt', 'local_lworkspace_acdraft',
            'local_lworkspace_nread', 'local_lworkspace_onboard', 'local_lworkspace_discuss',
            'local_lworkspace_attend', 'local_lworkspace_bevent', 'local_lworkspace_acstart',
        ];
        foreach ($directtables as $table) {
            $records = $DB->get_records($table, ['userid' => $userid], 'id ASC');
            self::export_records($writer, [$table], 'records', array_values($records));
        }

        $answers = $DB->get_records_sql(
            'SELECT a.* FROM {local_lworkspace_lanswer} a JOIN {local_lworkspace_lesson} l ON l.id = a.lessonid WHERE l.userid = :userid ORDER BY a.lessonid, a.revision',
            ['userid' => $userid]
        );
        self::export_records($writer, ['local_lworkspace_lanswer'], 'records', array_values($answers));

        $studentreviews = $DB->get_records_sql(
            'SELECT r.* FROM {local_lworkspace_acreview} r JOIN {local_lworkspace_acattempt} a ON a.id = r.attemptid WHERE a.userid = :userid ORDER BY r.id',
            ['userid' => $userid]
        );
        self::export_records($writer, ['local_lworkspace_acreview', 'learner'], 'records', array_values($studentreviews));
        $reviewerreviews = $DB->get_records_sql(
            'SELECT r.id, r.reviewerid, r.decision, r.rubricjson, r.feedback, r.timecreated, a.kind, a.lessonid, a.attemptno
               FROM {local_lworkspace_acreview} r JOIN {local_lworkspace_acattempt} a ON a.id = r.attemptid
              WHERE r.reviewerid = :reviewerid AND a.userid <> :otheruserid ORDER BY r.id',
            ['reviewerid' => $userid, 'otheruserid' => $userid]
        );
        self::export_records($writer, ['local_lworkspace_acreview', 'reviewer'], 'records', array_values($reviewerreviews));

        $reviewdrafts = $DB->get_records_sql(
            'SELECT d.*, a.kind, a.lessonid, a.attemptno, a.userid AS candidateid
               FROM {local_lworkspace_acrdraft} d JOIN {local_lworkspace_acattempt} a ON a.id = d.attemptid
              WHERE a.userid = :ownerid OR d.reviewerid = :reviewerid ORDER BY d.id',
            ['ownerid' => $userid, 'reviewerid' => $userid]
        );
        foreach ($reviewdrafts as $draft) {
            if ((int)$draft->candidateid !== $userid) {
                unset($draft->candidateid);
            } else {
                $draft->candidateid = $userid;
            }
        }
        self::export_records($writer, ['local_lworkspace_acrdraft'], 'records', array_values($reviewdrafts));

        $permits = $DB->get_records('local_lworkspace_acpermit', ['userid' => $userid], 'id ASC');
        self::export_records($writer, ['local_lworkspace_acpermit', 'learner'], 'records', array_values($permits));
        $authoredpermits = $DB->get_records_sql(
            'SELECT id, reviewerid, kind, lessonid, attemptno, reason, timecreated FROM {local_lworkspace_acpermit} WHERE reviewerid = :reviewerid AND userid <> :ownerid ORDER BY id',
            ['reviewerid' => $userid, 'ownerid' => $userid]
        );
        self::export_records($writer, ['local_lworkspace_acpermit', 'reviewer'], 'records', array_values($authoredpermits));

        $notices = $DB->get_records('local_lworkspace_notice', ['authorid' => $userid], 'id ASC');
        self::export_records($writer, ['local_lworkspace_notice'], 'records', array_values($notices));
        $help = $DB->get_records('local_lworkspace_help', ['userid' => $userid], 'id ASC');
        self::export_records($writer, ['local_lworkspace_help', 'requester'], 'records', array_values($help));
        $handledhelp = $DB->get_records_sql(
            'SELECT id, handledby, reply, timemodified FROM {local_lworkspace_help} WHERE handledby = :handlerid AND userid <> :ownerid ORDER BY id',
            ['handlerid' => $userid, 'ownerid' => $userid]
        );
        self::export_records($writer, ['local_lworkspace_help', 'responder'], 'records', array_values($handledhelp));
        $policies = $DB->get_records('local_lworkspace_acpolicy', ['authorid' => $userid], 'id ASC');
        self::export_records($writer, ['local_lworkspace_acpolicy'], 'records', array_values($policies));

        $authoredattendance = $DB->get_records_sql(
            'SELECT id, sessionid, actorid, status, watched, note, timemodified FROM {local_lworkspace_attend} WHERE actorid = :actorid AND userid <> :ownerid ORDER BY id',
            ['actorid' => $userid, 'ownerid' => $userid]
        );
        self::export_records($writer, ['local_lworkspace_attend', 'recorded_by_user'], 'records', array_values($authoredattendance));

        $sessions = $DB->get_records('local_lworkspace_session', ['authorid' => $userid], 'id ASC');
        foreach ($sessions as $session) {
            self::export_records($writer, ['local_lworkspace_session', (string)$session->id], 'record', [$session]);
            $writer->export_area_files(['local_lworkspace_session', (string)$session->id], 'local_learning_workspace', 'sessionrecord', (string)$session->id);
            $writer->export_area_files(['local_lworkspace_session', (string)$session->id], 'local_learning_workspace', 'sessioncaption', (string)$session->id);
        }
        $groupsessions = $DB->get_records_sql(
            'SELECT DISTINCT s.id, s.groupid, s.lessonid, s.title, s.startsat, s.duration, s.timezone, s.meeturl,
                    s.agenda, s.summary, s.recordurl, s.recordname, s.captionname, s.published, s.recordnotice,
                    s.status, s.revision, s.timecreated, s.timemodified
               FROM {local_lworkspace_session} s JOIN {groups_members} gm ON gm.groupid = s.groupid
              WHERE gm.userid = :userid AND s.authorid <> :authorid ORDER BY s.id',
            ['userid' => $userid, 'authorid' => $userid]
        );
        foreach ($groupsessions as $session) {
            // Export only fields visible to this group member on academy_session.php.
            if ($session->status !== 'planned') {
                $session->meeturl = '';
            }
            if (empty($session->published)) {
                $session->recordurl = '';
                $session->recordname = '';
                $session->captionname = '';
                $session->recordnotice = 0;
            }
            if (empty($session->published) && $session->status !== 'finished') {
                $session->summary = '';
            }
            self::export_records($writer, ['local_lworkspace_session', (string)$session->id], 'shared_session', [$session]);
            if (!empty($session->published)) {
                $writer->export_area_files(['local_lworkspace_session', (string)$session->id], 'local_learning_workspace', 'sessionrecord', (string)$session->id);
                $writer->export_area_files(['local_lworkspace_session', (string)$session->id], 'local_learning_workspace', 'sessioncaption', (string)$session->id);
            }
        }
    }

    /** Export a collection of rows only when there is something to disclose. */
    private static function export_records($writer, array $subcontext, string $key, array $records): void {
        if ($records) {
            $writer->export_data($subcontext, (object)[$key => array_values($records)]);
        }
    }

    /**
     * Delete the user's own records and anonymise authored references on shared records.
     *
     * @param int $userid
     */
    private static function delete_user_data(int $userid): void {
        global $DB;
        if ($userid <= 0) {
            return;
        }
        $transaction = $DB->start_delegated_transaction();

        $lessonids = array_map('intval', array_keys($DB->get_records('local_lworkspace_lesson', ['userid' => $userid], '', 'id')));
        if ($lessonids) {
            [$lessonsql, $lessonparams] = $DB->get_in_or_equal($lessonids, SQL_PARAMS_NAMED, 'ownedlesson');
            $DB->delete_records_select('local_lworkspace_lanswer', 'lessonid ' . $lessonsql, $lessonparams);
        }
        $attemptids = array_map('intval', array_keys($DB->get_records('local_lworkspace_acattempt', ['userid' => $userid], '', 'id')));
        if ($attemptids) {
            [$attemptsql, $attemptparams] = $DB->get_in_or_equal($attemptids, SQL_PARAMS_NAMED, 'ownedattempt');
            $DB->delete_records_select('local_lworkspace_acreview', 'attemptid ' . $attemptsql, $attemptparams);
            $DB->delete_records_select('local_lworkspace_acrdraft', 'attemptid ' . $attemptsql, $attemptparams);
        }

        $sessionids = array_map('intval', array_keys($DB->get_records('local_lworkspace_session', ['authorid' => $userid], '', 'id')));
        if ($sessionids && function_exists('get_file_storage')) {
            $storage = get_file_storage();
            $contextid = \context_system::instance()->id;
            foreach ($sessionids as $sessionid) {
                $storage->delete_area_files($contextid, 'local_learning_workspace', 'sessionrecord', $sessionid);
                $storage->delete_area_files($contextid, 'local_learning_workspace', 'sessioncaption', $sessionid);
            }
        }

        if ($lessonids) {
            $DB->delete_records('local_lworkspace_lesson', ['userid' => $userid]);
        }
        $DB->delete_records('local_lworkspace_acattempt', ['userid' => $userid]);
        $DB->delete_records('local_lworkspace_acdraft', ['userid' => $userid]);
        $DB->delete_records('local_lworkspace_acrdraft', ['reviewerid' => $userid]);
        $DB->delete_records('local_lworkspace_nread', ['userid' => $userid]);
        $DB->delete_records('local_lworkspace_help', ['userid' => $userid]);
        $DB->delete_records('local_lworkspace_acpermit', ['userid' => $userid]);
        $DB->delete_records('local_lworkspace_acstart', ['userid' => $userid]);
        $DB->delete_records('local_lworkspace_onboard', ['userid' => $userid]);
        $DB->delete_records('local_lworkspace_bevent', ['userid' => $userid]);

        // Preserve shared feedback and policy outcomes for learners while removing account attribution.
        $DB->set_field('local_lworkspace_acreview', 'reviewerid', 0, ['reviewerid' => $userid]);
        $DB->execute('UPDATE {local_lworkspace_acpermit} SET reviewerid = 0, reason = :reason WHERE reviewerid = :userid', [
            'reason' => get_string('privacy:metadata:anonymisedreason', 'local_learning_workspace'),
            'userid' => $userid,
        ]);
        $DB->set_field('local_lworkspace_acpolicy', 'authorid', 0, ['authorid' => $userid]);
        $DB->set_field('local_lworkspace_help', 'handledby', 0, ['handledby' => $userid]);
        $DB->set_field('local_lworkspace_attend', 'actorid', 0, ['actorid' => $userid]);

        if ($sessionids) {
            $DB->execute('UPDATE {local_lworkspace_session}
                             SET authorid = 0, title = :title, meeturl = :empty, agenda = :empty2, summary = :empty3,
                                 recordurl = :empty4, recordname = :empty5, captionname = :empty6,
                                 published = 0, recordnotice = 0
                           WHERE authorid = :userid', [
                'title' => get_string('privacy:metadata:removedsessiontitle', 'local_learning_workspace'),
                'empty' => '', 'empty2' => '', 'empty3' => '', 'empty4' => '', 'empty5' => '', 'empty6' => '',
                'userid' => $userid,
            ]);
        }
        // Keep reply threading intact, replacing authored public text with a neutral tombstone.
        $DB->execute('UPDATE {local_lworkspace_discuss}
                         SET userid = 0, body = :body, active = 0, pinned = 0, timemodified = :timemodified
                       WHERE userid = :userid', [
            'body' => get_string('privacy:metadata:removedpost', 'local_learning_workspace'),
            'timemodified' => time(), 'userid' => $userid,
        ]);
        // Keep notice IDs stable for other users' read tracking, but remove the author's content.
        $DB->execute('UPDATE {local_lworkspace_notice}
                         SET authorid = 0, title = :title, body = :body, active = 0, timemodified = :timemodified
                       WHERE authorid = :userid', [
            'title' => get_string('privacy:metadata:removednoticetitle', 'local_learning_workspace'),
            'body' => get_string('privacy:metadata:removednotice', 'local_learning_workspace'),
            'timemodified' => time(), 'userid' => $userid,
        ]);

        foreach (self::PREFERENCE_NAMES as $name) {
            unset_user_preference($name, $userid);
        }
        $transaction->allow_commit();
    }
}
