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
 * @copyright  IMT Lille Douai <imt-lille-douai.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Romain DELEAU
 */

defined('MOODLE_INTERNAL') || die;

function xmldb_enrol_attributes_upgrade($oldversion) {
    global $CFG, $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2021082408) {

        // Define table
        $table = new xmldb_table('enrol_attributes_groups');

        // Adding fields
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null);
        $table->add_field('enrolid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'id');
        $table->add_field('groupid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'id');

        // Adding keys
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('enrol', XMLDB_KEY_FOREIGN, ['enrolid'], 'enrol', ['id']);
        $table->add_key('group', XMLDB_KEY_FOREIGN, ['groupid'], 'groups', ['id']);

        // Create table if not exist
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Apply savepoint reached.
        upgrade_plugin_savepoint(true, 2021082408, 'enrol', 'attributes');
    }

    return true;

}