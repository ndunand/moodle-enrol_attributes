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
 * Database enrolment plugin installation.
 *
 * @package    enrol
 * @subpackage attributes
 * @copyright  2012 Nicolas Dunand {@link http://www.unil.ch/riset}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_enrol_attributes_install() {
//     global $CFG, $DB;

    // migrate old config settings first
//     if (isset($CFG->enrol_dbtype)) {
//         set_config('dbtype', $CFG->enrol_dbtype, 'enrol_attributes');
//         unset_config('enrol_dbtype');
//     } etc.

    // just make sure there are no leftovers after disabled plugin
//     if (!$DB->record_exists('enrol', array('enrol'=>'attributes'))) {
//         role_unassign_all(array('component'=>'enrol_attributes'));
//         return;
//     }
}
