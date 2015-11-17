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

require('../../config.php');
require_once('edit_form.php');

$courseid   = required_param('courseid', PARAM_INT);
$instanceid = optional_param('id', 0, PARAM_INT); // instanceid

$course = $DB->get_record('course', array('id'=>$courseid), '*', MUST_EXIST);
$context = context_course::instance($course->id);

require_login($course);
require_capability('enrol/attributes:config', $context);

$PAGE->set_url('/enrol/attributes/edit.php', array('courseid'=>$course->id, 'id'=>$instanceid));
$PAGE->set_pagelayout('admin');

$return = new moodle_url('/enrol/instances.php', array('id'=>$course->id));
if (!enrol_is_enabled('attributes')) {
    redirect($return);
}

$plugin = enrol_get_plugin('attributes');

if ($instanceid) {
    $instance = $DB->get_record('enrol', array('courseid'=>$course->id, 'enrol'=>'attributes', 'id'=>$instanceid), '*', MUST_EXIST);
}
else {
    require_capability('moodle/course:enrolconfig', $context);
    // no instance yet, we have to add new instance
    navigation_node::override_active_url(new moodle_url('/enrol/instances.php', array('id'=>$course->id)));
    $instance           = new stdClass();
    $instance->id       = null;
    $instance->courseid = $course->id;
}

$mform = new enrol_attributes_edit_form(NULL, array($instance, $plugin, $context));

if ($mform->is_cancelled()) {
    redirect($return);
}
else if ($data = $mform->get_data()) {

    if ($instance->id) {
        $instance->name           = $data->name;
        $instance->roleid         = $data->roleid;
        $instance->customint1     = isset($data->customint1) ? ($data->customint1) : 0;
        $instance->customtext1    = $data->customtext1;
        $DB->update_record('enrol', $instance);
    }
    else {
        $fields = array('name'=>$data->name, 'roleid'=>$data->roleid, 'customint1'=>isset($data->customint1), 'customtext1'=>$data->customtext1);
        $plugin->add_instance($course, $fields);
    }

    redirect($return);
}

$PAGE->set_heading($course->fullname);
$PAGE->set_title(get_string('pluginname', 'enrol_attributes'));

$PAGE->requires->jquery();
$PAGE->requires->js('/enrol/attributes/js/jquery.json-2.3.min.js');
$PAGE->requires->js('/enrol/attributes/jsparams.php');
$PAGE->requires->js('/enrol/attributes/js/jquery.booleanEditor.js');
$PAGE->requires->js('/enrol/attributes/js/javascript.js');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'enrol_attributes'));
$mform->display();

// DEBUGGING : BEGIN
if ($instanceid) {
    debugging('customtext1= ' . print_r(json_decode($instance->customtext1), true), DEBUG_DEVELOPER);
    $debug_fieldsandrules = enrol_attributes_plugin::attrsyntax_toarray($instance->customtext1);
    debugging('fieldsandrules= ' . print_r($debug_fieldsandrules, true), DEBUG_DEVELOPER);
    $debug_arraysql = enrol_attributes_plugin::arraysyntax_tosql($debug_fieldsandrules);
    debugging('arraysql= ' . print_r($debug_arraysql, true), DEBUG_DEVELOPER);
    $debug_sqlquery =
            'SELECT DISTINCT u.id FROM mdl_user u ' . $debug_arraysql['select'] . ' WHERE ' . $debug_arraysql['where'];
    debugging('sqlquery= ' . print_r($debug_sqlquery, true), DEBUG_DEVELOPER);
    $debug_users = $DB->get_records_sql($debug_sqlquery);
    debugging('countusers= ' . print_r(count($debug_users), true), DEBUG_DEVELOPER);
//    debugging('force.php DEBUGGING:', DEBUG_DEVELOPER);
//    $nbenrolled = enrol_attributes_plugin::process_enrolments(null, $instanceid);
//    debugging('nbenrolled= ' . print_r($nbenrolled, true), DEBUG_DEVELOPER);
}
// DEBUGGING : END

echo $OUTPUT->footer();

