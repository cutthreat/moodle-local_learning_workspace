<?php
defined('MOODLE_INTERNAL') || die();

/** Pre-uninstallation hook. */
function xmldb_local_learning_workspace_uninstall(): void {
    // Remove only component-owned tours while tool_usertours and this component's config still exist.
    \local_learning_workspace\local\academy_quickstart::uninstall();
}
