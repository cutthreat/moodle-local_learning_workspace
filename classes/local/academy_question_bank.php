<?php
namespace local_learning_workspace\local;
defined('MOODLE_INTERNAL') || die();

final class academy_question_bank {
    public const VERSION = 'community-demo-0.1';
    private const APPROVED = 'approved_structure';

    public static function day(string $lessonid): array {
        $lessonid = strtoupper(trim($lessonid));
        if (!preg_match('/^L[1-7]$/', $lessonid)) {
            throw new \InvalidArgumentException('Unknown demo module id');
        }
        return self::test($lessonid, 'Демо-проверка', [
            self::choice($lessonid . '-Q1', null,
                'Какое действие отправляет текущую редакцию проверяющему?',
                ['a' => 'Отправка', 'b' => 'Сохранение черновика', 'c' => 'Просмотр истории'],
                'a', 'Отправка создаёт проверяемую редакцию.'),
            self::text($lessonid . '-Q2', 'practice',
                'Опишите нейтральный порядок сохранения и отправки ответа.',
                'Укажите отдельные шаги сохранения, проверки редакции и отправки.',
            ),
        ]);
    }

    public static function final(int $variant = 1): array {
        $variant = max(1, min(3, $variant));
        $questions = [];
        for ($i = 1; $i <= 10; $i++) {
            $questions[] = self::choice('F-BASE-' . sprintf('%02d', $i), 'foundation',
                'Какое состояние показывает, что ответ сохранён как черновик?',
                ['a' => 'Черновик сохранён', 'b' => 'Ответ проверен', 'c' => 'Сессия завершена'],
                'a', 'Сохранённый черновик не означает, что ответ был отправлен.');
        }
        for ($i = 1; $i <= 10; $i++) {
            $questions[] = self::choice('F-PROCESS-' . sprintf('%02d', $i), 'process',
                'Что делает участник после получения замечания к отправленной версии?',
                ['a' => 'Создаёт редакцию и отправляет её повторно', 'b' => 'Удаляет историю', 'c' => 'Считает ответ одобренным'],
                'a', 'Повторная проверка относится к новой отправленной редакции.');
        }
        $questions[] = self::text('F-DIALOG-01', 'dialogue',
            'Опишите по шагам нейтральное взаимодействие участника с проверяющим.',
            'Укажите отправленную редакцию, обратную связь и следующее действие.',
            ['B1', 'B2', 'B3', 'B4', 'B5']);
        for ($i = 1; $i <= 5; $i++) {
            $questions[] = self::text('F-CASE-' . sprintf('%02d', $i), 'complex',
                'Как проверить, что новая редакция ответа готова к отправке?',
                'Назовите проверяемые поля и подтвердите выбранное состояние.',
                ['C' . $i], in_array($i, [1, 3, 4], true) ? 2 : 0);
        }
        $questions[] = self::text('F-PROMO-PROJECT', 'promotion',
            'Предложите нейтральный способ показать участнику ход проверки ответа.',
            'Дайте понятный статус, историю редакций и следующий шаг.',
            ['G1', 'G2', 'G3', 'G4', 'G5']);

        foreach ($questions as &$question) {
            $question['variant'] = $variant;
        }
        unset($question);
        return self::test('FINAL', 'Итоговая демо-проверка', $questions);
    }

    public static function final_variant_for_attempt(int $attemptno): int {
        return (($attemptno - 1) % 3) + 1;
    }

    private static function test(string $id, string $title, array $questions): array {
        return ['id' => $id, 'version' => self::VERSION, 'title' => $title, 'questions' => $questions];
    }

    private static function choice(string $id, ?string $part, string $prompt, array $options,
            string $correct, string $explanation, bool $hardfail = false): array {
        $normalized = [];
        foreach ($options as $optionid => $text) {
            $normalized[] = ['id' => (string)$optionid, 'text' => $text];
        }
        $item = [
            'type' => 'choice',
            'id' => $id,
            'prompt' => $prompt,
            'options' => $normalized,
            'correct' => $correct,
            'explanation' => $explanation,
            'source_ref' => 'synthetic-demo',
            'status' => self::APPROVED,
        ];
        if ($part !== null) {
            $item['part'] = $part;
        }
        if ($hardfail) {
            $item['hardfail'] = true;
        }
        return $item;
    }

    private static function text(string $id, string $part, string $prompt, string $explanation,
            array $criterionids = [], int $requiredscore = 0): array {
        $question = [
            'type' => 'text',
            'id' => $id,
            'part' => $part,
            'prompt' => $prompt,
            'explanation' => $explanation,
            'source_ref' => 'synthetic-demo',
            'status' => self::APPROVED,
        ];
        if ($id === 'F-DIALOG-01') {
            $question['turns'] = [];
            $question['training_offer'] = [];
            $question['training_spread'] = [];
            $question['spread_notice'] = 'Synthetic example; no lesson material or personal data.';
        }
        if ($criterionids) {
            $question['criteria'] = [];
            foreach ($criterionids as $criterionid) {
                $question['criteria'][] = [
                    'id' => $criterionid,
                    'label' => 'Criterion ' . $criterionid,
                    'required_score' => ($requiredscore > 0 ? $requiredscore : (in_array($criterionid, ['B3', 'C1', 'C3', 'C4'], true) ? 2 : 0)),
                ];
            }
        }
        return $question;
    }
}
