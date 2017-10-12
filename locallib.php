<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

class enrol_prerequisite_form extends moodleform {
    protected $instance;
    protected $toomany = false;

    /**
     * Overriding this function to get unique form id for multiple self enrolments.
     *
     * @return string form identifier
     */
    protected function get_form_identifier() {
        $formid = $this->_customdata->id.'_'.get_class($this);
        return $formid;
    }

    public function definition() {
        global $USER, $OUTPUT, $CFG;
        $mform = $this->_form;
        $instance = $this->_customdata;
        $this->instance = $instance;
        $plugin = enrol_get_plugin('self');

        $heading = $plugin->get_instance_name($instance);
        $mform->addElement('header', 'selfheader', $heading);

        if ($instance->password) {
            // Change the id of self enrolment key input as there can be multiple self enrolment methods.
            $mform->addElement('password', 'enrolpassword', get_string('password', 'enrol_self'),
                array('id' => 'enrolpassword_'.$instance->id));
        }


        $this->add_action_buttons(false, get_string('enrolme', 'enrol_self'));

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->setDefault('id', $instance->courseid);

        $mform->addElement('hidden', 'instance');
        $mform->setType('instance', PARAM_INT);
        $mform->setDefault('instance', $instance->id);
    }

    // public function validation($data, $files) {
    //     global $DB, $CFG;
    //
    //     $errors = parent::validation($data, $files);
    //     $instance = $this->instance;
    //
    //     if ($this->toomany) {
    //         $errors['notice'] = get_string('error');
    //         return $errors;
    //     }
    //
    //     if ($instance->password) {
    //         if ($data['enrolpassword'] !== $instance->password) {
    //             if ($instance->customint1) {
    //                 // Check group enrolment key.
    //                 if (!enrol_self_check_group_enrolment_key($instance->courseid, $data['enrolpassword'])) {
    //                     // We can not hint because there are probably multiple passwords.
    //                     $errors['enrolpassword'] = get_string('passwordinvalid', 'enrol_self');
    //                 }
    //
    //             } else {
    //                 $plugin = enrol_get_plugin('self');
    //                 if ($plugin->get_config('showhint')) {
    //                     $hint = core_text::substr($instance->password, 0, 1);
    //                     $errors['enrolpassword'] = get_string('passwordinvalidhint', 'enrol_self', $hint);
    //                 } else {
    //                     $errors['enrolpassword'] = get_string('passwordinvalid', 'enrol_self');
    //                 }
    //             }
    //         }
    //     }
    //
    //     return $errors;
    // }
}
