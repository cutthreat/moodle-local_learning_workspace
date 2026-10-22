<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/local/academy_page_shell.php');
require_once(__DIR__ . '/classes/local/academy_access.php');

use local_learning_workspace\local\lesson_one;
use local_learning_workspace\local\lesson_one_repository;
use local_learning_workspace\local\lesson_one_view;
use local_learning_workspace\local\academy_access;

require_login();
$context = academy_access::require_viewer();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/learning_workspace/lesson_one.php'));
$PAGE->set_pagelayout('embedded');
$PAGE->set_title('Демо-практика');
$PAGE->set_heading('Learning Workspace');
$current = lesson_one_repository::read((int)$USER->id);
$canwrite = academy_access::is_candidate();
$defaultstage = $canwrite ? min(5, (int)$current['state']['maxstage']) : 1;
if ($defaultstage >= 3 && empty($current['state']['checkpassed'])) {
    $defaultstage = 3;
} elseif ($defaultstage >= 5 && empty($current['state']['completed'])) {
    $defaultstage = 4;
}
$stage = optional_param('step', $defaultstage, PARAM_INT);
$stage = lesson_one::navigation_stage($current['state'], $stage, $canwrite);
$error = '';
$notice = '';
$unsaved = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    $action = required_param('action', PARAM_ALPHA);
    $stage = required_param('step', PARAM_INT);
    $value = optional_param('answer', '', PARAM_RAW);
    if ($action === 'check' && !empty($current['content']['quiz'])) {
        $value = lesson_one::encode(optional_param_array('quiz', [], PARAM_ALPHA));
    }
    $unsaved = $value;
    try {
        $current = lesson_one_repository::save((int)$USER->id, required_param('revision', PARAM_INT),
            required_param('contenthash', PARAM_ALPHANUM), $action, $stage, $value);
        $returnto = optional_param('returnto', '', PARAM_ALPHA);
        $leaveroutes = ['course' => 'course.php', 'sessions' => 'academy_sessions.php', 'results' => 'academy.php'];
        if ($action === 'draft' && isset($leaveroutes[$returnto])) {
            redirect(new moodle_url('/local/learning_workspace/' . $leaveroutes[$returnto]));
        }
        $next = $action === 'next' ? min(5, $stage + 1) : $stage;
        $returnstep = optional_param('returnstep', 0, PARAM_INT);
        if ($action === 'draft' && $returnstep >= 1 && $returnstep <= min(4, (int)$current['state']['maxstage'])) { $next = $returnstep; }
        $saved = $action === 'submit' ? 'answer' : ($action === 'draft' ? 'draft' : '');
        $target = new moodle_url('/local/learning_workspace/lesson_one.php', ['step' => $next, 'saved' => $saved]);
        if ($action === 'submit') { $target->set_anchor('review'); }
        redirect($target);
    } catch (\DomainException $exception) {
        $error = $exception->getMessage();
        $stage = max(1, min($stage, $current['state']['maxstage'], 5));
    }
}
$saved = $_SERVER['REQUEST_METHOD'] === 'GET' ? optional_param('saved', '', PARAM_ALPHA) : '';
if ($saved === 'answer') { $notice = 'Ответ сохранён. Теперь сравните его с разбором. Смысл ответа автоматически не оценивался.'; }
if ($saved === 'draft') { $notice = 'Черновик сохранён в вашем аккаунте. Можно вернуться позже.'; }
$history = $canwrite && $stage === 4 ? lesson_one_repository::history((int)$USER->id, $current['id'], 4) : [];
echo \local_learning_workspace\local\academy_page_shell::header($OUTPUT);
echo lesson_one_view::render($current, $stage, sesskey(), $error, $notice, $unsaved, $history,
    (new moodle_url('/local/learning_workspace/course.php'))->out(false),
    $canwrite,
    false);
echo $OUTPUT->footer();
