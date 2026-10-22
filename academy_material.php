<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/local/academy_access.php');
require_login();
$context = \local_learning_workspace\local\academy_access::require_viewer();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/learning_workspace/academy_material.php'));
$PAGE->set_title('Документ не включён');
$PAGE->set_heading('Документ не включён');
echo $OUTPUT->header();
echo $OUTPUT->notification('Документы курса исключены из демо-пакета.', 'info');
echo $OUTPUT->footer();
