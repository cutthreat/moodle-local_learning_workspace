<?php
namespace local_learning_workspace\local;
defined('MOODLE_INTERNAL') || die();

/** Generic sample content for the draft, submit, and review workflow. */
final class lesson_one {
    public const VERSION = 'community-demo-0.1';
    public const PREVIOUS_VERSION = 'community-demo-0.0';
    public const PRIOR_EDITIONS = [self::PREVIOUS_VERSION];

    public static function content(): array {
        return [
            'title' => 'Демо-практика',
            'positioning' => 'Нейтральный пример сохранения и проверки редакций ответа.',
            'steps' => ['Обзор', 'Пример', 'Проверка', 'Ваш ответ', 'Итог'],
            'flow' => [
                'Сохраните черновик.',
                'Проверьте выбранную редакцию.',
                'Отправьте её на проверку.',
            ],
            'intro' => ['heading' => 'Демонстрационный процесс', 'body' => 'Этот пример не содержит учебного курса.'],
            'anchors' => ['Сохранить черновик', 'Отправить ответ', 'Получить замечание'],
            'example' => [
                'prompt' => 'Участник обновляет ранее сохранённый ответ.',
                'response' => 'Сохраните новую редакцию и отправьте её на повторную проверку.',
                'notes' => ['Черновик не меняет предыдущую отправку.', 'Проверка относится к выбранной редакции.'],
                'alternatives' => ['Сохранить отдельно.', 'Показать понятный статус.'],
            ],
            'check' => [
                'prompt' => 'Какой шаг передаёт ответ проверяющему?',
                'options' => ['a' => 'Отправка ответа', 'b' => 'Сохранение черновика'],
                'correct' => 'a',
                'feedback' => [
                    'a' => 'Верно: отправка создаёт проверяемую редакцию.',
                    'b' => 'Сохранение оставляет ответ черновиком.',
                ],
            ],
            'quiz' => [[
                'id' => 'DEMO-Q1',
                'prompt' => 'Какой шаг передаёт ответ проверяющему?',
                'options' => ['a' => 'Отправка ответа', 'b' => 'Сохранение черновика'],
                'correct' => 'a',
            ]],
            'practice' => [
                3 => ['prompt' => 'Выберите действие, которое отправляет редакцию.', 'criteria' => ['Выбран шаг отправки.']],
                4 => ['prompt' => 'Запишите порядок действий для новой редакции.', 'criteria' => ['Черновик и отправка разделены.']],
                5 => ['prompt' => 'Проверьте итоговый статус.', 'criteria' => ['Указан статус проверки.']],
            ],
            'extra_practice' => [],
        ];
    }

    public static function carry_previous_practice(array $previous): array {
        $state = self::initial();
        if (isset($previous['answers'][4]) && is_array($previous['answers'][4])) {
            $state['answers'][4] = $previous['answers'][4];
            $state['answers'][4]['reviewed'] = false;
        }
        return $state;
    }

    public static function encode(array $value): string {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    public static function initial(): array {
        return ['maxstage' => 1, 'choice' => '', 'checkpassed' => false, 'answers' => [], 'completed' => false];
    }

    public static function navigation_stage(array $state, int $requested, bool $canwrite): int {
        $maximum = $canwrite ? max(1, min(5, (int)($state['maxstage'] ?? 1))) : 4;
        return max(1, min($requested, $maximum));
    }

    public static function transition(array $state, array $content, string $action, int $stage, string $value): array {
        $state['maxstage'] = max(1, min(5, (int)($state['maxstage'] ?? 1)));
        if ($stage < 1 || $stage > $state['maxstage']) {
            throw new \DomainException('Этот шаг пока не открыт. Продолжите с текущего шага.');
        }
        if ($action === 'check' && $stage !== 3) {
            throw new \DomainException('Проверка доступна только на шаге 3.');
        } elseif ($action === 'check') {
            try {
                $answers = json_decode($value, true, 16, JSON_THROW_ON_ERROR);
            } catch (\JsonException $error) {
                throw new \DomainException('Ответьте на все вопросы демо-проверки.');
            }
            $questions = $content['quiz'] ?? [];
            if (!is_array($answers) || count($answers) !== count($questions)) {
                throw new \DomainException('Ответьте на все вопросы демо-проверки.');
            }
            $score = 0;
            foreach ($questions as $question) {
                $answer = $answers[$question['id']] ?? null;
                if (!is_string($answer) || !isset($question['options'][$answer])) {
                    throw new \DomainException('Выберите ответ на каждый вопрос.');
                }
                $score += (int)($answer === $question['correct']);
            }
            $state['quizanswers'] = $answers;
            $state['quizscore'] = $score;
            $state['checkpassed'] = $score === count($questions);
            if (!$state['checkpassed']) {
                $state['completed'] = false;
            }
            return $state;
        } elseif (in_array($action, ['draft', 'submit'], true) && $stage !== 4) {
            throw new \DomainException('Ответ можно сохранить только на шаге 4.');
        } elseif (in_array($action, ['draft', 'submit'], true)) {
            if (strlen($value) > 16000) {
                throw new \DomainException('Ответ слишком длинный. Сократите его.');
            }
            if ($action === 'submit' && trim($value) === '') {
                throw new \DomainException('Сначала напишите демо-ответ.');
            }
            $old = $state['answers'][4] ?? ['submitted' => '', 'has_submission' => false];
            $old['draft'] = $value;
            if (trim($value) !== (string)($old['submitted'] ?? '')) {
                $old['reviewed'] = false;
                $state['completed'] = false;
            }
            if ($action === 'submit') {
                $old['submitted'] = trim($value);
                $old['has_submission'] = true;
                $old['reviewed'] = false;
                $state['completed'] = false;
            }
            $state['answers'][4] = $old;
        } elseif ($action === 'next') {
            if ($stage === 3 && empty($state['checkpassed'])) {
                throw new \DomainException('Сначала пройдите демо-проверку.');
            }
            if ($stage === 4) {
                $answer = $state['answers'][4] ?? [];
                if (empty($answer['has_submission'])) {
                    throw new \DomainException('Сначала сохраните и отправьте демо-ответ.');
                }
                $submitted = (string)($answer['submitted'] ?? '');
                if (trim((string)($answer['draft'] ?? '')) !== $submitted || trim($value) !== $submitted) {
                    throw new \DomainException('Сохраните текущую редакцию перед продолжением.');
                }
                if (empty($state['checkpassed'])) {
                    throw new \DomainException('Сначала пройдите демо-проверку.');
                }
                $state['answers'][4]['reviewed'] = true;
                $state['completed'] = true;
            } elseif ($stage >= 5) {
                throw new \DomainException('Демо-практика завершена.');
            }
            $state['maxstage'] = max((int)$state['maxstage'], min(5, $stage + 1));
        } else {
            throw new \DomainException('Неизвестное действие. Обновите страницу.');
        }
        return $state;
    }
}
