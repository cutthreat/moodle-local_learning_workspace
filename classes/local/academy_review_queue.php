<?php
namespace local_learning_workspace\local;

defined('MOODLE_INTERNAL') || die();

/** Presentation order for the examiner dashboard; never changes attempt state. */
final class academy_review_queue {
    /** Describe an empty result using only the caller's already authorized candidates. */
    public static function empty_state(array $authorizedrows, string $status, string $search): array {
        if (!$authorizedrows) {
            return ['kind' => 'no_candidates',
                'message' => 'В вашей области доступа пока нет кандидатов.', 'action' => null];
        }
        if ($status === 'pending' && trim($search) === '') {
            return ['kind' => 'no_work',
                'message' => 'Работ, ожидающих проверки, сейчас нет.',
                'action' => ['label' => 'Открыть кандидатов и результаты', 'status' => 'all', 'q' => '']];
        }
        return ['kind' => 'filtered', 'message' => 'По заданным условиям ничего не найдено.',
            'action' => ['label' => 'Показать всех доступных кандидатов', 'status' => 'all', 'q' => '']];
    }

    /** Work-centric queue, restricted to the caller's existing authorized candidate set. */
    public static function work_items(array $rows): array {
        global $DB;
        if (!$rows) { return []; }
        $byid=[];
        foreach ($rows as $row) { $byid[(int)$row->userid]=$row; }
        [$insql,$params]=$DB->get_in_or_equal(array_keys($byid),SQL_PARAMS_NAMED,'candidate');
        $items=$DB->get_records_sql('SELECT a.id,a.userid,a.kind,a.lessonid,a.attemptno,a.timecreated
            FROM {local_lworkspace_acattempt} a WHERE a.userid '.$insql.
            " AND a.kind IN ('day','final') AND NOT EXISTS
            (SELECT 1 FROM {local_lworkspace_acreview} r WHERE r.attemptid=a.id)
            ORDER BY a.timecreated ASC,a.id ASC",$params);
        foreach ($items as $item) {
            $person=$byid[(int)$item->userid];
            $item->firstname=$person->firstname; $item->lastname=$person->lastname;
            $item->username=$person->username ?? '';
        }
        return array_values($items);
    }
    public static function prioritized(array $rows): array {
        $priority = ['awaiting_review' => 0, 'rework' => 1, 'not_started' => 2,
            'ready' => 3, 'not_ready' => 4];
        $rows = array_values($rows);
        usort($rows, static function($left, $right) use ($priority): int {
            $leftstatus = (string)($left->finalstatus ?? '');
            $rightstatus = (string)($right->finalstatus ?? '');
            return (($priority[$leftstatus] ?? 5) <=> ($priority[$rightstatus] ?? 5))
                ?: ((int)($right->timecreated ?? 0) <=> (int)($left->timecreated ?? 0))
                ?: ((int)($left->userid ?? 0) <=> (int)($right->userid ?? 0));
        });
        return $rows;
    }

    public static function awaiting_count(array $rows): int {
        return count(array_filter($rows, static function($row): bool {
            return (string)($row->finalstatus ?? '') === 'awaiting_review';
        }));
    }

    /** Slice an already filtered and prioritized queue without losing its ordering. */
    public static function page(array $rows, int $requestedpage, int $perpage = 25): array {
        $total = count($rows);
        $perpage = max(1, $perpage);
        $pagecount = max(1, (int)ceil($total / $perpage));
        $page = min(max(1, $requestedpage), $pagecount);
        $offset = ($page - 1) * $perpage;
        return [
            'rows' => array_slice($rows, $offset, $perpage),
            'page' => $page,
            'pagecount' => $pagecount,
            'total' => $total,
            'first' => $total ? $offset + 1 : 0,
            'last' => min($total, $offset + $perpage),
        ];
    }
}
