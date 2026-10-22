<?php
/**
 * Temporary read-only preview for external review of lesson 1.
 *
 * This route intentionally does not call require_login(), does not read a
 * learner record and does not write to Moodle. It is for showing the lesson
 * flow to reviewers while the local contour remains the source of truth.
 */
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/local/academy_page_shell.php');

use local_learning_workspace\local\lesson_one;
use local_learning_workspace\local\lesson_one_view;

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/learning_workspace/lesson_one_preview.php'));
$PAGE->set_pagelayout('embedded');
$PAGE->set_title('Урок 1 · Learning Workspace');
$PAGE->set_heading('Learning Workspace');
$PAGE->requires->css(new moodle_url('/local/learning_workspace/assets/academy-base.css', ['v' => '20261011-clean-v2']));

$content = lesson_one::content();
$state = lesson_one::initial();
$state['maxstage'] = 5;
$state['choice'] = 'b';
$state['checkpassed'] = true;
$state['completed'] = true;
$sample = $content['practice'][4]['sample'];
$state['answers'][4] = [
    'draft' => $sample,
    'submitted' => $sample,
    'has_submission' => true,
    'reviewed' => true,
];

$current = [
    'content' => $content,
    'state' => $state,
    'revision' => 0,
    'id' => 0,
    'hash' => hash('sha256', lesson_one::encode($content)),
];

$stage = optional_param('step', 1, PARAM_INT);
$stage = max(1, min(5, $stage));
$home = (new moodle_url('/local/learning_workspace/lesson_one_preview.php'))->out(false);

echo \local_learning_workspace\local\academy_page_shell::header($OUTPUT);
echo lesson_one_view::render(
    $current,
    $stage,
    '',
    '',
    '',
    null,
    [],
    $home,
    false,
    false
);
echo $OUTPUT->footer();
