<?php
namespace local_learning_workspace\local;
defined('MOODLE_INTERNAL') || die();

/** Seven synthetic workflow modules; no course descriptions or external links. */
final class lesson_catalog {
    public static function attempt_count(int $count): string {
        return $count . ($count === 1 ? ' попытка' : ' попыток');
    }

    public static function all(): array {
        $lessons = [];
        for ($i = 1; $i <= 7; $i++) {
            $lessons[] = [
                'id' => 'L' . $i,
                'title' => 'Демо-модуль ' . $i,
                'subtitle' => 'Нейтральный пример рабочего процесса',
                'outcome' => 'Практика сохранения ответа и передачи его на проверку.',
            ];
        }
        return $lessons;
    }

    public static function by_id(string $id): ?array {
        $id = strtoupper(trim($id));
        foreach (self::all() as $lesson) {
            if ($lesson['id'] === $id) {
                return $lesson;
            }
        }
        return null;
    }
}
