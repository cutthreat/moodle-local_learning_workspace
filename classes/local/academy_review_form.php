<?php
namespace local_learning_workspace\local;
defined('MOODLE_INTERNAL') || die();

/** Field-level guidance and private reviewer drafts. Never commits an assessment. */
final class academy_review_form {
    public const TABLE = 'local_lworkspace_acrdraft';

    public static function upgrade(): void {
        global $DB;
        $manager = $DB->get_manager();
        $table = new \xmldb_table(self::TABLE);
        if ($manager->table_exists($table)) { return; }
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        foreach (['attemptid', 'reviewerid', 'revision', 'timemodified'] as $field) {
            $table->add_field($field, XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
        }
        $table->add_field('payloadjson', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('attempt_reviewer_uix', XMLDB_INDEX_UNIQUE, ['attemptid', 'reviewerid']);
        $manager->create_table($table);
    }

    /** Key is the actual input ID, so server and browser point at the same field. */
    public static function errors(array $detail, array $payload): array {
        $errors = [];
        $five = $detail['attempt']->kind === 'final' &&
            preg_match('/-final\.(\d+)-v/', $detail['attempt']->bankversion, $version) && (int)$version[1] >= 10;
        foreach ($detail['questions'] as $number => $question) {
            if (($question['type'] ?? '') !== 'text') { continue; }
            $qid = (string)$question['id'];
            $criteria = $five ? ($question['criteria'] ?? []) : [['id'=>$qid,'label'=>'Соответствие критерию']];
            foreach ($criteria as $criterion) {
                $id = $criterion['id'];
                $label = 'Вопрос '.($number + 1).' · '.$criterion['label'];
                $rating = (string)($payload['rubric'][$id] ?? '');
                if (!in_array($rating, $five ? ['0','1','2'] : ['pass','fail'], true)) {
                    $errors['rubric-'.$id] = $label.': выберите оценку.';
                    continue;
                }
                if (!in_array($rating, ['0','1','fail'], true)) { continue; }
                $quote = trim((string)($payload['quotes'][$id] ?? ''));
                $next = trim((string)($payload['nextsteps'][$id] ?? ''));
                if (\core_text::strlen($quote) < 8 || \core_text::strlen($quote) > 300) {
                    $errors['quote-'.$id] = $label.': цитата должна содержать от 8 до 300 символов.';
                } elseif (!academy_assessment_repository::quote_matches_answer($qid,
                        (string)($detail['answers'][$qid] ?? ''), $quote)) {
                    $errors['quote-'.$id] = $label.': такого фрагмента нет в ответе. Выделите и вставьте точную цитату.';
                }
                if (\core_text::strlen($next) < 15 || \core_text::strlen($next) > 1000) {
                    $errors['next-'.$id] = $label.': опишите конкретное исправление (15–1000 символов).';
                }
            }
        }
        $feedback = trim((string)($payload['feedback'] ?? ''));
        if (\core_text::strlen($feedback) < 10 || \core_text::strlen($feedback) > 4000) {
            $errors['review-feedback'] = 'Комментарий кандидату: от 10 до 4000 символов.';
        }
        if (!in_array($payload['decision'] ?? '', ['ready','rework','not_ready',
                'practice_accepted','practice_rework','practice_feedback'], true)) {
            $errors['review-decision'] = 'Выберите итоговое решение.';
        }
        return $errors;
    }

    private static function assert_reviewer(int $attemptid): array {
        global $USER;
        require_capability('local/learning_workspace:reviewassessment', \context_system::instance());
        $detail = academy_assessment_repository::attempt_details($attemptid);
        if ((int)$detail['attempt']->userid === (int)$USER->id) {
            throw new \moodle_exception('assessmentreviewforbidden', 'local_learning_workspace');
        }
        return $detail;
    }

    public static function draft(int $attemptid): ?object {
        global $DB, $USER;
        $detail = self::assert_reviewer($attemptid);
        if ($detail['review']) { return null; }
        return $DB->get_record(self::TABLE, ['attemptid'=>$attemptid,'reviewerid'=>(int)$USER->id]) ?: null;
    }

    public static function save_draft(int $attemptid, array $payload, int $expectedrevision): object {
        global $DB, $USER;
        $detail = self::assert_reviewer($attemptid);
        if ($detail['review']) { throw new \DomainException('Работа уже проверена. Черновик не сохранён.'); }
        $safe = [];
        foreach (['rubric','quotes','nextsteps'] as $key) {
            $safe[$key] = [];
            foreach (array_slice((array)($payload[$key] ?? []), 0, 100, true) as $id=>$value) {
                if (!is_string($value) || strlen($value) > 12000 || !preg_match('/^[a-zA-Z0-9_-]{1,80}$/D',(string)$id)) {
                    throw new \DomainException('Недопустимый размер или формат поля черновика.');
                }
                $safe[$key][$id] = $value;
            }
        }
        foreach (['decision','feedback','idemkey'] as $key) { $safe[$key] = (string)($payload[$key] ?? ''); }
        $json = json_encode($safe, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        if (strlen($json) > 262144) { throw new \DomainException('Черновик слишком большой.'); }
        $lock = \core\lock\lock_config::get_lock_factory('local_learning_workspace')
            ->get_lock('review-draft-'.$attemptid.'-'.(int)$USER->id, 10);
        if (!$lock) { throw new \DomainException('Черновик занят. Повторите сохранение.'); }
        try {
            if ($DB->record_exists('local_lworkspace_acreview', ['attemptid'=>$attemptid])) {
                throw new \DomainException('Работа уже проверена. Обновите страницу.');
            }
            $row = $DB->get_record(self::TABLE, ['attemptid'=>$attemptid,'reviewerid'=>(int)$USER->id]);
            if ((int)($row->revision ?? 0) !== $expectedrevision) {
                throw new \DomainException('Черновик изменён в другой вкладке. Введённое здесь сохранено в форме; сверяйте его с последним черновиком перед заменой.');
            }
            $record = (object)['attemptid'=>$attemptid,'reviewerid'=>(int)$USER->id,
                'revision'=>$expectedrevision + 1,'timemodified'=>time(),'payloadjson'=>$json];
            if ($row) { $record->id=$row->id; $DB->update_record(self::TABLE,$record); }
            else { $record->id=$DB->insert_record(self::TABLE,$record); }
            return $record;
        } finally { $lock->release(); }
    }
}
