<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/local/content_pack.php');
require_once(__DIR__ . '/classes/local/academy_access.php');
require_login();
$context = \local_learning_workspace\local\academy_access::require_viewer();
$id = optional_param('id', 'M0', PARAM_ALPHANUM);
$modules = \local_learning_workspace\local\content_pack::modules();
$module = null;
foreach ($modules as $candidate) {
    if ($candidate['id'] === $id) {
        $module = $candidate;
        break;
    }
}
if ($module === null) {
    print_error('invalidparameter');
}
$pack = \local_learning_workspace\local\content_pack::lesson_pack($id);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/learning_workspace/module.php', ['id' => $id]));
$PAGE->set_title($module['title']['ru']);
$PAGE->set_heading('Демо-модуль');
echo $OUTPUT->header();
echo html_writer::tag('h2', s($module['title']['ru']));
echo html_writer::tag('p', s($module['outcome']['ru']));
echo html_writer::tag('p', s($module['materials']));
echo html_writer::tag('h3', 'Пример ситуации');
echo html_writer::tag('p', s($pack['scenarios'][0]['prompt_ru'] ?? 'Нейтральный пример процесса.'));
echo html_writer::tag('h3', 'Шаги');
echo html_writer::alist($module['checklist']);
echo html_writer::link(new moodle_url('/local/learning_workspace/course.php'), 'Вернуться к демо-процессу');
echo $OUTPUT->footer();
