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
 * Database enrolment plugin.
 *
 * This plugin synchronises enrolment and roles with external attributes table.
 *
 * @package    enrol
 * @subpackage attributes
 * @copyright  2012 Nicolas Dunand {@link http://www.unil.ch/riset}
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
        return true; // anyway
//        if (!enrol_is_enabled('attributes')) {
//            return true;
//        }
//        return false;
    }

    public function js_load($filename) {
        global $PAGE;
        $jsurl = new moodle_url('/enrol/attributes/js/'.$filename.'.js');
        $PAGE->requires->js($jsurl);
    }

    /**
     * Returns link to page which may be used to add new instance of enrolment plugin in course.
     * @param int $courseid
     * @return moodle_url page url
     */
    public function get_newinstance_link($courseid) {
        $context = get_context_instance(CONTEXT_COURSE, $courseid, MUST_EXIST);

        if (!has_capability('moodle/course:enrolconfig', $context) or !has_capability('enrol/manual:config', $context)) {
            return NULL;
        }
        // multiple instances supported - different roles with different password
        return new moodle_url('/enrol/attributes/edit.php', array('courseid'=>$courseid));
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
        $context = get_context_instance(CONTEXT_COURSE, $instance->courseid);

        $icons = array();

        if (has_capability('enrol/attributes:config', $context)) {
            $editlink = new moodle_url("/enrol/attributes/edit.php", array('courseid'=>$instance->courseid, 'id'=>$instance->id));
            $icons[] = $OUTPUT->action_icon($editlink, new pix_icon('i/edit', get_string('edit'), 'core', array('class'=>'icon')));
        }

        return $icons;
    }

    public function attrsyntax_toarray($attrsyntax) { // TODO : protected
        global $DB;

        $attrsyntax_object = json_decode($attrsyntax);
        $rules = $attrsyntax_object->rules;

        $customuserfields = array();
        foreach ($DB->get_records('user_info_field') as $customfieldrecord) {
            $customuserfields[$customfieldrecord->id] = strtolower($customfieldrecord->shortname);
        }

        return array(
            'customuserfields'  => $customuserfields,
            'rules'             => $rules
        );
    }

    public function arraysyntax_tosql($arraysyntax) { // TODO : protected
        global $CFG;
        $select = '';
        $where = '1';
        static $join_id = 0;

        $customuserfields = $arraysyntax['customuserfields'];

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
                    $where .= ' (d'.$join_id.'.fieldid = '.$customkey.' AND d'.$join_id.'.data = \''.$rule->value.'\')';
                }
            }
        }

        // ugly hack:
        $where = str_replace('1 AND ', ' ', str_replace('1 OR ', ' ', $where));
        $where = preg_replace('/^(1)/', '', $where);

        return array(
            'select' => $select,
            'where' => $where
        );
    }

    public function cron() {
        $this->process_enrolments();
    }

    public function process_enrolments($eventdata = null, $instanceid = null) {
        global $DB, $CFG;
        $nbenrolled = 0;

        if ($instanceid) {
            $enrol_attributes_records = $DB->get_records('enrol', array('enrol' => 'attributes', 'status' => 0, 'id' => $instanceid));
        }
        else {
            $enrol_attributes_records = $DB->get_records('enrol', array('enrol' => 'attributes', 'status' => 0));
        }

        foreach ($enrol_attributes_records as $enrol_attributes_record) {

            $enrol_attributes_instance = new enrol_attributes_plugin();
            $enrol_attributes_instance->name = $enrol_attributes_record->name;

            $select = 'SELECT DISTINCT u.id FROM mdl_user u';
            if ($eventdata) { // called by an event
                $userid = (int)$eventdata->user->id;
                $where = ' WHERE u.id='.$userid;
            }
            else { // called by cron or by construct
                $where = ' WHERE 1';
            }
            $where .= ' AND ';
            $arraysyntax = self::attrsyntax_toarray($enrol_attributes_record->customtext1);
            $arraysql    = self::arraysyntax_tosql($arraysyntax);

            $users = $DB->get_records_sql($select . $arraysql['select'] . $where . $arraysql['where']);
            foreach ($users as $user) {
                if (!$eventdata && !$instanceid) {
                    // we only want output if runnning within the cron
                    mtrace('about to enrol user '.$user->id.' in course '.$enrol_attributes_record->courseid);
                }
                $enrol_attributes_instance->enrol_user($enrol_attributes_record, $user->id, $enrol_attributes_record->roleid);
                add_to_log($enrol_attributes_record->courseid, 'course', 'enrol', '../enrol/users.php?id='.$enrol_attributes_record->courseid, $enrol_attributes_record->courseid);
                $nbenrolled++;
            }

        }

        return $nbenrolled;

    }


    /*
     *
     */
    function purge_instance($instanceid, $context) {
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

        $context = get_context_instance(CONTEXT_COURSE, $instance->courseid);
        if (has_capability('enrol/attributes:config', $context)) {
            $managelink = new moodle_url('/enrol/attributes/edit.php', array('courseid' => $instance->courseid, 'id' => $instance->id));
            $instancesnode->add($this->get_instance_name($instance), $managelink, navigation_node::TYPE_SETTING);
        }
    }

}

