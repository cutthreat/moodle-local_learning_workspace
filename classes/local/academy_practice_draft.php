<?php
namespace local_learning_workspace\local;
defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/academy_access.php');
require_once(__DIR__ . '/academy_day_content.php');
require_once(__DIR__ . '/lesson_catalog.php');

/** Private ungraded practice in the existing draft store, isolated from final drafts. */
final class academy_practice_draft {
    public static function identity(string $lesson): array {
        if (!preg_match('/^L[2-7]$/', $lesson)) {
            throw new \invalid_parameter_exception('Unknown practice lesson');
        }
        $day = academy_day_content::day($lesson);
        return ['version' => academy_day_content::VERSION, 'hash' => hash('sha256', json_encode([
            'lesson' => $lesson, 'main' => $day['formative_case'],
            'tool' => $day['tool_practice'] ?? null,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR))];
    }

    private static function owner(int $userid): void {
        global $USER;
        if ($userid <= 0 || (int)$USER->id !== $userid) {
            throw new \moodle_exception('nopermissions', 'error');
        }
        academy_access::require_candidate();
    }

    public static function read(int $userid, string $lesson): array {
        global $DB;
        self::owner($userid);
        $identity = self::identity($lesson);
        $row = $DB->get_record('local_lworkspace_acdraft', [
            'userid' => $userid, 'kind' => 'practice', 'variant' => (int)substr($lesson, 1),
        ]);
        $result = ['revision' => 0, 'main' => '', 'tool' => '', 'saved_at' => 0, 'hash' => $identity['hash']];
        // The scoped exercise hash is authoritative for this ungraded draft. A new
        // editorial label alone must not hide unchanged tasks or reset their CAS.
        // Reads do not migrate rows; an explicit save records the current edition.
        if (!$row || !hash_equals($identity['hash'], $row->bankhash)) {
            return $result;
        }
        $saved = json_decode($row->answersjson, true, 16, JSON_THROW_ON_ERROR);
        if (!is_array($saved) || !is_int($saved['revision'] ?? null) || $saved['revision'] < 0 ||
                !is_string($saved['main'] ?? null) || !is_string($saved['tool'] ?? null)) {
            throw new \coding_exception('Invalid private practice draft');
        }
        return $saved + ['saved_at' => (int)$row->timemodified, 'hash' => $identity['hash']];
    }

    public static function save(int $userid, string $lesson, string $field, string $text,
            int $revision, string $expectedhash): array {
        global $DB;
        self::owner($userid);
        $identity = self::identity($lesson);
        $day = academy_day_content::day($lesson);
        if (!in_array($field, ['main', 'tool'], true) || ($field === 'tool' && empty($day['tool_practice'])) ||
                mb_strlen($text) > 2000 || $revision < 0 || !hash_equals($identity['hash'], $expectedhash)) {
            throw new \invalid_parameter_exception('Invalid practice draft');
        }
        $lock = \core\lock\lock_config::get_lock_factory('local_learning_workspace')->get_lock(
            'practice-draft-' . $userid . '-' . $lesson, 5);
        if (!$lock) {
            throw new \moodle_exception('concurrencyconflict', 'local_learning_workspace');
        }
        try {
            $current = self::read($userid, $lesson);
            if ($revision !== $current['revision']) {
                throw new \moodle_exception('concurrencyconflict', 'local_learning_workspace');
            }
            $current[$field] = $text;
            $payload = ['revision' => $revision + 1, 'main' => $current['main'], 'tool' => $current['tool']];
            $key = ['userid' => $userid, 'kind' => 'practice', 'variant' => (int)substr($lesson, 1)];
            $record = $DB->get_record('local_lworkspace_acdraft', $key) ?: (object)$key;
            $record->attemptno = 0;
            $record->bankversion = $identity['version'];
            $record->bankhash = $identity['hash'];
            $record->answersjson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $record->timemodified = time();
            if (!empty($record->id)) {
                $DB->update_record('local_lworkspace_acdraft', $record);
            } else {
                $record->id = $DB->insert_record('local_lworkspace_acdraft', $record);
            }
            return self::read($userid, $lesson);
        } finally {
            $lock->release();
        }
    }
}
