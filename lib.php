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
 * @package    enrol_attributes
 * @author     Nicolas Dunand <Nicolas.Dunand@unil.ch>
 * @copyright  2012-2015 Université de Lausanne (@link http://www.unil.ch}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Database enrolment plugin implementation.
 * @author  Petr Skoda - based on code by Martin Dougiamas, Martin Langhoff and others
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrol_attributes_plugin extends enrol_plugin {
    /**
     * Is it possible to delete enrol instance via standard UI?
     *
     * @param object $instance
     * @return bool
     */
    public function instance_deleteable($instance) {
        return true;
    }

    /**
     * Returns link to page which may be used to add new instance of enrolment plugin in course.
     * @param int $courseid
     * @return moodle_url page url
     */
    public function get_newinstance_link($courseid) {
        $context = context_course::instance($courseid);

        if (!has_capability('moodle/course:enrolconfig', $context) or !has_capability('enrol/attributes:config', $context)) {
            return NULL;
        }
        $configured_profilefields = explode(',', get_config('enrol_attributes', 'profilefields'));
        if (!strlen(array_shift($configured_profilefields))) {
            // no profile fields are configured for this plugin
            return NULL;
        }
        // multiple instances supported - different roles with different password
        return new moodle_url('/enrol/attributes/edit.php', array('courseid'=>$courseid));
    }

    /**
     * Is it possible to delete enrol instance via standard UI?
     *
     * @param object $instance
     * @return bool
     */
    public function can_delete_instance($instance) {
        $context = context_course::instance($instance->courseid);
        return has_capability('enrol/attributes:config', $context);
    }

    /**
     * Is it possible to hide/show enrol instance via standard UI?
     *
     * @param stdClass $instance
     * @return bool
     */
    public function can_hide_show_instance($instance) {
        $context = context_course::instance($instance->courseid);
        return has_capability('enrol/attributes:config', $context);
    }

    /**
     * Returns edit icons for the page with list of instances
     * @param stdClass $instance
     * @return array
     */
    public function get_action_icons(stdClass $instance) {
        global $OUTPUT;

        if ($instance->enrol !== 'attributes') {
            throw new coding_exception('invalid enrol instance!');
        }
        $context = context_course::instance($instance->courseid);

        $icons = array();

        if (has_capability('enrol/attributes:config', $context)) {
            $editlink = new moodle_url("/enrol/attributes/edit.php", array('courseid'=>$instance->courseid, 'id'=>$instance->id));
            $icons[] = $OUTPUT->action_icon($editlink, new pix_icon('i/edit', get_string('edit'), 'core', array('class'=>'icon')));
        }

        return $icons;
    }

    public static function attrsyntax_toarray($attrsyntax) { // TODO : protected
        global $DB;

        $attrsyntax_object = json_decode($attrsyntax);
        $rules = $attrsyntax_object->rules;

        $customuserfields = array();
        foreach ($DB->get_records('user_info_field') as $customfieldrecord) {
            $customuserfields[$customfieldrecord->id] = $customfieldrecord->shortname;
        }

        return array(
            'customuserfields'  => $customuserfields,
            'rules'             => $rules
        );
    }

    public static function arraysyntax_tosql($arraysyntax) { // TODO : protected
        global $CFG;
        $select = '';
        $where = 'true';
        static $join_id = 0;

        $customuserfields = $arraysyntax['customuserfields'];

        foreach ($arraysyntax['rules'] as $rule) {
            // first just check if we have a value 'ANY' to enroll all people :
            if (isset($rule->value) && $rule->value == 'ANY') {
                return array(
                    'select' => '',
                    'where'  => 'true'
                );
            }
        }

        foreach ($arraysyntax['rules'] as $rule) {
            if (isset($rule->cond_op)) {
                $where .= ' '.strtoupper($rule->cond_op).' ';
            }
            else {
                $where .= ' AND ';
            }
            if (isset($rule->rules)) {
                $sub_arraysyntax = array(
                    'customuserfields'  => $customuserfields,
                    'rules'             => $rule->rules
                );
                $sub_sql = self::arraysyntax_tosql($sub_arraysyntax);
                $select .= ' '.$sub_sql['select'].' ';
                $where .= ' ( '.$sub_sql['where'].' ) ';
            }
            else {
                if ($customkey = array_search($rule->param, $customuserfields, true)) {
                    // custom user field actually exists
                    $join_id++;
                    $select .= ' RIGHT JOIN '.$CFG->prefix.'user_info_data d'.$join_id.' ON d'.$join_id.'.userid = u.id';
                    $where .= ' (d'.$join_id.'.fieldid = '.$customkey.' AND ( d'.$join_id.'.data = \''.$rule->value.'\' OR d'.$join_id.'.data LIKE \'%;'.$rule->value.'\' OR d'.$join_id.'.data LIKE \''.$rule->value.';%\' OR d'.$join_id.'.data LIKE \'%;'.$rule->value.';%\' ))';
                }
            }
        }

        $where = preg_replace('/^true AND/', '', $where);
        $where = preg_replace('/^true OR/', '', $where);
        $where = preg_replace('/^true/', '', $where);

        return array(
            'select' => $select,
            'where' => $where
        );
    }

    public function cron() {
        $this->process_enrolments();
    }

    public static function process_login(\core\event\user_loggedin $event) {
        global $CFG, $DB;
        // we just received the event from the authentication system; check if well-formed:
        if (!$event->userid) {
            // didn't get an user ID, return as there is nothing we can do
            return true;
        }
        if (in_array('shibboleth', get_enabled_auth_plugins()) && $_SERVER['SCRIPT_FILENAME'] == $CFG->dirroot.'/auth/shibboleth/index.php') {
            // we did get this event from the Shibboleth authentication plugin,
            // so let's try to make the relevant mappings, ensuring that necessary profile fields exist and Shibboleth attributes are provided:
            $customfieldrecords = $DB->get_records('user_info_field');
            $customfields = array();
            foreach ($customfieldrecords as $customfieldrecord) {
                $customfields[] = $customfieldrecord->shortname;
            }
            $mapping = array();
            $mappings_str = explode("\n", str_replace("\r", '', get_config('enrol_attributes', 'mappings')));
            foreach ($mappings_str as $mapping_str) {
                if (preg_match('/^\s*([^: ]+)\s*:\s*([^: ]+)\s*$/', $mapping_str, $matches) && in_array($matches[2], $customfields) && array_key_exists($matches[1], $_SERVER)) {
                    $mapping[$matches[1]] = $matches[2];
                }
            }
            if (count($mapping)) {
                // now update user profile data from Shibboleth params received as part of the event:
                $user = $DB->get_record('user', ['id' => $event->userid], '*', MUST_EXIST);
                foreach ($mapping as $shibattr => $fieldname) {
                    if (isset($_SERVER[$shibattr])) {
                        $propertyname = 'profile_field_' . $fieldname;
                        $user->$propertyname = $_SERVER[$shibattr];
                    }
                }
                require_once($CFG->dirroot . '/user/profile/lib.php');
                profile_save_data($user);
            }
        }
        // last, process the actual enrolments, whether we're using Shibboleth authentication or not:
        self::process_enrolments($event);
    }

    public static function process_enrolments($event = null, $instanceid = null) {
        global $DB;
        $nbenrolled = 0;
        $possible_unenrolments = array();

        if ($instanceid) {
            // We're processing one particular instance, making sure it's active
            $enrol_attributes_records = $DB->get_records('enrol', array('enrol' => 'attributes', 'status' => 0, 'id' => $instanceid));
        }
        else {
            // We're processing all active instances,
            // because a user just logged in
            // OR we're running the cron
            $enrol_attributes_records = $DB->get_records('enrol', array('enrol' => 'attributes', 'status' => 0));
            if (!is_null($event)) {
                // Let's check if there are any potential unenroling instances
                $userid = (int)$event->userid;
                $possible_unenrolments = $DB->get_records_sql("SELECT id, enrolid FROM {user_enrolments} WHERE userid = ? AND status = 0 AND enrolid IN ( SELECT id FROM {enrol} WHERE enrol = 'attributes' AND customint1 = 1 ) ", array($userid));
            }
        }

        // are we to unenrol from anywhere?
        foreach ($possible_unenrolments as $id => $user_enrolment) {

            $unenrol_attributes_record = $DB->get_record('enrol', array('enrol' => 'attributes', 'status' => 0, 'customint1' => 1, 'id' => $user_enrolment->enrolid));
            if (!$unenrol_attributes_record) {
                continue;
            }

            $select = 'SELECT DISTINCT u.id FROM mdl_user u';
            $where = ' WHERE u.id='.$userid.' AND u.deleted=0 AND ';
            $arraysyntax = self::attrsyntax_toarray($unenrol_attributes_record->customtext1);
            $arraysql    = self::arraysyntax_tosql($arraysyntax);
            $users = $DB->get_records_sql($select . $arraysql['select'] . $where . $arraysql['where']);

            if (!array_key_exists($userid, $users)) {
                $enrol_attributes_instance = new enrol_attributes_plugin();
                $enrol_attributes_instance->unenrol_user($unenrol_attributes_record, (int)$userid);
            }

        }

        // are we to enrol anywhere?
        foreach ($enrol_attributes_records as $enrol_attributes_record) {

            $rules = json_decode($enrol_attributes_record->customtext1)->rules;
            $configured_profilefields = explode(',', get_config('enrol_attributes', 'profilefields'));
            foreach ($rules as $rule) {
                if (!isset($rule->param)) {
                    break;
                }
                if (!in_array($rule->param, $configured_profilefields)) {
                    break 2;
                }
            }
            $enrol_attributes_instance = new enrol_attributes_plugin();
            $enrol_attributes_instance->name = $enrol_attributes_record->name;

            $select = 'SELECT DISTINCT u.id FROM mdl_user u';
            if ($event) { // called by an event, i.e. user login
                $userid = (int)$event->userid;
                $where = ' WHERE u.id='.$userid;
            }
            else { // called by cron or by construct
                $where = ' WHERE true';
            }
            $where .= ' AND u.deleted=0 AND ';
            $arraysyntax = self::attrsyntax_toarray($enrol_attributes_record->customtext1);
            $arraysql    = self::arraysyntax_tosql($arraysyntax);

            $users = $DB->get_records_sql($select . $arraysql['select'] . $where . $arraysql['where']);
            foreach ($users as $user) {
                if (is_enrolled(context_course::instance($enrol_attributes_record->courseid), $user)) {
                    continue;
                }
                $enrol_attributes_instance->enrol_user($enrol_attributes_record, $user->id, $enrol_attributes_record->roleid);
                $nbenrolled++;
            }

        }

        if (!$event && !$instanceid) {
            // we only want output if runnning within the cron
            mtrace('enrol_attributes : enrolled '.$nbenrolled.' users.');
        }
        return $nbenrolled;

    }


    /*
     *
     */
    public static function purge_instance($instanceid, $context) {
        if (!$instanceid) {
            return false;
        }
        global $DB;
        if (!$DB->delete_records('role_assignments', array('component' => 'enrol_attributes', 'itemid' => $instanceid))) {
            return false;
        }
        if (!$DB->delete_records('user_enrolments', array('enrolid'=>$instanceid))) {
            return false;
        }
        $context->mark_dirty();
        return true;
    }


    /**
     * Returns enrolment instance manage link.
     *
     * By defaults looks for manage.php file and tests for manage capability.
     *
     * @param navigation_node $instancesnode
     * @param stdClass $instance
     * @return moodle_url;
     */
    public function add_course_navigation($instancesnode, stdClass $instance) {
        if ($instance->enrol !== 'attributes') {
             throw new coding_exception('Invalid enrol instance type!');
        }

        $context = context_course::instance($instance->courseid);
        if (has_capability('enrol/attributes:config', $context)) {
            $managelink = new moodle_url('/enrol/attributes/edit.php', array('courseid' => $instance->courseid, 'id' => $instance->id));
            $instancesnode->add($this->get_instance_name($instance), $managelink, navigation_node::TYPE_SETTING);
        }
    }

}

