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
 * @copyright  2018 Université de Lausanne (@link http://www.unil.ch}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require_once(dirname(__FILE__) . '/../../../config.php');
require_once("$CFG->libdir/clilib.php");
require_once($CFG->dirroot . '/enrol/attributes/lib.php');

// Now get cli options.
list($options, $unrecognized) = cli_get_params(array('help' => false), array('h' => 'help'));

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if ($options['help']) {
    $help = "Execute enrol sync with user profile attributes.
The enrol_attributes plugin must be enabled and properly configured.

Options:
-h, --help            Print out this help

Example:
\$ sudo -u www-data /usr/bin/php enrol/attributes/cli/sync.php
";

    echo $help;
    die;
}

if (!enrol_is_enabled('attributes')) {
    cli_error('enrol_attributes plugin is disabled, synchronisation stopped', 2);
}

$timezero = time();

\enrol_attributes_plugin::process_enrolments();

$deltatime = time() - $timezero;

echo "Done in $deltatime seconds.\n";

