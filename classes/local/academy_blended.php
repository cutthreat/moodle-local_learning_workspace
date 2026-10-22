<?php
namespace local_learning_workspace\local;
defined('MOODLE_INTERNAL') || die();

/** Cohort teaching tools. Core course groups and enrolment remain the access boundary. */
final class academy_blended {
    public static function upgrade(): void {
        global $DB;
        $defs=[
            'local_lworkspace_onboard'=>['userid'=>'int','answers'=>'text','status'=>16,'revision'=>'int','timecreated'=>'int','timemodified'=>'int'],
            'local_lworkspace_session'=>['groupid'=>'int','lessonid'=>8,'title'=>160,'startsat'=>'int','duration'=>'int','timezone'=>64,'meeturl'=>512,'agenda'=>'text','summary'=>'text','recordurl'=>1024,'recordname'=>255,'captionname'=>255,'published'=>'int','recordnotice'=>'int','status'=>16,'revision'=>'int','authorid'=>'int','timecreated'=>'int','timemodified'=>'int'],
            'local_lworkspace_discuss'=>['sessionid'=>'int','parentid'=>'int','userid'=>'int','body'=>'text','active'=>'int','pinned'=>'int','timecreated'=>'int','timemodified'=>'int'],
            'local_lworkspace_attend'=>['sessionid'=>'int','userid'=>'int','status'=>16,'watched'=>'int','note'=>'text','actorid'=>'int','timemodified'=>'int'],
            'local_lworkspace_bevent'=>['userid'=>'int','sessionid'=>'int','eventkey'=>64,'title'=>160,'body'=>'text','timecreated'=>'int'],
        ];
        $manager=$DB->get_manager();
        foreach($defs as $name=>$fields){
            $table=new \xmldb_table($name);if($manager->table_exists($table)){continue;}
            $table->add_field('id',XMLDB_TYPE_INTEGER,'10',null,XMLDB_NOTNULL,XMLDB_SEQUENCE);
            foreach($fields as $field=>$type){
                if($type==='int'){$table->add_field($field,XMLDB_TYPE_INTEGER,'10',null,XMLDB_NOTNULL,null,0);}
                elseif($type==='text'){$table->add_field($field,XMLDB_TYPE_TEXT,null,null,XMLDB_NOTNULL);}
                else{$table->add_field($field,XMLDB_TYPE_CHAR,(string)$type,null,XMLDB_NOTNULL);}
            }
            $table->add_key('primary',XMLDB_KEY_PRIMARY,['id']);
            if($name==='local_lworkspace_onboard'){$table->add_index('user_uix',XMLDB_INDEX_UNIQUE,['userid']);}
            if($name==='local_lworkspace_attend'){$table->add_index('session_user_uix',XMLDB_INDEX_UNIQUE,['sessionid','userid']);}
            if($name==='local_lworkspace_bevent'){$table->add_index('event_uix',XMLDB_INDEX_UNIQUE,['eventkey']);$table->add_index('user_ix',XMLDB_INDEX_NOTUNIQUE,['userid']);}
            if($name==='local_lworkspace_session'){$table->add_index('group_start_ix',XMLDB_INDEX_NOTUNIQUE,['groupid','startsat']);}
            if($name==='local_lworkspace_discuss'){$table->add_index('session_parent_ix',XMLDB_INDEX_NOTUNIQUE,['sessionid','parentid']);}
            $manager->create_table($table);
        }
    }
    public static function ready(): bool { global $DB;return $DB->get_manager()->table_exists('local_lworkspace_session'); }
    public static function courseid(): int { $context=academy_access::course_context();if(!$context){throw new \DomainException('Учебный курс недоступен.');}return (int)$context->instanceid; }
    public static function trainer(int $userid=0): bool { global $USER;return has_capability('local/learning_workspace:reviewassessment',\context_system::instance(),$userid?:$USER->id); }
    public static function groups(int $userid=0): array {
        global $DB,$USER;$userid=$userid?:$USER->id;
        if(!self::ready()){return [];}
        $all=$DB->get_records('groups',['courseid'=>self::courseid()],'name ASC');
        if(academy_workspace::manager() && (int)$userid===(int)$USER->id){return $all;}
        if(!academy_access::is_candidate($userid) && !self::trainer($userid)){return [];}
        return array_filter($all,static fn($g)=>$DB->record_exists('groups_members',['groupid'=>$g->id,'userid'=>$userid]));
    }
    public static function require_group(int $id,bool $write=false): object {
        $groups=self::groups();if(!isset($groups[$id]) || ($write && !self::trainer() && !academy_workspace::manager())){throw new \DomainException('Эта учебная группа недоступна в вашей роли.');}return $groups[$id];
    }
    public static function create_group(string $name): int {
        global $CFG;academy_workspace::require_manager();require_once($CFG->dirroot.'/group/lib.php');
        $name=academy_workspace::clean_text($name,120);
        return groups_create_group((object)['courseid'=>self::courseid(),'name'=>$name,'description'=>'Демо-группа','descriptionformat'=>FORMAT_PLAIN]);
    }
    public static function membership(int $groupid,string $username,bool $add): void {
        global $DB,$CFG;academy_workspace::require_manager();self::require_group($groupid);require_once($CFG->dirroot.'/group/lib.php');
        $user=$DB->get_record('user',['username'=>trim($username),'deleted'=>0,'suspended'=>0]);
        if(!$user || (!academy_access::is_candidate((int)$user->id) && !self::trainer((int)$user->id))){throw new \DomainException('Нужен логин приглашённого учащегося или тренера Академии.');}
        if($add){
            if(self::trainer((int)$user->id)&&!is_enrolled(academy_access::course_context(),$user,'',true)){
                // Existing trainers need course participation for Moodle-native groups.
                // Role 0 deliberately grants no new role or site capability.
                $manual=enrol_get_plugin('manual');$instances=enrol_get_instances(self::courseid(),true);$instance=null;
                foreach($instances as $i){if($i->enrol==='manual'){$instance=$i;break;}}
                if(!$manual||!$instance){throw new \DomainException('Сначала зачислите тренера в курс через управление Moodle.');}
                $manual->enrol_user($instance,(int)$user->id,0,time(),0,ENROL_USER_ACTIVE);
            }
            if(!groups_add_member($groupid,(int)$user->id)&&!groups_is_member($groupid,(int)$user->id)){throw new \DomainException('Не удалось добавить участника в группу. Проверьте зачисление в курс.');}
        }else{groups_remove_member($groupid,(int)$user->id);}
    }
    public static function members(int $groupid): array {
        global $DB;self::require_group($groupid,true);
        return $DB->get_records_sql('SELECT u.id,u.firstname,u.lastname,u.username FROM {user} u JOIN {groups_members} m ON m.userid=u.id WHERE m.groupid=:g AND u.deleted=0 AND u.suspended=0 ORDER BY u.lastname,u.firstname',['g'=>$groupid]);
    }
    public static function options(): array {
        return [
            'experience'=>['none'=>'Пока нет','under6'=>'Меньше 6 месяцев','6to24'=>'От 6 месяцев до 2 лет','over24'=>'Больше 2 лет'],
            'channel'=>['support'=>'Поддержка','sales'=>'Продажи','consulting'=>'Консультации','other'=>'Другое','none'=>'Опыта пока нет'],
            'area'=>['writing'=>'Работа с текстом','review'=>'Проверка работ','workflow'=>'Организация процесса','beginner'=>'Начинаю знакомство с процессом'],
            'language'=>['ru'=>'Русский','en'=>'Английский','es'=>'Испанский','de'=>'Немецкий','fr'=>'Французский','pl'=>'Польский','other'=>'Другой'],
            'level'=>['basic'=>'Базовый','working'=>'Могу вести рабочую переписку','fluent'=>'Свободный'],
            'days'=>['mon'=>'Пн','tue'=>'Вт','wed'=>'Ср','thu'=>'Чт','fri'=>'Пт','sat'=>'Сб','sun'=>'Вс'],
            'device'=>['computer'=>'Компьютер или ноутбук','tablet'=>'Планшет','phone'=>'Смартфон'],
            'meet'=>['ready'=>'Могу подключаться, микрофон и интернет работают','help'=>'Нужна помощь с подключением'],
        ];
    }
    public static function onboarding(int $userid): ?object {
        global $DB,$USER;
        if($userid!==(int)$USER->id && !academy_workspace::manager()){
            if(!self::trainer() || !array_intersect(array_keys(self::groups()),array_keys(self::groups($userid)))){throw new \DomainException('Анкета доступна только кандидату, его тренеру и управляющему.');}
        }
        return $DB->get_record('local_lworkspace_onboard',['userid'=>$userid])?:null;
    }
    public static function save_onboarding(array $input,int $revision,bool $complete): object {
        global $DB,$USER;academy_access::require_candidate();
        require_once(__DIR__.'/academy_onboarding_form.php');
        require_once(__DIR__.'/academy_onboarding_validation_exception.php');
        $validation=academy_onboarding_form::validate($input,$complete,self::options(),\core_date::get_list_of_timezones());
        $data=$validation['data'];
        if($validation['errors']){throw new academy_onboarding_validation_exception($validation['errors']);}
        $lock=\core\lock\lock_config::get_lock_factory('local_learning_workspace')->get_lock('onboard-'.$USER->id,10);if(!$lock){throw new \DomainException('Анкета сейчас сохраняется. Попробуйте ещё раз.');}
        try{$old=self::onboarding((int)$USER->id);if((int)($old->revision??0)!==$revision){throw new \DomainException('Анкета изменена в другой вкладке. Откройте актуальную версию перед сохранением.');}
            $r=(object)['userid'=>(int)$USER->id,'answers'=>json_encode($data,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),'status'=>$complete?'complete':'draft','revision'=>$revision+1,'timecreated'=>$old->timecreated??time(),'timemodified'=>time()];
            $tx=$DB->start_delegated_transaction();
            if($old){$r->id=$old->id;$DB->update_record('local_lworkspace_onboard',$r);}else{$r->id=$DB->insert_record('local_lworkspace_onboard',$r);}
            if($complete){academy_workspace::save_profile(['firstname'=>$USER->firstname,'lastname'=>$USER->lastname,'timezone'=>$data['timezone']]);}
            $tx->allow_commit();return $r;
        }finally{$lock->release();}
    }
    public static function session(int $id,bool $write=false): object { global $DB;$s=$DB->get_record('local_lworkspace_session',['id'=>$id],'*',MUST_EXIST);self::require_group((int)$s->groupid,$write);return $s; }
    public static function sessions(int $page=0): array {
        global $DB;$groups=array_keys(self::groups());if(!$groups){return [];}
        [$sql,$params]=$DB->get_in_or_equal($groups,SQL_PARAMS_NAMED);
        return $DB->get_records_select('local_lworkspace_session','groupid '.$sql,$params,'startsat DESC,id DESC','*',max(0,$page)*100,100);
    }
    public static function session_count(): int {global $DB;$groups=array_keys(self::groups());if(!$groups){return 0;}[$sql,$params]=$DB->get_in_or_equal($groups,SQL_PARAMS_NAMED);return $DB->count_records_select('local_lworkspace_session','groupid '.$sql,$params);}
    public static function next_session(): ?object {global $DB;$groups=array_keys(self::groups());if(!$groups){return null;}[$sql,$params]=$DB->get_in_or_equal($groups,SQL_PARAMS_NAMED);$params['now']=time();$rows=$DB->get_records_select('local_lworkspace_session',"groupid $sql AND status='planned' AND startsat+duration*60>=:now",$params,'startsat ASC,id ASC','*',0,1);return reset($rows)?:null;}
    public static function session_pager(int $page,int $total,string $target): string {
        if($total<=100){return '';}$html='<nav class="ab-inline" aria-label="Страницы занятий">';if($page){$html.='<a href="'.s(self::url($target,['page'=>$page-1])->out(false)).'">← Новее</a>';}$html.='<span>Страница '.($page+1).' из '.(int)ceil($total/100).'</span>';if(($page+1)*100<$total){$html.='<a href="'.s(self::url($target,['page'=>$page+1])->out(false)).'">Ранее →</a>';}return $html.'</nav>';
    }
    public static function url(string $page,array $params=[]): \moodle_url { if(!in_array($page,['sessions','session','teaching','onboarding','recording','calendar'],true)){throw new \InvalidArgumentException('Unknown teaching page');}return new \moodle_url('/local/learning_workspace/academy_'.$page.'.php',$params); }
    public static function denied(): void {
        http_response_code(403);academy_workspace_view::start('help','sessions','','','Раздел недоступен');
        echo '<section class="aw-panel"><p>Это занятие или анкета недоступны вашему аккаунту. Если вас добавили в другой поток, уточните назначение группы у тренера.</p><a href="academy_sessions.php">К занятиям своей группы →</a></section>';academy_workspace_view::end();
    }
    public static function validate_link(string $url,string $kind): string {
        $url=trim($url);if($url===''){return '';}$p=parse_url($url);
        if(strlen($url)>1000||!filter_var($url,FILTER_VALIDATE_URL)||($p['scheme']??'')!=='https'||isset($p['user'])||isset($p['pass'])||isset($p['port'])||isset($p['fragment'])){throw new \DomainException('Нужна HTTPS-ссылка Google без пароля, токена и нестандартного порта.');}
        if($kind==='meet'){$ok=($p['host']??'')==='meet.google.com'&&preg_match('~^/[a-z]{3}-[a-z]{4}-[a-z]{3}/?$~D',$p['path']??'');}
        else{$ok=($p['host']??'')==='drive.google.com'&&preg_match('~^/file/d/[A-Za-z0-9_-]+/(view|preview)$~D',$p['path']??'');}
        if(!$ok||preg_match('/(?:token|password|secret|access_key)=/i',$p['query']??'')){throw new \DomainException($kind==='meet'?'Вставьте ссылку встречи вида https://meet.google.com/abc-defg-hij.':'Вставьте ссылку Google Drive вида https://drive.google.com/file/d/ID/view.');}return $url;
    }
    public static function save_session(int $id,array $data,int $revision): object {
        global $DB,$USER;$old=$id?self::session($id,true):null;$groupid=(int)($data['groupid']??0);self::require_group($groupid,true);
        if($old&&(int)$old->groupid!==$groupid){throw new \DomainException('Нельзя переносить занятие с обсуждением в другую группу. Создайте новое занятие.');}
        $title=academy_workspace::clean_text((string)($data['title']??''),160);$lesson=(string)($data['lessonid']??'');if(!preg_match('/^L[1-7]$/D',$lesson)){throw new \DomainException('Выберите день курса.');}
        $zone=(string)($data['timezone']??'');$zones=\core_date::get_list_of_timezones();if(!isset($zones[$zone])){throw new \DomainException('Выберите часовой пояс занятия.');}
        $local=(string)($data['startlocal']??'');$dt=\DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$local,new \DateTimeZone($zone));if(!$dt||$dt->format('Y-m-d\TH:i')!==$local){throw new \DomainException('Проверьте дату и время занятия.');}
        $duration=(int)($data['duration']??0);if($duration<10||$duration>360){throw new \DomainException('Продолжительность: от 10 до 360 минут.');}
        $status=(string)($data['status']??'planned');if(!in_array($status,['planned','finished','cancelled'],true)){throw new \DomainException('Проверьте состояние занятия.');}
        $meet=self::validate_link((string)($data['meeturl']??''),'meet');$record=self::validate_link((string)($data['recordurl']??''),'record');
        $agenda=trim(clean_param((string)($data['agenda']??''),PARAM_TEXT));$summary=trim(clean_param((string)($data['summary']??''),PARAM_TEXT));if(\core_text::strlen($agenda)>4000||\core_text::strlen($summary)>8000){throw new \DomainException('Сократите план или конспект занятия.');}
        $published=!empty($data['published']);$notice=!empty($data['recordnotice']);
        if($published&&(!$notice||\core_text::strlen($summary)<10||(!$record&&empty($old->recordname)))){throw new \DomainException('Для публикации записи нужны файл или ссылка, конспект и подтверждение уведомления участников.');}
        $lock=\core\lock\lock_config::get_lock_factory('local_learning_workspace')->get_lock('session-'.($id?:'new-'.$groupid.'-'.$USER->id),10);if(!$lock){throw new \DomainException('Занятие сейчас обновляется.');}
        try{if($id){$old=self::session($id,true);if((int)$old->revision!==$revision){throw new \DomainException('Занятие изменено в другой вкладке. Обновите страницу перед сохранением.');}}
            $r=(object)['groupid'=>$groupid,'lessonid'=>$lesson,'title'=>$title,'startsat'=>$dt->getTimestamp(),'duration'=>$duration,'timezone'=>$zone,'meeturl'=>$meet,'agenda'=>$agenda,'summary'=>$summary,'recordurl'=>$record,'recordname'=>$old->recordname??'','captionname'=>$old->captionname??'','published'=>(int)$published,'recordnotice'=>(int)$notice,'status'=>$status,'revision'=>$revision+1,'authorid'=>$old->authorid??(int)$USER->id,'timecreated'=>$old->timecreated??time(),'timemodified'=>time()];
            if($id){$r->id=$id;$DB->update_record('local_lworkspace_session',$r);}else{$r->id=$DB->insert_record('local_lworkspace_session',$r);}
            self::notify($r,'session-'.$r->revision,'Занятие: '.$title,$status==='cancelled'?'Занятие отменено. Посмотрите обновлённую информацию.':'Обновлены время, материалы или запись занятия.');return $r;
        }finally{$lock->release();}
    }
    /** Native private file area. All downloads re-check current course-group access. */
    public static function upload(int $id,array $file,bool $caption=false): void {
        global $DB;$s=self::session($id,true);
        if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||!is_uploaded_file($file['tmp_name']??'')){throw new \DomainException('Файл не загружен. Проверьте размер и повторите выбор.');}
        self::attach_file($s,(string)$file['tmp_name'],(string)$file['name'],$caption);
    }
    public static function attach_file(object $session,string $path,string $name,bool $caption=false): void {
        global $DB;self::session((int)$session->id,true);$name=clean_param($name,PARAM_FILE);$size=filesize($path);$mime=(new \finfo(FILEINFO_MIME_TYPE))->file($path);
        if(!$size||$size>($caption?2097152:self::upload_limit())){throw new \DomainException('Файл превышает допустимый размер. Для больших записей используйте ссылку Google Drive.');}
        if($caption){if(strtolower(pathinfo($name,PATHINFO_EXTENSION))!=='vtt'||!str_starts_with((string)file_get_contents($path,false,null,0,16),'WEBVTT')){throw new \DomainException('Субтитры должны быть файлом WebVTT.');}$mime='text/vtt';}
        elseif(!in_array($mime,['video/mp4','video/webm'],true)||!in_array(strtolower(pathinfo($name,PATHINFO_EXTENSION)),['mp4','webm'],true)){throw new \DomainException('Нужен видеофайл MP4 или WebM.');}
        $lock=\core\lock\lock_config::get_lock_factory('local_learning_workspace')->get_lock('session-'.$session->id,10);if(!$lock){throw new \DomainException('Занятие сейчас обновляется. Повторите загрузку позже.');}
        try{$fs=get_file_storage();$area=$caption?'sessioncaption':'sessionrecord';$filename=bin2hex(random_bytes(8)).'-'.$name;
        $f=$fs->create_file_from_pathname(['contextid'=>\context_system::instance()->id,'component'=>'local_learning_workspace','filearea'=>$area,'itemid'=>$session->id,'filepath'=>'/','filename'=>$filename,'mimetype'=>$mime],$path);
        // Old private files are retained for rollback; only the published pointer changes.
        $fresh=self::session((int)$session->id,true);$field=$caption?'captionname':'recordname';$fresh->$field=$filename;$fresh->published=0;$fresh->revision++;$fresh->timemodified=time();$DB->update_record('local_lworkspace_session',$fresh);
        }finally{$lock->release();}
    }
    public static function upload_limit(): int { global $CFG;return (int)get_max_upload_file_size($CFG->maxbytes,209715200); }
    public static function recording(int $id,bool $caption=false): \stored_file {
        $s=self::session($id);$staff=self::trainer()||academy_workspace::manager();if(!$s->published&&!$staff){throw new \DomainException('Запись пока не опубликована тренером.');}
        $name=$caption?$s->captionname:$s->recordname;if(!$name){throw new \DomainException('Файл записи недоступен.');}
        $file=get_file_storage()->get_file(\context_system::instance()->id,'local_learning_workspace',$caption?'sessioncaption':'sessionrecord',$id,'/',$name);if(!$file||$file->is_directory()){throw new \DomainException('Файл записи недоступен.');}return $file;
    }
    public static function attendance(int $id,int $userid,string $status,string $note='',bool $watched=false): void {
        global $DB,$USER;$s=self::session($id);$staff=self::trainer()||academy_workspace::manager();
        if(!$DB->record_exists('groups_members',['groupid'=>$s->groupid,'userid'=>$userid])||!academy_access::is_candidate($userid)){throw new \DomainException('Учащийся не состоит в этой группе.');}
        if(!$staff&&($userid!==(int)$USER->id||!$watched||!$s->published)){throw new \DomainException('Посещение отмечает тренер; просмотр записи можно отметить только за себя.');}
        if(!in_array($status,['unknown','attended','missed','catchup'],true)||\core_text::strlen($note)>600){throw new \DomainException('Проверьте отметку посещения.');}
        $lock=\core\lock\lock_config::get_lock_factory('local_learning_workspace')->get_lock('attendance-'.$id.'-'.$userid,10);if(!$lock){throw new \DomainException('Отметка сейчас обновляется. Попробуйте ещё раз.');}
        try{$key=['sessionid'=>$id,'userid'=>$userid];$old=$DB->get_record('local_lworkspace_attend',$key);
        $r=(object)($key+['status'=>$staff?$status:($old->status??'unknown'),'watched'=>(int)($watched||!empty($old->watched)),'note'=>$staff?clean_param($note,PARAM_TEXT):($old->note??''),'actorid'=>(int)$USER->id,'timemodified'=>time()]);if($old){$r->id=$old->id;$DB->update_record('local_lworkspace_attend',$r);}else{$DB->insert_record('local_lworkspace_attend',$r);}
        }finally{$lock->release();}
    }
    public static function post(int $id,int $parentid,string $body): int {
        global $DB,$USER;$s=self::session($id);$body=academy_workspace::clean_text($body,4000);
        if($parentid){$parent=$DB->get_record('local_lworkspace_discuss',['id'=>$parentid,'sessionid'=>$id,'parentid'=>0,'active'=>1]);if(!$parent){throw new \DomainException('Вопрос недоступен для ответа.');}}
        $lock=\core\lock\lock_config::get_lock_factory('local_learning_workspace')->get_lock('discussion-'.$USER->id,10);if(!$lock){throw new \DomainException('Сообщение уже отправляется.');}
        try{$last=$DB->get_records('local_lworkspace_discuss',['userid'=>$USER->id],'id DESC','*',0,1);$last=reset($last);if($last&&time()-(int)$last->timecreated<10){if($last->body===$body&&(int)$last->sessionid===$id&&(int)$last->parentid===$parentid){return (int)$last->id;}throw new \DomainException('Подождите несколько секунд перед следующим сообщением.');}
            $r=(object)['sessionid'=>$id,'parentid'=>$parentid,'userid'=>(int)$USER->id,'body'=>$body,'active'=>1,'pinned'=>0,'timecreated'=>time(),'timemodified'=>time()];$r->id=$DB->insert_record('local_lworkspace_discuss',$r);
            self::notify($s,'post-'.$r->id,$parentid?'Ответ в обсуждении занятия':'Вопрос к занятию','Откройте обсуждение своей группы, чтобы прочитать сообщение.',$parentid?(int)$parent->userid:0);return (int)$r->id;
        }finally{$lock->release();}
    }
    public static function moderate(int $postid,string $action): void {
        global $DB,$USER;$p=$DB->get_record('local_lworkspace_discuss',['id'=>$postid],'*',MUST_EXIST);self::session((int)$p->sessionid);$staff=self::trainer()||academy_workspace::manager();
        if(!$staff&&((int)$p->userid!==(int)$USER->id||$action!=='archive')){throw new \DomainException('Изменять это сообщение нельзя.');}
        if($action==='pin'&&$staff){$p->pinned=(int)!$p->pinned;}elseif($action==='archive'){$p->active=0;}elseif($action==='restore'&&$staff){$p->active=1;}else{throw new \DomainException('Неизвестное действие.');}$p->timemodified=time();$DB->update_record('local_lworkspace_discuss',$p);
    }
    private static function notify(object $s,string $event,string $title,string $body,int $recipient=0): void {
        global $DB,$USER;$users=$DB->get_records('groups_members',['groupid'=>$s->groupid]);foreach($users as $m){$uid=(int)$m->userid;if($uid===(int)$USER->id){continue;}if(str_starts_with($event,'post-')&&$uid!==$recipient&&!self::trainer($uid)){continue;}$key=hash('sha256',$s->id.'-'.$event.'-'.$uid);if($DB->record_exists('local_lworkspace_bevent',['eventkey'=>$key])){continue;}$DB->insert_record('local_lworkspace_bevent',(object)['userid'=>$uid,'sessionid'=>$s->id,'eventkey'=>$key,'title'=>\core_text::substr($title,0,160),'body'=>\core_text::substr($body,0,800),'timecreated'=>time()]);}
    }
    public static function feed(): array {
        global $DB,$USER;if(!self::ready()){return [];}$groups=self::groups();$result=[];
        foreach($DB->get_records('local_lworkspace_bevent',['userid'=>$USER->id],'id DESC','*',0,30) as $event){$session=$DB->get_record('local_lworkspace_session',['id'=>$event->sessionid]);if(!$session||!isset($groups[$session->groupid])){continue;}$result[]=['key'=>'teaching.'.$event->id,'title'=>$event->title,'body'=>$event->body,'time'=>(int)$event->timecreated,'url'=>self::url('session',['id'=>$session->id])->out(false)];}return $result;
    }
    /** Progressive disclosure: a lesson shows only its own group resources. */
    public static function lesson_links(string $lesson): string {
        if(!function_exists('get_file_storage')||!self::ready()){return '';}
        global $DB;$groups=array_keys(self::groups());if(!$groups){return '';}
        [$sql,$params]=$DB->get_in_or_equal($groups,SQL_PARAMS_NAMED);$params['lesson']=$lesson;$sessions=$DB->get_records_select('local_lworkspace_session',"groupid $sql AND lessonid=:lesson AND status<>'cancelled'",$params,'startsat DESC,id DESC','*',0,10);if(!$sessions){return '';}
        $html='<details class="lw-details ab-lesson-links"><summary>Занятие с тренером, запись и вопросы</summary><ul>';
        foreach($sessions as $s){$html.='<li><a href="'.s(self::url('session',['id'=>$s->id])->out(false)).'">'.s($s->title).'</a> · '.s(userdate($s->startsat,'%d.%m · %H:%M')).($s->published?' · запись доступна':'').'</li>';}
        return $html.'</ul><a href="'.s(self::url('sessions')->out(false)).'">Все занятия и записи →</a></details>';
    }
}
