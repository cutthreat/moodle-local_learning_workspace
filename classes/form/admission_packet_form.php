<?php
namespace local_learning_workspace\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

class admission_packet_form extends \moodleform {
    public function definition(): void {
        $mform = $this->_form;
        $options = $this->_customdata['reviews'] ?? [];

        $mform->addElement('select', 'reviewid', get_string('reviewtopacket', 'local_learning_workspace'), $options);
        $mform->setType('reviewid', PARAM_INT);
        $mform->addRule('reviewid', null, 'required', null, 'client');

        $mform->addElement('static', 'fixture', get_string('policyfixture', 'local_learning_workspace'), 'stage_only_placeholder_v0');

        $mform->addElement('hidden', 'idemkey');
        $mform->setType('idemkey', PARAM_ALPHANUMEXT);

        $this->add_action_buttons(false, get_string('preparepacket', 'local_learning_workspace'));
    }

    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if (empty($this->_customdata['reviews'])) {
            $errors['reviewid'] = get_string('incompletepacketdenied', 'local_learning_workspace');
        }
        return $errors;
    }
}
