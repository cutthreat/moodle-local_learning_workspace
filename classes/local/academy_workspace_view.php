<?php
namespace local_learning_workspace\local;
defined('MOODLE_INTERNAL') || die();
require_once(__DIR__.'/academy_page_shell.php');
final class academy_workspace_view {
    public static function e($value): string { return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
    public static function start(string $key,string $page,string $error='',string $notice='',string $title=''): void {
        global $PAGE,$OUTPUT,$SCRIPT;
        $title=$title!==''?$title:academy_workspace::text($key);
        $PAGE->set_context(\context_system::instance());
        $PAGE->set_url(academy_workspace::url($page));
        $PAGE->set_pagelayout('embedded');
        $PAGE->set_title($title.' · Learning Workspace');
        $PAGE->requires->css(new \moodle_url('/local/learning_workspace/assets/academy-base.css',['v'=>'20261011-workspace1']));
        echo academy_page_shell::header($OUTPUT);
        echo '<div class="lw-lesson"><a class="lw-skip" href="#workspace-main">К содержанию</a>'.academy_page_shell::topbar().'<main id="workspace-main" class="aw-page" tabindex="-1"><h1>'.self::e($title).'</h1>';
        if ($page!=='manage' && academy_layout::kind((string)$SCRIPT)!=='viewer') { echo academy_layout::section_nav($page); }
        if($error!==''){echo '<div class="lw-alert lw-error" role="alert">'.self::e($error).'</div>';}
        if($notice!==''){echo '<div class="lw-alert" role="status">'.self::e($notice).'</div>';}
    }
    public static function end(): void { global $OUTPUT; echo '</main></div>'.$OUTPUT->footer(); }
    public static function fields(string $action): string {
        return '<input type="hidden" name="sesskey" value="'.self::e(sesskey()).'"><input type="hidden" name="workspaceform" value="'.self::e(academy_workspace::form_token($action)).'"><input type="hidden" name="action" value="'.self::e($action).'">';
    }
}
