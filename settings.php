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

require_once $CFG->dirroot . '/enrol/attributes/locallib.php';

if ($ADMIN->fulltree) {

    // Default role
    $options = get_default_enrol_roles(context_system::instance());

    $student = get_archetype_roles('student');
    $student_role = array_shift($student);

    $settings->add(new admin_setting_configselect('enrol_attributes/default_roleid',
            get_string('defaultrole', 'enrol_attributes'), get_string('defaultrole_desc', 'enrol_attributes'),
            $student_role->id, $options));

    // Behaviour when enrolled user doesn't have required attributes anymore.
    $whenexpiredoptions = [
            ENROL_ATTRIBUTES_WHENEXPIREDDONOTHING => get_string('whenexpireddonothing', 'enrol_attributes'),
            ENROL_ATTRIBUTES_WHENEXPIREDREMOVE => get_string('whenexpiredremove', 'enrol_attributes'),
            ENROL_ATTRIBUTES_WHENEXPIREDSUSPEND => get_string('whenexpiredsuspend', 'enrol_attributes'),
    ];
    $settings->add(new admin_setting_configselect('enrol_attributes/default_whenexpired',
            get_string('defaultwhenexpired', 'enrol_attributes'), get_string('defaultwhenexpired_desc', 'enrol_attributes'),
            ENROL_ATTRIBUTES_WHENEXPIREDDONOTHING, $whenexpiredoptions));

    // Fields to use in the selector
    $customfieldrecords = $DB->get_records('user_info_field');
    if ($customfieldrecords) {
        $customfields = [];
        foreach ($customfieldrecords as $customfieldrecord) {
            $customfields[$customfieldrecord->shortname] = $customfieldrecord->name;
        }
        asort($customfields);
        $settings->add(new admin_setting_configmultiselect('enrol_attributes/profilefields',
                get_string('profilefields', 'enrol_attributes'), get_string('profilefields_desc', 'enrol_attributes'),
                [], $customfields));
    }

    // Listen to login events
    $settings->add(new admin_setting_configcheckbox('enrol_attributes/observelogins', get_string('observelogins', 'enrol_attributes'), get_string('observelogins_desc', 'enrol_attributes'), 1));

    // Fields to update via Shibboleth login
    if (in_array('shibboleth', get_enabled_auth_plugins())) {
        $settings->add(new admin_setting_configtextarea('enrol_attributes/mappings',
                get_string('mappings', 'enrol_attributes'), get_string('mappings_desc', 'enrol_attributes'), '',
                PARAM_TEXT, 60, 10));
    }
}

