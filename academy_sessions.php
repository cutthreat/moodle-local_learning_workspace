<?php
require_once(__DIR__.'/../../config.php');require_once(__DIR__.'/classes/local/academy_workspace_view.php');
use local_learning_workspace\local\academy_blended as b;use local_learning_workspace\local\academy_workspace_view as v;
require_login();\local_learning_workspace\local\academy_access::require_viewer();
v::start('help','sessions','','','Занятия и группа');$groups=b::groups();$total=b::session_count();$page=min(max(0,optional_param('page',0,PARAM_INT)),max(0,(int)ceil($total/100)-1));$sessions=b::sessions($page);
if(b::trainer()||\local_learning_workspace\local\academy_workspace::manager()){echo '<p><a class="lw-primary" href="academy_teaching.php">Кабинет тренера →</a></p>';}
if(!$groups){echo '<section class="aw-panel"><h2>Группа ещё не назначена</h2><p>Демонстрационная программа доступна. Тренер сообщит, когда появятся расписание и занятия вашей группы.</p><a href="course.php">К программе обучения →</a></section>';v::end();return;}
echo '<p>Здесь — встречи, записи и вопросы только ваших учебных групп. Дата и время показаны в часовом поясе вашего профиля.</p>';
$upcoming=array_filter($sessions,static fn($s)=>$s->status==='planned'&&$s->startsat+$s->duration*60>=time());uasort($upcoming,static fn($a,$z)=>$a->startsat<=>$z->startsat);
$past=array_diff_key($sessions,$upcoming);
foreach(['Ближайшие занятия'=>$upcoming,'Прошедшие занятия и записи'=>$past] as $heading=>$rows){echo '<section class="aw-panel"><h2>'.$heading.'</h2>';if(!$rows){echo '<p>Пока нет занятий в этом разделе.</p>';}
foreach($rows as $s){echo '<article class="ab-session-card"><p class="aw-meta">'.v::e($groups[$s->groupid]->name).' · День '.substr($s->lessonid,1).'</p><h3><a href="'.v::e(b::url('session',['id'=>$s->id])->out(false)).'">'.v::e($s->title).'</a></h3><p>'.\local_learning_workspace\local\academy_timezone::render((int)$s->startsat).' · '.(int)$s->duration.' мин</p><p>'.($s->status==='cancelled'?'Занятие отменено':($s->published?'Запись опубликована':($s->status==='finished'?'Занятие завершено':'Запланировано'))).'</p><a href="'.v::e(b::url('session',['id'=>$s->id])->out(false)).'">Открыть занятие и обсуждение →</a></article>';}
echo '</section>';}
echo b::session_pager($page,$total,'sessions');v::end();
