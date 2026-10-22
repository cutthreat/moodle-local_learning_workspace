<?php
namespace local_learning_workspace\local;

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__.'/academy_page_shell.php');

/** Neutral, role-aware denial. It never exposes works or grants reviewer authority. */
final class academy_access_denied_view {
    public static function page(bool $candidate, bool $management = false): void {
        global $PAGE, $OUTPUT, $SCRIPT;
        $PAGE->set_context(\context_system::instance());
        $PAGE->set_url(new \moodle_url($SCRIPT));
        $PAGE->set_pagelayout('embedded');
        $PAGE->set_title('Доступ к разделу · Learning Workspace');
        $PAGE->requires->css(new \moodle_url('/local/learning_workspace/assets/academy-base.css'));
        $PAGE->requires->css(new \moodle_url('/local/learning_workspace/assets/academy.css'));
        http_response_code(403);
        echo academy_page_shell::header($OUTPUT);
        echo self::review($candidate, $management);
        echo $OUTPUT->footer();
    }

    public static function review(bool $candidate, bool $management = false): string {
        $url = $candidate ? 'course.php' : '../../my/';
        $label = $candidate ? 'К обучению' : 'На главную';
        $help = $candidate
            ? 'Ваши тесты и результаты доступны в программе обучения.'
            : 'Вернитесь на главную. Если проверка работ входит в ваши обязанности, обратитесь к администратору платформы.';
        $heading = $management ? 'Раздел доступен управляющему' : 'Раздел доступен экзаменатору';
        $reason = $management
            ? 'В текущей роли нельзя управлять объявлениями и обращениями других пользователей.'
            : 'В текущей роли нельзя просматривать или оценивать работы других учащихся.';
        if ($management) {
            $help = 'Свои обращения можно открыть в справке. Если управление платформой входит в ваши обязанности, уточните доступ у администратора.';
        }
        return '<div class="lw-lesson lw-academy" lang="ru">' .
            '<a class="lw-skip" href="#academy-denied-main">К основному содержимому</a>' .
            academy_page_shell::topbar($url, '← ' . $label) .
            '<div class="lw-workshop"><main id="academy-denied-main" class="lw-main">' .
            '<div class="lw-heading"><div class="lw-eyebrow">ДОСТУП К РАЗДЕЛУ</div>' .
            '<h1>' . $heading . '</h1></div>' .
            '<article class="lw-sheet"><div class="lw-sheetbody">' .
            '<p>' . $reason . '</p>' .
            '<p>' . $help . '</p><p><a href="' . $url . '">' . $label . '</a></p>' .
            '</div></article></main></div></div>';
    }
}
