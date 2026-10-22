<?php
defined('MOODLE_INTERNAL') || die();
function xmldb_local_learning_workspace_install(): void {
    require_once(__DIR__.'/../classes/local/academy_attempt_policy.php');
    \local_learning_workspace\local\academy_attempt_policy::upgrade();
    require_once(__DIR__.'/../classes/local/academy_review_form.php');
    \local_learning_workspace\local\academy_review_form::upgrade();
    require_once(__DIR__.'/../classes/local/academy_blended.php');
    \local_learning_workspace\local\academy_blended::upgrade();
    require_once(__DIR__.'/../classes/local/academy_quickstart.php');
    \local_learning_workspace\local\academy_quickstart::install();
}
