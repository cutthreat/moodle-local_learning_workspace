<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/local/academy_page_shell.php');
require_once(__DIR__ . '/classes/local/academy_access.php');
require_login();
$context = \local_learning_workspace\local\academy_access::require_viewer();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/learning_workspace/academy_work.php'));
$PAGE->set_title('Демо-работы');
$PAGE->set_heading('Демо-работы');
echo \local_learning_workspace\local\academy_page_shell::header($OUTPUT);
echo \local_learning_workspace\local\academy_page_shell::topbar();
echo $OUTPUT->header();
echo html_writer::tag('h2', 'Статус отправленного ответа');
echo html_writer::tag('p', 'Черновики, отправленные версии и замечания проверяющего показаны в рабочих разделах демо-пакета.');
echo html_writer::tag('p', 'Учебные инструкции, правила работы и материалы курса в эту программную дистрибуцию не включены.');
echo html_writer::link(new moodle_url('/local/learning_workspace/course.php'), 'Открыть демо-процесс');
echo $OUTPUT->footer();
