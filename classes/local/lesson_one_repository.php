<?php
namespace local_learning_workspace\local;

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/academy_access.php');

/** Self-guided practice has a separate edition and never awards certification or work access. */
final class lesson_one_repository {
    public static function read(int $userid): array {
        global $DB;
        $row = $DB->get_record('local_lworkspace_lesson', ['userid' => $userid, 'edition' => lesson_one::VERSION]);
        if (!$row) {
            $content = lesson_one::content();
            $previous = false;
            foreach (lesson_one::PRIOR_EDITIONS as $edition) {
                $previous = $DB->get_record('local_lworkspace_lesson', ['userid'=>$userid, 'edition'=>$edition]);
                if ($previous) { break; }
            }
            $state = lesson_one::initial();
            if ($previous) {
                if (!hash_equals($previous->contenthash, hash('sha256', $previous->contentjson))) {
                    throw new \coding_exception('Previous lesson snapshot integrity check failed.');
                }
                $state = lesson_one::carry_previous_practice(json_decode($previous->statejson, true, 64, JSON_THROW_ON_ERROR));
            }
            return ['id' => 0, 'revision' => 0, 'content' => $content, 'state' => $state,
                'previousedition' => (bool)$previous, 'hash' => hash('sha256', lesson_one::encode($content))];
        }
        if (!hash_equals($row->contenthash, hash('sha256', $row->contentjson))) {
            throw new \coding_exception('Lesson snapshot integrity check failed.');
        }
        return ['id' => (int)$row->id, 'revision' => (int)$row->revision,
            'content' => json_decode($row->contentjson, true, 64, JSON_THROW_ON_ERROR),
            'state' => json_decode($row->statejson, true, 64, JSON_THROW_ON_ERROR), 'hash' => $row->contenthash];
    }

    public static function history(int $userid, int $lessonid, int $stage): array {
        global $DB;
        $history = [];
        if ($lessonid) {
            $DB->get_record('local_lworkspace_lesson', ['id' => $lessonid, 'userid' => $userid], 'id', MUST_EXIST);
            $history = array_values($DB->get_records('local_lworkspace_lanswer',
                ['lessonid' => $lessonid, 'stage' => $stage], 'revision DESC', '*', 0, 20));
        }
        foreach (lesson_one::PRIOR_EDITIONS as $edition) {
            $previous = $DB->get_record('local_lworkspace_lesson', ['userid'=>$userid, 'edition'=>$edition], 'id');
            if ($previous) {
                foreach ($DB->get_records('local_lworkspace_lanswer', ['lessonid'=>$previous->id, 'stage'=>$stage], 'revision DESC', '*', 0, 20) as $entry) {
                    $entry->previousedition = true;
                    $history[] = $entry;
                }
            }
        }
        return $history;
    }

    public static function save(int $userid, int $revision, string $hash, string $action, int $stage, string $value): array {
        global $DB, $USER;
        academy_access::require_candidate();
        if ($userid !== (int)$USER->id) { throw new \coding_exception('Cannot write another learner state.'); }
        $factory = \core\lock\lock_config::get_lock_factory('local_learning_workspace');
        $lock = $factory->get_lock('reference-lesson-one-' . $userid, 5);
        if (!$lock) { throw new \DomainException('Ответ сейчас сохраняется. Подождите немного и повторите.'); }
        try {
            $tx = $DB->start_delegated_transaction();
            try {
                $current = self::read($userid);
                if ($revision !== $current['revision'] || !hash_equals($current['hash'], $hash)) {
                    throw new \DomainException('Урок изменён в другой вкладке. Скопируйте свой текст и обновите страницу; он не будет перезаписан.');
                }
                $state = lesson_one::transition($current['state'], $current['content'], $action, $stage, $value);
                $row = (object)['userid' => $userid, 'edition' => lesson_one::VERSION,
                    'contenthash' => $current['hash'], 'contentjson' => lesson_one::encode($current['content']),
                    'statejson' => lesson_one::encode($state), 'revision' => $revision + 1, 'timemodified' => time()];
                if ($current['id']) {
                    $row->id = $current['id'];
                    $DB->update_record('local_lworkspace_lesson', $row);
                } else {
                    $row->id = $DB->insert_record('local_lworkspace_lesson', $row);
                }
                if ($action === 'submit') {
                    $DB->insert_record('local_lworkspace_lanswer', (object)['lessonid' => $row->id,
                        'stage' => $stage, 'revision' => $row->revision, 'answertext' => trim($value), 'timecreated' => time()]);
                }
                $tx->allow_commit();
                return ['id' => (int)$row->id, 'revision' => (int)$row->revision,
                    'content' => $current['content'], 'state' => $state, 'hash' => $current['hash']];
            } catch (\Throwable $error) {
                $tx->rollback($error);
                throw $error;
            }
        } finally {
            $lock->release();
        }
    }
}

