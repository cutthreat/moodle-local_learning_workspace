<?php
/** Examiner-only evidence card: one candidate, seven day histories and final attempts. */
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/local/academy_result_view.php');
require_once(__DIR__ . '/classes/local/academy_page_shell.php');
require_once(__DIR__ . '/classes/local/academy_access_denied_view.php');
require_once(__DIR__ . '/classes/local/lesson_catalog.php');
require_once(__DIR__ . '/classes/local/academy_day_content.php');
require_once(__DIR__ . '/classes/local/academy_assessment_repository.php');
require_once(__DIR__ . '/classes/local/academy_candidate_evidence.php');
require_once(__DIR__ . '/classes/local/academy_review_context.php');

use local_learning_workspace\local\lesson_catalog;
use local_learning_workspace\local\academy_day_content;
use local_learning_workspace\local\academy_assessment_repository as assessments;
use local_learning_workspace\local\academy_candidate_evidence;
use local_learning_workspace\local\academy_review_context;
use local_learning_workspace\local\academy_access;

require_login();
$context = context_system::instance();
if (!has_capability('local/learning_workspace:reviewassessment', $context)) {
    \local_learning_workspace\local\academy_access_denied_view::page(academy_access::is_candidate());
    return;
}
require_capability('local/learning_workspace:reviewassessment', $context);
$queuecontext = academy_review_context::normalize([
    'status' => optional_param('status', 'all', PARAM_ALPHAEXT),
    'q' => optional_param('q', '', PARAM_TEXT),
    'page' => optional_param('page', 1, PARAM_INT),
]);
$userid = required_param('userid', PARAM_INT);
if ($userid <= 0) {
    throw new moodle_exception('invalidparameter');
}
$hasattempt = $DB->record_exists('local_lworkspace_acattempt', ['userid' => $userid]);
if (!$hasattempt && !academy_access::is_candidate($userid)) {
    throw new moodle_exception('nopermissions', 'error');
}
$candidate = $DB->get_record('user', ['id' => $userid, 'deleted' => 0],
    'id,firstname,lastname,suspended', MUST_EXIST);
if (!$hasattempt && (int)$candidate->suspended === 1) {
    throw new moodle_exception('nopermissions', 'error');
}
$days = assessments::day_summary($userid);
$final = assessments::final_summary($userid);
$e = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$status = static function(string $code): string {
    return [
        'passed' => 'Закрытая часть пройдена',
        'failed' => 'Закрытая часть не пройдена',
        'draft_unverified' => 'Материал проверяется · готовность не подтверждена',
        'awaiting_review' => 'Ожидает проверки',
        'ready' => 'Готов по экзамену',
        'rework' => 'Нужна доработка',
        'not_ready' => 'Пока не готов',
        'practice_accepted' => 'Свободный ответ принят',
        'practice_rework' => 'Доработать свободный ответ',
        'practice_feedback' => 'Комментарий без зачёта',
    ][$code] ?? 'Не начат';
};
$attempturl = static function(int $id) use ($queuecontext): string {
    return (new moodle_url('/local/learning_workspace/academy_review.php', academy_review_context::params($queuecontext, $id)))->out(false);
};
$queueurl = (new moodle_url('/local/learning_workspace/academy_review.php', academy_review_context::params($queuecontext)))->out(false);
$attemptsword = static function(int $count): string {
    $lasttwo = $count % 100;
    $last = $count % 10;
    return $lasttwo >= 11 && $lasttwo <= 14 ? 'попыток' : ($last === 1 ? 'попытка' : ($last >= 2 && $last <= 4 ? 'попытки' : 'попыток'));
};
$sectionword = static function(array $section): string {
    if (($section['status'] ?? '') === 'incomplete_bank') {
        return 'не сопоставимо';
    }
    $score = (int)($section['correct'] ?? 0) . '/' . (int)($section['total'] ?? 0);
    return ($section['status'] ?? '') === 'unverified' ? $score . ' (черновик)' : $score;
};

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/learning_workspace/academy_candidate.php', array_merge(academy_review_context::params($queuecontext), ['userid' => $userid])));
$PAGE->set_pagelayout('embedded');
$PAGE->set_title('Ответы кандидата · Learning Workspace');
$PAGE->set_heading('Learning Workspace');
$PAGE->requires->css(new moodle_url('/local/learning_workspace/assets/academy-base.css', ['v' => '20261007-header1']));
$PAGE->requires->css(new moodle_url('/local/learning_workspace/assets/academy.css'));

echo \local_learning_workspace\local\academy_page_shell::header($OUTPUT);
?>
<div class="lw-lesson lw-academy" lang="ru">
  <a class="lw-skip" href="#candidate-main">К ответам кандидата</a>
  <?= \local_learning_workspace\local\academy_page_shell::topbar($queueurl, '← К кандидатам') ?>
  <div class="lw-workshop">
    <div class="lw-heading"><p class="lw-eyebrow">КАБИНЕТ ПРОВЕРКИ · ИСТОРИЯ</p><h1><?= $e(trim($candidate->firstname . ' ' . $candidate->lastname)) ?></h1><p>все дневные и итоговые попытки сохранены отдельно</p></div>
    <div class="lw-layout">
      <nav class="lw-sidebar" aria-label="Разделы карточки"><p class="lw-navcaption">ОТВЕТЫ</p><ol class="lw-stepnav"><li class="is-current"><a href="#day-evidence" aria-current="page"><b aria-hidden="true">07</b><span>Семь дней<small>Практика и тесты</small></span></a></li><li><a href="#final-evidence"><b aria-hidden="true">★</b><span>Итог<small>Экзамен и решения</small></span></a></li></ol></nav>
      <main id="candidate-main" class="lw-main" tabindex="-1">
        <article class="lw-sheet"><div class="lw-workbenchbar"><span>Доказательства обучения</span><span>Для проверяющего</span></div><div class="lw-sheetbody">
          <div class="ac-warning"><strong>Учебный результат.</strong> Эти ответы не являются автоматическим разрешением работать с клиентами. Черновые задания не подтверждают готовность.</div>
          <h2 id="day-evidence">Ответы по семи дням</h2>
          <ol class="ac-day-list">
            <?php foreach (lesson_catalog::all() as $lesson): $id = $lesson['id']; $one = $days[$id] ?? ['history' => [], 'latest' => null, 'attempts' => 0]; $latest = $one['latest'] ?? null; $dayreview = $one['review'] ?? null; $provisional = $id !== 'L1' && academy_day_content::day($id)['status'] !== 'approved_structure'; ?>
            <li><b aria-hidden="true"><?= $e($id) ?></b><span><strong><?= $e($lesson['title']) ?></strong><small><?= $e(\local_learning_workspace\local\academy_result_view::state($latest,$dayreview ?: null)) ?> · <?= (int)$one['attempts'] ?> <?= $attemptsword((int)$one['attempts']) ?><?= $provisional ? ' · материал проверяется' : '' ?></small>
              <?php if ($latest): ?><?= \local_learning_workspace\local\academy_result_view::timeline($latest,$dayreview ?: null) ?><?php if ($dayreview): ?><small class="ac-feedback-excerpt"><?= $e(\core_text::substr($dayreview->feedback,0,180)) ?></small><?php endif; ?></span><a href="<?= $e($attempturl((int)$latest->id)) ?>"><?= $dayreview?'Открыть решение →':'Проверить дневную практику →' ?></a><?php else: ?></span><span>Ответов пока нет</span><?php endif; ?></li>
            <?php endforeach; ?>
          </ol>
          <?php foreach (lesson_catalog::all() as $lesson): $history = $days[$lesson['id']]['history'] ?? []; if (!$history) { continue; } ?>
            <details class="lw-details" id="day-<?= $e($lesson['id']) ?>"><summary>История · день <?= (int)substr($lesson['id'],1) ?> · <?= count($history) ?> <?= $attemptsword(count($history)) ?></summary>
              <h3>День <?= (int)substr($lesson['id'],1) ?> · <?= $e($lesson['title']) ?> · <?= count($history) ?> <?= $attemptsword(count($history)) ?></h3>
              <?php foreach ($history as $attempt): $questionrows = academy_candidate_evidence::questions($attempt); $dayreview = $days[$lesson['id']]['reviews_by_attempt'][(int)$attempt->id] ?? null; ?>
                <details class="lw-details"><summary>Попытка <?= (int)$attempt->attemptno ?> · <?= $e($status((string)$attempt->status)) ?> · <?= (int)$attempt->score ?>%<?= (int)$attempt->hasdraft ? ' · есть черновые вопросы' : '' ?> · <?= $dayreview ? $e($status((string)$dayreview->decision)) : 'свободный ответ ждёт проверки' ?></summary>
                  <?= \local_learning_workspace\local\academy_result_view::timeline($attempt,$dayreview ?: null) ?>
                  <div class="ac-answers">
                    <?php foreach ($questionrows as $index => $question): ?>
                      <section class="ac-answer"><h4>Вопрос <?= $index + 1 ?><?= $question['draft'] ? ' · ЧЕРНОВИК' : '' ?></h4><p><?= $e(\local_learning_workspace\local\academy_branding::text($question['prompt'])) ?></p><p class="ac-muted">Сохранённый ответ<?= $question['type'] === 'text' ? ' своими словами' : '' ?>:</p><p><?= nl2br($e($question['answer'])) ?></p>
                        <?php if ($question['type'] === 'choice'): ?><p class="ac-pill <?= $question['result'] === true ? 'is-success' : 'is-warn' ?>"><?= $question['result'] === true ? 'Верно' : ($question['result'] === false ? 'Ошибка' : 'Без автоматической оценки') ?></p><?php endif; ?>
                      </section>
                    <?php endforeach; ?>
                    <?php if (!$questionrows): ?><p>Снимок вопросов этой попытки недоступен. Откройте исходную запись для проверки.</p><?php endif; ?>
                  </div>
                  <?php if ($dayreview): ?><div class="ac-result <?= (string)$dayreview->decision==='practice_accepted'?'':'is-warn' ?>"><h4><?= $e($status((string)$dayreview->decision)) ?></h4><p><?= nl2br($e($dayreview->feedback)) ?></p></div><?php endif; ?>
                  <p><a href="<?= $e($attempturl((int)$attempt->id)) ?>">Открыть исходную попытку →</a></p>
                </details>
              <?php endforeach; ?>
            </details>
          <?php endforeach; ?>
          <h2 id="final-evidence">Итоговый экзамен</h2>
<?php if (!empty($final['latest'])): $currentfinal=$final['latest']; $currentreview=$final['review']??null; ?><div class="ac-result <?= ($currentreview->decision??'')==='ready' && !(int)$currentfinal->hasdraft?'':'is-warn' ?>"><h3><?= $e(\local_learning_workspace\local\academy_result_view::state($currentfinal,$currentreview)) ?></h3><?= \local_learning_workspace\local\academy_result_view::timeline($currentfinal,$currentreview) ?><?php if ($currentreview): ?><p><?= nl2br($e($currentreview->feedback)) ?></p><?php endif; ?><a href="<?= $e($attempturl((int)$currentfinal->id)) ?>"><?= $currentreview?'Открыть полное решение →':'Проверить итоговый экзамен →' ?></a></div><?php endif; ?>
          <?php if (empty($final['history'])): ?><p>Итоговый экзамен ещё не отправлен. Дневные ответы остаются доступными выше.</p><?php else: ?><details class="lw-details"><summary>История итоговых попыток</summary><ol class="ac-day-list"><?php foreach ($final['history'] as $attempt): $review = $final['reviews_by_attempt'][(int)$attempt->id] ?? null; $result = $review ? (string)$review->decision : (string)$attempt->status; $questions = json_decode((string)$attempt->questionsjson, true) ?: []; $feedback = json_decode((string)$attempt->feedbackjson, true) ?: []; $sections = assessments::objective_sections($questions, $feedback); $savedrubric = $review ? json_decode((string)$review->rubricjson, true) : []; $parts = is_array($savedrubric) && ($savedrubric['schema'] ?? '') === 'five_part_v1' ? ($savedrubric['parts'] ?? []) : []; ?><li><b aria-hidden="true">★</b><span><strong>Попытка <?= (int)$attempt->attemptno ?></strong><?= \local_learning_workspace\local\academy_result_view::timeline($attempt,$review ?: null) ?><small><?= $e($status($result)) ?> · А1: <?= $e($sectionword($sections['foundation'])) ?> · А2: <?= $e($sectionword($sections['process'])) ?></small><?php if ($parts): ?><small>Б · Диалог: <?= (int)($parts['dialogue']['score'] ?? 0) ?>/10 · В · Ситуации: <?= (int)($parts['complex']['score'] ?? 0) ?>/10 · Г · Продвижение: <?= (int)($parts['promotion']['score'] ?? 0) ?>/10</small><?php elseif (strpos((string)$attempt->bankversion, '-final.10-') !== false): ?><small>Б/В/Г: ожидают проверки</small><?php endif; ?></span><a href="<?= $e($attempturl((int)$attempt->id)) ?>">Ответы и решение →</a></li><?php endforeach; ?></ol></details><?php endif; ?>
        </div></article>

      </main>
    </div>
  </div>
</div>
<?php echo $OUTPUT->footer();
