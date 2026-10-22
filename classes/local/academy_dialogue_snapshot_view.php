<?php
namespace local_learning_workspace\local;

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/academy_branding.php');
require_once(__DIR__ . '/academy_dialogue_answer_codec.php');

/** Read-only case context from the immutable question snapshot of one attempt. */
final class academy_dialogue_snapshot_view {
    public static function answer_turns(string $answer): array {
        return academy_dialogue_answer_codec::decode($answer);
    }

    public static function has_complete_answers(array $question, string $answer): bool {
        if (($question['id'] ?? '') !== 'F-DIALOG-01' || empty($question['turns'])) {
            return false;
        }
        $answers = self::answer_turns($answer);
        $expected = array_map(static fn(array $turn): int => (int)($turn['number'] ?? 0),
            (array)$question['turns']);
        sort($expected, SORT_NUMERIC);
        $actual = array_keys($answers);
        sort($actual, SORT_NUMERIC);
        return $expected === $actual && count($expected) === 9 &&
            count(array_filter($answers, static fn(string $value): bool => $value === '')) === 0;
    }

    public static function render(array $question, bool $open = false, string $savedanswer = ''): string {
        if (($question['id'] ?? '') !== 'F-DIALOG-01' || empty($question['turns'])) {
            return '';
        }
        $escape = static fn($value): string => htmlspecialchars((string)$value,
            ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $question = academy_branding::copy($question); $turns = (array)$question['turns'];
        $answers = self::has_complete_answers($question, $savedanswer)
            ? self::answer_turns($savedanswer) : [];
        $html = '<details class="lw-details ac-case-context"' . ($open ? ' open' : '') . '><summary>' .
            'Условия диалога этой попытки · ' . count($turns) . ' ходов</summary>' .
            '<p class="ac-help">Для проверки используйте именно сохранённые условия этой попытки, ' .
            'а не текущую версию экзамена.</p>';
        foreach ($turns as $turn) {
            $number = (int)($turn['number'] ?? 0);
            $html .= '<section class="ac-dialogue-turn"><span class="ac-dialogue-phase">ХОД ' . $number .
                ' · ' . $escape($turn['phase'] ?? '') . '</span>';
            if ($number === 3 && !empty($question['training_offer'])) {
                $offer = $question['training_offer'];
                $html .= '<div class="ac-training-card"><strong>Учебная карточка услуги</strong><p>' .
                    $escape($offer['format'] ?? '') . ' · ' . $escape($offer['duration'] ?? '') .
                    ' · ' . $escape($offer['price'] ?? '') . '</p><small>' .
                    $escape(academy_branding::text($offer['notice'] ?? '')) . '</small></div>';
            }
            if ($number === 6 && !empty($question['training_spread'])) {
                $html .= '<div class="ac-training-card"><strong>Учебный расклад · три позиции</strong><ol>';
                foreach ((array)$question['training_spread'] as $card) {
                    $html .= '<li><b>' . $escape($card['position'] ?? '') . ' — ' .
                        $escape($card['card'] ?? '') . '</b><span>' . $escape($card['hint'] ?? '') .
                        '</span></li>';
                }
                $html .= '</ol><small>' . $escape($question['spread_notice'] ?? '') . '</small></div>';
            }
            $html .= '<p class="ac-client-message"><strong>Клиент</strong><br>' .
                $escape($turn['client'] ?? '') . '</p>';
            if (isset($answers[$number])) {
                $html .= '<div class="ac-turn-answer"><strong>Ответ на этот ход</strong><p>' .
                    nl2br($escape($answers[$number])) . '</p></div>';
            }
            $html .= '</section>';
        }
        return $html . '</details>';
    }
}
