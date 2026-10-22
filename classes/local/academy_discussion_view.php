<?php
namespace local_learning_workspace\local;
defined('MOODLE_INTERNAL') || die();
/** Preserve failed input when its parent is hidden or outside the current page. */
final class academy_discussion_view {
    public static function reply_recovery(string $error, int $parentid, string $body, array $threads): string {
        if ($error === '' || $parentid < 1 || (isset($threads[$parentid]) && !empty($threads[$parentid]->active))) {
            return '';
        }
        $e=static fn(string $value): string=>htmlspecialchars($value, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8');
        $text=static fn(string $key): string=>get_string('session_reply_recovery_'.$key,'local_learning_workspace');
        return '<section class="lw-alert ab-reply-recovery" aria-labelledby="reply-recovery-title">'.
            '<h3 id="reply-recovery-title">'.$e($text('title')).'</h3>'.
            '<p id="reply-recovery-help">'.$e($text('help')).'</p>'.
            '<label for="reply-recovery-text">'.$e($text('label')).'</label>'.
            '<textarea id="reply-recovery-text" rows="4" readonly aria-describedby="reply-recovery-help">'.$e($body).'</textarea></section>';
    }
}
