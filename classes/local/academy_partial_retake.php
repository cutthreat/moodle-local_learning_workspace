<?php
declare(strict_types=1);

namespace local_learning_workspace\local;

/**
 * Pure source-level contract for planning a partial final-exam retake.
 *
 * This class deliberately has no Moodle dependency and never accepts answer
 * text. It only compares the immutable public question snapshot and a saved
 * reviewer rubric, then returns an explicit carry/retake provenance plan.
 * The repository may use that plan when creating a new immutable attempt.
 */
final class academy_partial_retake {
    public const PLAN_SCHEMA = 'partial_retake_plan_v1';
    public const RUBRIC_SCHEMA = 'five_part_v1';

    /** Stable public skill parts used by final.10. */
    public const PARTS = [
        'foundation' => 'А1 · Предметная база',
        'process' => 'А2 · Понимание процесса',
        'dialogue' => 'Б · Сквозной диалог',
        'complex' => 'В · Сложные ситуации',
        'promotion' => 'Г · Продвижение',
    ];

    private const PART_THRESHOLDS = [
        'foundation' => 8,
        'process' => 8,
        'dialogue' => 8,
        'complex' => 8,
        'promotion' => 8,
    ];

    private const PART_QUESTION_COUNTS = [
        'foundation' => 10,
        'process' => 10,
        'dialogue' => 1,
        'complex' => 5,
        'promotion' => 1,
    ];

    private const SECRET_KEYS = ['correct', 'correctid', 'rubric'];

    /**
     * Build a partial-retake plan from one reviewed final.10 attempt.
     *
     * Required input fields:
     * - $sourceattempt: id, kind=final, bankversion, questions (public array)
     * - $sourcereview: id, attemptid, reviewerid, decision, rubric (five_part_v1)
     * - $targetquestions: next public question snapshot (usually another variant)
     * - $options[target_bank_version]: target final.10 bank version
     *
     * The source review must be the latest distinct review. If the caller has
     * review history, pass it as options[review_history]; otherwise an explicit
     * options[latest_review_id] or source attempt latest_review_id is checked.
     * A partial plan redoes every currently non-passed part and every passed
     * part whose saved public question snapshot changed. This prevents a
     * passed A1/A2 or Б/В/Г result from being silently carried to a different
     * case merely because question IDs and rubric labels still match.
     */
    public static function plan(array $sourceattempt, array $sourcereview,
            array $targetquestions, array $options = []): array {
        self::assert_attempt($sourceattempt);
        self::assert_review($sourceattempt, $sourcereview);
        self::assert_latest_review($sourceattempt, $sourcereview, $options);

        $sourcequestions = self::field_array($sourceattempt, 'questions', 'questionsjson');
        $targetquestions = self::extract_questions($targetquestions);
        $sourceversion = (string)($sourceattempt['bankversion'] ?? '');
        $targetversion = trim((string)($options['target_bank_version'] ?? ''));
        self::assert_final10_version($sourceversion, 'legacy_bank_version');
        if ($targetversion === '') {
            self::reject('missing_target_bank_version');
        }
        self::assert_final10_version($targetversion, 'target_bank_version_invalid');
        if ($sourceversion === $targetversion) {
            self::reject('same_bank_variant');
        }

        $sourcepublic = self::public_question_contract($sourcequestions);
        $targetpublic = self::public_question_contract($targetquestions);
        $sourcecontract = self::rubric_contract_from_public($sourcepublic);
        $targetcontract = self::rubric_contract_from_public($targetpublic);
        $sourcerubrictash = self::hash_value($sourcecontract);
        $targetrubrictash = self::hash_value($targetcontract);
        if (!hash_equals($sourcerubrictash, $targetrubrictash)) {
            self::reject('rubric_incompatible');
        }
        $sourcepublichash = self::hash_value($sourcepublic);
        $targetpublichash = self::hash_value($targetpublic);
        $sourcepartsnapshots = self::part_snapshot_hashes($sourcepublic);
        $targetpartsnapshots = self::part_snapshot_hashes($targetpublic);

        $rubric = self::field_array($sourcereview, 'rubric', 'rubricjson');
        self::assert_rubric($rubric, $sourcecontract, $sourcerubrictash);
        $retake = [];
        $carry = [];
        $retakereasons = [];
        foreach (array_keys(self::PARTS) as $part) {
            if (($rubric['parts'][$part]['status'] ?? '') !== 'passed') {
                $retake[] = $part;
                $retakereasons[$part] = 'source_part_not_passed';
            } else if (!hash_equals($sourcepartsnapshots[$part], $targetpartsnapshots[$part])) {
                $retake[] = $part;
                $retakereasons[$part] = 'public_snapshot_changed';
            } else {
                $carry[] = $part;
            }
        }
        if (!$retake) {
            self::reject('no_partial_scope');
        }

        $requested = $options['requested_parts'] ?? null;
        if ($requested !== null) {
            $requested = self::normalize_parts($requested, 'requested_parts_invalid');
            if ($requested !== $retake) {
                self::reject('scope_must_include_all_retake_parts');
            }
        }

        $sourcebankhash = trim((string)($sourceattempt['bankhash'] ?? ''));
        $targetbankhash = trim((string)($options['target_bank_hash'] ?? ''));
        $provenance = [];
        foreach (array_keys(self::PARTS) as $part) {
            $partresult = $rubric['parts'][$part];
            $isretake = in_array($part, $retake, true);
            $provenance[$part] = [
                'action' => $isretake ? 'retake' : 'carry',
                'part' => $part,
                'source_part_status' => (string)$partresult['status'],
                'source_part_score' => (int)$partresult['score'],
                'source_attempt_id' => (int)$sourceattempt['id'],
                'source_review_id' => (int)$sourcereview['id'],
                'source_bank_version' => $sourceversion,
                'source_bank_hash' => $sourcebankhash,
                'source_rubric_hash' => $sourcerubrictash,
                'source_public_snapshot_hash' => $sourcepartsnapshots[$part],
                'target_public_snapshot_hash' => $targetpartsnapshots[$part],
            ];
            if ($isretake) {
                $provenance[$part]['retake_reason'] = $retakereasons[$part];
            }
        }

        return [
            'schema' => self::PLAN_SCHEMA,
            'mode' => 'partial',
            'source_attempt_id' => (int)$sourceattempt['id'],
            'source_review_id' => (int)$sourcereview['id'],
            'source_attempt_no' => (int)($sourceattempt['attemptno'] ?? 0),
            'source_bank_version' => $sourceversion,
            'source_bank_hash' => $sourcebankhash,
            'target_bank_version' => $targetversion,
            'target_bank_hash' => $targetbankhash,
            'source_rubric_hash' => $sourcerubrictash,
            'target_rubric_hash' => $targetrubrictash,
            'source_public_snapshot_hash' => $sourcepublichash,
            'target_public_snapshot_hash' => $targetpublichash,
            'source_part_snapshot_hashes' => $sourcepartsnapshots,
            'target_part_snapshot_hashes' => $targetpartsnapshots,
            'retake_parts' => $retake,
            'carry_parts' => $carry,
            'provenance' => $provenance,
            'rules' => [
                'carried_parts_must_be_passed' => true,
                'all_unpassed_or_changed_parts_must_be_retaken' => true,
                'part_compatibility_uses_saved_public_snapshot' => true,
                'source_attempt_is_immutable' => true,
                'source_review_is_immutable' => true,
            ],
        ];
    }

    /**
     * Validate and return the full safe public snapshot used for comparison.
     * The saved attempt contains no answer key, but it does contain the learner-
     * facing prompt, option text, explanations and scenario text. Those fields
     * are part of compatibility; IDs or a shared rubric family are not enough.
     */
    public static function public_snapshot(array $questions): array {
        return self::public_question_contract(self::extract_questions($questions));
    }

    /** Return the stable rubric contract and its hash for a public snapshot. */
    public static function rubric(array $questions): array {
        $public = self::public_snapshot($questions);
        $contract = self::rubric_contract_from_public($public);
        return ['schema' => self::RUBRIC_SCHEMA, 'contract' => $contract,
            'hash' => self::hash_value($contract)];
    }

    /**
     * Assert that a review is the one distinct latest review for an attempt.
     * Review history is optional so the helper remains usable before DB wiring.
     */
    public static function assert_latest_review(array $sourceattempt, array $sourcereview,
            array $options = []): void {
        $attemptid = self::positive_id($sourceattempt, 'id', 'source_attempt_invalid');
        $reviewid = self::positive_id($sourcereview, 'id', 'review_invalid');
        if ((int)($sourcereview['attemptid'] ?? 0) !== $attemptid) {
            self::reject('review_attempt_mismatch');
        }
        $expected = $options['latest_review_id'] ?? ($sourceattempt['latest_review_id'] ?? null);
        if ($expected !== null && (int)$expected !== $reviewid) {
            self::reject('review_not_latest');
        }
        if (array_key_exists('review_history', $options)) {
            if (!is_array($options['review_history'])) {
                self::reject('review_history_invalid');
            }
            $matching = [];
            foreach ($options['review_history'] as $review) {
                if (!is_array($review) || (int)($review['attemptid'] ?? 0) !== $attemptid) {
                    continue;
                }
                $matching[] = $review;
            }
            if (count($matching) !== 1 || (int)($matching[0]['id'] ?? 0) !== $reviewid) {
                self::reject('review_not_distinct');
            }
        }
    }

    private static function assert_attempt(array $attempt): void {
        self::positive_id($attempt, 'id', 'source_attempt_invalid');
        if (isset($attempt['kind']) && (string)$attempt['kind'] !== 'final') {
            self::reject('source_attempt_not_final');
        }
        if (!array_key_exists('questions', $attempt) && !array_key_exists('questionsjson', $attempt)) {
            self::reject('source_questions_missing');
        }
    }

    private static function assert_review(array $attempt, array $review): void {
        self::positive_id($review, 'id', 'review_invalid');
        if ((int)($review['attemptid'] ?? 0) !== (int)$attempt['id']) {
            self::reject('review_attempt_mismatch');
        }
        if ((int)($review['reviewerid'] ?? 0) <= 0) {
            self::reject('reviewer_invalid');
        }
        if (!in_array((string)($review['decision'] ?? ''), ['rework', 'not_ready'], true)) {
            self::reject('review_decision_not_retakeable');
        }
        if (!array_key_exists('rubric', $review) && !array_key_exists('rubricjson', $review)) {
            self::reject('rubric_missing');
        }
    }

    private static function assert_final10_version(string $version, string $error): void {
        if (!preg_match('/-final\.10-v[1-3]$/', $version)) {
            self::reject($error);
        }
    }

    private static function assert_rubric(array $rubric, array $contract, string $expectedhash): void {
        if ((string)($rubric['schema'] ?? '') !== self::RUBRIC_SCHEMA) {
            self::reject('legacy_rubric');
        }
        if (isset($rubric['rubric_hash']) &&
                !hash_equals($expectedhash, (string)$rubric['rubric_hash'])) {
            self::reject('source_rubric_hash_mismatch');
        }
        $expectedcriteria = self::criterion_ids($contract);
        $criteria = $rubric['criteria'] ?? null;
        if (!is_array($criteria) || self::sorted_keys($criteria) !== $expectedcriteria) {
            self::reject('rubric_criteria_incomplete');
        }
        foreach ($expectedcriteria as $id) {
            $item = $criteria[$id];
            $score = is_array($item) ? ($item['score'] ?? null) : null;
            if ((!is_int($score) && !is_string($score)) ||
                    !in_array((string)$score, ['0', '1', '2'], true)) {
                self::reject('rubric_score_invalid');
            }
        }
        $parts = $rubric['parts'] ?? null;
        if (!is_array($parts) || self::sorted_keys($parts) !== self::sorted_keys(self::PARTS)) {
            self::reject('rubric_parts_incomplete');
        }
        foreach (array_keys(self::PARTS) as $part) {
            $item = $parts[$part];
            if (!is_array($item) || !in_array((string)($item['status'] ?? ''), ['passed', 'failed'], true)) {
                self::reject('rubric_part_status_invalid');
            }
            $score = $item['score'] ?? null;
            if ((!is_int($score) && !is_string($score)) || (int)$score < 0 || (int)$score > 10) {
                self::reject('rubric_part_score_invalid');
            }
            if ((string)$item['status'] === 'passed' && (int)$score < self::PART_THRESHOLDS[$part]) {
                self::reject('rubric_part_status_invalid');
            }
            foreach ($contract['parts'][$part]['criteria'] as $criterion) {
                $required = (int)$criterion['required_score'];
                if ((string)$item['status'] === 'passed' && $required > 0 &&
                        (int)$criteria[$criterion['id']]['score'] < $required) {
                    self::reject('rubric_required_criterion_failed');
                }
            }
        }
    }

    private static function extract_questions(array $value): array {
        if (array_key_exists('questions', $value)) {
            $value = $value['questions'];
        }
        if (!is_array($value) || !$value || !array_is_list($value)) {
            self::reject('questions_snapshot_invalid');
        }
        return array_values($value);
    }

    private static function field_array(array $record, string $arraykey, string $jsonkey): array {
        if (array_key_exists($arraykey, $record)) {
            if (!is_array($record[$arraykey])) {
                self::reject($arraykey . '_invalid');
            }
            return $record[$arraykey];
        }
        if (!array_key_exists($jsonkey, $record) || !is_string($record[$jsonkey])) {
            self::reject($arraykey . '_missing');
        }
        try {
            $value = json_decode($record[$jsonkey], true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $error) {
            self::reject($arraykey . '_invalid');
        }
        if (!is_array($value)) {
            self::reject($arraykey . '_invalid');
        }
        return $value;
    }

    private static function public_question_contract(array $questions): array {
        $result = [];
        $seen = [];
        foreach ($questions as $question) {
            if (!is_array($question)) {
                self::reject('question_invalid');
            }
            foreach (self::SECRET_KEYS as $secret) {
                if (array_key_exists($secret, $question)) {
                    self::reject('non_public_snapshot');
                }
            }
            $id = trim((string)($question['id'] ?? ''));
            $type = (string)($question['type'] ?? '');
            $part = (string)($question['part'] ?? '');
            if ($id === '' || isset($seen[$id]) || !in_array($part, array_keys(self::PARTS), true) ||
                    !in_array($type, ['choice', 'text'], true)) {
                self::reject('question_contract_invalid');
            }
            $status = (string)($question['status'] ?? '');
            if ($status !== 'approved_structure') {
                self::reject('draft_question_snapshot');
            }
            $seen[$id] = true;
            $item = [
                'id' => $id,
                'type' => $type,
                'part' => $part,
                'status' => $status,
                'hardfail' => !empty($question['hardfail']),
                'prompt' => (string)($question['prompt'] ?? ''),
                'explanation' => (string)($question['explanation'] ?? ''),
                'source_ref' => (string)($question['source_ref'] ?? ''),
            ];
            if ($type === 'choice') {
                $options = $question['options'] ?? null;
                if (!is_array($options) || !$options) {
                    self::reject('choice_options_missing');
                }
                $optionsnapshot = [];
                foreach ($options as $option) {
                    if (!is_array($option) || trim((string)($option['id'] ?? '')) === '') {
                        self::reject('choice_option_invalid');
                    }
                    $optionsnapshot[] = [
                        'id' => (string)$option['id'],
                        'text' => (string)($option['text'] ?? ''),
                    ];
                }
                $optionids = array_column($optionsnapshot, 'id');
                if (count($optionids) !== count(array_unique($optionids))) {
                    self::reject('choice_options_duplicate');
                }
                usort($optionsnapshot, static fn(array $a, array $b): int => strcmp($a['id'], $b['id']));
                $item['options'] = $optionsnapshot;
            } else {
                $criteria = $question['criteria'] ?? null;
                if (!is_array($criteria) || !$criteria) {
                    self::reject('text_criteria_missing');
                }
                $item['criteria'] = self::normalize_criteria($criteria);
            }
            if (isset($question['turns'])) {
                $item['turns'] = self::normalize_turns($question['turns']);
            }
            if (isset($question['training_offer'])) {
                $item['training_offer'] = self::normalize_offer($question['training_offer']);
            }
            if (isset($question['training_spread'])) {
                $item['training_spread'] = self::normalize_spread($question['training_spread']);
            }
            $result[] = $item;
        }
        $counts = array_fill_keys(array_keys(self::PARTS), 0);
        foreach ($result as $item) {
            $counts[$item['part']]++;
        }
        if ($counts !== self::PART_QUESTION_COUNTS) {
            self::reject('part_question_counts_invalid');
        }
        return $result;
    }

    /** Hash each part independently so a passed part is carried only unchanged. */
    private static function part_snapshot_hashes(array $public): array {
        $parts = array_fill_keys(array_keys(self::PARTS), []);
        foreach ($public as $question) {
            $parts[$question['part']][] = $question;
        }
        foreach ($parts as $part => &$questions) {
            usort($questions, static fn(array $a, array $b): int => strcmp($a['id'], $b['id']));
            $questions = self::hash_value($questions);
        }
        unset($questions);
        return $parts;
    }

    private static function rubric_contract_from_public(array $public): array {
        $parts = [];
        foreach (array_keys(self::PARTS) as $part) {
            $parts[$part] = [
                'threshold' => self::PART_THRESHOLDS[$part],
                'questions' => [],
                'criteria' => [],
            ];
        }
        foreach ($public as $question) {
            $part = $question['part'];
            $parts[$part]['questions'][] = [
                'id' => $question['id'],
                'type' => $question['type'],
                'hardfail' => $question['hardfail'],
            ];
            foreach (($question['criteria'] ?? []) as $criterion) {
                $parts[$part]['criteria'][] = [
                    'id' => $criterion['id'],
                    'label' => $criterion['label'],
                    'required_score' => $criterion['required_score'],
                    'question_id' => $question['id'],
                ];
            }
        }
        foreach ($parts as &$part) {
            usort($part['questions'], static fn(array $a, array $b): int => strcmp($a['id'], $b['id']));
            usort($part['criteria'], static fn(array $a, array $b): int => strcmp($a['id'], $b['id']));
        }
        unset($part);
        return ['schema' => self::RUBRIC_SCHEMA, 'parts' => $parts];
    }

    private static function normalize_criteria(array $criteria): array {
        $result = [];
        $seen = [];
        foreach ($criteria as $criterion) {
            if (!is_array($criterion)) {
                self::reject('criterion_invalid');
            }
            $id = trim((string)($criterion['id'] ?? ''));
            $label = trim((string)($criterion['label'] ?? ''));
            $required = (int)($criterion['required_score'] ?? 0);
            if ($id === '' || isset($seen[$id]) || $label === '' || $required < 0 || $required > 2) {
                self::reject('criterion_contract_invalid');
            }
            $seen[$id] = true;
            $result[] = ['id' => $id, 'label' => $label, 'required_score' => $required];
        }
        usort($result, static fn(array $a, array $b): int => strcmp($a['id'], $b['id']));
        return $result;
    }

    private static function normalize_turns($turns): array {
        if (!is_array($turns) || !$turns) {
            self::reject('dialogue_turns_invalid');
        }
        $result = [];
        foreach ($turns as $turn) {
            if (!is_array($turn) || !isset($turn['number']) || trim((string)($turn['phase'] ?? '')) === '') {
                self::reject('dialogue_turn_invalid');
            }
            $result[] = [
                'number' => (int)$turn['number'],
                'phase' => (string)$turn['phase'],
                'client' => (string)($turn['client'] ?? ''),
            ];
        }
        usort($result, static fn(array $a, array $b): int => $a['number'] <=> $b['number']);
        return $result;
    }

    private static function normalize_offer($offer): array {
        if (!is_array($offer)) {
            self::reject('training_offer_invalid');
        }
        foreach (['format', 'duration', 'price'] as $key) {
            if (trim((string)($offer[$key] ?? '')) === '') {
                self::reject('training_offer_invalid');
            }
        }
        return ['format' => (string)$offer['format'], 'duration' => (string)$offer['duration'],
            'price' => (string)$offer['price'], 'notice' => (string)($offer['notice'] ?? '')];
    }

    private static function normalize_spread($spread): array {
        if (!is_array($spread) || !$spread) {
            self::reject('training_spread_invalid');
        }
        $result = [];
        foreach ($spread as $card) {
            if (!is_array($card) || trim((string)($card['position'] ?? '')) === '' ||
                    trim((string)($card['card'] ?? '')) === '') {
                self::reject('training_spread_invalid');
            }
            $result[] = [
                'position' => (string)$card['position'],
                'card' => (string)$card['card'],
                'hint' => (string)($card['hint'] ?? ''),
            ];
        }
        return $result;
    }

    private static function criterion_ids(array $contract): array {
        $ids = [];
        foreach ($contract['parts'] as $part) {
            foreach ($part['criteria'] as $criterion) {
                $ids[] = $criterion['id'];
            }
        }
        sort($ids, SORT_STRING);
        return $ids;
    }

    private static function normalize_parts($parts, string $error): array {
        if (!is_array($parts) || !$parts) {
            self::reject($error);
        }
        $parts = array_values(array_unique(array_map('strval', $parts)));
        foreach ($parts as $part) {
            if (!array_key_exists($part, self::PARTS)) {
                self::reject($error);
            }
        }
        $ordered = [];
        foreach (array_keys(self::PARTS) as $part) {
            if (in_array($part, $parts, true)) {
                $ordered[] = $part;
            }
        }
        return $ordered;
    }

    private static function positive_id(array $record, string $key, string $error): int {
        $id = (int)($record[$key] ?? 0);
        if ($id <= 0) {
            self::reject($error);
        }
        return $id;
    }

    private static function sorted_keys(array $value): array {
        $keys = array_map('strval', array_keys($value));
        sort($keys, SORT_STRING);
        return $keys;
    }

    private static function hash_value($value): string {
        return hash('sha256', self::encode(self::canonical($value)));
    }

    private static function canonical($value) {
        if (!is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            return array_map([self::class, 'canonical'], $value);
        }
        $keys = array_keys($value);
        sort($keys, SORT_STRING);
        $result = [];
        foreach ($keys as $key) {
            $result[(string)$key] = self::canonical($value[$key]);
        }
        return $result;
    }

    private static function encode($value): string {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private static function reject(string $code): void {
        throw new \InvalidArgumentException($code);
    }
}
