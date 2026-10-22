<?php
namespace local_learning_workspace\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

class assessment_submission_form extends \moodleform {
    public function definition(): void {
        $mform = $this->_form;
        $mform->addElement('textarea', 'evidence', get_string('assessmentevidence', 'local_learning_workspace'), [
            'rows' => 8,
            'cols' => 72,
        ]);
        $mform->setType('evidence', PARAM_TEXT);
        $mform->addRule('evidence', null, 'required', null, 'client');

        $mform->addElement('hidden', 'idemkey');
        $mform->setType('idemkey', PARAM_ALPHANUMEXT);

        $this->add_action_buttons(false, get_string('submitlocalassessment', 'local_learning_workspace'));
    }

    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        $length = \core_text::strlen(trim($data['evidence'] ?? ''));
        if ($length < 20) {
            $errors['evidence'] = get_string('evidencetooshort', 'local_learning_workspace');
        } else if ($length > 20000) {
            $errors['evidence'] = get_string('evidencetoolarge', 'local_learning_workspace');
        }
        return $errors;
    }
}
