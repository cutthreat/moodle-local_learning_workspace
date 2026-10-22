<?php
namespace local_learning_workspace\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

class admission_decision_form extends \moodleform {
    public function definition(): void {
        $mform = $this->_form;
        $options = $this->_customdata['packets'] ?? [];

        $mform->addElement('select', 'packetid', get_string('packettodecide', 'local_learning_workspace'), $options);
        $mform->setType('packetid', PARAM_INT);
        $mform->addRule('packetid', null, 'required', null, 'client');

        $mform->addElement('static', 'decision', get_string('decision', 'local_learning_workspace'), 'pilot_admitted');
        $mform->addElement('static', 'locallabel', get_string('localtestlabeltitle', 'local_learning_workspace'), get_string('localtestlabel', 'local_learning_workspace'));

        $mform->addElement('hidden', 'idemkey');
        $mform->setType('idemkey', PARAM_ALPHANUMEXT);

        $this->add_action_buttons(false, get_string('approvelocalpilot', 'local_learning_workspace'));
    }

    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if (empty($this->_customdata['packets'])) {
            $errors['packetid'] = get_string('incompletepacketdenied', 'local_learning_workspace');
        }
        return $errors;
    }
}
