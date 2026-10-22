<?php
namespace local_learning_workspace\local;

defined('MOODLE_INTERNAL') || die();

/** Stable display-only order for one candidate's assessment attempt. */
final class academy_option_order {
    public static function answer_token(string $questionid, string $optionid, string $tokenseed): string {
        return substr(hash('sha256', $tokenseed . "\0" . $questionid . "\0" . $optionid), 0, 24);
    }

    /** Convert only choice tokens; text responses keep their original content. */
    public static function decode_answers(array $pack, array $answers, string $tokenseed): array {
        foreach ($pack['questions'] as $question) {
            if (($question['type'] ?? '') !== 'choice') {
                continue;
            }
            $questionid = (string)$question['id'];
            if (!array_key_exists($questionid, $answers) || $answers[$questionid] === '') {
                continue;
            }
            if (!is_scalar($answers[$questionid])) {
                throw new \InvalidArgumentException('Invalid choice shape: ' . $questionid);
            }
            $submitted = (string)$answers[$questionid];
            $matched = false;
            foreach ($question['options'] as $option) {
                $optionid = (string)$option['id'];
                if (hash_equals(self::answer_token($questionid, $optionid, $tokenseed), $submitted)) {
                    $answers[$questionid] = $optionid;
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                throw new \InvalidArgumentException('Invalid choice token: ' . $questionid);
            }
        }
        return $answers;
    }

    public static function ordered(array $question, string $attemptseed): array {
        $options = array_values((array)($question['options'] ?? []));
        $questionid = (string)($question['id'] ?? '');
        usort($options, static function(array $left, array $right) use ($attemptseed, $questionid): int {
            $leftid = (string)($left['id'] ?? '');
            $rightid = (string)($right['id'] ?? '');
            $leftkey = hash('sha256', $attemptseed . "\0" . $questionid . "\0" . $leftid);
            $rightkey = hash('sha256', $attemptseed . "\0" . $questionid . "\0" . $rightid);
            return strcmp($leftkey, $rightkey) ?: strcmp($leftid, $rightid);
        });
        return $options;
    }
}
