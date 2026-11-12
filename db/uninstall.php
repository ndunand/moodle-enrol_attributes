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
 * Attributes enrolment plugin uninstallation.
 *
 * Removes all remaining enrol attributes instances. The user enrolments
 * belonging to those instances and the role assignments of the component
 * are purged by core (see \core\plugininfo\enrol::uninstall_cleanup()),
 * which runs after this file.
 *
 * @package    enrol_attributes
 * @author     Nicolas Dunand <Nicolas.Dunand@unil.ch>
 * @copyright  2012-2024 Université de Lausanne {@link http://www.unil.ch}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_enrol_attributes_uninstall() {
    global $DB;

    // Drop the legacy groups-mapping table if it is still around
    // (created by upgrade.php in earlier 2.x releases, unused since 2.10).
    $dbman = $DB->get_manager();
    $table = new xmldb_table('enrol_attributes_groups');
    if ($dbman->table_exists($table)) {
        $dbman->drop_table($table);
    }

    return true;
}