<?php
namespace local_learning_workspace\local;

defined('MOODLE_INTERNAL') || die();

/** Access to the seven-day academy is tied to its Moodle course enrolment. */
final class academy_access {
    private const SHORTNAME = 'LEARNING-WORKSPACE-DEMO';
    private const IDNUMBER = 'learning-workspace-demo';

    public static function course_context(): ?\context_course {
        global $DB;
        $course = $DB->get_record('course', [
            'shortname' => self::SHORTNAME,
            'idnumber' => self::IDNUMBER,
        ], 'id');
        return $course ? \context_course::instance((int)$course->id) : null;
    }

    public static function is_candidate(int $userid = 0): bool {
        global $USER;
        $userid = $userid > 0 ? $userid : (int)($USER->id ?? 0);
        $context = self::course_context();
        return $userid > 0 && $context !== null &&
            is_enrolled($context, $userid, '', true) &&
            has_capability('local/learning_workspace:view', $context, $userid) &&
            has_capability('local/learning_workspace:submitassessment', $context, $userid);
    }

    public static function require_candidate(): \context_course {
        global $USER;
        $context = self::course_context();
        if ($context === null || !self::is_candidate((int)$USER->id)) {
            throw new \moodle_exception('nopermissions', 'error');
        }
        require_capability('local/learning_workspace:view', $context);
        require_capability('local/learning_workspace:submitassessment', $context);
        return $context;
    }

    public static function can_view_course(): bool {
        global $USER;
        return !empty($USER->id) && (has_capability('local/learning_workspace:reviewassessment', \context_system::instance()) ||
            has_capability('local/learning_workspace:approveadmission', \context_system::instance()) || self::is_candidate());
    }

    /** Staff share course content, never candidate submission or reviewer authority. */
    public static function require_viewer(): \context {
        if (has_capability('local/learning_workspace:reviewassessment', \context_system::instance()) ||
                has_capability('local/learning_workspace:approveadmission', \context_system::instance())) {
            return \context_system::instance();
        }
        return self::require_candidate();
    }
}
