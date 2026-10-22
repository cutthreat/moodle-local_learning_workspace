<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade the first clean Learning Workspace distribution.
 *
 * This component has no public migration path from earlier schemas.
 * Future releases should add only migrations required by the current clean install.xml.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_learning_workspace_upgrade(int $oldversion): bool {
    if ($oldversion < 2026102201) {
        upgrade_plugin_savepoint(true, 2026102201, 'local', 'learning_workspace');
    }
    return true;
}

