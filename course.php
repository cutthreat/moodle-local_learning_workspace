<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/local/academy_page_shell.php');
require_once(__DIR__ . '/classes/local/lesson_catalog.php');
require_once(__DIR__ . '/classes/local/lesson_one_repository.php');
require_once(__DIR__ . '/classes/local/academy_assessment_repository.php');
require_once(__DIR__ . '/classes/local/academy_day_content.php');
require_once(__DIR__ . '/classes/local/academy_access.php');
require_once(__DIR__ . '/classes/local/academy_course_progress.php');

use local_learning_workspace\local\lesson_catalog;
use local_learning_workspace\local\lesson_one_repository;
use local_learning_workspace\local\academy_assessment_repository;
use local_learning_workspace\local\academy_day_content;
use local_learning_workspace\local\academy_access;

require_login();
$context = academy_access::require_viewer();
$islearner = \local_learning_workspace\local\academy_workspace::role()==='candidate' && academy_access::is_candidate();
$onboardpending=false;
if ($islearner && \local_learning_workspace\local\academy_blended::ready()) {
    $onboard=\local_learning_workspace\local\academy_blended::onboarding((int)$USER->id);
    $onboardpending=!$onboard || $onboard->status!=='complete';
    $alreadylearning=$DB->record_exists('local_lworkspace_acattempt',['userid'=>$USER->id])||$DB->record_exists('local_lworkspace_lesson',['userid'=>$USER->id])||$DB->record_exists('local_lworkspace_acdraft',['userid'=>$USER->id]);
    if ($onboardpending&&!$alreadylearning) { redirect(new moodle_url('/local/learning_workspace/academy_onboarding.php')); }
}
$startrole=\local_learning_workspace\local\academy_workspace::role();
$startkey='local_lworkspace_start_'.$startrole;
if (($_SERVER['REQUEST_METHOD']??'GET')==='POST' && optional_param('action','',PARAM_ALPHA)==='intro') {
    require_sesskey(); set_user_preference($startkey,1); redirect(new moodle_url('/local/learning_workspace/course.php'));
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/learning_workspace/course.php'));
$PAGE->set_pagelayout('embedded');
$PAGE->set_title('Learning Workspace · 7 уроков');
$PAGE->set_heading('Learning Workspace');
$PAGE->requires->css(new moodle_url('/local/learning_workspace/assets/academy-base.css', ['v' => '20261007-header1']));

$selected = strtoupper(optional_param('lesson', 'L1', PARAM_ALPHANUMEXT));
$catalog = lesson_catalog::all();
$lessonOneCompleted = !empty(lesson_one_repository::read((int)$USER->id)['state']['completed']);
$dayMap = academy_assessment_repository::day_summary((int)$USER->id);

$esc = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$status = static function(array $lesson) use ($dayMap, $lessonOneCompleted): array {
    $summary = $dayMap[$lesson['id']] ?? [];
    $latest = $summary['latest'] ?? null;
    $review = $summary['review'] ?? null;
    $result = $latest ? (string)$latest->status : '';
    $decision = $review ? (string)$review->decision : '';
    if ($result === 'passed' && $decision === 'practice_accepted') {
        if ($lesson['id'] !== 'L1' &&
                academy_day_content::day($lesson['id'])['status'] !== 'approved_structure') {
            return ['label' => 'Тест и практика приняты · урок проверяется', 'class' => ''];
        }
        return ['label' => 'Тест и практика приняты', 'class' => 'is-done'];
    }
    if ($result === 'passed' && $decision === 'practice_rework') {
        return ['label' => 'Доработать ответ', 'class' => ''];
    }
    if ($result === 'passed') {
        return ['label' => 'Тест пройден · ответ ждёт проверки', 'class' => ''];
    }
    if ($result === 'failed') {
        global $USER;
        $attemptstate = \local_learning_workspace\local\academy_attempt_policy::state((int)$USER->id,'day',$lesson['id']);
        return ['label' => $attemptstate['allowed'] ? 'Повтор разрешён' :
            ($attemptstate['awaiting_review'] ? 'Ответы на проверке' : 'Разбор с тренером'), 'class' => ''];
    }
    if ($result === 'draft_unverified') {
        return ['label' => 'Сохранённая версия задания требует проверки', 'class' => ''];
    }
    if ($lesson['id'] === 'L1' && $lessonOneCompleted) {
        return ['label' => 'Практика готова', 'class' => ''];
    }
    return ['label' => 'Доступен', 'class' => ''];
};
$link = static function(array $lesson): moodle_url {
    if ($lesson['id'] === 'L1') {
        return new moodle_url('/local/learning_workspace/lesson_one.php');
    }
    return new moodle_url('/local/learning_workspace/academy_day.php', ['lesson' => $lesson['id']]);
};
$submittedCount = 0;
$passedCount = 0;
$acceptedCount = 0;
$pilotCount = 0;
foreach ($catalog as $lesson) {
    $summary = $dayMap[$lesson['id']] ?? [];
    $latest = $summary['latest'] ?? null;
    if ($latest) {
        $submittedCount++;
        if ((string)$latest->status === 'passed') {
            $passedCount++;
            if ((string)($summary['review']->decision ?? '') === 'practice_accepted') {
                $acceptedCount++;
            }
        } else if ((string)$latest->status === 'draft_unverified') {
            $pilotCount++;
        }
    }
}
$coursePercent = (int)round(($submittedCount / max(1, count($catalog))) * 100);
$continue = null;
$actionstates = [];
foreach ($catalog as $item) {
    $summary = $dayMap[$item['id']] ?? [];
    $actionstates[$item['id']] = ['test'=>(string)($summary['latest']->status ?? ''),'review'=>(string)($summary['review']->decision ?? '')];
    if ($islearner && ($summary['latest']->status??'')==='failed') {
        $actionstates[$item['id']]['retry_allowed']=\local_learning_workspace\local\academy_attempt_policy::state((int)$USER->id,'day',$item['id'])['allowed'];
    }
}
$courseaction=\local_learning_workspace\local\academy_layout::course_action($actionstates);
foreach($catalog as $item){if($item['id']===$courseaction['lesson']){$continue=$item;break;}}

echo \local_learning_workspace\local\academy_page_shell::header($OUTPUT);
?>
<div class="lw-lesson lw-course" lang="ru">
  <a class="lw-skip" href="#course-content">К содержанию курса</a>
  <?= \local_learning_workspace\local\academy_page_shell::topbar() ?>
  <div class="lw-workshop">
    <div class="lw-heading"><p class="lw-eyebrow">Обучение экспертов · 7 уроков</p><h1>Программа обучения</h1><p>От первого сообщения до осмысленной консультации.</p></div>
    <div class="lw-layout">
      <main id="course-content" class="lw-main" tabindex="-1">
        <?php if($onboardpending):?><p class="aw-meta"><a href="academy_onboarding.php">Укажите язык и удобное время занятий →</a></p><?php endif;?>
        <div class="sa-course-overview">
        <section class="al-course-start" id="continue">
          <div class="sa-start-copy"><p class="sa-section-label"><?= !$islearner ? 'Программа для экспертов' : ($continue ? 'Ваш следующий урок · '.(int)substr($continue['id'],1) : 'Все ответы отправлены') ?></p>
          <?php if($islearner && $continue): ?>
          <h2><?= $esc($continue['subtitle']) ?></h2>
          <p class="sa-current-status"><?= $esc($status($continue)['label']) ?></p></div>
          <a class="lw-primary" href="<?= $esc($link($continue)->out(false)) ?>">Открыть урок <span aria-hidden="true">→</span></a>
          <?php elseif($islearner): ?>
          <h2>Результаты обучения</h2></div><a class="lw-primary" href="academy.php">Результаты <span aria-hidden="true">→</span></a>
          <?php else: ?>
          <h2>От знакомства — к практике</h2><p>Семь уроков с примерами, практикой и учебными материалами.</p></div><a class="lw-primary" href="#program">К урокам <span aria-hidden="true">↓</span></a>
          <?php endif; ?>
        </section>
        <?php if($islearner): ?>
        <aside class="sa-course-progress" aria-labelledby="sa-progress-title">
          <?= \local_learning_workspace\local\academy_course_progress::render($submittedCount, $passedCount, $acceptedCount, count($catalog)) ?>
          <?php if($continue): ?><a href="academy.php">Результаты и обратная связь <span aria-hidden="true">↗</span></a><?php endif; ?>
        </aside>
        <?php else: ?>
        <aside class="sa-course-progress"><h2>Работа с кандидатами</h2><a href="academy_review.php">Проверить работы <span aria-hidden="true">→</span></a><a href="academy_teaching.php">Занятия и группы <span aria-hidden="true">→</span></a></aside>
        <?php endif; ?>
        </div>
        <?= \local_learning_workspace\local\academy_quickstart::card() ?>
        <?php $nextmeeting=\local_learning_workspace\local\academy_blended::next_session();if($nextmeeting): ?>
        <section class="lw-note"><strong>Ближайшее занятие с тренером</strong><p><?= $esc($nextmeeting->title) ?> · <?= $esc(userdate($nextmeeting->startsat,'%d.%m.%Y · %H:%M')) ?></p><a href="<?= $esc(\local_learning_workspace\local\academy_blended::url('session',['id'=>$nextmeeting->id])->out(false)) ?>">Время, подключение и вопросы →</a></section>
        <?php endif; ?>
        <article class="lw-sheet sa-syllabus"><div class="lw-workbenchbar"><h2>Все уроки <span class="sa-section-count">7</span></h2><div class="sa-course-links"><a href="academy_entry.php">Самопроверка <span aria-hidden="true">↗</span></a><a href="#course-final">Итог курса <span aria-hidden="true">↓</span></a></div></div><div class="lw-sheetbody">
          <ol class="lw-anchors" id="program">
<?php foreach ($catalog as $index => $lesson): $state = $status($lesson); $href = $link($lesson)->out(false); $daytest = (new moodle_url('/local/learning_workspace/academy.php', ['view' => 'day', 'lesson' => $lesson['id']]))->out(false); ?>
            <li<?= $islearner && $continue && $continue['id']===$lesson['id'] ? ' class="sa-current-lesson"' : '' ?>>
              <span aria-hidden="true">0<?= $index + 1 ?></span>
              <div class="lw-course-lesson-content">
                <div class="lw-course-lesson-heading">
                  <h2><a href="<?= $esc($href) ?>"><?= $esc($lesson['subtitle']) ?></a></h2>
                  <span class="lw-lesson-status <?= $esc($state['class']) ?>"><?= $esc($state['label']) ?></span>
                </div>
                <div class="lw-course-lesson-description">
                  <p><?= $esc($lesson['title']) ?></p>
                </div>
                <div class="lw-actions">
                  <a class="lw-secondary" href="<?= $esc($href) ?>" aria-label="Открыть урок <?= $index + 1 ?>">Открыть урок <span aria-hidden="true">→</span></a>
                  <a href="<?= $esc($daytest) ?>" aria-label="Тест дня <?= $index + 1 ?>">Тест</a>
                  
                </div>
                <details class="sa-outcome"><summary>Чему научитесь</summary><p><?= $esc($lesson['outcome']) ?></p></details>
              </div>
            </li>
<?php endforeach; ?>
          </ol>
          <section class="sa-course-finish" id="course-final"><div><p class="sa-section-label">После седьмого урока</p><h2>Итоговый экзамен</h2><p>Свободные ответы проверяет экзаменатор. Опытный кандидат может перейти к экзамену сразу.</p></div><a class="lw-secondary" href="academy.php?view=final">К экзамену <span aria-hidden="true">→</span></a><div class="sa-course-links"><a href="academy_rehearsal.php">Тренировка диалога без оценки</a><a href="academy_work.php">Памятка перед первой сменой</a></div><p class="sa-access-note">Учебный результат не открывает рабочий доступ автоматически.</p></section>
        </div></article>
        
      </main>
    </div>
  </div>
</div>
<?php echo $OUTPUT->footer();
