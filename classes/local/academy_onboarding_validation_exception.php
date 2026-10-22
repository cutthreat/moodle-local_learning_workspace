<?php
namespace local_learning_workspace\local;
defined('MOODLE_INTERNAL') || die();

/** Field-addressable validation only; revision and persistence failures stay global. */
final class academy_onboarding_validation_exception extends \DomainException {
    private array $fielderrors;
    public function __construct(array $errors) {
        $this->fielderrors = $errors;
        parent::__construct(implode(' ', array_unique($errors)));
    }
    public function field_errors(): array { return $this->fielderrors; }
}
