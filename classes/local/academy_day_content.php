<?php
namespace local_learning_workspace\local;
defined('MOODLE_INTERNAL') || die();

/** Generic examples for the review workflow. No instructional course content. */
final class academy_day_content {
    public const VERSION = 'community-demo-0.1';

    public static function day(string $lessonid): array {
        $id = strtoupper(trim($lessonid));
        $lesson = lesson_catalog::by_id($id);
        if ($lesson === null) {
            throw new \InvalidArgumentException('Unknown demo module id');
        }
        $steps = [
            ['title' => 'Сохранить', 'body' => 'Сохраните рабочий черновик.'],
            ['title' => 'Отправить', 'body' => 'Отправьте выбранную редакцию на проверку.'],
            ['title' => 'Проверить', 'body' => 'Посмотрите состояние очереди и замечание проверяющего.'],
        ];
        return [
            'id' => $id,
            'version' => self::VERSION,
            'outcome' => $lesson['outcome'],
            'title' => $lesson['title'],
            'subtitle' => $lesson['subtitle'],
            'status' => 'approved_structure',
            'principle' => 'Черновик и отправленный ответ имеют разные состояния.',
            'micro_lessons' => $steps,
            'worked_example' => [
                'title' => 'Пример редакции',
                'prompt' => 'Участник исправил ответ после замечания.',
                'answer' => 'Сохраните исправление как новую редакцию и отправьте её на повторную проверку.',
                'notes' => ['Проверьте номер редакции.', 'Оставьте предыдущую отправленную версию в истории.'],
            ],
            'formative_case' => [
                'id' => $id . '-DEMO',
                'title' => 'Нейтральное демо-задание',
                'prompt' => 'Опишите, как сохранить черновик и отдельно отправить ответ проверяющему.',
                'client' => 'Demo participant',
                'checklist' => ['Черновик сохранён.', 'Отправка подтверждена.', 'Состояние видно участнику.'],
                'criteria' => ['Разделены сохранение и отправка.', 'Указан следующий шаг.'],
                'turns' => [],
            ],
            'knowledge_check' => [
                'prompt' => 'Что происходит с отправленной версией после сохранения черновика?',
                'options' => ['Она остаётся отдельной версией.', 'Она считается проверенной автоматически.'],
                'correct' => 0,
            ],
            'practice' => [
                'title' => 'Короткая практика',
                'task' => 'Запишите порядок действий для новой редакции ответа.',
                'example' => 'Сохранить черновик, проверить его, отправить.',
                'criteria' => ['Порядок действий ясен.'],
            ],
            'takeaway' => 'Сохраняйте каждую редакцию и отправляйте её явным действием.',
            'verification_note' => 'Учебное содержимое заменено нейтральным программным примером.',
            'work_rules' => ['Не изменяйте чужие ответы.', 'Не считайте черновик отправленным.'],
            'tool_practice' => [
                'title' => 'Проверка состояния',
                'body' => 'Сравните черновик, отправленную редакцию и результат проверки.',
                'task' => 'Назовите следующий шаг для участника.',
                'checklist' => ['Состояние распознано.', 'Следующий шаг указан.'],
                'feedback' => 'Замечание проверяющего относится к конкретной отправленной редакции.',
                'links' => [],
                'localpdf' => null,
            ],
            'check' => [
                'prompt' => 'Какое действие отправляет ответ проверяющему?',
                'options' => ['Отдельная отправка', 'Сохранение черновика'],
                'correct' => 0,
            ],
            'quiz' => [],
            'extra_practice' => [],
        ];
    }

    public static function all(): array {
        $result = [];
        foreach (lesson_catalog::all() as $lesson) {
            $result[$lesson['id']] = self::day($lesson['id']);
        }
        return $result;
    }
}
