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
        if (!enrol_is_enabled('attributes')) {
            return true;
        }
        return false;
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

    protected function attrsyntax_toarray($attrsyntax) {
        global $DB;
        $return = array();

        $userfields_core = array('username', 'deleted', 'institution'); // TODO !!
        $userfields_custom = array();
        foreach ($DB->get_records('user_info_field') as $customfieldrecord) {
            $userfields_custom[$customfieldrecord->id] = $customfieldrecord->shortname;
        }

        $array_core = array(
            'deleted' => 0
        );
        $array_custom = array();
        $attrsyntax = str_replace("\r", "", $attrsyntax);
        $lines = explode("\n", $attrsyntax);
        foreach ($lines as $line) {
            if (preg_match('/([^ =]*) = (.*)/', $line, $matches)) {
                $key = $matches[1];
                $value = $matches[2];
                if (in_array($key, $userfields_core, true)) {
                    $array_core[$key] = $value;
                }
                if ($customkey = array_search($key, $userfields_custom, true)) {
                    $array_custom[$customkey] = $value;
                }
            }
        }
        $return['core'] = $array_core;
        $return['custom'] = $array_custom;
        return $return;
    }

    public function cron() {
        $this->process_enrolments();
    }

    public function process_enrolments($eventdata = null) {
        global $DB, $CFG;

        $enrol_attributes_records = $DB->get_records('enrol', array('enrol' => 'attributes', 'status' => 0));

        foreach ($enrol_attributes_records as $enrol_attributes_record) {

            $enrol_attributes_instance = new enrol_attributes_plugin();
            $enrol_attributes_instance->name = $enrol_attributes_record->name;

            $select = 'SELECT u.id FROM mdl_user u';
            if ($eventdata) { // called by an event
                $userid = (int)$eventdata->user->id;
                $where = ' WHERE u.id='.$userid;
            }
            else { // called by cron or by construct
                $where = ' WHERE 1';
            }
            $join_id = 0;
            $fields = enrol_attributes_plugin::attrsyntax_toarray($enrol_attributes_record->customtext1);

            foreach ($fields['custom'] as $key => $value) {
                $join_id++;
                $key = (int)$key;
                $value = addslashes($value);
                $select .= ' RIGHT JOIN '.$CFG->prefix.'user_info_data d'.$join_id.' ON d'.$join_id.'.userid = u.id';
                $where .= ' AND (d'.$join_id.'.fieldid = '.$key.' AND d'.$join_id.'.data = \''.$value.'\')';
            }

            foreach ($fields['core'] as $key => $value) {
                $key = addslashes($key);
                $value = addslashes($value);
                $where .= ' AND u.'.$key.' = \''.$value.'\'';
            }

            $users = $DB->get_records_sql($select . $where);
            foreach ($users as $user) {
                if (!$eventdata) {
                    mtrace('about to enrol user '.$user->id.' in course '.$enrol_attributes_record->courseid);
                }
                $enrol_attributes_instance->enrol_user($enrol_attributes_record, $user->id, $enrol_attributes_record->roleid);
                add_to_log($enrol_attributes_record->courseid, 'course', 'enrol', '../enrol/users.php?id='.$enrol_attributes_record->courseid, $enrol_attributes_record->courseid);
            }

        }

    }
}

