<?php
namespace local_learning_workspace\local;
defined('MOODLE_INTERNAL') || die();

/** The community draft has no product branding or course-specific copy. */
final class academy_branding {
    public static function text(string $text): string {
        return $text;
    }

    public static function copy(array $content): array {
        return $content;
    }
}
