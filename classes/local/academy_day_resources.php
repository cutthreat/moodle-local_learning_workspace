<?php
namespace local_learning_workspace\local;
defined('MOODLE_INTERNAL') || die();

/** Optional cohort meeting/recording links, empty by default and never access gates. */
final class academy_day_resources {
    public static function read(string $lesson): array {
        if (!preg_match('/^L[1-7]$/D',$lesson)) { throw new \InvalidArgumentException('Unknown lesson'); }
        $value=get_config('local_learning_workspace','dayresources_'.$lesson);
        return $value ? (json_decode($value,true) ?: []) : [];
    }
    public static function save(string $lesson,string $topic,string $meeting,string $recording): void {
        academy_workspace::require_manager();
        $before=self::read($lesson);
        $topic=trim(clean_param($topic,PARAM_TEXT));
        if (\core_text::strlen($topic)>160) { throw new \DomainException('Тема встречи: не больше 160 символов.'); }
        foreach ([$meeting,$recording] as $url) {
            if ($url==='') { continue; }
            $parts=parse_url($url);
            if (strlen($url)>2000 || !filter_var($url,FILTER_VALIDATE_URL) || ($parts['scheme']??'')!=='https' ||
                empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) ||
                preg_match('/(?:token|password|secret|access_key)=/i',$parts['query']??'')) {
                throw new \DomainException('Укажите корректную HTTPS-ссылку без учётных данных.');
            }
        }
        $data=['topic'=>$topic,'meeting'=>$meeting,'recording'=>$recording];
        $json=json_encode($data,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        set_config('dayresources_'.$lesson,$json,'local_learning_workspace');
        add_to_config_log('dayresources_'.$lesson,json_encode($before,JSON_UNESCAPED_UNICODE),$json,'local_learning_workspace');
    }
    public static function render(string $lesson): string {
        $data=self::read($lesson);
        if (empty($data['meeting']) && empty($data['recording'])) { return ''; }
        $e=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        $html='<details class="lw-details ac-day-resources"><summary>Встреча с тренером и запись</summary>';
        if (!empty($data['topic'])) { $html.='<p>'.$e($data['topic']).'</p>'; }
        foreach (['meeting'=>'Открыть встречу с тренером','recording'=>'Посмотреть запись'] as $key=>$label) {
            if (!empty($data[$key])) { $html.='<p><a target="_blank" rel="noopener noreferrer" href="'.$e($data[$key]).'">'.$label.' ↗</a></p>'; }
        }
        return $html.'</details>';
    }
}
