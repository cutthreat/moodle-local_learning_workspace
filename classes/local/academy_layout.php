<?php
namespace local_learning_workspace\local;
defined('MOODLE_INTERNAL') || die();
/** Canonical composition taxonomy; presentation only, never access or completion. */
final class academy_layout {
 public const VERSION='20261012-ux-review2';
 /** Suggest a learner action, not an unreviewed completion claim. Waiting work is not a new task. */
 public static function course_action(array $states): array {
  foreach($states as $id=>$state){if(($state['review']??'')==='practice_rework'){return ['type'=>'lesson','lesson'=>$id];}}
  foreach($states as $id=>$state){if(($state['test']??'')==='failed' && ($state['retry_allowed']??true)){return ['type'=>'lesson','lesson'=>$id];}}
  foreach($states as $id=>$state){if(($state['test']??'')===''){return ['type'=>'lesson','lesson'=>$id];}}
  return ['type'=>'results','lesson'=>null];
 }
 public static function kind(string $script): string {
  return ['course.php'=>'course','lesson_one.php'=>'lesson','lesson_one_preview.php'=>'lesson',
   'academy_day.php'=>'lesson','academy.php'=>'assessment','academy_entry.php'=>'entry',
   'academy_rehearsal.php'=>'rehearsal','academy_work.php'=>'guide','academy_review.php'=>'review',
   'academy_candidate.php'=>'candidate','academy_profile.php'=>'profile','academy_help.php'=>'help',
   'academy_notifications.php'=>'notifications','academy_manage.php'=>'manage',
   'academy_materials.php'=>'library','academy_material.php'=>'viewer',
   'academy_session.php'=>'session','academy_sessions.php'=>'sessions',
   'academy_onboarding.php'=>'onboarding','academy_teaching.php'=>'teaching'][basename($script)]??'utility';
 }
 public static function sections(string $page): array {
  switch($page){
   case 'help':return ['role-start'=>'Как начать','faq'=>'Частые вопросы','glossary'=>'Глоссарий','support'=>'Поддержка','requests'=>'Мои обращения'];
   case 'manage':return ['test-policy'=>'Контрольные тесты','teaching'=>'Группы и занятия','support'=>'Обращения','announcements'=>'Объявления'];
   case 'materials':return ['library-days'=>'Материалы уроков','library-work'=>'Подготовка к работе'];
   default:return [];
  }
 }
 public static function section_nav(string $page): string {
  $sections=self::sections($page);if(!$sections){return '';}
  if($page==='help'&&(!isloggedin()||isguestuser())){unset($sections['requests']);}
  $html='<nav class="al-section-nav" aria-label="Разделы страницы">';
  foreach($sections as $id=>$title){$html.='<a href="#'.s($id).'">'.s($title).'</a>';}
  return $html.'</nav>';
 }
}

