<?php
// Moodle 5.2 primary navigation is extended through db/hooks.php, not the
// legacy global_navigation callback. Keep lib.php for other plugin callbacks.
defined('MOODLE_INTERNAL') || die();

/** Send academy users from the generic Moodle dashboard to their workspace. */
function local_learning_workspace_before_http_headers(): void {
    global $SCRIPT;
    if ((defined('CLI_SCRIPT') && CLI_SCRIPT) ||
            (defined('AJAX_SCRIPT') && AJAX_SCRIPT) ||
            ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET' ||
            !in_array($SCRIPT, ['/my/index.php', '/index.php'], true) ||
            !isloggedin() || isguestuser() || is_siteadmin()) {
        return;
    }
    require_once(__DIR__ . '/classes/local/academy_navigation.php');
    $destination = \local_learning_workspace\local\academy_navigation::landing_path();
    if ($destination !== null) {
        redirect(new moodle_url($destination));
    }
}
