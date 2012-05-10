<?php

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once('lib.php');

require_sesskey();

$courseid   = required_param('courseid', PARAM_INT);
$instanceid = required_param('instanceid', PARAM_INT);

$course = $DB->get_record('course', array('id'=>$courseid), '*', MUST_EXIST);
$context = get_context_instance(CONTEXT_COURSE, $course->id, MUST_EXIST);

require_login($course);
require_capability('enrol/attributes:config', $context);

if (!$courseid || !$instanceid) {
    print_string('ajax-error', 'enrol_attributes');
    exit;
}

if($nbenrolled = enrol_attributes_plugin::process_enrolments(null, $instanceid)) {
    print_string('ajax-okforced', 'enrol_attributes', $nbenrolled);
}
else {
    print_string('ajax-error', 'enrol_attributes');
}
