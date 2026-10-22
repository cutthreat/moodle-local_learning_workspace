<?php
namespace local_learning_workspace\local;
defined('MOODLE_INTERNAL') || die();
require_once(__DIR__.'/academy_timezone.php');

/** Shared, escaped event presentation; callers retain ownership/capability checks. */
final class academy_result_view {
    public static function text(string $key, $a = null): string {
        return get_string_manager()->get_string('result_'.$key, 'local_learning_workspace', $a, 'ru');
    }
    /** A day has two independent outcomes; accepted practice never masks a failed test. */
    public static function day_summary(object $attempt, ?object $review): array {
        $passed = $attempt->status === 'passed';
        $decision = $review->decision ?? '';
        if ($decision === 'practice_feedback') {
            return ['title'=>self::text('feedback'), 'tone'=>'is-warn', 'next'=>self::text('feedback_next')];
        }
        if ($decision === 'practice_rework') {
            return ['title'=>self::text('rework'), 'tone'=>'is-warn', 'next'=>self::text('rework_next')];
        }
        if ($decision === 'practice_accepted') {
            return $passed
                ? ['title'=>self::text('accepted'), 'tone'=>'', 'next'=>self::text('accepted_next')]
                : ['title'=>self::text('mixed'), 'tone'=>'is-warn', 'next'=>self::text('mixed_next')];
        }
        if ($attempt->status === 'draft_unverified') {
            return ['title'=>self::text('unscored'), 'tone'=>'is-warn', 'next'=>self::text('unscored_next')];
        }
        return ['title'=>self::text($passed ? 'pending_passed' : 'pending_failed'),
            'tone'=>'is-warn', 'next'=>$passed
                ? self::text('pending_passed_next') : self::text('pending_failed_next')];
    }

    public static function state(?object $attempt, ?object $review): string {
        if (!$attempt) { return 'Ещё не отправлено'; }
        if (!$review) { return 'Ожидает проверки тренером'; }
        return ['ready'=>'Готов по экзамену','rework'=>'Нужна доработка','not_ready'=>'Пока не готов',
            'practice_accepted'=>$attempt->status==='passed'?'Тест и практика приняты':'Практика принята · нужен разбор теста',
            'practice_rework'=>'Доработать открытый ответ','practice_feedback'=>'Обратная связь без зачёта'][$review->decision] ?? 'Проверено';
    }

    public static function timeline(object $attempt, ?object $review): string {
        global $DB;
        $e = static fn($v): string => htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        $html='<small class="ac-event-meta" data-attempt-id="'.(int)$attempt->id.'">Попытка №'.(int)$attempt->attemptno.
            ' · Отправлено '.academy_timezone::render((int)$attempt->timecreated);
        if ($review) {
            static $names=[];
            $id=(int)$review->reviewerid;
            if (!isset($names[$id])) {
                $user=$DB->get_record('user',['id'=>$id],'id,firstname,lastname');
                $names[$id]=$user ? fullname($user) : 'Тренер';
            }
            $html.='<br>Проверено '.academy_timezone::render((int)$review->timecreated).' · '.$e($names[$id]);
        } else { $html.='<br>Тренер ещё не проверил'; }
        return $html.'</small>';
    }
}
