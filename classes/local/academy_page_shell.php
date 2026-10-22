<?php
namespace local_learning_workspace\local;
defined('MOODLE_INTERNAL') || die();
require_once(__DIR__.'/academy_workspace.php');
require_once(__DIR__.'/academy_layout.php');
require_once(__DIR__.'/academy_quickstart.php');
require_once(__DIR__.'/academy_timezone.php');

/** One shared workspace; Moodle retains authentication and grading ownership. */
final class academy_page_shell {
    public const BRAND='Learning Workspace';
    public const VERSION='20261019-ux1';
    private static bool $active=false;
    private static bool $preview=false;
    private static function e(string $s): string { return htmlspecialchars($s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
    public static function header(object $output): string {
        global $PAGE,$SCRIPT;
        self::$active=true;
        if (method_exists($PAGE,'add_body_class')) {
            $PAGE->add_body_class('al-surface');
            $PAGE->add_body_class('al-'.academy_layout::kind((string)$SCRIPT));
        }
        self::$preview=str_ends_with((string)$SCRIPT,'lesson_one_preview.php');
        foreach (['academy-shell','academy-workspace','academy-layout'] as $asset) {
            $PAGE->requires->css(new \moodle_url('/local/learning_workspace/assets/'.$asset.'.css',['v'=>$asset==='academy-layout'?academy_layout::VERSION:self::VERSION]));
        }
        $PAGE->requires->js(new \moodle_url('/local/learning_workspace/assets/academy-shell.js',['v'=>'20261019-current-step1']));
        $PAGE->requires->js(new \moodle_url('/local/learning_workspace/assets/academy-workspace.js',['v'=>self::VERSION.'-menus3']));
        if (in_array(basename((string)$SCRIPT), ['academy_profile.php','academy_help.php','academy_onboarding.php'], true)) {
            $PAGE->requires->js(new \moodle_url('/local/learning_workspace/assets/academy-settings.js',['v'=>'20261021-pending2']));
        }
        $PAGE->requires->css(new \moodle_url('/local/learning_workspace/assets/academy-blended.css',['v'=>self::VERSION.'-multiple3']));
        $PAGE->requires->js(new \moodle_url('/local/learning_workspace/assets/academy-blended.js',['v'=>self::VERSION.'-multiple5-undo-label1']));
        $staffconfig='';
        if (in_array(basename((string)$SCRIPT), ['academy_review.php','academy_manage.php','academy_teaching.php','academy_candidate.php'], true)) {
            if (method_exists($PAGE,'add_body_class')) { $PAGE->add_body_class('al-staff'); }
            $PAGE->requires->css(new \moodle_url('/local/learning_workspace/assets/academy-staff.css',['v'=>'20261015-workflow6']));
            $PAGE->requires->js(new \moodle_url('/local/learning_workspace/assets/academy-staff.js',['v'=>'20261015-workflow1']));
            $staffstrings=[];foreach(['unsaved','saving','unconfirmed','leave'] as $key){$staffstrings[$key]=get_string_manager()->get_string('ux_'.$key,'local_learning_workspace',null,'ru');}
            $staffconfig='<script type="application/json" id="academy-staff-config">'.json_encode($staffstrings,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE).'</script>';
        }
        $config=['endpoint'=>(new \moodle_url('/local/learning_workspace/academy_workspace_action.php'))->out(false),
            'sesskey'=>sesskey(),'dismissed'=>academy_workspace::dismissed(),
            'close'=>academy_workspace::text('close_notice'),'error'=>academy_workspace::text('session_failed')];
        $quickstart=academy_quickstart::boot();
        $PAGE->requires->css(new \moodle_url('/local/learning_workspace/assets/academy-product.css',['v'=>'20261020-layout7']));
        return preg_replace('/(<div\b[^>]*?)\srole=(["\'])main\2/i','$1',(string)$output->header(),1).$quickstart.
            $staffconfig.
            '<template id="academy-account-tools">'.self::tools('header').'</template>'.
            '<noscript><nav class="aw-noscript"><a href="'.academy_workspace::url('help')->out().'">'.academy_workspace::text('help').'</a> · <a href="'.academy_workspace::url('notifications')->out().'">'.academy_workspace::text('notifications').'</a> · <a href="'.academy_workspace::url('profile')->out().'">'.academy_workspace::text('profile').'</a></nav></noscript>'.
            '<script type="application/json" id="academy-workspace-config">'.
            json_encode($config,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE).'</script>';
    }
    /** One header contract for every role and page, including denied and read-only states. */
    public static function topbar(string $backurl='',string $backlabel='',bool $saveleave=false): string {
        global $SCRIPT;
        $signed=!self::$preview && function_exists('isloggedin') && isloggedin() && !isguestuser();
        $links=['course.php'=>'Обучение'];
        if($signed){
            $links['academy_sessions.php']='Занятия и группа';
            if(academy_workspace::role()==='candidate'){$links['academy.php']='Мои результаты';}
            if(academy_workspace::can_review()){$links['academy_review.php']='Проверка работ';}
            if(academy_workspace::manager()){$links['academy_manage.php']='Управление';}
        }
        $leave=$saveleave?' lw-save-leave':'';
        $current=basename((string)($SCRIPT??''));
        // A lesson is inside Learning, but it is not the course overview itself.
        $learningchild=in_array($current,['lesson_one.php','lesson_one_preview.php','academy_day.php',
            'academy_material.php','academy_materials.php','academy_rehearsal.php'],true);
        $html='<header class="lw-top aw-shared-top" data-academy-shell="'.self::VERSION.'">'.
            '<a class="lw-brand'.$leave.'" href="course.php"><span class="lw-brand-symbol" aria-hidden="true">LW</span><span>'.self::BRAND.'</span></a>'.
            '<nav class="aw-primary" aria-label="Основная навигация">';
        foreach($links as $url=>$label){
            $active=$current===$url?' aria-current="page"':($url==='course.php'&&$learningchild?' aria-current="location"':'');
            $tourkey=['course.php'=>'course','academy_sessions.php'=>'sessions','academy.php'=>'results','academy_review.php'=>'review','academy_manage.php'=>'manage'][$url]??'';
            $html.='<a class="aw-primary-link'.$leave.'" data-aq-nav="'.$tourkey.'" href="'.$url.'"'.$active.'>'.self::e($label).'</a>';
        }
        $html.='</nav>';
        if($backurl!==''&&$backurl!=='course.php'&&$backlabel!==''){
            $html.='<a class="lw-course-link aw-context-back'.$leave.'" href="'.self::e($backurl).'">'.self::e($backlabel).'</a>';
        }
        return $html.'</header>';
    }
    public static function tools(string $location): string {
        global $USER, $CFG;
        $w=academy_workspace::class; $e=static fn($s)=>self::e((string)$s);
        $signed=!self::$preview && isloggedin() && !isguestuser() && !empty($USER->id);
        $role=$signed?$w::role():'guest';
        $name=$signed?fullname($USER):$w::text('login');
        $initials=$signed?\core_text::strtoupper(\core_text::substr($USER->firstname,0,1).\core_text::substr($USER->lastname,0,1)):'☆';
        $digest=$w::notification_digest($signed?$w::feed():[]);$unread=$digest['count'];
        $id='aw-profile-'.$location;
        $notificationsid='aw-notifications-'.$location;
        $logout = new \moodle_url('/login/logout.php');
        ob_start(); ?>
        <nav class="aw-tools" aria-label="<?= $e($w::text('account')) ?>">
          <a class="aw-help" href="<?= $e($w::url('help')->out(false)) ?>" aria-label="<?= $e($w::text('help')) ?>"><svg aria-hidden="true" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.5 2.5 0 0 1 5 .3c0 1.6-2.5 1.8-2.5 3.7M12 17h.01"/></svg><span class="aw-help-label"><?= $e($w::text('help')) ?></span></a>
          <div class="aw-notification-dropdown" data-aw-popover>
            <button type="button" class="aw-notifications" data-aw-popover-toggle aria-label="<?= $e($unread?$w::text('notifications_count',$unread):$w::text('notifications')) ?>" aria-expanded="false" aria-controls="<?= $notificationsid ?>"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg><?php if($unread): ?><span class="aw-count" aria-hidden="true"><?= $unread>99?'99+':$unread ?></span><?php endif; ?></button>
            <section id="<?= $notificationsid ?>" class="aw-notification-menu" aria-labelledby="<?= $notificationsid ?>-title" hidden>
              <h2 id="<?= $notificationsid ?>-title"><?= $e($w::text('notifications')) ?></h2>
              <?php if($digest['items']): ?><ul class="aw-notification-list">
                <?php foreach($digest['items'] as $event): ?><li><a class="aw-notification-item" href="<?= $e($w::url('notifications')->out(false).'#'.$w::notification_anchor($event['key'])) ?>"><span class="aw-notification-dot" aria-hidden="true"></span><span class="aw-notification-copy"><strong><?= $e($event['title']) ?></strong><span class="aw-notification-excerpt"><?= $e($event['excerpt']) ?></span><?= academy_timezone::render((int)$event['time'],'%d.%m · %H:%M') ?></span></a></li><?php endforeach; ?>
              </ul><?php else: ?><p class="aw-notification-empty"><?= $e($w::text('empty')) ?></p><?php endif; ?>
              <a class="aw-notification-all" href="<?= $e($w::url('notifications')->out(false)) ?>"><?= $e($w::text('notifications_all')) ?><span aria-hidden="true"> →</span></a>
            </section>
          </div>
          <div class="aw-profile" data-aw-popover>
            <button type="button" class="aw-profile-toggle" data-aw-popover-toggle aria-label="<?= $e($w::text('profile').': '.$name) ?>" aria-expanded="false" aria-controls="<?= $id ?>"><span class="aw-avatar" aria-hidden="true"><?= $e($initials) ?></span><span class="aw-profile-name"><?= $e($name) ?><small><?= $e($w::text($role)) ?></small></span><span class="aw-footer-label"><?= $e($w::text('profile')) ?></span></button>
            <div id="<?= $id ?>" class="aw-profile-menu" hidden>
              <div class="aw-profile-card"><span class="aw-avatar" aria-hidden="true"><?= $e($initials) ?></span><div><strong><?= $e($name) ?></strong><small><?= $e($w::text($role)) ?></small><?php if($signed): ?><small><?= $e($USER->email) ?></small><?php endif; ?></div></div>
              <div class="aw-menu-group"><p><?= $e($w::text('workspace')) ?></p>
                <a href="course.php">Программа обучения</a>
                <?php if($signed): ?><a href="course.php?quickstart=1"><?= $e(academy_quickstart::text('title')) ?></a><?php endif; ?>
                <a href="<?= $e($w::url('profile')->out(false)) ?>"><?= $e($w::text('settings')) ?></a>
                <a href="<?= $e($w::url('notifications')->out(false)) ?>"><?= $e($w::text('notifications')) ?></a>
                <?php if($signed && $role==='candidate'): ?><a href="academy.php"><?= $e($w::text('results')) ?></a><?php endif; ?>
                <?php if($signed && $role==='candidate'): ?><a href="academy_onboarding.php">Учебная анкета</a><?php endif; ?>
                <?php if($signed && ($w::can_review()||$w::manager())): ?><a href="academy_teaching.php">Кабинет тренера</a><?php endif; ?>
                <?php if($signed && $w::can_review()): ?><a href="academy_review.php"><?= $e($w::text('review_queue')) ?></a><?php endif; ?>
                <?php if($signed && $w::manager()): ?><a href="<?= $e($w::url('manage')->out(false)) ?>"><?= $e($w::text('manage')) ?></a><?php endif; ?>
              </div>
              <div class="aw-menu-group"><p>Материалы и поддержка</p><a href="<?= $e($w::url('materials')->out(false)) ?>"><?= $e($w::text('materials')) ?></a><a href="<?= $e($w::url('help')->out(false)) ?>#support">Задать вопрос</a><?php if($signed): ?><a href="<?= $e($w::url('help')->out(false)) ?>#requests">Мои обращения</a><?php endif; ?><a href="<?= $e($w::url('help')->out(false)) ?>"><?= $e($w::text('help')) ?></a></div>
              <?php if($signed): ?><a class="aw-logout" href="<?= $e($logout->out(false)) ?>"><?= $e($w::text('logout')) ?></a><?php else: ?><a href="<?= $e((new \moodle_url('/login/index.php'))->out(false)) ?>"><?= $e($w::text('login')) ?></a><?php endif; ?>
            </div>
          </div>
        </nav>
        <?php return ob_get_clean();
    }
    public static function footer(\core\hook\output\before_footer_html_generation $hook): void {
        if (!self::$active) { return; }
        $w=academy_workspace::class;
        $links='<nav class="aw-useful" aria-label="'.self::e($w::text('materials')).'">'.
            '<a href="'.$w::url('help')->out().'#glossary">'.$w::text('glossary').'</a>'.
            '<a href="'.$w::url('materials')->out().'">'.$w::text('materials').'</a>'.
            '<a href="'.(new \moodle_url('/local/learning_workspace/academy_work.php'))->out().'">'.$w::text('work_guide').'</a></nav>';
        $hook->add_html('<footer class="aw-footer"><strong>'.self::BRAND.'</strong>'.$links.'</footer>');
        self::$active=false;
    }
    public static function after_login(\core_user\hook\after_login_completed $hook): void {
        global $SESSION;
        unset($SESSION->academy_dismissed_notices);
    }
}
