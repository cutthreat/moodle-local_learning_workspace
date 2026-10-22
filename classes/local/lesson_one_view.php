<?php
namespace local_learning_workspace\local;
defined('MOODLE_INTERNAL') || die();

/** Compact neutral view for the generic draft/review demonstration. */
final class lesson_one_view {
    private static function e(string $text): string {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function render(array $current, int $stage, string $sesskey, string $error = '', string $notice = '',
            ?string $unsaved = null, array $history = [], string $home = 'index.php', bool $canwrite = true,
            bool $sourceavailable = false): string {
        $content = $current['content'] ?? lesson_one::content();
        $state = $canwrite ? ($current['state'] ?? lesson_one::initial()) : lesson_one::initial();
        $stage = max(1, min(5, $stage));
        $steps = $content['steps'] ?? [];
        $html = '<main class="lesson-one-demo"><h1>' . self::e((string)($content['title'] ?? 'Демо-практика')) . '</h1>';
        $html .= '<p>' . self::e((string)($content['positioning'] ?? 'Нейтральный пример рабочего процесса.')) . '</p>';
        if ($error !== '') {
            $html .= '<div role="alert" class="alert alert-danger">' . self::e($error) . '</div>';
        }
        if ($notice !== '') {
            $html .= '<div role="status" class="alert alert-info">' . self::e($notice) . '</div>';
        }
        $html .= '<ol class="lesson-one-steps">';
        foreach ($steps as $index => $label) {
            $html .= '<li' . ($index + 1 === $stage ? ' aria-current="step"' : '') . '>' . self::e((string)$label) . '</li>';
        }
        $html .= '</ol>';
        if ($stage === 1) {
            $html .= '<h2>Обзор</h2><p>Черновик хранится отдельно от отправленного ответа. Проверяющий оставляет замечание к конкретной редакции.</p>';
        } elseif ($stage === 2) {
            $html .= '<h2>Пример</h2><p>Сохраните изменение, проверьте его и отправьте выбранную редакцию на проверку.</p>';
        } elseif ($stage === 3) {
            $question = $content['quiz'][0] ?? [];
            $selected = $state['quizanswers'][$question['id'] ?? ''] ?? '';
            $html .= '<h2>Демо-проверка</h2><p>' . self::e((string)($question['prompt'] ?? 'Выберите следующий шаг.')) . '</p>';
            $html .= '<form method="post"><input type="hidden" name="sesskey" value="' . self::e($sesskey) . '">';
            $html .= '<input type="hidden" name="revision" value="' . (int)($current['revision'] ?? 0) . '">';
            $html .= '<input type="hidden" name="contenthash" value="' . self::e((string)($current['hash'] ?? '')) . '">';
            $html .= '<input type="hidden" name="step" value="3">';
            foreach ((array)($question['options'] ?? []) as $optionid => $label) {
                $checked = $selected === (string)$optionid ? ' checked' : '';
                $html .= '<label><input type="radio" name="quiz[' . self::e((string)($question['id'] ?? 'DEMO-Q1')) . ']" value="' .
                    self::e((string)$optionid) . '"' . $checked . '> ' . self::e((string)$label) . '</label><br>';
            }
            $html .= '<button type="submit" name="action" value="check">Проверить ответ</button>';
            if (!empty($state['checkpassed'])) {
                $html .= '<button type="submit" name="action" value="next">Продолжить</button>';
            }
            $html .= '</form>';
        } elseif ($stage === 4 && $canwrite) {
            $answer = $unsaved ?? (string)($state['answers'][4]['draft'] ?? '');
            $html .= '<h2>Ваш демо-ответ</h2><p>Опишите отдельные шаги сохранения черновика и отправки редакции.</p>';
            $html .= '<form method="post"><input type="hidden" name="sesskey" value="' . self::e($sesskey) . '">';
            $html .= '<input type="hidden" name="revision" value="' . (int)($current['revision'] ?? 0) . '">';
            $html .= '<input type="hidden" name="contenthash" value="' . self::e((string)($current['hash'] ?? '')) . '">';
            $html .= '<input type="hidden" name="step" value="4">';
            $html .= '<textarea name="answer" rows="6" maxlength="16000">' . self::e((string)$answer) . '</textarea>';
            $html .= '<button type="submit" name="action" value="draft">Сохранить черновик</button>';
            $html .= '<button type="submit" name="action" value="submit">Отправить на проверку</button>';
            if (!empty($state['answers'][4]['has_submission'])) { $html .= '<button type="submit" name="action" value="next">Завершить демо</button>'; }
            $html .= '</form>';
        } elseif ($stage === 4) {
            $html .= '<h2>Пример ответа</h2><p>Черновик и отправленная редакция являются разными состояниями.</p>';
        } else {
            $html .= '<h2>Демо завершено</h2><p>Проверка и сохранение в этом примере не являются сертификатом или допуском к работе.</p>';
        }
        if ($stage < 5 && $canwrite && $stage !== 3 && $stage !== 4) {
            $html .= '<form method="post"><input type="hidden" name="sesskey" value="' . self::e($sesskey) . '">';
            $html .= '<input type="hidden" name="revision" value="' . (int)($current['revision'] ?? 0) . '">';
            $html .= '<input type="hidden" name="contenthash" value="' . self::e((string)($current['hash'] ?? '')) . '">';
            $html .= '<input type="hidden" name="step" value="' . $stage . '">';
            $html .= '<button type="submit" name="action" value="next">Продолжить</button></form>';
        }
        $html .= '<p><a href="' . self::e($home) . '">Вернуться к списку</a></p></main>';
        return $html;
    }
}
