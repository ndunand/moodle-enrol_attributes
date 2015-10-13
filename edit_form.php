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

require_once($CFG->libdir.'/formslib.php');

class enrol_attributes_edit_form extends moodleform {

    function definition() {
        $mform = $this->_form;

        list($instance, $plugin, $context) = $this->_customdata;

        $mform->addElement('header', 'header', get_string('pluginname', 'enrol_attributes'));

        $mform->addElement('text', 'name', get_string('custominstancename', 'enrol'));
        $mform->setType('name', PARAM_TEXT);

        if ($instance->id) {
            $roles = get_default_enrol_roles($context, $instance->roleid);
        } else {
            $roles = get_default_enrol_roles($context, $plugin->get_config('default_roleid'));
        }
        $mform->addElement('select', 'roleid', get_string('role'), $roles);
        $mform->setDefault('roleid', $plugin->get_config('default_roleid'));

        $mform->addElement('textarea', 'customtext1', get_string('attrsyntax', 'enrol_attributes'), array('cols'=>'60', 'rows'=>'8'));
        $mform->addHelpButton('customtext1', 'attrsyntax', 'enrol_attributes');

        $mform->addElement('checkbox', 'customint1', get_string('removewhenexpired', 'enrol_attributes'));
        $mform->addHelpButton('customint1', 'removewhenexpired', 'enrol_attributes');

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'courseid');
        $mform->setType('courseid', PARAM_INT);

        $this->add_action_buttons(true, ($instance->id ? null : get_string('addinstance', 'enrol')));

        $this->set_data($instance);
    }


    function add_action_buttons($cancel = true, $submitlabel=null){
        if (is_null($submitlabel)){
            $submitlabel = get_string('savechanges');
        }
        $mform =& $this->_form;
        if ($cancel){
            //when two elements we need a group
            $buttonarray=array();
            $buttonarray[] = &$mform->createElement('submit', 'submitbutton', $submitlabel);
            $buttonarray[] = &$mform->createElement('cancel');
            $buttonarray[] = &$mform->createElement('button', 'purge', get_string('purge', 'enrol_attributes'), array('onclick' => 'enrol_attributes_purge(\''.  addslashes(get_string('confirmpurge', 'enrol_attributes')).'\');'));
            $buttonarray[] = &$mform->createElement('button', 'force', get_string('force', 'enrol_attributes'), array('onclick' => 'enrol_attributes_force(\''.  addslashes(get_string('confirmforce', 'enrol_attributes')).'\');'));
            $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
            $mform->closeHeaderBefore('buttonar');
        }
    }


}

