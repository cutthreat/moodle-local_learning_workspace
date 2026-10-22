<?php
namespace local_learning_workspace\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

class assessment_review_form extends \moodleform {
    public function definition(): void {
        $mform = $this->_form;
        $options = $this->_customdata['attempts'] ?? [];

        $mform->addElement('select', 'attemptid', get_string('attempttoreview', 'local_learning_workspace'), $options);
        $mform->setType('attemptid', PARAM_INT);
        $mform->addRule('attemptid', null, 'required', null, 'client');

        $mform->addElement('text', 'score', get_string('score', 'local_learning_workspace'));
        $mform->setType('score', PARAM_INT);
        $mform->addRule('score', null, 'required', null, 'client');

        $mform->addElement('textarea', 'feedback', get_string('reviewfeedback', 'local_learning_workspace'), [
            'rows' => 5,
            'cols' => 72,
        ]);
        $mform->setType('feedback', PARAM_TEXT);

        $mform->addElement('hidden', 'idemkey');
        $mform->setType('idemkey', PARAM_ALPHANUMEXT);

        $this->add_action_buttons(false, get_string('savereview', 'local_learning_workspace'));
    }

    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if (empty($this->_customdata['attempts'])) {
            $errors['attemptid'] = get_string('nostagedata', 'local_learning_workspace');
        }
        $score = (int)($data['score'] ?? -1);
        if ($score < 0 || $score > 100) {
            $errors['score'] = get_string('invalidscore', 'local_learning_workspace');
        }
        return $errors;
    }
}
