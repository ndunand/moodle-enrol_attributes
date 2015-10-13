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
 * @package    enrol
 * @subpackage attributes
 * @copyright  2012 Copyright Université de Lausanne, RISET {@link http://www.unil.ch/riset}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once('lib.php');

header('Content-type: application/javascript');


$customfieldrecords = $DB->get_records('user_info_field');

$customfields = array();

foreach ($customfieldrecords as $customfieldrecord) {
    $customfields[$customfieldrecord->shortname] = $customfieldrecord->name;
}

$items = array();

$mappings_str = get_config('enrol_attributes', 'mappings');
$mappings = explode("\n", str_replace("\r", '', $mappings_str));

foreach ($mappings as $mapping) {
    if (preg_match('/^\s*([^: ]+)\s*:\s*([^: ]+)\s*$/', $mapping, $matches) && array_key_exists($matches[2], $customfields)) {
        $items[] = array('label' => $customfields[$matches[2]], 'value' => $matches[2]);
    }
}

$jsvar = json_encode($items);

echo <<<EOF
M.enrol_attributes = M.enrol_attributes || {};
M.enrol_attributes.paramList = {$jsvar};
EOF;

