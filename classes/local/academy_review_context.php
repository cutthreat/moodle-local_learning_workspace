<?php
namespace local_learning_workspace\local;

defined('MOODLE_INTERNAL') || die();

/** Validated presentation-only queue context; no arbitrary URL or state parameters. */
final class academy_review_context {
    public static function normalize(array $input): array {
        $allowed = ['all', 'pending', 'day_pending', 'historical', 'not_started', 'awaiting_review',
            'ready', 'rework', 'not_ready'];
        $status = is_string($input['status'] ?? null) ? $input['status'] : 'all';
        if (!in_array($status, $allowed, true)) {
            $status = 'all';
        }
        $search = is_string($input['q'] ?? null) ? strip_tags($input['q']) : '';
        $search = trim(preg_replace('/[\x00-\x1F\x7F]/u', ' ', $search) ?? '');
        preg_match('/^.{0,100}/us', $search, $characters);
        $search = $characters[0] ?? '';
        $rawpage = $input['page'] ?? 1;
        $page = is_int($rawpage) || is_string($rawpage)
            ? filter_var($rawpage, FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1, 'max_range' => 1000000]]) : false;
        return ['status' => $status, 'q' => $search, 'page' => $page === false ? 1 : $page];
    }

    /** Known queue fields plus an explicitly supplied attempt/success marker only. */
    public static function params(array $input, int $attemptid = 0, bool $saved = false): array {
        $context = self::normalize($input);
        $params = [];
        if ($context['status'] !== 'all' || array_key_exists('status', $input)) {
            $params['status'] = $context['status'];
        }
        if ($context['q'] !== '') {
            $params['q'] = $context['q'];
        }
        if ($context['page'] > 1) {
            $params['page'] = $context['page'];
        }
        if ($attemptid > 0) {
            $params['attempt'] = $attemptid;
            if ($saved) {
                $params['saved'] = 1;
            }
        }
        return $params;
    }
}
