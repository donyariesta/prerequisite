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

/**
 * The enrol plugin prerequisite is defined here.
 *
 * @package     enrol_prerequisite
 * @copyright   2017 Dony Ariesta <donyariesta.rin@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// The base class 'enrol_plugin' can be found at lib/enrollib.php. Override
// methods as necessary.

/**
 * Class enrol_prerequisite_plugin.
 */
class enrol_prerequisite_plugin extends enrol_plugin {

    /**
    * Does this plugin allow manual enrolments?
    * @param stdClass $instance Course enrol instance.
    * @return bool True means user with 'enrol/prerequisite:enrol' may enrol others freely, false means nobody may add more enrolments manually.
    */
    public function allow_enrol(stdClass $instance) {
        return true;
    }

    /**
    * This function allow manual unenrolment of all users
    * @param stdClass $instance Course enrol instance.
    * @return bool True means user with 'enrol/prerequisite:unenrol' may unenrol others freely, false means nobody may touch user_enrolments.
    */
    public function allow_unenrol(stdClass $instance) {
        return true;
    }

    /**
    * Use the standard interface for adding/editing the form.
    *
    * @since Moodle 3.1.
    * @return bool.
    */
    public function use_standard_editing_ui() {
        return true;
    }

    private static function get_aggregation_methods() {
        return array(
            COMPLETION_AGGREGATION_ALL => get_string('all'),
            COMPLETION_AGGREGATION_ANY => get_string('any', 'completion'),
        );
    }

    private function get_aggregation_method($criteriatype = null, $course_id) {
        $params = array(
            'course'        => $course_id,
            'criteriatype'  => $criteriatype
        );

        $aggregation = new completion_aggregation($params);

        if (!$aggregation->id) {
            $aggregation->method = COMPLETION_AGGREGATION_ALL;
        }

        return $aggregation->method;
    }

    /**
     * Gets an array of the user enrolment actions.
     *
     * @param course_enrolment_manager $manager
     * @param stdClass $ue A user enrolment object
     * @return array An array of user_enrolment_actions
     */
    public function get_user_enrolment_actions(course_enrolment_manager $manager, $ue) {
        $actions = array();
        $context = $manager->get_context();
        $instance = $ue->enrolmentinstance;
        $params = $manager->get_moodlepage()->url->params();
        $params['ue'] = $ue->id;
        if ($this->allow_unenrol($instance) && has_capability("enrol/self:unenrol", $context)) {
            $url = new moodle_url('/enrol/unenroluser.php', $params);
            $strunenrol = get_string('unenrol', 'enrol');
            $actions[] = new user_enrolment_action(new pix_icon('t/delete', $strunenrol),
                $strunenrol, $url, array('class' => 'unenrollink', 'rel' => $ue->id));
        }

        return $actions;
    }

    /**
     * Adds form elements to add/edit instance form.
     *
     * @since Moodle 3.1.
     * @param object $instance Enrol instance or null if does not exist yet.
     * @param MoodleQuickForm $mform.
     * @param context $context.
     * @return void
     */
    public function edit_instance_form($instance, MoodleQuickForm $mform, $context) {
        global $DB, $CFG;

        $aggregation_methods = self::get_aggregation_methods();
        // Do nothing by default.
        $nameattribs = array('size' => '20', 'maxlength' => '255');
        $mform->addElement('text', 'name', get_string('custominstancename', 'enrol'), $nameattribs);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'server');


        $passattribs = array('size' => '20', 'maxlength' => '50');
        $mform->addElement('passwordunmask', 'password', get_string('password', 'enrol_self'), $passattribs);
        $mform->addHelpButton('password', 'password', 'enrol_self');
        if (empty($instance->id) and $this->get_config('requirepassword')) {
            $mform->addRule('password', get_string('required'), 'required', null, 'client');
        }
        $mform->addRule('password', get_string('maximumchars', '', 50), 'maxlength', 50, 'server');

        // Get applicable courses (prerequisites).
        $prerequisiteRawQuery = $instance->id ? "LEFT JOIN {enrol_prerequisite} cc ON cc.enrol = {$instance->id} and c.id = cc.courseinstance" :'';
        $prerequisiteRawSelectQuery = $instance->id ? ", cc.id AS selected" :', null as selected';
        $courses = $DB->get_records_sql("
        SELECT DISTINCT
        c.id, c.category, c.fullname{$prerequisiteRawSelectQuery}
        FROM {course} c
        {$prerequisiteRawQuery}
        INNER JOIN {course_completion_criteria} ccc
        ON ccc.course = c.id WHERE c.enablecompletion = ".COMPLETION_ENABLED."
        AND c.id <> {$instance->courseid}
        ");


        if (!empty($courses)) {
            // Get category list.
            require_once($CFG->libdir. '/coursecatlib.php');
            $list = coursecat::make_categories_list();

            // Get course list for select box.
            $selectbox = array();
            $selected = array();
            foreach ($courses as $c) {
                $selectbox[$c->id] = $list[$c->category] . ' / ' . format_string($c->fullname, true,
                    array('context' => context_course::instance($c->id)));
                // If already selected ...
                if ($c->selected) {
                    $selected[] = $c->id;
                }
            }

            // Show multiselect box.
            $mform->addElement('select', 'courseinstance', get_string('coursesavailable', 'completion'), $selectbox,
                array('multiple' => 'multiple', 'size' => 6));

            // Select current criteria.
            $mform->setDefault('courseinstance', $selected);

            // Explain list.
            $mform->addElement('static', 'criteria_courses_explaination', '', get_string('coursesavailableexplaination', 'completion'));

            if (count($courses) > 1) {
                // Map aggregation methods to context-sensitive human readable dropdown menu.
                $courseaggregationmenu = array();
                foreach ($aggregation_methods as $methodcode => $methodname) {
                    if ($methodcode === COMPLETION_AGGREGATION_ALL) {
                        $courseaggregationmenu[COMPLETION_AGGREGATION_ALL] = get_string('courseaggregation_all', 'core_completion');
                    } else if ($methodcode === COMPLETION_AGGREGATION_ANY) {
                        $courseaggregationmenu[COMPLETION_AGGREGATION_ANY] = get_string('courseaggregation_any', 'core_completion');
                    } else {
                        $courseaggregationmenu[$methodcode] = $methodname;
                    }
                }
                $mform->addElement('select', 'customint8', get_string('courseaggregation', 'core_completion'), $courseaggregationmenu);
                $mform->setDefault('customint8', self::get_aggregation_method(COMPLETION_CRITERIA_TYPE_COURSE, $instance->courseid));
            }
        } else {
            $mform->addElement('static', 'nocourses', '', get_string('err_nocourses', 'completion'));
        }
    }

    /**
     * Perform custom validation of the data used to edit the instance.
     *
     * @since Moodle 3.1.
     * @param array $data Array of ("fieldname"=>value) of submitted data.
     * @param array $files Array of uploaded files "element_name"=>tmp_file_path.
     * @param object $instance The instance data loaded from the DB.
     * @param context $context The context of the instance we are editing.
     * @return array Array of "element_name"=>"error_description" if there are errors, empty otherwise.
     */
    public function edit_instance_validation($data, $files, $instance, $context) {
        $errors = array();

        $checkpassword = !empty($data['password']);

        if ($checkpassword) {
            $require = $this->get_config('requirepassword');
            $policy  = $this->get_config('usepasswordpolicy');
            if ($require and trim($data['password']) === '') {
                $errors['password'] = get_string('required');
            } else if (!empty($data['password']) && $policy) {
                $errmsg = '';
                if (!check_password_policy($data['password'], $errmsg)) {
                    $errors['password'] = $errmsg;
                }
            }
        }


        // Now these ones are checked by quickforms, but we may be called by the upload enrolments tool, or a webservive.
        if (core_text::strlen($data['name']) > 255) {
            $errors['name'] = get_string('err_maxlength', 'form', 255);
        }
        if (empty($data['courseinstance'])) {
            $errors['courseinstance'] = get_string('err_required', 'form', 255);
        }

        if (empty($data['name'])) {
            $errors['name'] = get_string('err_required', 'form', 255);
        }

        if (core_text::strlen($data['password']) > 50) {
            $errors['password'] = get_string('err_maxlength', 'form', 50);
        }
        return $errors;
    }

    /**
     * Add new instance of enrol plugin.
     * @param object $course
     * @param array $fields instance fields
     * @return int id of new instance, null if can not be created
     */
    public function add_instance($course, array $fields = null) {
        $fields['status'] = 0;
        $instanceId = parent::add_instance($course, $fields);
        return self::save_prerequisite_map($instanceId,$course->id, $fields['courseinstance']);
    }

    /**
     * Update instance of enrol plugin.
     * @param stdClass $instance
     * @param stdClass $data modified instance fields
     * @return boolean
     */
    public function update_instance($instance, $data) {
        self::save_prerequisite_map($instance->id, $instance->courseid, $data->courseinstance);
        return parent::update_instance($instance, $data);
    }

    private function save_prerequisite_map($instanceId,$courseId, $courseInstances){
        global $DB;
        $instance = new stdClass();
        $instance->enrol          = $instanceId;
        $instance->course         = $courseId;
        $DB->delete_records('enrol_prerequisite',(array) $instance);
        foreach($courseInstances as $courseInstance){
            $instance = new stdClass();
            $instance->enrol          = $instanceId;
            $instance->course         = $courseId;
            $instance->courseinstance = $courseInstance;
            $DB->insert_record('enrol_prerequisite', $instance);
        }

        return $instanceId;
    }

    /**
     * Return whether or not, given the current state, it is possible to add a new instance
     * of this enrolment plugin to the course.
     *
     * @param int $courseid.
     * @return bool.
     */
    public function can_add_instance($courseid) {
        return true;
    }

    /**
     * Is it possible to hide/show enrol instance via standard UI?
     *
     * @param stdClass $instance
     * @return bool
     */
    public function can_hide_show_instance($instance) {
        $context = context_course::instance($instance->courseid);
        return has_capability('enrol/prerequisite:config', $context);
    }

    public function can_delete_instance($instance) {
        $context = context_course::instance($instance->courseid);
        return has_capability('enrol/prerequisite:config', $context);
    }

    /**
     * Creates course enrol form, checks if form submitted
     * and enrols user if necessary. It can also redirect.
     *
     * @param stdClass $instance
     * @return string html text, usually a form in a text box
     */
    public function enrol_page_hook(stdClass $instance) {
        global $CFG, $OUTPUT;
        require_once("$CFG->dirroot/enrol/prerequisite/locallib.php");

        $enrolstatus = $this->can_enrol($instance);
        if (true === $enrolstatus) {
            // This user can self enrol using this instance.
            $form = new enrol_prerequisite_form(null, $instance);
            $instanceid = optional_param('instance', 0, PARAM_INT);
            if ($instance->id == $instanceid) {
                if ($data = $form->get_data()) {
                    $this->enrol_prerequisite($instance, $data);
                }
            }
        } else {
            require_once("$CFG->dirroot/enrol/prerequisite/classes/empty_form.php");
            // This user can not self enrol using this instance. Using an empty form to keep
            // the UI consistent with other enrolment plugins that returns a form.
            $data = new stdClass();
            $data->header = $this->get_instance_name($instance);
            $data->info = $enrolstatus;
            $data->RequiredCourses = $this->getPrerequisite($instance);

            // The can_self_enrol call returns a button to the login page if the user is a
            // guest, setting the login url to the form if that is the case.
            $url = isguestuser() ? get_login_url() : null;
            $form = new enrol_prerequisite_empty_form($url, $data);
        }

        ob_start();
        $form->display();
        $output = ob_get_clean();
        return $OUTPUT->box($output);
    }

    public function enrol_prerequisite(stdClass $instance, $data = null) {
        global $DB, $USER, $CFG;

        // Don't enrol user if password is not passed when required.
        if ($instance->password) {
            if (!isset($data->enrolpassword) || (isset($data->enrolpassword) && $data->enrolpassword != $instance->password)) {
                return;
            }
        }

        $timestart = time();
        if ($instance->enrolperiod) {
            $timeend = $timestart + $instance->enrolperiod;
        } else {
            $timeend = 0;
        }

        $this->enrol_user($instance, $USER->id, $instance->roleid, $timestart, $timeend);

        // // Send welcome message.
        // if ($instance->customint4 != ENROL_DO_NOT_SEND_EMAIL) {
        //     $this->email_welcome_message($instance, $USER);
        // }
    }

    /**
     * Checks if user can self enrol and meet all the prerequisite.
     *
     * @param stdClass $instance enrolment instance
     * @param bool $checkuserenrolment if true will check if user enrolment is inactive.
     *             used by navigation to improve performance.
     * @return bool|string true if successful, else error message or false.
     */
    public function can_enrol(stdClass $instance, $checkuserenrolment = true) {

        global $CFG, $DB, $OUTPUT, $USER;

        if ($checkuserenrolment) {
            if (isguestuser()) {
                // Can not enrol guest.
                return get_string('noguestaccess', 'enrol') . $OUTPUT->continue_button(get_login_url());
            }
            // Check if user is already enroled.
            if ($DB->get_record('user_enrolments', array('userid' => $USER->id, 'enrolid' => $instance->id))) {
                return get_string('alreadyenrolled', 'enrol_prerequisite');
            }
        }
        if ($instance->status != ENROL_INSTANCE_ENABLED) {
            return get_string('canntenrol', 'enrol_prerequisite');
        }

        $courses = $this->getPrerequisite($instance);
        $all = true;
        $any = false;
        foreach($courses as $each){
            if($each->completed == null){$all=false;}
            else{ $any = true; }
        }

        if($instance->customint8 == 1 && !$all){
            return get_string('canntenrolall', 'enrol_prerequisite');
        }elseif($instance->customint8 == 2 && !$any){
            return get_string('canntenrolany', 'enrol_prerequisite');
        }


        return true;
    }

    private function getPrerequisite($instance){
        global $DB;
        return $DB->get_records_sql("
            SELECT a.courseinstance, c.shortname, c.fullname, b.id as completed
            FROM mdl_enrol_prerequisite a
            LEFT JOIN mdl_course_completions b ON b.course = a.courseinstance
            INNER JOIN mdl_course c ON c.id IN (a.courseinstance)
            WHERE a.enrol = {$instance->id}
            ;
        ");
    }
}
