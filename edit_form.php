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

require_once($CFG->libdir . '/formslib.php');
require_once $CFG->dirroot . '/enrol/attributes/locallib.php';

class enrol_attributes_edit_form extends moodleform {

    function definition() {
        global $DB;
        $mform = $this->_form;


        [$instance, $plugin, $context] = $this->_customdata;

        $mform->addElement('header', 'header', get_string('pluginname', 'enrol_attributes'));

        $mform->addElement('text', 'name', get_string('custominstancename', 'enrol'));
        $mform->setType('name', PARAM_TEXT);

        if ($instance->id) {
            $roles = get_default_enrol_roles($context, $instance->roleid);
        }
        else {
            $roles = get_default_enrol_roles($context, $plugin->get_config('default_roleid'));
        }
        $mform->addElement('select', 'roleid', get_string('role'), $roles);
        $mform->setDefault('roleid', $plugin->get_config('default_roleid'));

        // Start modification
        $courseid = required_param('courseid', PARAM_INT);
        $groups = groups_get_all_groups($courseid);

        if (count($groups)) {
            $groups2 = array();
            foreach ($groups as $value) {
                $groups2[$value->id] = $value->name;
            }

            $groupselector = $mform->addElement('autocomplete', 'groupselect', get_string('group', 'enrol_attributes'),
                    $groups2);
            $groupselector->setMultiple(true);
            $mform->addHelpButton('groupselect', 'group', 'enrol_attributes');

            $recordgroups =
                    (property_exists($instance, 'customtext1') && property_exists(json_decode($instance->customtext1), 'groups')) ? json_decode($instance->customtext1)->groups : [];
            $recordgroups === [] ?: $groupselector->setSelected($recordgroups);
        }
        else {
            $groupselector = $mform->addElement('static', 'groupselect', get_string('group', 'enrol_attributes'), html_writer::div(get_string('nogroups', 'group'), 'alert alert-info'));
            $mform->addHelpButton('groupselect', 'group', 'enrol_attributes');
        }


        // End modification
        $mform->addElement('textarea', 'customtext1', get_string('attrsyntax', 'enrol_attributes'), array(
                'cols' => '60',
                'rows' => '8'
        ));
        $mform->addHelpButton('customtext1', 'attrsyntax', 'enrol_attributes');

        $mform->addElement('html', '<div class="alert alert-warning alert-block fade in" role="alert" data-aria-autofocus="true">' . get_string('listitem_description', 'enrol_attributes') . '</div>');

        $whenexpiredoptions = [
                ENROL_ATTRIBUTES_WHENEXPIREDDONOTHING => get_string('whenexpireddonothing', 'enrol_attributes'),
                ENROL_ATTRIBUTES_WHENEXPIREDREMOVE => get_string('whenexpiredremove', 'enrol_attributes'),
                ENROL_ATTRIBUTES_WHENEXPIREDSUSPEND => get_string('whenexpiredsuspend', 'enrol_attributes'),
        ];
        $mform->addElement('select', 'customint1', get_string('whenexpired', 'enrol_attributes'), $whenexpiredoptions);
        $mform->setDefault('customint1', $plugin->get_config('default_whenexpired'));
        $mform->addHelpButton('customint1', 'whenexpired', 'enrol_attributes');

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'courseid');
        $mform->setType('courseid', PARAM_INT);

        $this->add_action_buttons(true, ($instance->id ? null : get_string('addinstance', 'enrol')));

        $this->set_data($instance);
    }

    function add_action_buttons($cancel = true, $submitlabel = null) {
        if (is_null($submitlabel)) {
            $submitlabel = get_string('savechanges');
        }
        $mform =& $this->_form;
        if ($cancel) {
            //when two elements we need a group
            $buttonarray = array();
            $buttonarray[] = &$mform->createElement('submit', 'submitbutton', $submitlabel);
            $buttonarray[] = &$mform->createElement('cancel');
            $buttonarray[] = &$mform->createElement('button', 'purge', get_string('purge', 'enrol_attributes'), array(
                    'onclick' => 'enrol_attributes_purge(\'' . addslashes(get_string('confirmpurge',
                                    'enrol_attributes')) . '\');'
            ));
            $buttonarray[] = &$mform->createElement('button', 'force', get_string('force', 'enrol_attributes'), array(
                    'onclick' => 'enrol_attributes_force(\'' . addslashes(get_string('confirmforce',
                                    'enrol_attributes')) . '\');'
            ));
            $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
            $mform->closeHeaderBefore('buttonar');
        }
    }

}

