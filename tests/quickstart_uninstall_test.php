<?php
defined('MOODLE_INTERNAL') || die();

/** Ensures plugin removal does not delete unrelated native tours. */
final class local_learning_workspace_quickstart_uninstall_test extends advanced_testcase {
    private function create_tour(string $name, string $path): \tool_usertours\tour {
        $tour = new \tool_usertours\tour();
        $tour->set_name($name);
        $tour->set_description('Test tour');
        $tour->set_pathmatch($path);
        $tour->set_enabled(true);
        $tour->set_endtourlabel('Close');
        $tour->persist();
        return $tour;
    }

    public function test_uninstall_removes_owned_tour_and_preserves_other_tour(): void {
        $this->resetAfterTest();
        global $DB;
        $owned = $this->create_tour(
            'Learning Workspace · candidate · course · v2',
            '/local/learning_workspace/__quickstart_candidate_course__'
        );
        $other = $this->create_tour(
            'Other plugin tour',
            '/local/learning_workspace/__quickstart_candidate_lesson__'
        );
        $learner = $this->getDataGenerator()->create_user();
        set_user_preference(\tool_usertours\tour::TOUR_LAST_COMPLETED_BY_USER . $owned->get_id(), (string)time(), $learner->id);
        set_config('quickstart_tour_candidate_course', $owned->get_id(), 'local_learning_workspace');
        set_config('quickstart_tour_candidate_lesson', $other->get_id(), 'local_learning_workspace');

        \local_learning_workspace\local\academy_quickstart::uninstall();

        $this->assertFalse($DB->record_exists('tool_usertours_tours', ['id' => $owned->get_id()]));
        $this->assertTrue($DB->record_exists('tool_usertours_tours', ['id' => $other->get_id()]));
        $this->assertNull(get_user_preferences(\tool_usertours\tour::TOUR_LAST_COMPLETED_BY_USER . $owned->get_id(), null, $learner->id));
        $this->assertFalse(get_config('local_learning_workspace', 'quickstart_tour_candidate_course'));
        $this->assertFalse(get_config('local_learning_workspace', 'quickstart_tour_candidate_lesson'));
    }
}
