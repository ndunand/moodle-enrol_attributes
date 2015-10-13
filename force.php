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

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once('lib.php');

require_sesskey();

$courseid   = required_param('courseid', PARAM_INT);
$instanceid = required_param('instanceid', PARAM_INT);

$course = $DB->get_record('course', array('id'=>$courseid), '*', MUST_EXIST);
$context = context_course::instance($course->id);

require_login($course);
require_capability('enrol/attributes:config', $context);

if (!$courseid || !$instanceid) {
    print_string('ajax-error', 'enrol_attributes');
    exit;
}

$nbenrolled = enrol_attributes_plugin::process_enrolments(null, $instanceid);

ob_end_clean();

if($nbenrolled !== false) {
    print_string('ajax-okforced', 'enrol_attributes', $nbenrolled);
}
else {
    print_string('ajax-error', 'enrol_attributes');
}

