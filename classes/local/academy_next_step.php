<?php
namespace local_learning_workspace\local;

defined('MOODLE_INTERNAL') || die();

/** Learner-facing primary action after the latest final-exam decision. */
final class academy_next_step {
    public static function for_final_status(string $status): array {
        return match ($status) {
            'awaiting_review' => [
                'text' => 'Ваш итоговый экзамен ожидает решения проверяющего. Ответы сохранены; новую работу пока отправлять не нужно.',
                'label' => 'Посмотреть отправленные ответы →',
                'destination' => 'final',
            ],
            'ready' => [
                'text' => 'Решение по учебному экзамену готово. Рабочий доступ оформляется отдельно.',
                'label' => 'Посмотреть решение →',
                'destination' => 'final',
            ],
            'rework' => [
                'text' => 'Тренер указал, что нужно исправить. Прочитайте замечания и обсудите работу над ошибками. Повторная отправка зависит от правил курса.',
                'label' => 'Посмотреть замечания →',
                'destination' => 'final',
            ],
            'not_ready' => [
                'text' => 'Экзамен пока не подтверждает готовность. Посмотрите замечания, повторите нужные темы и обсудите дальнейшие шаги с тренером.',
                'label' => 'Посмотреть решение и замечания →',
                'destination' => 'final',
            ],
            default => [
                'text' => 'Начните с первого урока или проверьте знания сразу на итоговом экзамене.',
                'label' => 'Открыть урок 1 →',
                'destination' => 'lesson_one',
            ],
        };
    }
}
