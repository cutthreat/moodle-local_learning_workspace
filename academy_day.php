<?php
/** Compact self-paced day route for lessons 2–7. Assessment is a separate persisted step. */
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/local/academy_page_shell.php');
require_once(__DIR__ . '/classes/local/lesson_catalog.php');
require_once(__DIR__ . '/classes/local/academy_day_content.php');
require_once(__DIR__ . '/classes/local/academy_branding.php');
require_once(__DIR__ . '/classes/local/academy_access.php');
require_once(__DIR__ . '/classes/local/academy_practice_draft.php');

use local_learning_workspace\local\lesson_catalog;
use local_learning_workspace\local\academy_day_content;
use local_learning_workspace\local\academy_access;
use local_learning_workspace\local\academy_practice_draft;

require_login();
$context = academy_access::require_viewer();

$lessonid = strtoupper(optional_param('lesson', 'L2', PARAM_ALPHANUMEXT));
if ($lessonid === 'L1') {
    redirect(new moodle_url('/local/learning_workspace/lesson_one.php'));
}
if (!preg_match('/^L[2-7]$/', $lessonid)) {
    throw new moodle_exception('invalidlesson', 'local_learning_workspace');
}
$step = optional_param('step', 1, PARAM_INT);
$step = max(1, min(4, $step));
$lesson = lesson_catalog::by_id($lessonid);
$content = \local_learning_workspace\local\academy_branding::copy(academy_day_content::day($lessonid));
$canwrite = academy_access::is_candidate();
$draft = $canwrite ? academy_practice_draft::read((int)$USER->id, $lessonid) :
    ['revision' => 0, 'main' => '', 'tool' => '', 'saved_at' => 0, 'hash' => ''];
$draftnotice = '';
$drafterror = '';
$draftrequestid = bin2hex(random_bytes(8));
$senddraftjson = static function(array $result, int $status = 200) use ($draftrequestid): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Academy-Request-ID: ' . $draftrequestid);
    $result['request_id'] = $draftrequestid;
    echo json_encode($result, JSON_THROW_ON_ERROR);
    exit;
};
// Read only the current learner's draft. Used to reconcile a lost save response
// before retrying; it never updates a revision or bypasses compare-and-swap.
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' &&
        optional_param('draftformat', '', PARAM_ALPHA) === 'json' && optional_param('draftstate', 0, PARAM_BOOL)) {
    academy_access::require_candidate();
    $field = required_param('draftfield', PARAM_ALPHA);
    if (!in_array($field, ['main', 'tool'], true) || ($field === 'tool' && empty($content['tool_practice']))) {
        $senddraftjson(['status' => 'not_saved', 'reason' => 'invalid'], 422);
    }
    $senddraftjson(['status' => 'state', 'text' => $draft[$field],
        'revision' => $draft['revision'], 'hash' => $draft['hash']]);
}
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $json = optional_param('draftformat', '', PARAM_ALPHA) === 'json';
    $field = optional_param('draftfield', '', PARAM_ALPHA);
    $text = optional_param('drafttext', '', PARAM_RAW);
    try {
        academy_access::require_candidate();
        require_sesskey();
        if (!hash_equals($draft['hash'], required_param('drafthash', PARAM_ALPHANUM))) {
            if ($json) { $senddraftjson(['status' => 'not_saved', 'reason' => 'content_changed'], 409); }
            throw new invalid_parameter_exception('Practice content changed');
        }
        $draft = academy_practice_draft::save((int)$USER->id, $lessonid, $field, $text,
            required_param('draftrevision', PARAM_INT), required_param('drafthash', PARAM_ALPHANUM));
        if ($json) {
            $senddraftjson(['status' => 'saved', 'revision' => $draft['revision']]);
        }
        redirect(new moodle_url('/local/learning_workspace/academy_day.php',
            ['lesson' => $lessonid, 'step' => $field === 'tool' ? 1 : 3, 'draftsaved' => 1]));
    } catch (Throwable $error) {
        error_log('Academy private draft save failed [' . $draftrequestid . '] ' . $lessonid .
            ':' . (in_array($field, ['main', 'tool'], true) ? $field : 'unknown') . ' ' . get_class($error) .
            (isset($error->errorcode) ? ':' . preg_replace('/[^a-zA-Z0-9_]/', '', $error->errorcode) : ''));
        if ($json) {
            $code = $error->errorcode ?? '';
            $reason = $code === 'concurrencyconflict' ? 'conflict' :
                (in_array($code, ['invalidsesskey', 'nopermissions', 'requireloginerror'], true) ? 'reauthenticate' :
                ($error instanceof invalid_parameter_exception ? 'invalid' : 'unavailable'));
            $senddraftjson(['status' => 'not_saved', 'reason' => $reason],
                ['conflict' => 409, 'reauthenticate' => 403, 'invalid' => 422, 'unavailable' => 503][$reason]);
        }
        $drafterror = 'Сохранение не подтверждено. Скопируйте свой текст перед перезагрузкой: возможно, черновик изменён в другой вкладке или вход завершился.';
        if (in_array($field, ['main', 'tool'], true)) { $draft[$field] = $text; }
    }
}
if (optional_param('draftsaved', 0, PARAM_BOOL)) { $draftnotice = 'Черновик сохранён в вашем аккаунте.'; }
$daynumber = (int)substr($lessonid, 1);
$nextlesson = $daynumber === 7
    ? new moodle_url('/local/learning_workspace/academy.php', ['view' => 'final'])
    : new moodle_url('/local/learning_workspace/academy_day.php', ['lesson' => 'L' . ($daynumber + 1)]);
$testurl = new moodle_url('/local/learning_workspace/academy.php', ['view' => 'day', 'lesson' => $lessonid]);
$stepurl = static function(int $number) use ($lessonid): string {
    return (new moodle_url('/local/learning_workspace/academy_day.php', ['lesson' => $lessonid, 'step' => $number]))->out(false);
};
$e = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/learning_workspace/academy_day.php', ['lesson' => $lessonid, 'step' => $step]));
$PAGE->set_pagelayout('embedded');
$PAGE->set_title($lesson['title'] . ' · Learning Workspace');
$PAGE->set_heading('Learning Workspace');
$PAGE->requires->css(new moodle_url('/local/learning_workspace/assets/academy-base.css', ['v' => '20261012-recovery1']));
$PAGE->requires->css(new moodle_url('/local/learning_workspace/assets/academy.css'));

$steps = ['Материал', 'Разбор примера', 'Свободная практика', 'Итог и тест'];
$headings = [
    1 => $lesson['title'],
    2 => 'Посмотрите, как это работает',
    3 => 'Теперь новая ситуация',
    4 => 'Проверьте понимание',
];
$example = (array)($content['worked_example'] ?? []);
$training = (array)($content['formative_case'] ?? []);
$policy = (string)($content['status'] ?? 'approved_structure');
$renderdraft = static function(string $field, string $task) use ($canwrite, $draft, $e, $lessonid): void {
    $id = 'lw-' . ($field === 'main' ? 'formative' : 'tool') . '-draft';
    $action = (new moodle_url('/local/learning_workspace/academy_day.php',
        ['lesson' => $lessonid, 'step' => $field === 'main' ? 3 : 1]))->out(false);
    ?>
    <form method="post" action="<?= $e($action) ?>" data-practice-form>
      <input type="hidden" name="sesskey" value="<?= $e(sesskey()) ?>">
      <input type="hidden" name="draftfield" value="<?= $e($field) ?>">
      <input type="hidden" name="draftrevision" value="<?= (int)$draft['revision'] ?>">
      <input type="hidden" name="drafthash" value="<?= $e($draft['hash']) ?>">
      <label for="<?= $e($id) ?>"><strong><?= $e($task) ?></strong></label>
      <textarea id="<?= $e($id) ?>" name="drafttext" rows="6" maxlength="2000" aria-describedby="<?= $e($id) ?>-note<?= $canwrite ? ' ' . $e($id) . '-status' : '' ?>" <?= !$canwrite ? 'disabled' : '' ?>><?= $e($draft[$field]) ?></textarea>
      <p id="<?= $e($id) ?>-note" class="lw-source-note"><?= $canwrite?'Личный черновик: не оценивается и не отправляется экзаменатору. Не вводите данные реальных клиентов.':'Режим просмотра: собственный ответ и сохранение доступны учащемуся.' ?></p>
      <?php if ($canwrite): ?><p id="<?= $e($id) ?>-status" class="lw-source-note" role="status" aria-live="polite" data-practice-status><?= $draft['saved_at'] ? 'Черновик восстановлен из вашего аккаунта.' : 'Изменения сохраняются автоматически.' ?></p><?php endif; ?>
      <button class="lw-secondary" type="submit" <?= !$canwrite ? 'disabled' : '' ?>>Сохранить черновик</button>
      <?php if ($canwrite): ?>
      <div class="lw-draft-recovery" data-practice-recovery hidden>
        <button class="lw-back" type="button" data-practice-copy>Скопировать мой ответ</button>
        <div data-practice-conflict hidden>
          <label for="<?= $e($id) ?>-remote">Версия, сохранённая в аккаунте</label>
          <textarea id="<?= $e($id) ?>-remote" rows="4" readonly data-practice-remote></textarea>
          <button class="lw-secondary" type="button" data-practice-use-remote>Загрузить эту версию в поле</button>
        </div>
        <div data-practice-backup hidden>
          <label for="<?= $e($id) ?>-backup">Ваш вариант до загрузки версии из аккаунта</label>
          <textarea id="<?= $e($id) ?>-backup" rows="4" readonly data-practice-local></textarea>
        </div>
      </div>
      <dialog class="lw-draft-dialog" data-practice-leave aria-labelledby="<?= $e($id) ?>-leave-title">
        <h2 id="<?= $e($id) ?>-leave-title">Ответ ещё не сохранён</h2>
        <p>Можно остаться и повторить сохранение. Если перейти сейчас, последние изменения будут потеряны.</p>
        <div class="lw-actions"><button class="lw-primary" type="button" data-practice-stay autofocus>Остаться</button><button class="lw-secondary" type="button" data-practice-copy>Скопировать ответ</button><button class="lw-back" type="button" data-practice-discard>Перейти без сохранения</button></div>
        <p role="status" aria-live="polite" data-practice-copy-status></p>
      </dialog>
      <?php endif; ?>
    </form>
    <?php
};

echo \local_learning_workspace\local\academy_page_shell::header($OUTPUT);
?>
<div class="lw-lesson lw-academy" lang="ru">
  <a class="lw-skip" href="#day-content">К содержанию урока</a>
  <?= \local_learning_workspace\local\academy_page_shell::topbar() ?>
  <div class="lw-workshop">
    <div class="lw-heading"><p class="lw-eyebrow">ДЕНЬ <?= sprintf('%02d', $daynumber) ?> · <?= $e(mb_strtoupper($lesson['title'])) ?></p><h1><?= $e($headings[$step]) ?></h1><p><?= $e($lesson['subtitle']) ?></p></div>
    <div class="lw-layout">
      <nav class="lw-sidebar" aria-label="Шаги учебного дня"><p class="lw-navcaption">ВАШ МАРШРУТ</p><ol class="lw-stepnav">
      <?php foreach ($steps as $index => $label): $number = $index + 1; $current = $number === $step; ?>
        <li class="<?= $current ? 'is-current' : '' ?>"><a href="<?= $e($stepurl($number)) ?>" <?= $current ? 'aria-current="step"' : '' ?>><b aria-hidden="true"><?= sprintf('%02d', $number) ?></b><span><?= $e($label) ?><small><?= ['Принцип и опоры','Разбор ответа','Свой черновик','Тест дня'][$index] ?></small></span></a></li>
      <?php endforeach; ?>
      </ol><div class="lw-navprogress"><span>Шаг <?= $step ?> из 4</span><div class="lw-progress" role="progressbar" aria-label="Положение в уроке" aria-valuemin="1" aria-valuemax="4" aria-valuenow="<?= $step ?>"><span style="width:<?= $step * 25 ?>%"></span></div></div></nav>
      <main id="day-content" class="lw-main" tabindex="-1">
        <?php if ($step===1): echo \local_learning_workspace\local\academy_day_resources::render($lessonid); if(class_exists(\local_learning_workspace\local\academy_blended::class)){echo \local_learning_workspace\local\academy_blended::lesson_links($lessonid);} endif; ?>
        <article class="lw-sheet"><div class="lw-workbenchbar"><span><?= $e($steps[$step - 1]) ?></span><span>День <?= $daynumber ?></span></div><div class="lw-sheetbody">
          <?php if ($drafterror): ?><p class="lw-note" role="alert"><?= $e($drafterror) ?></p><?php elseif ($draftnotice): ?><p class="lw-note" role="status"><?= $e($draftnotice) ?></p><?php endif; ?>
          <?php if ($policy !== 'approved_structure'): ?><div class="lw-note lw-content-hold"><strong>Учебная версия — примеры ещё проверяются</strong><p>До утверждения предметным экспертом используйте материал только для обучения, не для работы с клиентами.</p><details class="lw-details"><summary>Что ещё проверяется</summary><p><?= $e($content['verification_note'] ?? '') ?></p></details></div><?php endif; ?>
          <?php if ($step === 1): ?>
            <?php if ($lessonid === 'L7'): ?><p class="lw-note">В упражнениях связывайте тему карты с вопросом и позицией в раскладе. Не превращайте символическую интерпретацию в гарантию будущего.</p><?php endif; ?>
            <p class="lw-lead"><?= $e($content['outcome'] ?? $lesson['outcome']) ?></p>
            <section class="lw-principle"><h2>Главный принцип</h2><p><?= $e($content['principle'] ?? '') ?></p></section>
            <?php if (!empty($content['micro_lessons'])): ?><h2>Три опоры</h2><?php foreach ((array)$content['micro_lessons'] as $micro): ?><section class="lw-note"><h3><?= $e($micro['title'] ?? '') ?></h3><p><?= $e($micro['body'] ?? '') ?></p><p><strong><?= $e($micro['apply'] ?? '') ?></strong></p></section><?php endforeach; ?><?php endif; ?>
            <?php if (!empty($content['tool_practice'])): $tool = $content['tool_practice']; ?><details class="lw-details"><summary><?= $e($tool['title'] ?? '') ?></summary><p><?= $e($tool['body'] ?? '') ?></p><?php $renderdraft('tool', $tool['task'] ?? ''); ?><?php if (!empty($tool['checklist'])): ?><h3>Самопроверка</h3><ul class="lw-checklist"><?php foreach ($tool['checklist'] as $item): ?><li><?= $e($item) ?></li><?php endforeach; ?></ul><?php endif; ?><?php if (!empty($tool['feedback'])): ?><details class="lw-details"><summary>Сравнить с ориентиром</summary><p><?= $e($tool['feedback']) ?></p></details><?php endif; ?><div class="lw-source-links"><?php foreach ((array)($tool['links'] ?? []) as $link): ?><a href="<?= $e($link['url'] ?? '') ?>" target="_blank" rel="noopener noreferrer"><?= $e($link['label'] ?? '') ?></a><?php endforeach; ?></div></details><?php endif; ?>
            <?php if (!empty($content['work_rules'])): ?><h2>Подготовка и рабочие правила</h2><p><a href="academy_work.php">Краткая памятка перед сменой →</a></p><?php foreach ((array)$content['work_rules'] as $rule): ?><details class="lw-details"><summary><?= $e($rule['title'] ?? '') ?></summary><p><?= $e($rule['body'] ?? '') ?></p></details><?php endforeach; ?><?php endif; ?>
            
            <div class="lw-actions"><a class="lw-primary" href="<?= $e($stepurl(2)) ?>">Посмотреть пример →</a></div>
          <?php elseif ($step === 2): ?>
            <?php if (!empty($content['knowledge_check'])): $knowledge = $content['knowledge_check']; ?>
            <section class="lw-note" data-knowledge-check="<?= $e($lessonid) ?>"><h2><?= $e($knowledge['question']) ?></h2><ol><?php foreach ($knowledge['options'] as $option): ?><li><?= $e($option) ?></li><?php endforeach; ?></ol><details class="lw-details"><summary>Проверить себя</summary><p><?= $e($knowledge['explanation']) ?></p></details></section>
            <h2>Как применить в разговоре</h2>
            <?php endif; ?>
            
            <div class="lw-message lw-client"><span>КЛИЕНТ ПИШЕТ</span><p><?= $e($example['client'] ?? '') ?></p></div>
            <?php if (!empty($example['setup'])): ?><p class="lw-source-note"><?= $e($example['setup']) ?></p><?php endif; ?>
            <div class="lw-message"><span>НЕ ПОДХОДИТ</span><p><?= $e($example['bad'] ?? '') ?></p></div>
            <?php if (!empty($example['incomplete'])): ?><div class="lw-message"><span>ТОЖЕ НЕ ПОДХОДИТ</span><p><?= $e($example['incomplete']) ?></p></div><?php endif; ?>
            <div class="lw-message lw-expert"><span>ПОДХОДЯЩИЙ ОРИЕНТИР</span><p><?= $e($example['good'] ?? '') ?></p></div>
            <div class="lw-note"><strong>Почему так</strong><p><?= $e($example['why'] ?? '') ?></p></div>
            <div class="lw-actions"><a class="lw-back" href="<?= $e($stepurl(3)) ?>">Потренироваться без оценки</a><a class="lw-primary" href="<?= $e($stepurl(4)) ?>">К итогу и контрольному тесту →</a></div>
          <?php elseif ($step === 3): ?>
            <p class="lw-lead">Сначала потренируйтесь без оценки. В тесте будет другая ситуация.</p>
            <div class="lw-message lw-client"><span>ТРЕНИРОВОЧНАЯ СИТУАЦИЯ</span><p><?= $e($training['client'] ?? '') ?></p></div>
            <?php $renderdraft('main', $training['task'] ?? ''); ?>
            <details class="lw-details"><summary>Сверить ответ и исправить черновик</summary><p><?= $e($training['feedback'] ?? '') ?></p></details>
            <h2>Проверьте ответ на эту ситуацию</h2><ul class="lw-checklist" data-formative-checklist="<?= $e($lessonid) ?>"><?php foreach ((array)($training['checklist'] ?? []) as $criterion): ?><li><?= $e($criterion) ?></li><?php endforeach; ?></ul>
            <div class="lw-actions"><a class="lw-back" href="<?= $e($stepurl(2)) ?>">← К примеру</a><a class="lw-primary" href="<?= $e($stepurl(4)) ?>">К итогу урока →</a></div>
          <?php else: ?>
            <p class="lw-lead">Пройдите короткий тест и напишите ответ на новую ситуацию. После отправки ответы сохранятся в «Моих результатах». <?= $e(\local_learning_workspace\local\academy_attempt_policy::retry_message((int)$USER->id,$lessonid)) ?></p>
            <section class="lw-principle"><h2>Главное из урока</h2><p><?= $e($content['takeaway'] ?? '') ?></p></section>
            <div class="lw-actions"><a class="lw-back" href="<?= $e($stepurl(3)) ?>">← К ситуации</a><a class="lw-primary" href="<?= $e($testurl->out(false)) ?>">Пройти тест дня <?= $daynumber ?> →</a></div>
          <?php endif; ?>
        </div></article>
        
      <?php if ($step === 4): ?><p class="lw-source-links"><a href="<?= $e($nextlesson->out(false)) ?>"><?= $daynumber === 7 ? 'К итоговому экзамену →' : 'Следующий день →' ?></a></p><?php endif; ?>
      </main>
    </div>
  </div>
</div>
<?php echo $OUTPUT->footer();
