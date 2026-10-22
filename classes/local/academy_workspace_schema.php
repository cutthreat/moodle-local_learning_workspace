<?php
namespace local_learning_workspace\local;
defined('MOODLE_INTERNAL') || die();

/** Additive workspace schema. Learning records and core accounts are untouched. */
final class academy_workspace_schema {
    public static function upgrade(): void {
        global $DB;
        $dbman = $DB->get_manager();
        $definitions = [
            'local_lworkspace_notice' => [
                'authorid'=>'int', 'audience'=>['char',16], 'title'=>['char',160],
                'body'=>'text', 'active'=>'int', 'timecreated'=>'int', 'timemodified'=>'int',
            ],
            'local_lworkspace_nread' => [
                'userid'=>'int', 'eventkey'=>['char',64], 'timecreated'=>'int',
            ],
            'local_lworkspace_help' => [
                'userid'=>'int', 'subject'=>['char',160], 'message'=>'text',
                'pagepath'=>['char',255], 'status'=>['char',16], 'reply'=>'text',
                'handledby'=>'int', 'timecreated'=>'int', 'timemodified'=>'int',
            ],
        ];
        foreach ($definitions as $name=>$fields) {
            $table = new \xmldb_table($name);
            if ($dbman->table_exists($table)) { continue; }
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            foreach ($fields as $field=>$type) {
                if ($type === 'int') { $table->add_field($field, XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0); }
                elseif ($type === 'text') { $table->add_field($field, XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL); }
                else { $table->add_field($field, XMLDB_TYPE_CHAR, (string)$type[1], null, XMLDB_NOTNULL); }
            }
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            if ($name === 'local_lworkspace_nread') {
                $table->add_index('user_event_uix', XMLDB_INDEX_UNIQUE, ['userid','eventkey']);
            } elseif ($name === 'local_lworkspace_help') {
                $table->add_index('user_time_ix', XMLDB_INDEX_NOTUNIQUE, ['userid','timecreated']);
            }
            $dbman->create_table($table);
        }
    }
}
