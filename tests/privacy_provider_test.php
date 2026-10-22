<?php
defined('MOODLE_INTERNAL') || die();

/** Integration tests for the Learning Workspace Privacy API provider. */
final class local_learning_workspace_privacy_provider_test extends advanced_testcase {
    private function create_attempt(int $userid, string $suffix): int {
        global $DB;
        return (int)$DB->insert_record('local_lworkspace_acattempt', (object)[
            'userid' => $userid,
            'kind' => 'final',
            'lessonid' => 'FINAL',
            'bankversion' => 'demo-v1',
            'bankhash' => hash('sha256', 'demo-bank'),
            'attemptno' => 1,
            'status' => 'submitted',
            'hasdraft' => 0,
            'score' => 50,
            'passmark' => 80,
            'answersjson' => '{"DEMO-Q1":"a"}',
            'questionsjson' => '{"DEMO-Q1":"Sample prompt"}',
            'feedbackjson' => '{}',
            'policyjson' => '{}',
            'answershash' => hash('sha256', $suffix),
            'idemkey' => 'privacy-test-' . $suffix,
            'timecreated' => time(),
        ]);
    }

    public function test_context_discovery_user_list_and_export(): void {
        $this->resetAfterTest();
        $learner = $this->getDataGenerator()->create_user();
        $reviewer = $this->getDataGenerator()->create_user();
        $attemptid = $this->create_attempt((int)$learner->id, 'learner');
        global $DB;
        $DB->insert_record('local_lworkspace_acreview', (object)[
            'attemptid' => $attemptid,
            'reviewerid' => $reviewer->id,
            'decision' => 'needs_rework',
            'rubricjson' => '{}',
            'feedback' => 'Demo feedback',
            'idemkey' => 'privacy-review-' . $attemptid,
            'timecreated' => time(),
        ]);
        set_user_preference('local_lworkspace_qs_examiner_course_v1', '{"status":"skipped"}', $reviewer->id);
        set_user_preference('local_lworkspace_start_examiner', 1, $reviewer->id);

        $learnercontexts = \local_learning_workspace\privacy\provider::get_contexts_for_userid((int)$learner->id);
        $reviewercontexts = \local_learning_workspace\privacy\provider::get_contexts_for_userid((int)$reviewer->id);
        $this->assertContains(SYSCONTEXTID, $learnercontexts->get_contextids());
        $this->assertContains(SYSCONTEXTID, $reviewercontexts->get_contextids());

        $userlist = new \core_privacy\local\request\userlist(\context_system::instance(), 'local_learning_workspace');
        \local_learning_workspace\privacy\provider::get_users_in_context($userlist);
        $this->assertContains((int)$learner->id, $userlist->get_userids());
        $this->assertContains((int)$reviewer->id, $userlist->get_userids());

        // Exercise both request-provider paths against Moodle's real export writer in PHPUnit mode.
        \core_privacy\local\request\writer::reset();
        \core_privacy\local\request\writer::setup_real_writer_instance();
        \local_learning_workspace\privacy\provider::export_user_data(
            new \core_privacy\local\request\approved_contextlist($reviewer, 'local_learning_workspace', [SYSCONTEXTID])
        );
        \local_learning_workspace\privacy\provider::export_user_preferences((int)$reviewer->id);
        $this->assertSame('{"status":"skipped"}', get_user_preferences('local_lworkspace_qs_examiner_course_v1', null, $reviewer->id));
        $this->assertSame('1', get_user_preferences('local_lworkspace_start_examiner', null, $reviewer->id));
    }

    public function test_deletion_removes_subject_records_and_anonymises_reviewer_link(): void {
        $this->resetAfterTest();
        $learner = $this->getDataGenerator()->create_user();
        $reviewer = $this->getDataGenerator()->create_user();
        $otherlearner = $this->getDataGenerator()->create_user();
        $attemptid = $this->create_attempt((int)$learner->id, 'owned');
        $otherattemptid = $this->create_attempt((int)$otherlearner->id, 'other');
        set_user_preference('local_lworkspace_start_examiner', 1, $reviewer->id);
        global $DB;
        $reviewid = (int)$DB->insert_record('local_lworkspace_acreview', (object)[
            'attemptid' => $attemptid,
            'reviewerid' => $reviewer->id,
            'decision' => 'needs_rework',
            'rubricjson' => '{}',
            'feedback' => 'Learner feedback',
            'idemkey' => 'privacy-review-owned',
            'timecreated' => time(),
        ]);
        $otherreviewid = (int)$DB->insert_record('local_lworkspace_acreview', (object)[
            'attemptid' => $otherattemptid,
            'reviewerid' => $reviewer->id,
            'decision' => 'needs_rework',
            'rubricjson' => '{}',
            'feedback' => 'Other learner feedback',
            'idemkey' => 'privacy-review-other',
            'timecreated' => time(),
        ]);
        $reviewdraftid = (int)$DB->insert_record('local_lworkspace_acrdraft', (object)[
            'attemptid' => $otherattemptid,
            'reviewerid' => $reviewer->id,
            'revision' => 1,
            'timemodified' => time(),
            'payloadjson' => '{"note":"private reviewer draft"}',
        ]);

        \local_learning_workspace\privacy\provider::delete_data_for_user(
            new \core_privacy\local\request\approved_contextlist($learner, 'local_learning_workspace', [SYSCONTEXTID])
        );
        $this->assertFalse($DB->record_exists('local_lworkspace_acattempt', ['id' => $attemptid]));
        $this->assertFalse($DB->record_exists('local_lworkspace_acreview', ['id' => $reviewid]));
        $this->assertTrue($DB->record_exists('local_lworkspace_acattempt', ['id' => $otherattemptid]));
        $this->assertTrue($DB->record_exists('local_lworkspace_acreview', ['id' => $otherreviewid]));
        $this->assertTrue($DB->record_exists('local_lworkspace_acrdraft', ['id' => $reviewdraftid]));

        \local_learning_workspace\privacy\provider::delete_data_for_user(
            new \core_privacy\local\request\approved_contextlist($reviewer, 'local_learning_workspace', [SYSCONTEXTID])
        );
        $remainingreview = $DB->get_record('local_lworkspace_acreview', ['id' => $otherreviewid], '*', MUST_EXIST);
        $this->assertSame(0, (int)$remainingreview->reviewerid);
        $this->assertSame('Other learner feedback', $remainingreview->feedback);
        $this->assertFalse($DB->record_exists('local_lworkspace_acrdraft', ['id' => $reviewdraftid]));
        $this->assertNull(get_user_preferences('local_lworkspace_start_examiner', null, $reviewer->id));
    }
}
