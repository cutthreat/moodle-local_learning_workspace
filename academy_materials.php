<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/local/academy_access.php');
require_login();
$context = \local_learning_workspace\local\academy_access::require_viewer();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/learning_workspace/academy_materials.php'));
$PAGE->set_title('Документы демо-пакета');
$PAGE->set_heading('Документы демо-пакета');
echo $OUTPUT->header();
echo $OUTPUT->notification('PDF и учебные материалы в эту программную дистрибуцию не включены.', 'info');
echo $OUTPUT->footer();
