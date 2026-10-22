<?php
namespace local_learning_workspace\local;
defined('MOODLE_INTERNAL') || die();

/** Presentation only: Moodle owns the selected timezone and stored UTC timestamps. */
final class academy_timezone {
    public static function label(?int $timestamp = null, ?\DateTimeZone $zone = null): string {
        $zone = $zone ?? \core_date::get_user_timezone_object();
        $at = (new \DateTimeImmutable('@'.($timestamp ?? time())))->setTimezone($zone);
        return $zone->getName().' · UTC'.$at->format('P');
    }

    public static function format(int $timestamp, string $format = '%d.%m.%Y · %H:%M'): string {
        $zone = \core_date::get_user_timezone_object();
        $at = (new \DateTimeImmutable('@'.$timestamp))->setTimezone($zone);
        return userdate($timestamp, $format).' · UTC'.$at->format('P');
    }

    public static function render(int $timestamp, string $format = '%d.%m.%Y · %H:%M', string $class = ''): string {
        $e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return '<time datetime="'.gmdate('c', $timestamp).'" title="'.$e(self::label($timestamp)).'"'.
            ($class !== '' ? ' class="'.$e($class).'"' : '').'>'.$e(self::format($timestamp, $format)).'</time>';
    }
}
