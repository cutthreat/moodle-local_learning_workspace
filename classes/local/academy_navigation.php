<?php
namespace local_learning_workspace\local;

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/academy_access.php');

/** Role-specific entries in Moodle's actual primary navigation. */
final class academy_navigation {
    /** Capability-based routing; unrelated Moodle users keep their dashboard. */
    public static function landing_path(): ?string {
        // Every authorized academy role enters the same current course shell.
        if (academy_access::can_view_course()) {
            return '/local/learning_workspace/course.php';
        }
        return null;
    }

    public static function extend_primary(\core\hook\navigation\primary_extend $hook): void {
        global $USER;
        if (!isloggedin() || isguestuser($USER)) {
            return;
        }
        $context = \context_system::instance();
        $primary = $hook->get_primaryview();
        if (academy_access::can_view_course()) {
            $primary->add(
                get_string('academyhome', 'local_learning_workspace'),
                new \moodle_url('/local/learning_workspace/course.php'),
                \navigation_node::TYPE_CUSTOM,
                null,
                'learning-workspace'
            );
        }
        if (has_capability('local/learning_workspace:reviewassessment', $context)) {
            $primary->add(
                get_string('academyreview', 'local_learning_workspace'),
                new \moodle_url('/local/learning_workspace/academy_review.php'),
                \navigation_node::TYPE_CUSTOM,
                null,
                'learning-workspace-review'
            );
        }
    }
}
