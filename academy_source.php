<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/local/academy_access.php');
require_login();
\local_learning_workspace\local\academy_access::require_viewer();
print_error('filenotfound', 'error');
