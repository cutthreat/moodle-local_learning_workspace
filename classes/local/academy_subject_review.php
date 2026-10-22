<?php
namespace local_learning_workspace\local;
defined('MOODLE_INTERNAL') || die();

/** Generic review metadata for the synthetic sample assessment. */
final class academy_subject_review {
    public static function metadata(): array {
        return [
            'required' => false,
            'status' => 'synthetic_demo',
            'note' => 'Replace with approved subject matter and a qualified reviewer before instructional use.',
        ];
    }
}
