<?php
namespace local_learning_workspace\local;

defined('MOODLE_INTERNAL') || die();

/** Compare immutable final-attempt snapshots without merging their results. */
final class academy_retake_comparison {
    /**
     * @param array $previous Version, public questions, answers and reviewed rubric.
     * @param array $current Version, public questions, answers and optional reviewed rubric.
     */
    public static function build(array $previous, array $current): array {
        $base = [
            'compatible' => false,
            'reason' => 'different_contract',
            'previousversion' => (string)($previous['version'] ?? ''),
            'currentversion' => (string)($current['version'] ?? ''),
            'items' => [],
        ];
        $family = static function(string $version): string {
            return preg_match('/^(.+-final\.\d+)-v[123]$/', $version, $matches) ? $matches[1] : '';
        };
        $oldfamily = $family($base['previousversion']);
        if ($oldfamily === '' || $oldfamily !== $family($base['currentversion'])) {
            return $base;
        }
        $oldrubric = (array)($previous['rubric'] ?? []);
        $newrubric = (array)($current['rubric'] ?? []);
        if (($oldrubric['schema'] ?? '') !== 'five_part_v1' ||
                ($newrubric && ($newrubric['schema'] ?? '') !== 'five_part_v1')) {
            return $base;
        }
        $oldquestions = self::text_questions((array)($previous['questions'] ?? []));
        $newquestions = self::text_questions((array)($current['questions'] ?? []));
        if (!$oldquestions || array_keys($oldquestions) !== array_keys($newquestions)) {
            return $base;
        }
        $oldcriteria = self::criteria_contract($oldquestions);
        $newcriteria = self::criteria_contract($newquestions);
        if (!$oldcriteria || $oldcriteria !== $newcriteria || count($oldcriteria) !== 15) {
            return $base;
        }
        $oldanswers = (array)($previous['answers'] ?? []);
        $newanswers = (array)($current['answers'] ?? []);
        $items = [];
        foreach ($newquestions as $id => $question) {
            $before = trim((string)($oldanswers[$id] ?? ''));
            $after = trim((string)($newanswers[$id] ?? ''));
            $criteria = [];
            foreach ((array)$question['criteria'] as $criterion) {
                $criterionid = (string)$criterion['id'];
                $oldgrade = $oldrubric['criteria'][$criterionid] ?? null;
                $newgrade = $newrubric['criteria'][$criterionid] ?? null;
                if (!is_array($oldgrade) || !isset($oldgrade['score']) ||
                        ($newrubric && (!is_array($newgrade) || !isset($newgrade['score'])))) {
                    return $base;
                }
                $criteria[] = [
                    'id' => $criterionid,
                    'label' => (string)$criterion['label'],
                    'previous' => (int)$oldgrade['score'],
                    'current' => $newgrade === null ? null : (int)$newgrade['score'],
                    'previousnextstep' => (string)($oldgrade['nextstep'] ?? ''),
                    'currentnextstep' => (string)($newgrade['nextstep'] ?? ''),
                ];
            }
            $items[] = [
                'id' => $id,
                'part' => (string)$question['part'],
                'previousanswer' => $before,
                'currentanswer' => $after,
                'changed' => $before !== $after,
                'criteria' => $criteria,
            ];
        }
        return array_merge($base, ['compatible' => true, 'reason' => '', 'items' => $items]);
    }

    private static function text_questions(array $questions): array {
        $result = [];
        foreach ($questions as $question) {
            if (($question['type'] ?? '') !== 'text') {
                continue;
            }
            $id = (string)($question['id'] ?? '');
            if ($id === '' || isset($result[$id]) || !in_array(($question['part'] ?? ''),
                    ['dialogue', 'complex', 'promotion'], true)) {
                return [];
            }
            $result[$id] = $question;
        }
        return $result;
    }

    private static function criteria_contract(array $questions): array {
        $result = [];
        foreach ($questions as $question) {
            foreach ((array)($question['criteria'] ?? []) as $criterion) {
                $id = (string)($criterion['id'] ?? '');
                if ($id === '' || isset($result[$id])) {
                    return [];
                }
                $result[$id] = [
                    'questionid' => (string)$question['id'],
                    'part' => (string)$question['part'],
                    'label' => (string)($criterion['label'] ?? ''),
                    'requiredscore' => (int)($criterion['required_score'] ?? 0),
                ];
            }
        }
        return $result;
    }
}
