<?php
defined('MOODLE_INTERNAL') || die();

$callbacks = [
    [
        'hook' => \core\hook\output\before_footer_html_generation::class,
        'callback' => [\local_learning_workspace\local\academy_page_shell::class, 'footer'],
    ],
    [
        'hook' => \core_user\hook\after_login_completed::class,
        'callback' => [\local_learning_workspace\local\academy_page_shell::class, 'after_login'],
    ],
    [
        'hook' => \core\hook\navigation\primary_extend::class,
        'callback' => [\local_learning_workspace\local\academy_navigation::class, 'extend_primary'],
    ],
];
