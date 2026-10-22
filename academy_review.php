<?php
/** Examiner pilot dashboard for immutable academy attempts. No work-access mutation. */
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/local/academy_page_shell.php');
require_once(__DIR__ . '/classes/local/academy_access.php');
require_once(__DIR__ . '/classes/local/academy_access_denied_view.php');
require_once(__DIR__ . '/classes/local/academy_assessment_repository.php');
require_once(__DIR__ . '/classes/local/academy_review_queue.php');
require_once(__DIR__ . '/classes/local/academy_review_context.php');
require_once(__DIR__ . '/classes/local/academy_dialogue_snapshot_view.php');
require_once(__DIR__ . '/classes/local/academy_question_bank.php');
require_once(__DIR__ . '/classes/local/lesson_catalog.php');
require_once(__DIR__ . '/classes/local/academy_review_form.php');
require_once(__DIR__ . '/classes/local/academy_result_view.php');
require_once(__DIR__ . '/classes/local/academy_staff_workflow.php');
use local_learning_workspace\local\academy_staff_workflow as ux;

use local_learning_workspace\local\academy_assessment_repository as assessments;
use local_learning_workspace\local\academy_review_queue;
use local_learning_workspace\local\academy_review_context;
use local_learning_workspace\local\academy_dialogue_snapshot_view;
use local_learning_workspace\local\academy_question_bank as bank;
use local_learning_workspace\local\lesson_catalog;
use local_learning_workspace\local\academy_review_form;
use local_learning_workspace\local\academy_result_view;

require_login();
$context = context_system::instance();
if (!has_capability('local/learning_workspace:reviewassessment', $context)) {
    // Deny before reading an attempt, building the queue, or processing any POST.
    $PAGE->set_context($context);
    $PAGE->set_url(new moodle_url('/local/learning_workspace/academy_review.php'));
    $PAGE->set_pagelayout('embedded');
    $PAGE->set_title('Доступ к разделу · Learning Workspace');
    $PAGE->set_heading('Learning Workspace');
    $PAGE->requires->css(new moodle_url('/local/learning_workspace/assets/academy-base.css', ['v' => '20261007-header1']));
    $PAGE->requires->css(new moodle_url('/local/learning_workspace/assets/academy.css'));
    http_response_code(403);
    echo \local_learning_workspace\local\academy_page_shell::header($OUTPUT);
    echo \local_learning_workspace\local\academy_access_denied_view::review(
        \local_learning_workspace\local\academy_access::is_candidate());
    echo $OUTPUT->footer();
    return;
}
require_capability('local/learning_workspace:reviewassessment', $context);
$attemptid = optional_param('attempt', 0, PARAM_INT);
$queuecontext = academy_review_context::normalize([
    'status' => optional_param('status', 'pending', PARAM_ALPHAEXT),
    'q' => optional_param('q', '', PARAM_TEXT),
    'page' => optional_param('page', 1, PARAM_INT),
]);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/learning_workspace/academy_review.php', academy_review_context::params($queuecontext, $attemptid)));
$PAGE->set_pagelayout('embedded');
$PAGE->set_title('Проверка работ · Learning Workspace');
$PAGE->set_heading('Learning Workspace');
$PAGE->requires->css(new moodle_url('/local/learning_workspace/assets/academy-base.css', ['v' => '20261007-header1']));
$PAGE->requires->css(new moodle_url('/local/learning_workspace/assets/academy.css'));

function acr_e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function acr_value($record, string $key, $fallback = null) {
    return is_object($record) ? ($record->{$key} ?? $fallback) : (is_array($record) ? ($record[$key] ?? $fallback) : $fallback);
}
function acr_return(moodle_url $url): void {
    if (($_SERVER['HTTP_X_ACADEMY_REVIEW'] ?? '') === '1') {
        // Match Moodle redirect(): explicitly persist recovery before the next GET.
        \core\session\manager::write_close();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['redirect'=>$url->out(false)],JSON_UNESCAPED_UNICODE); exit;
    }
    redirect($url);
}
function acr_criterion(string $id,string $label,bool $five): string {
    global $postedrubric,$postedquotes,$postednextsteps;
    ob_start(); ?>
    <div class="ac-criterion" data-criterion="<?= acr_e($id) ?>"><label for="rubric-<?= acr_e($id) ?>"><?= acr_e($label) ?></label>
    <select id="rubric-<?= acr_e($id) ?>" name="rubric[<?= acr_e($id) ?>]" required><option value="">Выберите оценку</option>
    <?php foreach ($five ? ['0'=>'0 · не выполнено','1'=>'1 · частично','2'=>'2 · выполнено'] : ['pass'=>'Соответствует','fail'=>'Не соответствует'] as $value=>$copy): ?>
    <option value="<?= acr_e($value) ?>" <?= (string)($postedrubric[$id]??'')===(string)$value?'selected':'' ?>><?= acr_e($copy) ?></option><?php endforeach; ?></select>
    <div class="ac-criterion-evidence"><label for="quote-<?= acr_e($id) ?>">Точная цитата из ответа</label>
    <button type="button" class="ac-quote-copy" data-ac-copy-quote>Вставить выделенный фрагмент ответа</button>
    <textarea id="quote-<?= acr_e($id) ?>" name="rubricquote[<?= acr_e($id) ?>]" rows="3" minlength="8" maxlength="300" data-ac-quote aria-describedby="quote-<?= acr_e($id) ?>-hint"><?= acr_e($postedquotes[$id]??'') ?></textarea>
    <small id="quote-<?= acr_e($id) ?>-hint" data-ac-count>8–300 символов</small>
    <label for="next-<?= acr_e($id) ?>">Что конкретно исправить</label>
    <textarea id="next-<?= acr_e($id) ?>" name="rubricnext[<?= acr_e($id) ?>]" minlength="15" maxlength="1000" data-ac-next aria-describedby="next-<?= acr_e($id) ?>-hint"><?= acr_e($postednextsteps[$id]??'') ?></textarea>
    <small id="next-<?= acr_e($id) ?>-hint" data-ac-count>15–1000 символов</small></div></div>
    <?php return ob_get_clean();
}
function acr_status(string $status): array {
    $labels = [
        'passed' => ['Закрытая часть пройдена', 'is-success'],
        'failed' => ['Закрытая часть требует разбора', 'is-warn'],
        'draft_unverified' => ['Материал проверяется · готовность не подтверждена', 'is-warn'],
        'not_started' => ['Итог ещё не сдан', 'is-muted'],
        'awaiting_review' => ['Ожидает проверки', 'is-warn'],
        'ready' => ['Готов по экзамену', 'is-success'],
        'rework' => ['Нужна доработка', 'is-warn'],
        'not_ready' => ['Пока не готов', 'is-warn'],
        'practice_accepted' => ['Свободный ответ принят', 'is-success'],
        'practice_rework' => ['Доработать свободный ответ', 'is-warn'],
        'practice_feedback' => ['Комментарий без зачёта', 'is-muted'],
    ];
    return $labels[$status] ?? ['Не начат', 'is-muted'];
}
function acr_url(int $attemptid = 0): string {
    global $queuecontext;
    return (new moodle_url('/local/learning_workspace/academy_review.php', academy_review_context::params($queuecontext, $attemptid)))->out(false);
}
function acr_filter_url(string $status, string $search): string {
    $params = academy_review_context::params(['status' => $status, 'q' => $search, 'page' => 1]);
    return (new moodle_url('/local/learning_workspace/academy_review.php', $params))->out(false);
}
function acr_page_url(int $page, string $status, string $search): string {
    $params = academy_review_context::params(['status' => $status, 'q' => $search, 'page' => $page]);
    return (new moodle_url('/local/learning_workspace/academy_review.php', $params))->out(false);
}
function acr_draft_questions(array $questions): array {
    $drafts = [];
    foreach ($questions as $question) {
        if (acr_value($question, 'status', '') !== 'approved_structure') {
            $drafts[] = $question;
        }
    }
    return $drafts;
}
function acr_answer_display($question, $answer): string {
    if ((string)acr_value($question, 'type', '') === 'choice') {
        foreach ((array)acr_value($question, 'options', []) as $option) {
            if ((string)acr_value($option, 'id', '') === (string)$answer) {
                return (string)acr_value($option, 'text', $answer);
            }
        }
    }
    return is_scalar($answer) ? (string)$answer : '';
}
function acr_mark_result($mark): string {
    return is_array($mark) ? (string)($mark['result'] ?? '') : (string)$mark;
}
function acr_criterion_copy(string $copy): string {
    $copy = \local_learning_workspace\local\academy_branding::text($copy);
    return str_replace('Предметная ценность обязательна на 2.',
        'Для зачёта критерия «Предметная ценность» нужны 2 балла.', $copy);
}
function acr_is_five_part(string $version): bool {
    return preg_match('/-final\.(\d+)-v/', $version, $match) === 1 && (int)$match[1] >= 10;
}
function acr_section_label($section): string {
    if (!is_array($section) || ($section['status'] ?? '') === 'incomplete_bank') {
        return 'нет сопоставимой разбивки';
    }
    $result = (int)($section['correct'] ?? 0) . '/' . (int)($section['total'] ?? 0);
    return ($section['status'] ?? '') === 'unverified' ? $result . ' · есть черновики' : $result;
}
function acr_error_message(Throwable $exception): string {
    $code = $exception instanceof moodle_exception ? $exception->errorcode : '';
    $messages = [
        'assessmentrubricincomplete' => 'Оцените каждый свободный ответ и выберите согласованное с оценкой решение. В итоговом экзамене оцените все критерии от 0 до 2 баллов.',
        'assessmentrubricevidence' => 'Если ответ не соответствует критерию или итоговый критерий оценён ниже 2, скопируйте точную цитату из ответа кандидата (8–300 символов) и укажите, что исправить (15–1000 символов).',
        'assessmentreadinessblocked' => 'Решение «готов» невозможно: есть черновой вопрос, критическая ошибка или недостающий балл по одной из частей А1, А2, Б, В, Г.',
        'assessmentalreadyreviewed' => 'Эта попытка уже проверена. Сохранённое решение показано ниже.',
        'assessmentidempotencyconflict' => 'Эта отправка уже использована для другого решения. Обновите карточку и отправьте новый запрос.',
        'assessmentreviewforbidden' => 'Эта попытка недоступна вашей роли или принадлежит вам.',
    ];
    return $messages[$code] ?? 'Решение не сохранено. Проверьте все критерии и комментарий, затем повторите отправку.';
}

$error = '';
$postedrubric = [];
$postedquotes = [];
$postednextsteps = [];
$posteddecision = '';
$postedfeedback = '';
$fielderrors = [];
$formrevision = 0;
$formidemkey = bin2hex(random_bytes(16));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    if (optional_param('action','review',PARAM_ALPHA)==='permit') {
        try {
            \local_learning_workspace\local\academy_workspace::consume_form('permit');
            \local_learning_workspace\local\academy_attempt_policy::permit(required_param('attemptid',PARAM_INT), required_param('reason',PARAM_TEXT));
            redirect(new moodle_url('/local/learning_workspace/academy_review.php', academy_review_context::params($queuecontext, required_param('attemptid',PARAM_INT), true)));
        } catch (DomainException $exception) { $error=$exception->getMessage(); }
    } else {
    $attemptid = required_param('attemptid', PARAM_INT);
    $decision = optional_param('decision', '', PARAM_ALPHANUMEXT);
    $feedback = optional_param('feedback', '', PARAM_TEXT);
    $rubric = optional_param_array('rubric', [], PARAM_ALPHANUM);
    // Evidence must remain exact candidate text, including literal markup. Repository
    // checks length/presence; every form and saved readback escapes it on output.
    $postedquotes = optional_param_array('rubricquote', [], PARAM_RAW);
    $postednextsteps = optional_param_array('rubricnext', [], PARAM_TEXT);
    $postedrubric = $rubric;
    $posteddecision = $decision;
    $postedfeedback = $feedback;
    $idemkey = required_param('idemkey', PARAM_ALPHANUMEXT);
    $formrevision = optional_param('draftrevision', 0, PARAM_INT);
    $payload = ['rubric'=>$rubric,'quotes'=>$postedquotes,'nextsteps'=>$postednextsteps,
        'decision'=>$decision,'feedback'=>$feedback,'idemkey'=>$idemkey];
    try {
        $submitteddetail = assessments::attempt_details($attemptid);
        if (optional_param('action','review',PARAM_ALPHAEXT) === 'save_draft') {
            academy_review_form::save_draft($attemptid,$payload,$formrevision);
            unset($SESSION->academy_review_recovery[$attemptid]);
            acr_return(new moodle_url('/local/learning_workspace/academy_review.php',
                array_merge(academy_review_context::params($queuecontext,$attemptid),['draftsaved'=>1])));
        }
        $fielderrors = academy_review_form::errors($submitteddetail,$payload);
        if ($fielderrors) { throw new DomainException('Исправьте отмеченные поля.'); }
        $detailedrubric = [];
        foreach ($rubric as $id => $rating) {
            $detailedrubric[$id] = ['result' => $rating, 'score' => $rating, 'quote' => $postedquotes[$id] ?? '',
                'nextstep' => $postednextsteps[$id] ?? ''];
        }
        $submittedattempt = $submitteddetail['attempt'];
        if ($submittedattempt->kind === 'day') {
            assessments::review_day($attemptid, (int)$USER->id, $detailedrubric, $decision, $feedback, $idemkey);
        } else {
            assessments::review_final($attemptid, (int)$USER->id, $detailedrubric, $decision, $feedback, $idemkey);
        }
        unset($SESSION->academy_review_recovery[$attemptid]);
        if(optional_param('action','review',PARAM_ALPHAEXT)==='review_next'){
            try{$next=ux::next_work($queuecontext,$attemptid);}catch(Throwable $nextfailure){
                // The immutable decision already exists. Never report it as an unsaved POST.
                acr_return(new moodle_url('/local/learning_workspace/academy_review.php',academy_review_context::params($queuecontext,$attemptid,true)));
            }
            $nextparams=academy_review_context::params($queuecontext,$next?(int)$next->id:0);
            $nextparams['continued']=$next?1:2;
            acr_return(new moodle_url('/local/learning_workspace/academy_review.php',$nextparams));
        }
        acr_return(new moodle_url('/local/learning_workspace/academy_review.php', academy_review_context::params($queuecontext, $attemptid, true)));
    } catch (Throwable $exception) {
        if (!$fielderrors && $exception instanceof moodle_exception &&
                in_array($exception->errorcode,['assessmentrubricincomplete','assessmentreadinessblocked'],true)) {
            $fielderrors['review-decision']='Итоговое решение не соответствует оценкам или ограничениям работы. Выберите доработку / обратную связь без зачёта.';
        }
        $error = $fielderrors ? 'Решение не сохранено. Исправьте поля: '.count($fielderrors).'.' :
            ($exception instanceof DomainException ? $exception->getMessage() : acr_error_message($exception));
        // POST/Redirect/GET also on errors: refresh never replays the review POST.
        // Recovery is private Moodle session state, not browser storage or a committed grade.
        $SESSION->academy_review_recovery[$attemptid] = ['payload'=>$payload,'errors'=>$fielderrors,
            'message'=>$error,'revision'=>$formrevision,'time'=>time()];
        acr_return(new moodle_url('/local/learning_workspace/academy_review.php',
            academy_review_context::params($queuecontext,$attemptid)));
    }
}

    }
$detail = null;
$comparison = null;
if ($attemptid > 0) {
    try {
        $detail = assessments::attempt_details($attemptid);
        // Both day practice and final attempts have a separate immutable reviewer decision.
        if ($detail['attempt']->kind === 'final' && (int)$detail['attempt']->attemptno > 1) {
            $comparison = assessments::final_comparison($attemptid);
        }
    } catch (Throwable $exception) {
        $error = 'Попытка недоступна или больше не существует.';
        $attemptid = 0;
    }
}
$reviewdraft = null;
if ($detail && !$detail['review'] && (int)$detail['attempt']->userid !== (int)$USER->id) {
    $reviewdraft = academy_review_form::draft($attemptid);
    if (optional_param('load_draft',0,PARAM_INT) === 1) { unset($SESSION->academy_review_recovery[$attemptid]); }
    $recovery = $SESSION->academy_review_recovery[$attemptid] ?? null;
    if ($recovery && (int)$recovery['time'] < time()-86400) {
        unset($SESSION->academy_review_recovery[$attemptid]); $recovery=null;
    }
    $restored = $recovery['payload'] ?? ($reviewdraft ? json_decode($reviewdraft->payloadjson,true) : []);
    $postedrubric = $restored['rubric'] ?? [];
    $postedquotes = $restored['quotes'] ?? [];
    $postednextsteps = $restored['nextsteps'] ?? [];
    $posteddecision = $restored['decision'] ?? '';
    $postedfeedback = $restored['feedback'] ?? '';
    $formidemkey = $restored['idemkey'] ?? $formidemkey;
    $formrevision = $recovery['revision'] ?? (int)($reviewdraft->revision ?? 0);
    $fielderrors = $recovery['errors'] ?? [];
    $error = $recovery['message'] ?? $error;
}
$rows = assessments::reviewer_rows();
// Add login only to already-authorized candidate rows; never widen the search scope.
$identities = $rows ? $DB->get_records_list('user','id',array_map(static fn($r)=>(int)$r->userid,$rows),'','id,username') : [];
foreach ($rows as $row) { $row->username=(string)($identities[(int)$row->userid]->username ?? ''); }
$detailcandidate = null;
if ($detail) {
    foreach ($rows as $row) {
        if ((int)acr_value($row, 'userid', 0) === (int)$detail['attempt']->userid) {
            $detailcandidate = $row;
            break;
        }
    }
}
$filter = $queuecontext['status'];
$search = $queuecontext['q'];
$visible = array_values(array_filter($rows, static function($row) use ($filter, $search): bool {
    if ($filter === 'pending' && (int)acr_value($row,'pendingdayreviews',0)<1 &&
            acr_value($row,'finalstatus','')!=='awaiting_review') { return false; }
    if ($filter === 'day_pending' && (int)acr_value($row, 'pendingdayreviews', 0) < 1) {
        return false;
    }
    if ($filter === 'historical' && !acr_value($row, 'historicalbank', false)) {
        return false;
    }
    if (!in_array($filter, ['all', 'pending', 'day_pending', 'historical'], true) &&
            (string)acr_value($row, 'finalstatus', '') !== $filter) {
        return false;
    }
    if ($search === '') {
        return true;
    }
    $identity = trim((string)acr_value($row, 'firstname', '') . ' ' .
        (string)acr_value($row, 'lastname', '')) . ' ' . (int)acr_value($row, 'userid', 0).
        ' '.(string)acr_value($row,'username','');
    return mb_stripos($identity, $search) !== false;
}));
$workitems = $filter==='pending' ? academy_review_queue::work_items($visible) : [];
$pagination = academy_review_queue::page(
    $filter==='pending' ? $workitems : academy_review_queue::prioritized($visible),
    $queuecontext['page']
);
if (!$detail) {
    $queuecontext['page'] = $pagination['page'];
}
$visible = $pagination['rows'];
$awaitingcount = academy_review_queue::awaiting_count($rows);
$pendingdaycount = array_sum(array_map(static fn($row): int => (int)acr_value($row, 'pendingdayreviews', 0), $rows));
$historicalcount = count(array_filter($rows, static fn($row): bool => (bool)acr_value($row, 'historicalbank', false)));
$saved = optional_param('saved', 0, PARAM_INT) === 1;
$reviewheading = $filter==='pending' ? 'Проверка работ' : 'Кандидаты и результаты';
if ($detail) {
    $headingattempt = $detail['attempt'];
    $headinglesson = $headingattempt->kind === 'day' ?
        lesson_catalog::by_id((string)$headingattempt->lessonid) : null;
    $reviewheading = $headingattempt->kind === 'day' ?
        ($headinglesson ? 'День ' . (int)substr($headinglesson['id'], 1) . ' · ' . $headinglesson['title'] : 'Тест дня') :
        'Итоговый экзамен';
    $reviewheading .= ' · попытка ' . (int)$headingattempt->attemptno;
}
if ($error !== '') {
    $PAGE->requires->js_init_code('setTimeout(function(){var e=document.querySelector(".lw-academy .lw-error");if(e){e.focus();}},0);');
}
$PAGE->requires->js(new moodle_url('/local/learning_workspace/assets/academy-review.js',['v'=>'20261017-reliable1']));

echo \local_learning_workspace\local\academy_page_shell::header($OUTPUT);
?>
<div class="lw-lesson lw-academy ac-review-page <?= !$detail?'ac-review-queue':'' ?>" lang="ru">
  <a class="lw-skip" href="#review-main">К основному содержанию</a>
  <?= \local_learning_workspace\local\academy_page_shell::topbar() ?>
  <div class="lw-workshop">
    <div class="lw-heading"><p class="lw-eyebrow">КАБИНЕТ ПРОВЕРКИ</p><h1><?= acr_e($reviewheading) ?></h1><p><?php if ($detail): ?><?= acr_e(trim((string)acr_value($detailcandidate,'firstname','').' '.(string)acr_value($detailcandidate,'lastname','')) ?: 'Кандидат') ?> · ответы и решение<?php else: ?><?= \local_learning_workspace\local\academy_workspace::staff_text('review_subtitle') ?><?php endif; ?></p></div>
    <?php if(!$detail): ?><div class="as-stats" aria-label="<?= \local_learning_workspace\local\academy_workspace::staff_text('overview') ?>">
    <?php foreach([['pending',$awaitingcount+$pendingdaycount,'staff_waiting'],['awaiting_review',$awaitingcount,'staff_final_waiting'],['day_pending',$pendingdaycount,'staff_day_waiting'],['all',count($rows),'staff_candidates']] as [$code,$count,$label]): ?><a class="as-stat <?= $filter===$code?'is-current':'' ?>" href="<?= acr_e(acr_filter_url($code,$search)) ?>"><strong><?= (int)$count ?></strong><span><?= \local_learning_workspace\local\academy_workspace::staff_text(substr($label,6)) ?></span></a><?php endforeach; ?>
    </div><?php endif; ?>
    <div class="lw-layout">
      <?php if($detail): ?><nav class="lw-sidebar as-review-nav" aria-label="Разделы проверки"><a href="<?= acr_e(acr_url()) ?>"><?= \local_learning_workspace\local\academy_workspace::staff_text('back_queue') ?></a><a href="#ac-answers"><?= \local_learning_workspace\local\academy_workspace::staff_text('answers') ?></a><?php if(!empty($detail['review'])||((int)$detail['attempt']->userid!==(int)$USER->id&&($detail['attempt']->kind==='day'||$detail['attempt']->status==='awaiting_review'))):?><a href="<?= !empty($detail['review'])?'#ac-saved-review':'#review-feedback' ?>"><?= \local_learning_workspace\local\academy_workspace::staff_text('feedback') ?></a><?php endif;?><a href="<?= acr_e((new moodle_url('/local/learning_workspace/academy_candidate.php',array_merge(academy_review_context::params($queuecontext),['userid'=>(int)$detail['attempt']->userid])))->out(false)) ?>"><?= \local_learning_workspace\local\academy_workspace::staff_text('candidate_history') ?></a></nav><?php endif; ?>
      <main id="review-main" class="lw-main" tabindex="-1">
        <div id="ac-review-errors" class="lw-alert lw-error" role="alert" tabindex="-1" <?= $error===''?'hidden':'' ?>><strong><?= acr_e($error) ?></strong><?php if ($fielderrors): ?><ul><?php foreach ($fielderrors as $field=>$message): ?><li><a href="#<?= acr_e($field) ?>"><?= acr_e($message) ?></a></li><?php endforeach; ?></ul><?php endif; ?></div>
        <?php if ($saved && $error === '' && !empty($detail['review'])): ?><div class="lw-alert" role="status">Решение сохранено. Кандидат видит обратную связь в своих результатах.</div><?php endif; ?>
        <?php if(optional_param('continued',0,PARAM_INT)>0):?><div class="lw-alert" role="status"><?=acr_e(ux::text(optional_param('continued',0,PARAM_INT)===1?'review_continued':'queue_done'))?></div><?php endif;?>
        <?php if ($reviewdraft && empty($detail['review'])): ?><p class="ac-event-meta" role="status">Черновик проверки: <?= acr_e(userdate($reviewdraft->timemodified,'%d.%m.%Y · %H:%M')) ?> · ученику не отправлен.<?php if ($error!==''): ?> <a href="<?= acr_e((new moodle_url('/local/learning_workspace/academy_review.php',array_merge(academy_review_context::params($queuecontext,$attemptid),['load_draft'=>1])))->out(false)) ?>">Открыть сохранённый черновик</a><?php endif; ?></p><?php endif; ?>
        <article class="lw-sheet"><div class="lw-workbenchbar"><span><?= $detail ? 'Карточка попытки' : 'Обзор кандидатов' ?></span></div><div class="lw-sheetbody">

        <?php if (!$detail): ?>
          <form class="ac-search as-filter" method="get" action="academy_review.php">
            <div><label for="ac-candidate-search">Имя, логин или ID кандидата</label><input id="ac-candidate-search" name="q" type="search" maxlength="100" value="<?= acr_e($search) ?>"></div>
            <div><label for="ac-status-filter"><?= \local_learning_workspace\local\academy_workspace::staff_text('status') ?></label><select id="ac-status-filter" name="status"><?php foreach(['pending'=>'Ожидают проверки','all'=>'Все кандидаты','day_pending'=>'Дневные ответы','awaiting_review'=>'Итоговые работы','rework'=>'На доработке','not_ready'=>'Пока не готовы','ready'=>'Готовы по своей версии','not_started'=>'Без итогового экзамена','historical'=>'Прежние версии'] as $code=>$label):?><option value="<?= $code ?>" <?= $filter===$code?'selected':'' ?>><?= acr_e($label) ?></option><?php endforeach;?></select></div>
            <button class="lw-secondary" type="submit"><?= \local_learning_workspace\local\academy_workspace::staff_text('apply') ?></button>
          </form>
          <div class="as-results-head"><p class="ac-queue-count" role="status"><?= \local_learning_workspace\local\academy_workspace::staff_text('found') ?> <strong><?= (int)$pagination['total'] ?></strong><?php if($pagination['total']): ?> · <?= (int)$pagination['first'] ?>–<?= (int)$pagination['last'] ?><?php endif; ?></p><?php if($search!==''||$filter!=='pending'):?><a class="ac-active-filters" href="<?= acr_e(acr_filter_url('pending','')) ?>"><?= \local_learning_workspace\local\academy_workspace::staff_text('reset') ?></a><?php endif;?></div>
          <?php if (!$visible): $emptystate = academy_review_queue::empty_state($rows, $filter, $search); ?><p class="ac-empty" data-empty-state="<?= acr_e($emptystate['kind']) ?>"><?= acr_e($emptystate['message']) ?><?php if ($emptystate['action']): ?> <a href="<?= acr_e(acr_filter_url($emptystate['action']['status'], $emptystate['action']['q'])) ?>"><?= acr_e($emptystate['action']['label']) ?></a><?php endif; ?></p><?php endif; ?>
          <?php if ($visible): ?><?php if ($filter==='pending'): ?><div class="ac-table-wrap" id="ac-candidate-list"><table class="ac-table ac-work-table"><caption class="lw-sr-only">Конкретные работы, ожидающие проверки</caption><thead><tr><th scope="col">Кандидат</th><th scope="col">Работа</th><th scope="col">Отправлено</th><th scope="col">Действие</th></tr></thead><tbody><?php foreach ($visible as $item): ?><tr><td data-label="Кандидат"><?= acr_e(trim($item->firstname.' '.$item->lastname)) ?><small><?= acr_e($item->username) ?></small></td><td data-label="Работа"><?= $item->kind==='day'?'Дневная практика · день '.(int)substr($item->lessonid,1):'Итоговый экзамен' ?><small>Попытка №<?= (int)$item->attemptno ?></small></td><td data-label="Отправлено"><?= acr_e(userdate($item->timecreated,'%d.%m.%Y · %H:%M')) ?></td><td data-label="Действие"><a href="<?= acr_e(acr_url((int)$item->id)) ?>">Проверить работу →<span class="lw-sr-only"> · <?= acr_e(trim($item->firstname.' '.$item->lastname)) ?></span></a></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?>
          <div class="ac-table-wrap" id="ac-candidate-list" tabindex="-1"><table class="ac-table"><caption class="lw-sr-only">Сводка по кандидатам: прогресс, итоговый экзамен, попытки и решение</caption><thead><tr><th scope="col">Кандидат</th><th scope="col">Дни</th><th scope="col">Итог</th><th scope="col">Попытки</th><th scope="col">Навыки</th><th scope="col">Решение</th><th scope="col">Действие</th></tr></thead><tbody>
          <?php foreach ($visible as $row): $state = acr_status((string)acr_value($row, 'finalstatus', '')); $latestid = (int)acr_value($row, 'latestattemptid', 0); $candidateid = (int)acr_value($row, 'userid', 0); $sections = acr_value($row, 'objectivesections', []); $manual = acr_value($row, 'manualparts', null); $submitted = (int)acr_value($row, 'submitteddaycount', 0); $passed = (int)acr_value($row, 'passeddaycount', 0); if ($latestid && acr_value($row, 'historicalbank', false)) { $state[0] .= ' · прежняя версия'; if ($state[1] === 'is-success') { $state[1] = 'is-warn'; } } ?>
            <tr><td data-label="Кандидат"><?= acr_e(trim((string)acr_value($row, 'firstname', '') . ' ' . (string)acr_value($row, 'lastname', ''))) ?><small><?= acr_value($row, 'enrolled', false) ? 'активен в курсе' : 'нет активного доступа' ?></small></td><td data-label="Дни"><strong>Отправлено <?= $submitted ?>/7</strong><small>Закрытая часть: <?= $passed ?>/7 · практика принята: <?= (int)acr_value($row, 'accepteddaycount', 0) ?>/7 · ответов ждут проверки: <?= (int)acr_value($row, 'pendingdayreviews', 0) ?></small></td><td data-label="Итог"><span class="ac-pill <?= acr_e($state[1]) ?>"><?= acr_e($state[0]) ?></span><?php if ($latestid && (int)acr_value($row, 'hasdraft', 0) === 1): ?><small>В попытке есть неутверждённые вопросы · готовность не подтверждается</small><?php endif; ?></td><td data-label="Попытки"><?= (int)acr_value($row, 'attempts', 0) ?></td><td data-label="Навыки"><strong>А1 <?= $latestid ? acr_e(acr_section_label($sections['foundation'] ?? null)) : '—' ?> · А2 <?= $latestid ? acr_e(acr_section_label($sections['process'] ?? null)) : '—' ?></strong><small>Б / В / Г: <?= is_array($manual) ? (int)($manual['dialogue']['score'] ?? 0) . ' / ' . (int)($manual['complex']['score'] ?? 0) . ' / ' . (int)($manual['promotion']['score'] ?? 0) : ($latestid ? 'ожидают оценки или старая рубрика' : '—') ?></small></td><td data-label="Решение"><?php if (acr_value($row, 'decision', null)): ?><?= acr_e(acr_status((string)acr_value($row, 'decision', ''))[0]) ?><?php elseif (acr_value($row, 'previousdecision', null)): $priorstate = acr_status((string)acr_value($row, 'previousdecision', '')); ?><span class="ac-muted">Прежнее: <?= acr_e($priorstate[0]) ?><small><?= acr_e(userdate((int)acr_value($row, 'previousdecisiontime', 0), '%d.%m.%Y')) ?> · новая попытка ожидает проверки</small></span><?php else: ?>—<?php endif; ?></td><td data-label="Действие"><div class="ac-row-actions"><?php if ($latestid): ?><a href="<?= acr_e(acr_url($latestid)) ?>"><?= acr_value($row, 'finalstatus', '') === 'awaiting_review' ? 'Проверить итог →' : 'Открыть итог →' ?></a><?php endif; ?><a href="<?= acr_e((new moodle_url('/local/learning_workspace/academy_candidate.php', array_merge(academy_review_context::params($queuecontext), ['userid' => $candidateid])))->out(false)) ?>"><?= (int)acr_value($row, 'pendingdayreviews', 0) > 0 ? 'Проверить дни →' : 'Вся история' ?></a></div></td></tr>
          <?php endforeach; ?>
          </tbody></table></div><?php endif; ?><?php endif; ?>
          <?php if ($pagination['pagecount'] > 1): ?><nav class="ac-pagination" aria-label="Страницы списка кандидатов"><?php if ($pagination['page'] > 1): ?><a class="lw-secondary" href="<?= acr_e(acr_page_url($pagination['page'] - 1, $filter, $search)) ?>">← Предыдущая</a><?php endif; ?><span>Страница <?= (int)$pagination['page'] ?> из <?= (int)$pagination['pagecount'] ?></span><?php if ($pagination['page'] < $pagination['pagecount']): ?><a class="lw-secondary" href="<?= acr_e(acr_page_url($pagination['page'] + 1, $filter, $search)) ?>">Следующая →</a><?php endif; ?></nav><?php endif; ?>
          <details class="as-review-help ac-queue-info"><summary>Решение по экзамену и рабочий доступ</summary><p>Решение относится к конкретной попытке и её версии заданий. Если в попытке есть неутверждённые задания, ограничения указаны в её карточке. Рабочий доступ здесь не выдаётся.</p></details>
        <?php else: $attempt = $detail['attempt']; $questions = (array)($detail['questions'] ?? []); $answers = (array)($detail['answers'] ?? []); $feedbackitems = (array)($detail['feedback'] ?? []); $sections = (array)($detail['objectivesections'] ?? []); $review = $detail['review'] ?? null; $reviewrubric = $review ? json_decode((string)acr_value($review, 'rubricjson', '{}'), true) : []; $reviewrubric = is_array($reviewrubric) ? $reviewrubric : []; $fivepart = acr_is_five_part((string)acr_value($attempt, 'bankversion', '')); $draftitems = acr_draft_questions($questions); $draft = count($draftitems) > 0; $status = acr_status($review ? (string)acr_value($review, 'decision', '') : (string)acr_value($attempt, 'status', '')); $isday = (string)acr_value($attempt, 'kind', '') === 'day'; $canreview = !$review && (int)$attempt->userid !== (int)$USER->id && ($isday || ((string)acr_value($attempt, 'kind', '') === 'final' && (string)acr_value($attempt, 'status', '') === 'awaiting_review')); $currentbankversion = $isday ? '' : (string)bank::final(bank::final_variant_for_attempt((int)$attempt->attemptno))['version']; ?>
          <div class="ac-statusline"><?php if(!$draft): ?><span class="ac-pill <?= acr_e(!$isday && (string)$attempt->bankversion !== $currentbankversion && $status[1] === 'is-success' ? 'is-warn' : $status[1]) ?>"><?= acr_e($status[0]) ?><?= !$isday && (string)$attempt->bankversion !== $currentbankversion ? ' · прежняя версия' : '' ?></span><?php endif; ?><span class="ac-pill is-muted">Попытка <?= (int)acr_value($attempt, 'attemptno', 0) ?></span><span class="ac-pill is-muted">Автобалл утверждённых закрытых вопросов <?= (int)acr_value($attempt, 'score', 0) ?>%</span><a class="ac-jump-answers" href="#ac-answers">К ответам ↓</a></div>
          <?php if (!$isday && (string)$attempt->bankversion !== $currentbankversion): ?><details class="lw-note ac-history-note"><summary>Историческая версия экзамена · подробности</summary><p>Это решение относится к <?= acr_e($attempt->bankversion) ?>. Текущая версия для этого варианта — <?= acr_e($currentbankversion) ?>. История сохранена; требуется ли переаттестация, решает ответственный за обучение.</p></details><?php endif; ?>
          <?php if ($sections): ?><div class="ac-statusline" aria-label="Результаты закрытых частей"><span class="ac-pill <?= ($sections['foundation']['status'] ?? '') === 'passed' ? 'is-success' : 'is-warn' ?>">А1 · <?= acr_e(acr_section_label($sections['foundation'] ?? null)) ?></span><span class="ac-pill <?= ($sections['process']['status'] ?? '') === 'passed' ? 'is-success' : 'is-warn' ?>">А2 · <?= acr_e(acr_section_label($sections['process'] ?? null)) ?></span></div><?php endif; ?>
          <?php if ($fivepart && ($reviewrubric['schema'] ?? '') === 'five_part_v1'): ?><div class="ac-statusline" aria-label="Результаты свободных частей"><?php foreach (['dialogue' => 'Б · Диалог', 'complex' => 'В · Ситуации', 'promotion' => 'Г · Продвижение'] as $partid => $label): $partscore = $reviewrubric['parts'][$partid] ?? []; ?><span class="ac-pill <?= ($partscore['status'] ?? '') === 'passed' ? 'is-success' : 'is-warn' ?>"><?= acr_e($label) ?>: <?= (int)($partscore['score'] ?? 0) ?>/10</span><?php endforeach; ?></div><?php endif; ?>
          <?php if($canreview):?><details class="as-review-help"><summary><?= \local_learning_workspace\local\academy_workspace::staff_text('review_help') ?></summary><?php endif;?><p class="ac-intro"><?= $review ? 'Решение сохранено. Ниже — ответы кандидата, оценки и обратная связь. Эта попытка доступна только для чтения.' : (!$canreview ? 'Ответы сохранены. Эта попытка доступна только для чтения; ниже можно проверить её содержание и историю.' : (!$isday ? ($fivepart ? 'Ответы сохранены. Оцените каждый критерий на 0, 1 или 2 балла; для оценки ниже 2 укажите точный фрагмент и способ исправления.' : 'Полные ответы сохранены в исходной попытке. Оцените каждый свободный ответ как «соответствует» или «не соответствует» и объясните решение кандидату.') : ($draft ? 'Это черновая версия теста дня. Прочитайте свободный ответ и дайте комментарий без зачёта: учебные вопросы ещё требуют утверждения.' : 'Это тест дня: закрытые вопросы оценены отдельно. Проверьте свободный ответ и дайте обратную связь. Принятие практики не означает прохождение всего дня или допуск к работе.'))) ?></p><?php if($canreview):?></details><?php endif;?>
          <?php if ($draft): ?><div class="ac-warning ac-draft-summary"><strong>Неутверждённых заданий: <?= count($draftitems) ?> · решение «готов» недоступно.</strong><details class="lw-details"><summary>Почему и какие задания нельзя засчитать</summary><p>Ответы можно проверить, но рабочий доступ здесь не выдаётся. Решение «готов» заблокировано до новой одобренной версии банка.</p><ol><?php foreach ($draftitems as $draftitem): $draftid = (string)acr_value($draftitem, 'id', ''); ?><li><a href="#ac-question-<?= acr_e($draftid) ?>">Открыть задание</a> · <?= acr_e(\local_learning_workspace\local\academy_branding::text(acr_value($draftitem, 'prompt', ''))) ?></li><?php endforeach; ?></ol></details></div><?php endif; ?>
          <?php if ($comparison): $comparisonpreviousurl = acr_url((int)$comparison['previousid']); require(__DIR__ . '/views/final_comparison.php'); endif; ?>
          <?= academy_result_view::timeline($attempt,$review ?: null) ?>
          <?php if ($canreview): ?><form id="ac-review-form" method="post" action="<?= acr_e(acr_url($attemptid)) ?>"><input type="hidden" name="sesskey" value="<?= acr_e(sesskey()) ?>"><input type="hidden" name="attemptid" value="<?= (int)$attemptid ?>"><input type="hidden" name="draftrevision" value="<?= (int)$formrevision ?>"><input type="hidden" name="idemkey" value="<?= acr_e($formidemkey) ?>"><div class="ac-review-progress"><strong data-ac-review-progress role="status" tabindex="-1">Оценка критериев</strong><button class="lw-secondary" type="button" data-ac-review-find>К незавершённому критерию</button><button type="submit" name="action" value="save_draft" formnovalidate class="lw-secondary">Сохранить черновик</button></div><?php endif; ?>
          <div id="ac-answers" class="ac-answers">
          <?php $orderedquestions=array_filter($questions,static fn($q)=>acr_value($q,'type','choice')!=='choice')+array_filter($questions,static fn($q)=>acr_value($q,'type','choice')==='choice');$closedgroup=false;
          foreach ($orderedquestions as $index => $question): $id = (string)acr_value($question, 'id', ''); $type = (string)acr_value($question, 'type', 'choice'); $answer = $answers[$id] ?? ''; $itemfeedback = (array)($feedbackitems[$id] ?? []); $correct = $itemfeedback['correct'] ?? null; $inlinecase = academy_dialogue_snapshot_view::has_complete_answers((array)$question, (string)$answer); ?>
<?php if($type==='choice'&&!$closedgroup):$closedgroup=true;?><details id="ac-closed" class="as-closed-group"><summary><?= \local_learning_workspace\local\academy_workspace::staff_text('closed_answers') ?> · <?=count(array_filter($questions,static fn($q)=>acr_value($q,'type','choice')==='choice'))?></summary><?php endif;?>
<?php if($type==='choice'): ?><details class="ac-choice-detail"><summary><?= \local_learning_workspace\local\academy_workspace::staff_text('closed_question') ?> <?= $index+1 ?> · <?= $correct===true?'Верно':($correct===false?'Ошибка':'Без оценки') ?></summary><?php endif; ?>
<section id="ac-question-<?= acr_e($id) ?>" class="ac-answer" data-question-id="<?= acr_e($id) ?>"><h3>Вопрос <?= $index + 1 ?><?= acr_value($question, 'status', '') !== 'approved_structure' ? ' · ЧЕРНОВИК' : '' ?><?= !empty(acr_value($question, 'hardfail', false)) ? ' · КРИТИЧЕСКОЕ ПРАВИЛО' : '' ?></h3><div class="<?=$canreview&&$type==='text'?'as-answer-grid':''?>"><div class="as-answer-content"><p><?= acr_e(\local_learning_workspace\local\academy_branding::text(acr_value($question, 'prompt', ''))) ?></p><div class="sa-candidate-response" data-ac-source><?= academy_dialogue_snapshot_view::render((array)$question, true, (string)$answer) ?><?php if ($inlinecase): ?><details class="lw-details"><summary>Полный исходный текст ответа</summary><p><?= nl2br(acr_e((string)$answer)) ?></p></details><?php else: ?><p class="sa-response-label">Ответ кандидата</p><p><?= nl2br(acr_e(acr_answer_display($question, $answer))) ?></p><?php endif; ?></div>
              <?php $quotetexts=$id==='F-DIALOG-01' ? \local_learning_workspace\local\academy_dialogue_answer_codec::decode((string)$answer) : []; ?>
              <script type="application/json" data-ac-answer-text><?= json_encode(array_values($quotetexts ?: [(string)$answer]),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE) ?></script>
              
              <?php if ($type === 'choice'): ?><p class="ac-pill <?= $correct === true ? 'is-success' : 'is-warn' ?>"><?= $correct === true ? 'Верно' : ($correct === false ? 'Ошибка' : 'Черновик · без автоматической оценки') ?></p><?php endif; ?>
              <?php $mark = $reviewrubric[$id] ?? null; $markresult = acr_mark_result($mark); if ($type === 'text' && $markresult !== ''): ?><p class="ac-pill <?= $markresult === 'pass' ? 'is-success' : 'is-warn' ?>"><?= $markresult === 'pass' ? 'Критерий выполнен' : 'Критерий не выполнен' ?></p><?php if (is_array($mark) && $markresult === 'fail'): ?><div class="ac-evidence"><p><strong>Фрагмент ответа:</strong> «<?= acr_e($mark['quote'] ?? '') ?>»</p><p><strong>Что исправить:</strong> <?= acr_e($mark['nextstep'] ?? '') ?></p></div><?php endif; endif; ?>
              <?php if ($fivepart && $type === 'text' && ($reviewrubric['schema'] ?? '') === 'five_part_v1'): foreach ((array)acr_value($question, 'criteria', []) as $criterionitem): $criterionid = (string)($criterionitem['id'] ?? ''); $criterionmark = $reviewrubric['criteria'][$criterionid] ?? []; ?><div class="ac-evidence <?= (int)($criterionmark['score'] ?? 0)===2?'is-success':'' ?>"><p><strong><?= acr_e($criterionitem['label'] ?? '') ?>:</strong> <?= (int)($criterionmark['score'] ?? 0) ?>/2<?= !empty($criterionitem['required_score']) ? ' · обязательно 2' : '' ?></p><?php if ((int)($criterionmark['score'] ?? 0) < 2): ?><p>Фрагмент: «<?= acr_e($criterionmark['quote'] ?? '') ?>»</p><p>Что исправить: <?= acr_e($criterionmark['nextstep'] ?? '') ?></p><?php endif; ?></div><?php endforeach; endif; ?>
               <?php $criterion = $type === 'choice' ? (string)($itemfeedback['explanation'] ?? '') : (string)acr_value($question, 'explanation', ''); if ($criterion !== ''): ?><?php if($type === 'text'): ?><details class="sa-review-reference"><summary>Ориентир проверки</summary><p class="ac-muted"><?= acr_e(acr_criterion_copy($criterion)) ?></p></details><?php else: ?><p class="ac-muted"><strong>Пояснение:</strong> <?= acr_e(acr_criterion_copy($criterion)) ?></p><?php endif; ?><?php endif; ?>
               <?php if (($correct === false || $markresult === 'fail') && (!empty(acr_value($question, 'hardfail', false)) || acr_value($question, 'part', '') === 'safety')): ?><p class="ac-warning">Критическая ошибка: статус «готов» заблокирован.</p><?php endif; ?>
               </div><?php if ($canreview && $type === 'text'): ?><div class="ac-rubric ac-inline-review"><h4>Оценка этого ответа</h4>
                 <?php if ($fivepart && !$isday): foreach ((array)acr_value($question,'criteria',[]) as $criterionitem):
                    echo acr_criterion((string)$criterionitem['id'],(string)$criterionitem['label'].(!empty($criterionitem['required_score'])?' · обязательно 2':''),true);
                  endforeach; else: echo acr_criterion($id,'Соответствие критерию',false); endif; ?>
               </div><?php endif; ?></div>
             </section><?php if($type==='choice'): ?></details><?php endif; ?>
          <?php endforeach; ?><?php if($closedgroup):?></details><?php endif;?>
          </div>
          <?php if ($review): ?><div id="ac-saved-review" class="ac-result <?= in_array((string)$review->decision,['ready','practice_accepted'],true)?'':'is-warn' ?>"><h2><?= $isday ? 'Обратная связь по практике' : 'Сохранённое решение' ?>: <?= acr_e(acr_status((string)acr_value($review, 'decision', ''))[0]) ?></h2><p><?= nl2br(acr_e(acr_value($review, 'feedback', ''))) ?></p></div><?php endif; ?>
          <?php if ($review): require(__DIR__.'/views/attempt_permit.php'); endif; ?>
          <?php if ($canreview): ?>
            <div class="ac-review-finish"><h2>Итог проверки</h2><label class="ac-text-label" for="review-feedback">Комментарий кандидату</label><textarea id="review-feedback" name="feedback" required minlength="10" maxlength="4000" aria-describedby="review-feedback-help"><?= acr_e($postedfeedback) ?></textarea><p id="review-feedback-help" class="ac-help">Не меньше 10 символов. При принятии ответа отметьте, что выполнено. Если нужна доработка, укажите конкретный пробел и следующий шаг. Не вставляйте персональные данные клиентов.</p>
            <label class="ac-text-label" for="review-decision">Решение</label><select id="review-decision" name="decision" required><option value="">Выберите решение</option><?php if ($isday): ?><?php if ($draft): ?><option value="practice_feedback" <?= $posteddecision === 'practice_feedback' ? 'selected' : '' ?>>Комментарий без зачёта</option><?php else: ?><option value="practice_accepted" <?= $posteddecision === 'practice_accepted' ? 'selected' : '' ?>>Свободный ответ принят</option><option value="practice_rework" <?= $posteddecision === 'practice_rework' ? 'selected' : '' ?>>Доработать свободный ответ</option><?php endif; ?><?php else: ?><?php if (!$draft): ?><option value="ready" <?= $posteddecision === 'ready' ? 'selected' : '' ?>>Готов по экзамену</option><?php endif; ?><option value="rework" <?= $posteddecision === 'rework' ? 'selected' : '' ?>>Нужна доработка</option><option value="not_ready" <?= $posteddecision === 'not_ready' ? 'selected' : '' ?>>Пока не готов</option><?php endif; ?></select>
            <p id="ac-review-summary" class="ac-help" role="status">Выберите решение и завершите критерии перед отправкой.</p><p class="as-form-status" role="status" aria-live="polite" data-ac-save-status></p><div class="ac-sticky-action"><button class="lw-primary" type="submit" name="action" value="review_next"><?=acr_e(ux::text('save_next'))?></button><button class="lw-secondary" type="submit" name="action" value="review" data-ac-commit>Сохранить решение</button></div></div>
          </form>
          <?php endif; ?>
           <?php if (!empty($detail['history'])): ?><details class="lw-details"><summary>История попыток</summary><ol><?php foreach ($detail['history'] as $prior): $priorid = (int)acr_value($prior, 'id', 0); if ($priorid === $attemptid) { continue; } $priorreview = $detail['reviews_by_attempt'][$priorid] ?? null; $priorstatus = $priorreview ? (string)acr_value($priorreview, 'decision', '') : (string)acr_value($prior, 'status', ''); ?><li><a href="<?= acr_e(acr_url($priorid)) ?>">Попытка <?= (int)acr_value($prior, 'attemptno', 0) ?></a> · <?= acr_e(acr_status($priorstatus)[0]) ?></li><?php endforeach; ?></ol></details><?php endif; ?>
          <p><a class="lw-secondary" href="<?= acr_e(acr_url()) ?>">К списку кандидатов</a></p>
        <?php endif; ?>
        </div></article>

      </main>
    </div>
  </div>
</div>
<?php echo $OUTPUT->footer();
