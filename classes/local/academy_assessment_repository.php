<?php
namespace local_learning_workspace\local;

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/academy_dialogue_answer_codec.php');
require_once(__DIR__ . '/academy_access.php');
require_once(__DIR__ . '/academy_attempt_policy.php');

/**
 * Seven-day academy assessments. These records are learning evidence only;
 * nothing here grants access to client work or changes the legacy M0-M10 run.
 */
final class academy_assessment_repository {
    private const ATTEMPT = 'local_lworkspace_acattempt';
    private const REVIEW = 'local_lworkspace_acreview';
    private const DRAFT = 'local_lworkspace_acdraft';
    private const DEFAULT_PASSMARK = 80; // Pilot threshold, explicit and replaceable after calibration.

    public static function day_summary(int $userid): array {
        global $DB;
        self::assert_owner_or_reviewer($userid);
        $result = [];
        for ($day = 1; $day <= 7; $day++) {
            $lessonid = 'L' . $day;
            $history = array_values($DB->get_records(self::ATTEMPT,
                ['userid' => $userid, 'kind' => 'day', 'lessonid' => $lessonid], 'attemptno DESC'));
            $reviewsbyattempt = [];
            foreach ($history as $attempt) {
                $review = $DB->get_record(self::REVIEW, ['attemptid' => (int)$attempt->id]);
                if ($review) {
                    $reviewsbyattempt[(int)$attempt->id] = $review;
                }
            }
            $result[$lessonid] = [
                'lessonid' => $lessonid,
                'attempts' => count($history),
                'latest' => $history[0] ?? null,
                'history' => $history,
                'reviews_by_attempt' => $reviewsbyattempt,
                'review' => $history ? ($reviewsbyattempt[(int)$history[0]->id] ?? null) : null,
            ];
        }
        return $result;
    }

    public static function submit_day(int $userid, string $lessonid, array $answers, string $idemkey): object {
        self::assert_owner($userid);
        $lessonid = strtoupper(trim($lessonid));
        if (!preg_match('/^L[1-7]$/', $lessonid)) {
            throw new \moodle_exception('invalidlesson', 'local_learning_workspace');
        }
        return self::submit($userid, 'day', $lessonid, academy_question_bank::day($lessonid), $answers, $idemkey);
    }

    public static function final_summary(int $userid): array {
        global $DB;
        self::assert_owner_or_reviewer($userid);
        $history = array_values($DB->get_records(self::ATTEMPT,
            ['userid' => $userid, 'kind' => 'final', 'lessonid' => 'FINAL'], 'attemptno DESC'));
        $latest = $history[0] ?? null;
        $reviewsbyattempt = [];
        foreach ($history as $attempt) {
            $savedreview = $DB->get_record(self::REVIEW, ['attemptid' => $attempt->id]);
            if ($savedreview) {
                $reviewsbyattempt[(int)$attempt->id] = $savedreview;
            }
        }
        $review = $latest ? ($reviewsbyattempt[(int)$latest->id] ?? null) : null;
        return [
            'attempts' => count($history),
            'latest' => $latest,
            'history' => $history,
            'reviews_by_attempt' => $reviewsbyattempt,
            'review' => $review,
            'status' => !$latest ? 'not_started' : ($review ? (string)$review->decision : 'awaiting_review'),
        ];
    }

    /** Read-only A1/A2 breakdown from the immutable public question/feedback snapshot. */
    public static function objective_sections(array $questions, array $feedback): array {
        $sections = [
            'foundation' => ['correct' => 0, 'scored' => 0, 'total' => 0, 'threshold' => 8],
            'process' => ['correct' => 0, 'scored' => 0, 'total' => 0, 'threshold' => 8],
        ];
        foreach ($questions as $question) {
            $part = (string)($question['part'] ?? '');
            if (!isset($sections[$part]) || ($question['type'] ?? '') !== 'choice') {
                continue;
            }
            $id = (string)($question['id'] ?? '');
            $sections[$part]['total']++;
            $correct = $feedback[$id]['correct'] ?? null;
            if (($question['status'] ?? '') === 'approved_structure' && is_bool($correct)) {
                $sections[$part]['scored']++;
                $sections[$part]['correct'] += $correct ? 1 : 0;
            }
        }
        foreach ($sections as &$section) {
            $section['status'] = $section['total'] !== 10 ? 'incomplete_bank' :
                ($section['scored'] !== 10 ? 'unverified' :
                    ($section['correct'] >= $section['threshold'] ? 'passed' : 'failed'));
        }
        unset($section);
        return $sections;
    }

    /** Version-10 examiner contract; derived only from the saved question snapshot. */
    public static function five_part_spec(array $questions): array {
        $expected = [
            'dialogue' => ['B1' => 'F-DIALOG-01', 'B2' => 'F-DIALOG-01', 'B3' => 'F-DIALOG-01',
                'B4' => 'F-DIALOG-01', 'B5' => 'F-DIALOG-01'],
            'complex' => ['C1' => 'F-CASE-01', 'C2' => 'F-CASE-02', 'C3' => 'F-CASE-03',
                'C4' => 'F-CASE-04', 'C5' => 'F-CASE-05'],
            'promotion' => ['G1' => 'F-PROMO-PROJECT', 'G2' => 'F-PROMO-PROJECT',
                'G3' => 'F-PROMO-PROJECT', 'G4' => 'F-PROMO-PROJECT', 'G5' => 'F-PROMO-PROJECT'],
        ];
        $required = ['B3' => 2, 'C1' => 2, 'C3' => 2, 'C4' => 2];
        $spec = [];
        foreach ($questions as $question) {
            if (($question['type'] ?? '') !== 'text') {
                continue;
            }
            $part = (string)($question['part'] ?? '');
            $questionid = (string)($question['id'] ?? '');
            if (!isset($expected[$part]) || empty($question['criteria']) || !is_array($question['criteria'])) {
                throw new \moodle_exception('assessmentrubricincomplete', 'local_learning_workspace');
            }
            foreach ($question['criteria'] as $criterion) {
                $id = (string)($criterion['id'] ?? '');
                $score = (int)($criterion['required_score'] ?? 0);
                if (!isset($expected[$part][$id]) || $expected[$part][$id] !== $questionid ||
                        isset($spec[$id]) || $score !== ($required[$id] ?? 0) ||
                        trim((string)($criterion['label'] ?? '')) === '') {
                    throw new \moodle_exception('assessmentrubricincomplete', 'local_learning_workspace');
                }
                $spec[$id] = ['part' => $part, 'questionid' => $questionid,
                    'label' => (string)$criterion['label'], 'required_score' => $score];
            }
        }
        if (count($spec) !== 15) {
            throw new \moodle_exception('assessmentrubricincomplete', 'local_learning_workspace');
        }
        return $spec;
    }

    private static function normalize_five_part_rubric(array $questions, array $answers, array $rubric): array {
        $spec = self::five_part_spec($questions);
        if (count($rubric) !== count($spec)) {
            throw new \moodle_exception('assessmentrubricincomplete', 'local_learning_workspace');
        }
        $criteria = [];
        $parts = [
            'dialogue' => ['score' => 0, 'max' => 10, 'threshold' => 8],
            'complex' => ['score' => 0, 'max' => 10, 'threshold' => 8],
            'promotion' => ['score' => 0, 'max' => 10, 'threshold' => 8],
        ];
        $criticalerrors = [];
        foreach ($spec as $id => $item) {
            $value = $rubric[$id] ?? null;
            if (!is_array($value)) {
                throw new \moodle_exception('assessmentrubricincomplete', 'local_learning_workspace');
            }
            $rawscore = $value['score'] ?? null;
            if ((!is_int($rawscore) && !is_string($rawscore)) ||
                    !in_array((string)$rawscore, ['0', '1', '2'], true)) {
                throw new \moodle_exception('assessmentrubricincomplete', 'local_learning_workspace');
            }
            $score = (int)$rawscore;
            $quote = trim((string)($value['quote'] ?? ''));
            $nextstep = trim((string)($value['nextstep'] ?? ''));
            if ($score < 2 && (mb_strlen($quote) < 8 || mb_strlen($quote) > 300 ||
                    !self::quote_matches_answer($item['questionid'], (string)($answers[$item['questionid']] ?? ''), $quote) ||
                    mb_strlen($nextstep) < 15 || mb_strlen($nextstep) > 1000)) {
                throw new \moodle_exception('assessmentrubricevidence', 'local_learning_workspace');
            }
            $criteria[$id] = ['score' => $score, 'quote' => $score < 2 ? $quote : '',
                'nextstep' => $score < 2 ? $nextstep : ''];
            $parts[$item['part']]['score'] += $score;
            if ($item['required_score'] > 0 && $score < $item['required_score']) {
                $criticalerrors[] = $id;
            }
        }
        foreach ($rubric as $id => $_) {
            if (!isset($spec[$id])) {
                throw new \moodle_exception('assessmentrubricincomplete', 'local_learning_workspace');
            }
        }
        foreach ($parts as $part => &$result) {
            $requiredmiss = false;
            foreach ($criticalerrors as $id) {
                if ($spec[$id]['part'] === $part) {
                    $requiredmiss = true;
                }
            }
            $result['status'] = $result['score'] >= $result['threshold'] && !$requiredmiss ? 'passed' : 'failed';
        }
        unset($result);
        return ['schema' => 'five_part_v1', 'criteria' => $criteria, 'parts' => $parts,
            'critical_errors' => $criticalerrors];
    }

    public static function quote_matches_answer(string $questionid, string $answer, string $quote): bool {
        return $questionid === 'F-DIALOG-01'
            ? academy_dialogue_answer_codec::quote_is_present($answer, $quote)
            : academy_dialogue_answer_codec::text_contains_quote($answer, $quote);
    }

    public static function submit_final(int $userid, array $answers, string $idemkey, int $variant = 0,
            ?int $expectedattemptno = null, ?string $expectedbankhash = null,
            ?int $expectedrevision = null): object {
        global $DB;
        self::assert_owner($userid);
        // Deliberately no completion gate: experienced candidates may go direct to the same exam.
        if ($variant === 0) {
            // Backward-compatible CLI callers choose the next case on the server.
            $next = 1 + (int)$DB->count_records(self::ATTEMPT,
                ['userid' => $userid, 'kind' => 'final', 'lessonid' => 'FINAL']);
            $variant = academy_question_bank::final_variant_for_attempt($next);
        }
        return self::submit($userid, 'final', 'FINAL', academy_question_bank::final($variant),
            $answers, $idemkey, $variant, $expectedattemptno, $expectedbankhash, $expectedrevision);
    }

    /**
     * Return only a current, owner-visible draft's answers. A draft from an old
     * bank edition or an already submitted attempt is never replayed.
     */
    public static function final_draft(int $userid, int $variant): ?array {
        $state = self::final_draft_state($userid, $variant);
        return $state === null ? null : $state['answers'];
    }

    /** Owner-visible answers and CAS revision; legacy flat drafts start at revision zero. */
    public static function final_draft_state(int $userid, int $variant): ?array {
        global $DB;
        self::assert_owner($userid);
        $bank = academy_question_bank::final($variant);
        $attemptno = self::next_final_attemptno($userid);
        if ($attemptno === null || academy_question_bank::final_variant_for_attempt($attemptno) !== $variant) {
            return null;
        }
        $draft = $DB->get_record(self::DRAFT,
            ['userid' => $userid, 'kind' => 'final', 'variant' => $variant]);
        if (!$draft || (int)$draft->attemptno !== $attemptno ||
                !hash_equals(hash('sha256', self::encode($bank)), (string)$draft->bankhash) ||
                (string)$draft->bankversion !== (string)($bank['version'] ?? academy_question_bank::VERSION)) {
            return null;
        }
        return self::decode_final_draft((string)$draft->answersjson) + ['attemptno' => $attemptno];
    }

    private static function decode_final_draft(string $json): array {
        $saved = self::decode($json);
        if (($saved['schema'] ?? null) === 'academy_final_draft.v1') {
            if (!is_int($saved['revision'] ?? null) || $saved['revision'] < 1 ||
                    !is_array($saved['answers'] ?? null)) {
                throw new \coding_exception('Invalid final draft revision');
            }
            $revision = $saved['revision'];
            $answers = $saved['answers'];
        } else {
            $revision = 0;
            $answers = $saved;
        }
        foreach ($answers as $id => $answer) {
            if (!is_string($id) || !is_string($answer)) {
                throw new \coding_exception('Invalid final draft answers');
            }
        }
        return ['revision' => $revision, 'answers' => $answers];
    }

    /** Explicit candidate save only; no score, attempt, review or admission changes. */
    public static function save_final_draft(int $userid, int $variant, array $answers,
            ?string $expectedbankhash = null, ?int $expectedrevision = null,
            ?int $expectedattemptno = null): object {
        global $DB;
        self::assert_owner($userid);
        $bank = academy_question_bank::final($variant);
        $bankhash = hash('sha256', self::encode($bank));
        if ($expectedbankhash !== null && !hash_equals($bankhash, $expectedbankhash)) {
            throw new \moodle_exception('invalidassessmentanswer', 'local_learning_workspace');
        }
        if (($expectedrevision !== null && $expectedrevision < 0) ||
                ($expectedattemptno !== null && $expectedattemptno < 1)) {
            throw new \invalid_parameter_exception('Invalid final draft identity');
        }
        $known = [];
        foreach (array_values($bank['questions'] ?? []) as $question) {
            $id = (string)($question['id'] ?? '');
            $type = (string)($question['type'] ?? '');
            if ($id === '' || isset($known[$id]) || !in_array($type, ['choice', 'text'], true)) {
                throw new \invalid_parameter_exception('Invalid academy question bank');
            }
            $known[$id] = $question;
        }
        if (!$known) {
            throw new \invalid_parameter_exception('Empty academy question bank');
        }
        $normalized = [];
        foreach ($answers as $id => $value) {
            if (!is_string($id) || !isset($known[$id]) || !is_string($value)) {
                throw new \moodle_exception('invalidassessmentanswer', 'local_learning_workspace');
            }
            $question = $known[$id];
            $value = trim($value);
            $type = (string)$question['type'];
            if (mb_strlen($value) > ($type === 'text' ? 10000 : 128)) {
                throw new \moodle_exception('invalidassessmentanswer', 'local_learning_workspace');
            }
            if ($type === 'choice' && $value !== '') {
                $optionids = array_map(static function(array $option): string {
                    return (string)$option['id'];
                }, array_values($question['options'] ?? []));
                if (!in_array($value, $optionids, true)) {
                    throw new \moodle_exception('invalidassessmentanswer', 'local_learning_workspace');
                }
            }
            $normalized[$id] = $value;
        }
        $factory = \core\lock\lock_config::get_lock_factory('local_learning_workspace');
        // Share the attempt lock: a submission and a stale draft save cannot
        // both claim the same pending attempt in different browser tabs.
        $lock = $factory->get_lock('academy-attempt-' . $userid . '-final-FINAL', 10);
        if (!$lock) {
            throw new \moodle_exception('concurrencyconflict', 'local_learning_workspace');
        }
        try {
            $attemptno = self::next_final_attemptno($userid);
            if ($attemptno === null || academy_question_bank::final_variant_for_attempt($attemptno) !== $variant) {
                throw new \moodle_exception('invalidassessmentanswer', 'local_learning_workspace');
            }
            if ($expectedattemptno !== null && $expectedattemptno !== $attemptno) {
                throw new \moodle_exception('concurrencyconflict', 'local_learning_workspace');
            }
            $transaction = $DB->start_delegated_transaction();
            try {
                $record = $DB->get_record(self::DRAFT,
                    ['userid' => $userid, 'kind' => 'final', 'variant' => $variant]);
                $current = $record && (int)$record->attemptno === $attemptno &&
                    (string)$record->bankversion === (string)($bank['version'] ?? academy_question_bank::VERSION) &&
                    hash_equals($bankhash, (string)$record->bankhash)
                    ? self::decode_final_draft((string)$record->answersjson) : null;
                $revision = $current['revision'] ?? 0;
                // Missing revision remains compatible with an initial save, not a blind update.
                $expected = $expectedrevision ?? 0;
                if ($current !== null && $expectedrevision === null && $revision === 0 &&
                        $normalized != $current['answers']) {
                    throw new \moodle_exception('concurrencyconflict', 'local_learning_workspace');
                }
                if ($expected !== $revision) {
                    // One safe replay of the preceding save: never rewrite newer answers.
                    if ($current !== null && $expected === $revision - 1 &&
                            $normalized == $current['answers']) {
                        $transaction->allow_commit();
                        return $record;
                    }
                    throw new \moodle_exception('concurrencyconflict', 'local_learning_workspace');
                }
                if ($current !== null && $normalized == $current['answers']) {
                    $transaction->allow_commit();
                    return $record;
                }
                $answersjson = self::encode(['schema' => 'academy_final_draft.v1',
                    'revision' => $revision + 1, 'answers' => $normalized]);
                if ($record) {
                    $record->attemptno = $attemptno;
                    $record->bankversion = (string)($bank['version'] ?? academy_question_bank::VERSION);
                    $record->bankhash = $bankhash;
                    $record->answersjson = $answersjson;
                    $record->timemodified = time();
                    $DB->update_record(self::DRAFT, $record);
                } else {
                    $record = (object)[
                        'userid' => $userid,
                        'kind' => 'final',
                        'variant' => $variant,
                        'attemptno' => $attemptno,
                        'bankversion' => (string)($bank['version'] ?? academy_question_bank::VERSION),
                        'bankhash' => $bankhash,
                        'answersjson' => $answersjson,
                        'timemodified' => time(),
                    ];
                    $record->id = $DB->insert_record(self::DRAFT, $record);
                }
                $transaction->allow_commit();
                return $record;
            } catch (\Throwable $error) {
                $transaction->rollback($error);
                throw $error;
            }
        } finally {
            $lock->release();
        }
    }

    /** Null means a submitted final attempt is still awaiting examiner review. */
    private static function next_final_attemptno(int $userid): ?int {
        global $DB;
        $history = array_values($DB->get_records(self::ATTEMPT,
            ['userid' => $userid, 'kind' => 'final', 'lessonid' => 'FINAL'], 'attemptno DESC'));
        if ($history && !$DB->record_exists(self::REVIEW, ['attemptid' => (int)$history[0]->id])) {
            return null;
        }
        return count($history) + 1;
    }

    /** Feedback on a day's free answer, never an admission or final-exam decision. */
    public static function review_day(int $attemptid, int $reviewerid, array $rubric,
            string $decision, string $feedback, string $idemkey): object {
        global $DB, $USER;
        require_capability('local/learning_workspace:reviewassessment', \context_system::instance());
        if ((int)$USER->id !== $reviewerid ||
                !in_array($decision, ['practice_accepted', 'practice_rework', 'practice_feedback'], true)) {
            throw new \moodle_exception('assessmentreviewforbidden', 'local_learning_workspace');
        }
        $attempt = $DB->get_record(self::ATTEMPT, ['id' => $attemptid, 'kind' => 'day'], '*', MUST_EXIST);
        if ((int)$attempt->userid === $reviewerid) {
            throw new \moodle_exception('assessmentreviewforbidden', 'local_learning_workspace');
        }
        $feedback = trim($feedback);
        if (mb_strlen($feedback) < 10 || mb_strlen($feedback) > 4000) {
            throw new \invalid_parameter_exception('Day feedback must be 10-4000 characters');
        }
        $questions = self::decode((string)$attempt->questionsjson);
        $answers = self::decode((string)$attempt->answersjson);
        $normalrubric = [];
        $failed = false;
        foreach ($questions as $question) {
            if (($question['type'] ?? '') !== 'text') {
                continue;
            }
            $id = (string)($question['id'] ?? '');
            $value = $rubric[$id] ?? null;
            if ($id === '' || !is_array($value) || !in_array($value['result'] ?? null, ['pass', 'fail'], true)) {
                throw new \moodle_exception('assessmentrubricincomplete', 'local_learning_workspace');
            }
            $result = (string)$value['result'];
            $quote = trim((string)($value['quote'] ?? ''));
            $nextstep = trim((string)($value['nextstep'] ?? ''));
            if ($result === 'fail' && (mb_strlen($quote) < 8 || mb_strlen($quote) > 300 ||
                    !self::quote_matches_answer($id, (string)($answers[$id] ?? ''), $quote) ||
                    mb_strlen($nextstep) < 15 || mb_strlen($nextstep) > 1000)) {
                throw new \moodle_exception('assessmentrubricevidence', 'local_learning_workspace');
            }
            $failed = $failed || $result === 'fail';
            $normalrubric[$id] = ['result' => $result, 'quote' => $result === 'fail' ? $quote : '',
                'nextstep' => $result === 'fail' ? $nextstep : ''];
        }
        if (!$normalrubric || count($normalrubric) !== count($rubric) ||
                ($decision === 'practice_accepted' && ($failed || (int)$attempt->hasdraft === 1)) ||
                ($decision === 'practice_rework' && (!$failed || (int)$attempt->hasdraft === 1)) ||
                ($decision === 'practice_feedback' && (int)$attempt->hasdraft !== 1)) {
            throw new \moodle_exception('assessmentrubricincomplete', 'local_learning_workspace');
        }
        $idemkey = self::normalize_idem($idemkey);
        $requesthash = hash('sha256', self::encode([$attemptid, $reviewerid, $normalrubric, $decision, $feedback]));
        $factory = \core\lock\lock_config::get_lock_factory('local_learning_workspace');
        $lock = $factory->get_lock('academy-day-review-' . $attemptid, 10);
        if (!$lock) {
            throw new \moodle_exception('concurrencyconflict', 'local_learning_workspace');
        }
        try {
            $existing = $DB->get_record(self::REVIEW, ['idemkey' => $idemkey]);
            if ($existing) {
                $savedhash = hash('sha256', self::encode([(int)$existing->attemptid, (int)$existing->reviewerid,
                    self::decode((string)$existing->rubricjson), $existing->decision, $existing->feedback]));
                if ((int)$existing->attemptid !== $attemptid || (int)$existing->reviewerid !== $reviewerid ||
                        !hash_equals($savedhash, $requesthash)) {
                    throw new \moodle_exception('assessmentidempotencyconflict', 'local_learning_workspace');
                }
                return $existing;
            }
            if ($DB->record_exists(self::REVIEW, ['attemptid' => $attemptid])) {
                throw new \moodle_exception('assessmentalreadyreviewed', 'local_learning_workspace');
            }
            $transaction = $DB->start_delegated_transaction();
            try {
                $record = (object)[
                    'attemptid' => $attemptid,
                    'reviewerid' => $reviewerid,
                    'decision' => $decision,
                    'rubricjson' => self::encode($normalrubric),
                    'feedback' => $feedback,
                    'idemkey' => $idemkey,
                    'timecreated' => time(),
                ];
                $record->id = $DB->insert_record(self::REVIEW, $record);
                $transaction->allow_commit();
                return $record;
            } catch (\Throwable $error) {
                $transaction->rollback($error);
                throw $error;
            }
        } finally {
            $lock->release();
        }
    }

    public static function review_final(int $attemptid, int $reviewerid, array $rubric,
            string $decision, string $feedback, string $idemkey): object {
        global $DB, $USER;
        require_capability('local/learning_workspace:reviewassessment', \context_system::instance());
        if ((int)$USER->id !== $reviewerid) {
            throw new \moodle_exception('assessmentreviewforbidden', 'local_learning_workspace');
        }
        if (!in_array($decision, ['ready', 'rework', 'not_ready'], true)) {
            throw new \invalid_parameter_exception('Invalid final decision');
        }
        $attempt = $DB->get_record(self::ATTEMPT, ['id' => $attemptid, 'kind' => 'final'], '*', MUST_EXIST);
        if ((int)$attempt->userid === $reviewerid) {
            throw new \moodle_exception('assessmentreviewforbidden', 'local_learning_workspace');
        }
        $feedback = trim($feedback);
        if (mb_strlen($feedback) < 10 || mb_strlen($feedback) > 10000) {
            throw new \invalid_parameter_exception('Review feedback must be 10-10000 characters');
        }
        $questions = self::decode((string)$attempt->questionsjson);
        $answers = self::decode((string)$attempt->answersjson);
        $results = self::decode((string)$attempt->feedbackjson);
        $normalrubric = [];
        $hasdraft = false;
        $criticalmiss = false;
        $anyfail = false;
        $versionmatch = [];
        $requireevidence = preg_match('/-final\.(\d+)-v/', (string)$attempt->bankversion, $versionmatch) === 1 &&
            (int)$versionmatch[1] >= 6;
        $requiresections = $requireevidence && (int)$versionmatch[1] >= 9;
        $requirefiveparts = $requiresections && (int)$versionmatch[1] >= 10;
        $sections = $requiresections ? self::objective_sections($questions, $results) : [];
        if ($requirefiveparts) {
            $normalrubric = self::normalize_five_part_rubric($questions, $answers, $rubric);
            foreach ($questions as $question) {
                $id = (string)$question['id'];
                $hasdraft = $hasdraft || ($question['status'] ?? '') !== 'approved_structure';
                if (($question['type'] ?? '') === 'choice' && !empty($question['hardfail']) &&
                        empty($results[$id]['correct'])) {
                    $criticalmiss = true;
                }
            }
            foreach ($normalrubric['parts'] as $part) {
                $anyfail = $anyfail || $part['status'] !== 'passed';
            }
        } else {
        foreach ($questions as $question) {
            $id = (string)$question['id'];
            $hasdraft = $hasdraft || ($question['status'] ?? '') !== 'approved_structure';
            if (($question['type'] ?? '') === 'text') {
                $value = $rubric[$id] ?? null;
                $rating = is_array($value) ? ($value['result'] ?? null) : $value;
                if (!in_array($rating, ['pass', 'fail'], true) || ($requireevidence && !is_array($value))) {
                    throw new \moodle_exception('assessmentrubricincomplete', 'local_learning_workspace');
                }
                if (is_array($value)) {
                    $quote = trim((string)($value['quote'] ?? ''));
                    $nextstep = trim((string)($value['nextstep'] ?? ''));
                    if ($rating === 'fail' && (mb_strlen($quote) < 8 || mb_strlen($quote) > 300 ||
                            !self::quote_matches_answer($id, (string)($answers[$id] ?? ''), $quote) ||
                            mb_strlen($nextstep) < 15 || mb_strlen($nextstep) > 1000)) {
                        throw new \moodle_exception('assessmentrubricevidence', 'local_learning_workspace');
                    }
                    $normalrubric[$id] = ['result' => $rating, 'quote' => $rating === 'fail' ? $quote : '',
                        'nextstep' => $rating === 'fail' ? $nextstep : ''];
                } else {
                    // Existing attempts and integrations retain their original scalar rubric.
                    $normalrubric[$id] = $rating;
                }
                $anyfail = $anyfail || $rating === 'fail';
                $criticalmiss = $criticalmiss || ((!empty($question['hardfail']) ||
                    ($question['part'] ?? '') === 'safety') && $rating === 'fail');
            } else if ((!empty($question['hardfail']) || ($question['part'] ?? '') === 'safety') &&
                    empty($results[$id]['correct'])) {
                $criticalmiss = true;
            }
        }
        if (count($normalrubric) !== count($rubric)) {
            throw new \moodle_exception('assessmentrubricincomplete', 'local_learning_workspace');
        }
        }
        $sectionmiss = $requiresections && ($sections['foundation']['status'] !== 'passed' ||
            $sections['process']['status'] !== 'passed');
        if ($decision === 'ready' && ($hasdraft || $criticalmiss || $sectionmiss ||
                (int)$attempt->score < (int)$attempt->passmark || $anyfail)) {
            throw new \moodle_exception('assessmentreadinessblocked', 'local_learning_workspace');
        }
        $idemkey = self::normalize_idem($idemkey);
        $requesthash = hash('sha256', self::encode([$attemptid, $reviewerid, $normalrubric, $decision, $feedback]));
        $factory = \core\lock\lock_config::get_lock_factory('local_learning_workspace');
        $lock = $factory->get_lock('academy-final-review-' . $attemptid, 10);
        if (!$lock) {
            throw new \moodle_exception('concurrencyconflict', 'local_learning_workspace');
        }
        try {
            $existing = $DB->get_record(self::REVIEW, ['idemkey' => $idemkey]);
            if ($existing) {
                if ((int)$existing->attemptid !== $attemptid || (int)$existing->reviewerid !== $reviewerid ||
                        !hash_equals(hash('sha256', self::encode([(int)$existing->attemptid, (int)$existing->reviewerid,
                            self::decode((string)$existing->rubricjson), $existing->decision, $existing->feedback])),
                            $requesthash)) {
                    throw new \moodle_exception('assessmentidempotencyconflict', 'local_learning_workspace');
                }
                return $existing;
            }
            if ($DB->record_exists(self::REVIEW, ['attemptid' => $attemptid])) {
                throw new \moodle_exception('assessmentalreadyreviewed', 'local_learning_workspace');
            }
            $transaction = $DB->start_delegated_transaction();
            try {
                $record = (object)[
                    'attemptid' => $attemptid,
                    'reviewerid' => $reviewerid,
                    'decision' => $decision,
                    'rubricjson' => self::encode($normalrubric),
                    'feedback' => $feedback,
                    'idemkey' => $idemkey,
                    'timecreated' => time(),
                ];
                $record->id = $DB->insert_record(self::REVIEW, $record);
                $transaction->allow_commit();
                return $record;
            } catch (\Throwable $error) {
                $transaction->rollback($error);
                throw $error;
            }
        } finally {
            $lock->release();
        }
    }

    public static function reviewer_rows(): array {
        global $DB, $CFG;
        require_capability('local/learning_workspace:reviewassessment', \context_system::instance());
        // The examiner sees the active Moodle cohort, including candidates
        // who have not submitted anything yet. Historical attempts remain
        // visible even after enrolment ends.
        $rows = [];
        $coursecontext = academy_access::course_context();
        if ($coursecontext !== null) {
            if (!function_exists('get_enrolled_users')) {
                require_once($CFG->libdir . '/enrollib.php');
            }
            $enrolled = get_enrolled_users($coursecontext,
                'local/learning_workspace:submitassessment', 0,
                'u.id,u.firstname,u.lastname,u.suspended', 'u.id ASC', 0, 0, true);
            foreach ($enrolled as $user) {
                if ((int)$user->suspended === 1 ||
                        !has_capability('local/learning_workspace:view', $coursecontext, (int)$user->id)) {
                    continue;
                }
                $rows[(int)$user->id] = self::reviewer_row((int)$user->id,
                    (string)$user->firstname, (string)$user->lastname, true);
            }
        }
        // The overview needs decisions and rubric totals, not full feedback
        // text or reviewer metadata from every historical record.
        $reviewsbyattempt = [];
        foreach ($DB->get_records(self::REVIEW, null, '',
                'id,attemptid,decision,rubricjson,timecreated') as $review) {
            $reviewsbyattempt[(int)$review->attemptid] = $review;
        }
        // In particular, do not pull every candidate's free-form answers into
        // the queue. The selected attempt's full answers are loaded only by
        // attempt_details() after the examiner opens its card.
        $attempts = $DB->get_records(self::ATTEMPT, null, 'userid ASC, attemptno DESC',
            'id,userid,kind,lessonid,attemptno,bankversion,status,hasdraft,score,' .
            'questionsjson,feedbackjson,timecreated');
        foreach ($attempts as $attempt) {
            $userid = (int)$attempt->userid;
            if (!isset($rows[$userid])) {
                $user = $DB->get_record('user', ['id' => $userid], 'id,firstname,lastname');
                $rows[$userid] = self::reviewer_row($userid,
                    (string)($user->firstname ?? ''), (string)($user->lastname ?? ''), false);
            }
            if ($attempt->kind === 'day') {
                if (!isset($rows[$userid]->daylatest[$attempt->lessonid])) {
                    $rows[$userid]->daylatest[$attempt->lessonid] = true;
                    $rows[$userid]->submitteddaycount++;
                    $rows[$userid]->passeddaycount += $attempt->status === 'passed' ? 1 : 0;
                    $dayreview = $reviewsbyattempt[(int)$attempt->id] ?? null;
                    if($dayreview && $dayreview->decision==='practice_accepted'){$rows[$userid]->practicacceptedcount++;}
                    if ($attempt->status === 'passed' && $dayreview &&
                            $dayreview->decision === 'practice_accepted') {
                        $rows[$userid]->accepteddaycount++;
                    }
                }
                if (!isset($reviewsbyattempt[(int)$attempt->id])) {
                    $rows[$userid]->pendingdayreviews++;
                }
                continue;
            }
            if ($attempt->kind !== 'final') {
                continue;
            }
            $rows[$userid]->attempts++;
            if ((int)$attempt->attemptno <= (int)$rows[$userid]->latestattemptno) {
                continue;
            }
            $review = $reviewsbyattempt[(int)$attempt->id] ?? null;
            $rows[$userid]->latestattemptno = (int)$attempt->attemptno;
            $rows[$userid]->latestattemptid = (int)$attempt->id;
            $rows[$userid]->latestbankversion = (string)$attempt->bankversion;
            $rows[$userid]->currentbankversion = (string)academy_question_bank::final(
                academy_question_bank::final_variant_for_attempt((int)$attempt->attemptno))['version'];
            $rows[$userid]->historicalbank =
                $rows[$userid]->latestbankversion !== $rows[$userid]->currentbankversion;
            $rows[$userid]->finalstatus = $review ? (string)$review->decision : 'awaiting_review';
            $rows[$userid]->hasdraft = (int)$attempt->hasdraft;
            $rows[$userid]->score = (int)$attempt->score;
            $rows[$userid]->decision = $review ? (string)$review->decision : null;
            if (!$review && (int)$attempt->attemptno > 1) {
                $priorattempt = $DB->get_record(self::ATTEMPT, [
                    'userid' => $userid, 'kind' => 'final', 'lessonid' => 'FINAL',
                    'attemptno' => (int)$attempt->attemptno - 1,
                ]);
                $priorreview = $priorattempt ? ($reviewsbyattempt[(int)$priorattempt->id] ?? null) : null;
                if ($priorreview) {
                    $rows[$userid]->previousdecision = (string)$priorreview->decision;
                    $rows[$userid]->previousdecisiontime = (int)$priorreview->timecreated;
                    $rows[$userid]->previousattemptid = (int)$priorattempt->id;
                }
            }
            $rows[$userid]->timecreated = (int)$attempt->timecreated;
            $rows[$userid]->objectivesections = self::objective_sections(
                self::decode((string)$attempt->questionsjson), self::decode((string)$attempt->feedbackjson));
            $savedrubric = $review ? self::decode((string)$review->rubricjson) : [];
            $rows[$userid]->manualparts = ($savedrubric['schema'] ?? '') === 'five_part_v1'
                ? ($savedrubric['parts'] ?? null) : null;
        }
        ksort($rows, SORT_NUMERIC);
        return array_values($rows);
    }

    private static function reviewer_row(int $userid, string $firstname, string $lastname, bool $enrolled): object {
        return (object)[
            'userid' => $userid,
            'firstname' => $firstname,
            'lastname' => $lastname,
            'enrolled' => $enrolled,
            'attempts' => 0,
            'latestattemptid' => 0,
            'finalstatus' => 'not_started',
            'hasdraft' => 0,
            'score' => 0,
            'decision' => null,
            'previousdecision' => null,
            'previousdecisiontime' => 0,
            'previousattemptid' => 0,
            'timecreated' => 0,
            'latestattemptno' => 0,
            'latestbankversion' => '',
            'currentbankversion' => '',
            'historicalbank' => false,
            'objectivesections' => null,
            'manualparts' => null,
            'pendingdayreviews' => 0,
            'submitteddaycount' => 0,
            'passeddaycount' => 0,
            'accepteddaycount' => 0,
            'practicacceptedcount' => 0,
            'daylatest' => [],
        ];
    }

    public static function attempt_details(int $attemptid): array {
        global $DB, $USER;
        $attempt = $DB->get_record(self::ATTEMPT, ['id' => $attemptid], '*', MUST_EXIST);
        if ((int)$attempt->userid === (int)$USER->id) {
            if (!has_capability('local/learning_workspace:reviewassessment', \context_system::instance())) {
                academy_access::require_candidate();
            }
        } else {
            require_capability('local/learning_workspace:reviewassessment', \context_system::instance());
        }
        $history = array_values($DB->get_records(self::ATTEMPT,
            ['userid' => $attempt->userid, 'kind' => $attempt->kind, 'lessonid' => $attempt->lessonid], 'attemptno DESC'));
        $reviewsbyattempt = [];
        foreach ($DB->get_records_list(self::REVIEW, 'attemptid',
                array_map(static fn($record): int => (int)$record->id, $history)) as $historicalreview) {
            $reviewsbyattempt[(int)$historicalreview->attemptid] = $historicalreview;
        }
        $review = $reviewsbyattempt[$attemptid] ?? null;
        return [
            'attempt' => $attempt,
            'questions' => self::decode((string)$attempt->questionsjson),
            'answers' => self::decode((string)$attempt->answersjson),
            'feedback' => self::decode((string)$attempt->feedbackjson),
            'objectivesections' => $attempt->kind === 'final' ? self::objective_sections(
                self::decode((string)$attempt->questionsjson), self::decode((string)$attempt->feedbackjson)) : null,
            'review' => $review,
            'history' => $history,
            'reviews_by_attempt' => $reviewsbyattempt,
        ];
    }

    /** Compare this final attempt with the immediately preceding reviewed one. */
    public static function final_comparison(int $attemptid): ?array {
        global $DB;
        $current = self::attempt_details($attemptid); // Enforces candidate ownership or reviewer capability.
        $attempt = $current['attempt'];
        if ($attempt->kind !== 'final' || (int)$attempt->attemptno < 2) {
            return null;
        }
        $previous = $DB->get_record(self::ATTEMPT, [
            'userid' => (int)$attempt->userid,
            'kind' => 'final',
            'lessonid' => 'FINAL',
            'attemptno' => (int)$attempt->attemptno - 1,
        ]);
        if (!$previous) {
            return null;
        }
        $previousreview = $DB->get_record(self::REVIEW, ['attemptid' => (int)$previous->id]);
        if (!$previousreview) {
            return null;
        }
        $currentreview = $current['review'];
        $comparison = academy_retake_comparison::build(
            [
                'version' => (string)$previous->bankversion,
                'questions' => self::decode((string)$previous->questionsjson),
                'answers' => self::decode((string)$previous->answersjson),
                'rubric' => self::decode((string)$previousreview->rubricjson),
            ],
            [
                'version' => (string)$attempt->bankversion,
                'questions' => $current['questions'],
                'answers' => $current['answers'],
                'rubric' => $currentreview ? self::decode((string)$currentreview->rubricjson) : [],
            ]
        );
        $comparison['previousid'] = (int)$previous->id;
        $comparison['previousdecision'] = (string)$previousreview->decision;
        $comparison['currentdecision'] = $currentreview ? (string)$currentreview->decision : 'awaiting_review';
        return $comparison;
    }

    private static function submit(int $userid, string $kind, string $lessonid, array $bank,
            array $answers, string $idemkey, int $submittedvariant = 0,
            ?int $expectedattemptno = null, ?string $expectedbankhash = null,
            ?int $expectedrevision = null): object {
        global $DB;
        $idemkey = self::normalize_idem($idemkey);
        if ($expectedrevision !== null && $expectedrevision < 0) {
            throw new \invalid_parameter_exception('Invalid final draft revision');
        }
        // An idempotent replay returns the original attempt, including its old
        // bank edition. A new key creates a new attempt against the current bank.
        $prior = $DB->get_record(self::ATTEMPT, ['idemkey' => $idemkey]);
        if ($prior) {
            $submitted = [];
            foreach ($answers as $id => $value) {
                if (!is_string($value)) {
                    throw new \moodle_exception('assessmentidempotencyconflict', 'local_learning_workspace');
                }
                $submitted[(string)$id] = trim($value);
            }
            if ((int)$prior->userid !== $userid || $prior->kind !== $kind ||
                    $prior->lessonid !== $lessonid || $submitted != self::decode((string)$prior->answersjson)) {
                throw new \moodle_exception('assessmentidempotencyconflict', 'local_learning_workspace');
            }
            return $prior;
        }
        $questions = array_values($bank['questions'] ?? []);
        if (!$questions || (string)($bank['id'] ?? '') !== $lessonid) {
            throw new \invalid_parameter_exception('Invalid academy question bank');
        }
        $version = (string)($bank['version'] ?? academy_question_bank::VERSION);
        $bankhash = hash('sha256', self::encode($bank));
        $normalized = [];
        $publicquestions = [];
        $feedback = [];
        $seen = [];
        $correct = 0;
        $objective = 0;
        $hasdraft = false;
        $criticalmiss = false;
        foreach ($questions as $question) {
            $id = (string)($question['id'] ?? '');
            $type = (string)($question['type'] ?? '');
            if ($id === '' || isset($seen[$id]) || !in_array($type, ['choice', 'text'], true)) {
                throw new \invalid_parameter_exception('Invalid academy question');
            }
            $seen[$id] = true;
            if (!array_key_exists($id, $answers) || !is_string($answers[$id])) {
                throw new \moodle_exception('assessmentincomplete', 'local_learning_workspace');
            }
            $value = trim($answers[$id]);
            if ($value === '' || mb_strlen($value) > ($type === 'text' ? 10000 : 128)) {
                throw new \moodle_exception('invalidassessmentanswer', 'local_learning_workspace');
            }
            $status = (string)($question['status'] ?? 'draft_with_verification_required');
            $hasdraft = $hasdraft || $status !== 'approved_structure';
            $visible = $question;
            unset($visible['correct'], $visible['correctid'], $visible['rubric']);
            $publicquestions[] = $visible;
            $normalized[$id] = $value;
            if ($type === 'choice') {
                $optionids = array_map(static function(array $option): string { return (string)$option['id']; },
                    array_values($question['options'] ?? []));
                if (!in_array($value, $optionids, true) || !in_array((string)($question['correct'] ?? ''), $optionids, true)) {
                    throw new \moodle_exception('invalidassessmentanswer', 'local_learning_workspace');
                }
                $iscorrect = hash_equals((string)$question['correct'], $value);
                if ($status === 'approved_structure') {
                    $objective++;
                    $correct += $iscorrect ? 1 : 0;
                }
                $criticalmiss = $criticalmiss || (!empty($question['hardfail']) && !$iscorrect);
                $feedback[$id] = [
                    'correct' => $status === 'approved_structure' ? $iscorrect : null,
                    'explanation' => $status === 'approved_structure' ? (string)($question['explanation'] ?? '') : '',
                    'status' => $status,
                ];
            } else {
                $feedback[$id] = ['correct' => null, 'explanation' => '', 'status' => $status];
            }
        }
        if (count($answers) !== count($seen)) {
            throw new \moodle_exception('invalidassessmentanswer', 'local_learning_workspace');
        }
        $score = $objective ? (int)floor($correct * 100 / $objective) : 0;
        $passmark = self::passmark($kind);
        $recordstatus = $kind === 'final' ? 'awaiting_review' :
            ($hasdraft ? 'draft_unverified' : (($score >= $passmark && !$criticalmiss) ? 'passed' : 'failed'));
        $answersjson = self::encode($normalized);
        $answershash = hash('sha256', $answersjson);
        $factory = \core\lock\lock_config::get_lock_factory('local_learning_workspace');
        $lock = $factory->get_lock('academy-attempt-' . $userid . '-' . $kind . '-' . $lessonid, 10);
        if (!$lock) {
            throw new \moodle_exception('concurrencyconflict', 'local_learning_workspace');
        }
        try {
            // Recheck under the lock: two browser tabs may submit the same
            // key or two distinct keys before either insert is committed.
            $existing = $DB->get_record(self::ATTEMPT, ['idemkey' => $idemkey]);
            if ($existing) {
                if ((int)$existing->userid !== $userid || $existing->kind !== $kind ||
                        $existing->lessonid !== $lessonid || $normalized != self::decode((string)$existing->answersjson)) {
                    throw new \moodle_exception('assessmentidempotencyconflict', 'local_learning_workspace');
                }
                return $existing;
            }
                if ($kind === 'final') {
                    // A retake is available only after the examiner has resolved
                    // the previous attempt. The UI gate alone is not sufficient:
                    // a direct POST could otherwise create parallel attempts.
                    $history = array_values($DB->get_records(self::ATTEMPT,
                        ['userid' => $userid, 'kind' => 'final', 'lessonid' => 'FINAL'], 'attemptno DESC'));
                    if ($history && !$DB->record_exists(self::REVIEW, ['attemptid' => (int)$history[0]->id])) {
                        throw new \moodle_exception('assessmentawaitingreview', 'local_learning_workspace');
                    }
                }
                $attemptno = 1 + (int)$DB->count_records(self::ATTEMPT,
                    ['userid' => $userid, 'kind' => $kind, 'lessonid' => $lessonid]);
                if ($kind === 'final' && $expectedattemptno !== null && $expectedattemptno !== $attemptno) {
                    throw new \moodle_exception('concurrencyconflict', 'local_learning_workspace');
                }
                if ($kind === 'final' && $expectedbankhash !== null &&
                        !hash_equals($bankhash, $expectedbankhash)) {
                    throw new \moodle_exception('invalidassessmentanswer', 'local_learning_workspace');
                }
                if ($kind === 'final' && $submittedvariant !==
                        academy_question_bank::final_variant_for_attempt($attemptno)) {
                    // A second tab may have finished the previous case while this
                    // form was open. Never score stale answers against the next case.
                    throw new \moodle_exception('invalidassessmentanswer', 'local_learning_workspace');
                }
                if ($kind === 'final' && $expectedrevision !== null) {
                    // The same lock fences both draft saves and submissions. A
                    // complete form from an older tab must not submit old answers
                    // after another tab has saved a newer draft of this attempt.
                    $draft = $DB->get_record(self::DRAFT,
                        ['userid' => $userid, 'kind' => 'final', 'variant' => $submittedvariant]);
                    $currentdraft = $draft && (int)$draft->attemptno === $attemptno &&
                        (string)$draft->bankversion === $version &&
                        hash_equals($bankhash, (string)$draft->bankhash)
                        ? self::decode_final_draft((string)$draft->answersjson) : null;
                    if ($expectedrevision !== ($currentdraft['revision'] ?? 0)) {
                        throw new \moodle_exception('concurrencyconflict', 'local_learning_workspace');
                    }
                }
                // Validate under the attempt lock before opening the write transaction.
                // A rejected POST must not abort an enclosing caller transaction.
                $policystate = academy_attempt_policy::state($userid, $kind, $lessonid);
                if (!$policystate['allowed']) {
                    throw new \moodle_exception($policystate['awaiting_review'] ?
                        'assessmentawaitingreview' : 'assessmentattemptlocked', 'local_learning_workspace');
                }
            $transaction = $DB->start_delegated_transaction();
            try {
                $policy = academy_attempt_policy::begin($userid, $kind, $lessonid, true);
                $record = (object)[
                    'userid' => $userid,
                    'kind' => $kind,
                    'lessonid' => $lessonid,
                    'bankversion' => $version,
                    'bankhash' => $bankhash,
                    'attemptno' => $attemptno,
                    'status' => $recordstatus,
                    'hasdraft' => $hasdraft ? 1 : 0,
                    'score' => $score,
                    'passmark' => $passmark,
                    'answersjson' => $answersjson,
                    'questionsjson' => self::encode($publicquestions),
                    'feedbackjson' => self::encode($feedback),
                    'policyjson' => academy_attempt_policy::encode($policy),
                    'answershash' => $answershash,
                    'idemkey' => $idemkey,
                    'timecreated' => time(),
                ];
                $record->id = $DB->insert_record(self::ATTEMPT, $record);
                $transaction->allow_commit();
                return $record;
            } catch (\Throwable $error) {
                $transaction->rollback($error);
                throw $error;
            }
        } finally {
            $lock->release();
        }
    }

    private static function assert_owner(int $userid): void {
        global $USER;
        academy_access::require_candidate();
        if ((int)$USER->id !== $userid) {
            throw new \moodle_exception('assessmentreviewforbidden', 'local_learning_workspace');
        }
    }

    private static function assert_owner_or_reviewer(int $userid): void {
        global $USER;
        if ((int)$USER->id === $userid) {
            if (!has_capability('local/learning_workspace:reviewassessment', \context_system::instance())) {
                academy_access::require_candidate();
            }
        } else {
            require_capability('local/learning_workspace:reviewassessment', \context_system::instance());
        }
    }

    private static function passmark(string $kind): int {
        $configured = get_config('local_learning_workspace', 'academy_' . $kind . '_passmark');
        return is_numeric($configured) && (int)$configured >= 1 && (int)$configured <= 100
            ? (int)$configured : self::DEFAULT_PASSMARK;
    }

    private static function normalize_idem(string $key): string {
        $key = trim($key);
        if ($key === '' || strlen($key) > 128 || !preg_match('/^[A-Za-z0-9_.:-]+$/', $key)) {
            throw new \invalid_parameter_exception('Invalid idempotency key');
        }
        return $key;
    }

    private static function encode($value): string {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private static function decode(string $value): array {
        return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
    }
}
