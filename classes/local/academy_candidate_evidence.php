<?php
namespace local_learning_workspace\local;

defined('MOODLE_INTERNAL') || die();

/** Read-only formatting of an immutable day-attempt snapshot for the examiner card. */
final class academy_candidate_evidence {
    public static function questions(object $attempt): array {
        $questions = json_decode((string)($attempt->questionsjson ?? ''), true);
        $answers = json_decode((string)($attempt->answersjson ?? ''), true);
        $feedback = json_decode((string)($attempt->feedbackjson ?? ''), true);
        if (!is_array($questions)) {
            return [];
        }
        $answers = is_array($answers) ? $answers : [];
        $feedback = is_array($feedback) ? $feedback : [];
        $rows = [];
        foreach ($questions as $question) {
            if (!is_array($question)) {
                continue;
            }
            $id = (string)($question['id'] ?? '');
            $type = (string)($question['type'] ?? '');
            $answer = $answers[$id] ?? '';
            if ($type === 'choice') {
                foreach ((array)($question['options'] ?? []) as $option) {
                    if (is_array($option) && (string)($option['id'] ?? '') === (string)$answer) {
                        $answer = (string)($option['text'] ?? $answer);
                        break;
                    }
                }
            }
            $result = $feedback[$id]['correct'] ?? null;
            $rows[] = [
                'prompt' => (string)($question['prompt'] ?? ''),
                'answer' => is_scalar($answer) ? (string)$answer : '',
                'type' => $type,
                'result' => is_bool($result) ? $result : null,
                'draft' => ($question['status'] ?? '') !== 'approved_structure',
            ];
        }
        return $rows;
    }
}
