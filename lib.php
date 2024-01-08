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
 * @copyright  2012-2018 Université de Lausanne (@link http://www.unil.ch}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
require_once $CFG->dirroot . '/enrol/attributes/locallib.php';
require_once $CFG->dirroot.'/group/lib.php';

/**
 * Database enrolment plugin implementation.
 *
 * @author  Petr Skoda - based on code by Martin Dougiamas, Martin Langhoff and others
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrol_attributes_plugin extends enrol_plugin {
    /**
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function process_login(\core\event\user_loggedin $event) {
        global $CFG, $DB;
        // we just received the event from the authentication system; check if well-formed:
        if (!$event->userid) {
            // didn't get an user ID, return as there is nothing we can do
            return true;
        }
        if (in_array('shibboleth', get_enabled_auth_plugins())
            && $_SERVER['SCRIPT_FILENAME'] == $CFG->dirroot . '/auth/shibboleth/index.php') {
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
                if (preg_match('/^\s*([^: ]+)\s*:\s*([^: ]+)\s*$/', $mapping_str, $matches)
                    && array_key_exists($matches[1], $_SERVER)
                    && in_array($matches[2], $customfields)) {
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

    /**
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function process_enrolments($event = null, $instanceid = null) {
        global $DB;
        $nbenrolled = 0;
        $nbdbqueries = 0;
        $nbcachequeries = 0;
        $nbpossunenrol = 0;
        $nbpossenrol = 0;
        $nbunenrolled = 0;
        $possible_unenrolments = array();

        $cache = cache::make('enrol_attributes', 'dbquerycache');

        if ($instanceid) {
            // We're processing one particular instance, making sure it's active
            $enrol_attributes_records = $DB->get_records('enrol', array(
                    'enrol'  => 'attributes',
                    'status' => 0,
                    'id'     => $instanceid
            ));
        }
        else {
            // We're processing all active instances,
            // because a user just logged in
            // OR we're running the scheduled task
            $enrol_attributes_records = $DB->get_records('enrol', array(
                    'enrol'  => 'attributes',
                    'status' => 0
            ));
            if (!is_null($event)) {
                // This is a login triggering
                if (!get_config('enrol_attributes', 'observelogins')) {
                    // Admin has decided not to process logins to save performance.
                    return;
                }
                // Let's check if there are any potential unenroling instances
                $userid = (int)$event->userid;
                $possible_unenrolments =
                        $DB->get_records_sql("SELECT id, enrolid, userid FROM {user_enrolments} WHERE userid = ? AND enrolid IN ( SELECT id FROM {enrol} WHERE enrol = 'attributes' AND customint1 > 0 ) ",
                                array($userid));
            } else {
                $possible_unenrolments =
                        $DB->get_records_sql("SELECT id, enrolid, userid FROM {user_enrolments} WHERE enrolid IN ( SELECT id FROM {enrol} WHERE enrol = 'attributes' AND customint1 > 0 ) ",
                                array());
            }
        }

        // are we to unenrol/suspend from anywhere?
        foreach ($possible_unenrolments as $id => $user_enrolment) {
            $nbpossunenrol++;
            // we only want output if runnning within the scheduled task
            if (!$event && !$instanceid && $nbpossunenrol % 1000 === 0 && strpos($_SERVER['argv'][0], 'phpunit') === FALSE) {
                mtrace('-', '');
            }

            $unenrol_attributes_record = $DB->get_record('enrol', array(
                    'enrol'  => 'attributes',
                    'status' => 0,
                    'id'     => $user_enrolment->enrolid
            ));

            if (!$unenrol_attributes_record) {
                continue;
            }

            if ($unenrol_attributes_record->customint1 == ENROL_ATTRIBUTES_WHENEXPIREDDONOTHING) {
                continue;
            }

            $select = 'SELECT DISTINCT u.id FROM {user} u';
            $where = ' WHERE u.id=' . $user_enrolment->userid . ' AND u.deleted=0 AND ';
            $arraysyntax = self::attrsyntax_toarray($unenrol_attributes_record->customtext1);
            $arraysql = self::arraysyntax_tosql($arraysyntax);
            $dbquerycachekey = md5($select . serialize($arraysql) . $where);
            $users_cache = $cache->get($dbquerycachekey);
            if ($users_cache) {
                $users = unserialize($users_cache);
                $nbcachequeries++;
            }
            else {
                $users = $DB->get_records_sql($select . $arraysql['select'] . $where . $arraysql['where'],
                        $arraysql['params']);
                $nbdbqueries++;
                $cache->set($dbquerycachekey, serialize($users));
            }

            if (!array_key_exists($user_enrolment->userid, $users)) {
                // User is to be either unenrolled or suspended
                $enrol_attributes_instance = new enrol_attributes_plugin();
                if ($unenrol_attributes_record->customint1 == ENROL_ATTRIBUTES_WHENEXPIREDREMOVE) {
                    $enrol_attributes_instance->unenrol_user($unenrol_attributes_record, (int)$user_enrolment->userid);
                } elseif ($unenrol_attributes_record->customint1 == ENROL_ATTRIBUTES_WHENEXPIREDSUSPEND) {
                    $enrol_attributes_instance->update_user_enrol($unenrol_attributes_record, (int)$user_enrolment->userid,
                            ENROL_USER_SUSPENDED);
                }
                $nbunenrolled++;
            }
        }

        // are we to enrol anywhere?
        foreach ($enrol_attributes_records as $enrol_attributes_record) {
            $nbpossenrol++;
            if (!$event && !$instanceid && strpos($_SERVER['argv'][0], 'phpunit') === FALSE) {
                // we only want output if runnning within the scheduled task
                mtrace('+', '');
            }

            $enroldetails = json_decode($enrol_attributes_record->customtext1 ?? '');
            if (isset($enroldetails->rules)) {
                $rules = $enroldetails->rules;
            } else {
                // skip this record, as it is malformed
                continue;
            }
            $configured_profilefields = explode(',', get_config('enrol_attributes', 'profilefields'));
            foreach ($rules as $rule) {
                if (!isset($rule->param) && !isset($rule->rules)) {
                    continue 2; // Rule malformed.
                }
                if (isset($rule->param) && !in_array($rule->param, $configured_profilefields)) {
                    continue 2; // Rule uses a param that's not allowed in the plugin settings.
                }
            }
            $enrol_attributes_instance = new enrol_attributes_plugin();
            $enrol_attributes_instance->name = $enrol_attributes_record->name;

            $select = 'SELECT DISTINCT u.id FROM {user} u';
            if ($event) { // called by an event, i.e. user login
                $userid = (int)$event->userid;
                $where = ' WHERE u.id=' . $userid;
            }
            else { // called by scheduled task or by construct
                $where = ' WHERE 1=1';
            }
            $where .= ' AND u.deleted=0 AND ';
            $arraysyntax = self::attrsyntax_toarray($enrol_attributes_record->customtext1);
            $arraysql = self::arraysyntax_tosql($arraysyntax);
            $dbquerycachekey = md5($select . serialize($arraysql) . $where);

            //TODO fix bug related to cache : users are not unenrolled
            $users_cache = $cache->get($dbquerycachekey);
            if ($users_cache) {
                $users = unserialize($users_cache);
                $nbcachequeries++;
            } else {
                $users = $DB->get_records_sql($select . $arraysql['select'] . $where . $arraysql['where'],
                        $arraysql['params']);
                $nbdbqueries++;
                $cache->set($dbquerycachekey, serialize($users));
            }
            foreach ($users ?? [] as $user) {
                $recovergrades = null;
                if (is_enrolled(context_course::instance($enrol_attributes_record->courseid), $user)) {
                    $recovergrades = false; // do not try to recover grades if user is already enrolled
                }
                $enrol_attributes_instance->enrol_user($enrol_attributes_record, $user->id,
                        $enrol_attributes_record->roleid, 0, 0, null, $recovergrades);
                $nbenrolled++;
                // Start modification

                $groups = json_decode($enrol_attributes_record->customtext1, true)['groups'] ?? [];
                foreach ($groups as $groupid) {
                    groups_add_member($groupid, $user->id);
                }
                // End modification
            }
        }

        if (!$event && !$instanceid && strpos($_SERVER['argv'][0], 'phpunit') === FALSE) {
            // we only want output if runnning within the scheduled task
            mtrace("\n" . 'enrol_attributes : ' . $nbdbqueries . ' DB queries.');
            mtrace('enrol_attributes : ' . $nbcachequeries . ' cache queries.');
            mtrace('enrol_attributes : ' . $nbpossenrol . ' enrolment instances.');
            mtrace('enrol_attributes : ' . $nbpossunenrol . ' possible unenrolments.');
            mtrace('enrol_attributes : ' . $nbunenrolled . ' unenrolments.');
            mtrace('enrol_attributes : enrolled ' . $nbenrolled . ' users.');
        }

        return $nbenrolled;
    }

    /**
     * @param stdClass $instance
     * @param int      $groupid
     * @param int      $userid
     *
     * @throws coding_exception
     */
    public function restore_group_member($instance, $groupid, $userid) {
        global $CFG;
        require_once("$CFG->dirroot/group/lib.php");
        groups_add_member($groupid, $userid);
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
                'customuserfields' => $customuserfields,
                'rules'            => $rules
        );
    }

    public static function arraysyntax_tosql($arraysyntax, &$join_id = 0) {
        global $DB;
        $select = '';
        $where = '1=1';
        $params = array();
        $customuserfields = $arraysyntax['customuserfields'];
        foreach ($arraysyntax['rules'] as $rule) {
            if (isset($rule->cond_op)) {
                $where .= ' ' . strtoupper($rule->cond_op) . ' ';
            }
            else {
                $where .= ' AND ';
            }
            // first just check if we have a value 'ANY' to enroll all people :
            if (isset($rule->value) && $rule->value === 'ANY') {
                $where .= '1=1';
                continue;
            }
            if (isset($rule->rules)) {
                $sub_arraysyntax = array(
                        'customuserfields' => $customuserfields,
                        'rules'            => $rule->rules
                );
                $sub_sql = self::arraysyntax_tosql($sub_arraysyntax, $join_id);
                $select .= ' ' . $sub_sql['select'] . ' ';
                $where .= ' ( ' . $sub_sql['where'] . ' ) ';
                $params = array_merge($params, $sub_sql['params']);
            } elseif ($customkey = array_search($rule->param, $customuserfields, true)) {
                // custom user field actually exists
                $join_id++;
                $data = 'd' . $join_id . '.data';
                $select .= ' RIGHT JOIN {user_info_data} d' . $join_id . ' ON d' . $join_id . '.userid = u.id AND d' . $join_id . '.fieldid = ' . $customkey;

                if (isset($rule->comp_op) && $rule->comp_op === 'contains') {
                    $where .= ' (' . $DB->sql_like($DB->sql_compare_text($data), '?') . ')';
                    $params[] = '%' . $rule->value . '%';
                } else if (isset($rule->comp_op) && $rule->comp_op !== 'listitem') {
                        $where .= ' (' . $DB->sql_compare_text($data) . ' ' . strtoupper($rule->comp_op) . ' ' . $DB->sql_compare_text('?') . ')';
                        $params[] = $rule->value;
                } else {
                    $where .= ' (' . $DB->sql_compare_text($data) . ' = ' . $DB->sql_compare_text(
                            '?'
                        ) . ' OR ' . $DB->sql_like(
                            $DB->sql_compare_text($data),
                            '?'
                        ) . ' OR ' . $DB->sql_like(
                            $DB->sql_compare_text($data),
                            '?'
                        ) . ' OR ' . $DB->sql_like(
                            $DB->sql_compare_text($data),
                            '?')
                        . ')';

                    array_push(
                        $params,
                        $rule->value,
                        '%;' . $rule->value,
                        $rule->value . ';%',
                        '%;' . $rule->value . ';%'
                    );
                }
            }
        }
        $where = preg_replace('/^1=1 AND ?/', '', $where);
        $where = preg_replace('/^1=1 OR/', '', $where);
        $where = preg_replace('/^1=1/', '', $where);

        if($where === '') {
            // Must be FALSE in any database without causing syntax error
            $where = '1=0';
        } else {
            $where = " ( $where ) ";
        }

        return array(
                'select' => $select,
                'where'  => $where,
                'params' => $params
        );
    }

    public static function purge_instance($instanceid) {
        global $DB;
        $enrolplugininstance = new self();

        if($instanceid) {
            $enrol_attributes_record = $DB->get_record('enrol', ['id' => $instanceid]);
            $enrolment_records = $DB->get_records('user_enrolments', ['enrolid'  => $enrol_attributes_record->id]);
            foreach ($enrolment_records as $record) {
                $enrolplugininstance->unenrol_user($enrol_attributes_record, $record->userid);
            }

            return true;
        }

        return false;
    }

    /**
     * Is it possible to delete enrol instance via standard UI?
     *
     * @param object $instance
     *
     * @return bool
     */
    public function instance_deleteable($instance) {
        return true;
    }

    /**
     * Returns link to page which may be used to add new instance of enrolment plugin in course.
     *
     * @param int $courseid
     *
     * @return moodle_url page url
     */
    public function get_newinstance_link($courseid) {
        $context = context_course::instance($courseid);

        if (!has_capability('moodle/course:enrolconfig', $context) or !has_capability('enrol/attributes:config',
                        $context)) {
            return null;
        }
        $configured_profilefields = explode(',', get_config('enrol_attributes', 'profilefields'));
        if (!strlen(array_shift($configured_profilefields))) {
            // no profile fields are configured for this plugin
            return null;
        }

        // multiple instances supported - different roles with different password
        return new moodle_url('/enrol/attributes/edit.php', array('courseid' => $courseid));
    }

    /**
     * Restore instance and map settings.
     *
     * @param restore_enrolments_structure_step $step
     * @param stdClass                          $data
     * @param stdClass                          $course
     * @param int                               $oldid
     */
    public function restore_instance(restore_enrolments_structure_step $step, stdClass $data, $course, $oldid) {
        if ($step->get_task()->get_target() !== backup::TARGET_NEW_COURSE) {
            return false;
        }
        $instanceid = $this->add_instance($course, (array)$data);
        $step->set_mapping('enrol', $oldid, $instanceid);
    }

    /**
     * Is it possible to delete enrol instance via standard UI?
     *
     * @param object $instance
     *
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
     *
     * @return bool
     */
    public function can_hide_show_instance($instance) {
        $context = context_course::instance($instance->courseid);

        return has_capability('enrol/attributes:config', $context);
    }

    /*
     *
     */

    /**
     * Returns edit icons for the page with list of instances
     *
     * @param stdClass $instance
     *
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
            $editlink = new moodle_url("/enrol/attributes/edit.php", array(
                    'courseid' => $instance->courseid,
                    'id'       => $instance->id
            ));
            $icons[] = $OUTPUT->action_icon($editlink,
                    new pix_icon('i/edit', get_string('edit'), 'core', array('class' => 'icon')));
        }

        return $icons;
    }

   /**
    * Does this plugin allow manual changes in user_enrolments table?
    *
    * All plugins allowing this must implement 'enrol/xxx:manage' capability
    *
    * @param stdClass $instance course enrol instance
    * @return bool - true means it is possible to change enrol period and status in user_enrolments table
    */
    public function allow_manage(stdClass $instance) {
        return true;
    }

    /**
     * Returns enrolment instance manage link.
     *
     * By defaults looks for manage.php file and tests for manage capability.
     *
     * @param navigation_node $instancesnode
     * @param stdClass $instance
     *
     * @return moodle_url;
     * @throws \coding_exception|\moodle_exception
     */
    public function add_course_navigation($instancesnode, stdClass $instance) {
        if ($instance->enrol !== 'attributes') {
            throw new coding_exception('Invalid enrol instance type!');
        }

        $context = context_course::instance($instance->courseid);
        if (has_capability('enrol/attributes:config', $context)) {
            $managelink = new moodle_url('/enrol/attributes/edit.php', array(
                    'courseid' => $instance->courseid,
                    'id'       => $instance->id
            ));
            $instancesnode->add($this->get_instance_name($instance), $managelink, navigation_node::TYPE_SETTING);
        }
    }

}

