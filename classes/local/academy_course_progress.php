<?php
namespace local_learning_workspace\local;
defined('MOODLE_INTERNAL') || die();

/** Presentation only. Acceptance is supplied by the existing assessment summary. */
final class academy_course_progress {
    public static function render(int $submitted, int $passed, int $accepted, int $total): string {
        if ($total < 1 || $accepted < 0 || $passed < $accepted || $submitted < $passed || $submitted > $total) {
            throw new \InvalidArgumentException('Invalid course progress summary');
        }
        return '<h2 id="sa-progress-title">Ваш прогресс</h2>'.
            '<p class="sa-progress-summary"><strong>'.$accepted.'<span> / '.$total.'</span></strong>'.
            '<span id="sa-progress-label">Практика принята</span></p>'.
            '<progress class="sa-progress-meter" value="'.$accepted.'" max="'.$total.'" '.
            'aria-labelledby="sa-progress-label">'.$accepted.' из '.$total.'</progress>'.
            '<dl><div><dt>Тесты отправлены</dt><dd>'.$submitted.'<span> / '.$total.'</span></dd></div>'.
            '<div><dt>Тесты пройдены</dt><dd>'.$passed.'<span> / '.$total.'</span></dd></div></dl>';
    }
}
