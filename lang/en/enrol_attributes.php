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

$string['pluginname'] = 'Enrol by user profile fields';
$string['defaultrole'] = 'Default role';
$string['defaultrole_desc'] = 'Default role used to enrol people with this plugin (each instance can override this).';
$string['attrsyntax'] = 'User profile fields rules';
$string['attrsyntax_help'] = '<p>These rules can only use custom user profile fields.</p>';
$string['attributes:config'] = 'Configure plugin instances';
$string['attributes:manage'] = 'Manage enrolled users';
$string['attributes:unenrol'] = 'Unenrol users from the course';
$string['attributes:unenrolself'] = 'Unenrol self from the course';
$string['ajax-error'] = 'An error occured';
$string['ajax-okpurged'] = 'OK, enrolments have been purged';
$string['ajax-okforced'] = 'OK, {$a} users have been enrolled';
$string['purge'] = 'Purge enrolments';
$string['force'] = 'Force enrolments now';
$string['confirmforce'] = 'This will (re)enrol all users corresponding to this rule.';
$string['confirmpurge'] = 'This will remove all enrolments corresponding to this rule.';
$string['mappings'] = 'Shibboleth mappings';
$string['mappings_desc'] =
        'When using Shibboleth authentication, this plugin can automatically update a user\'s profile upon each login.<br><br>For instance, if you want to update the user\'s <code>homeorganizationtype</code> profile field with the Shibboleth attribute <code>Shib-HomeOrganizationType</code> (provided that is the environment variable available to the server during login), you can enter on one line: <code>Shib-HomeOrganizationType:homeorganizationtype</code><br>You may enter as many lines as needed.<br><br>To not use this feature or if you don\'t use Shibboleth authentication, simple leave this empty.';
$string['profilefields'] = 'Profile fields to be used in the selector';
$string['profilefields_desc'] =
        'Which user profile fields can be used when configuring an enrolment instance?<br><br><b>If you don\'t select any role here, this makes the plugin moot and hence disables its use in courses.</b><br>The feature below may however still be used in this case.';
$string['removewhenexpired'] = 'Unenrol after attributes expiration';
$string['removewhenexpired_help'] = 'Unenrol users upon login if they don\'t match the attribute rule anymore.';
$string['addcondition'] = "Add condition";
$string['addgroup'] = "Add group";
$string['deletecondition'] = "Delete condition";
$string['privacy:metadata'] = 'The Enrol by user profile fields enrolment plugin does not store any personal data.';
$string['defaultwhenexpired'] = 'Default behaviour after attributes expiration';
$string['defaultwhenexpired_desc'] = 'What to do with users that don\'t match the attribute rule anymore. This setting can be overridden in each enrollment instance.';
$string['whenexpired'] = 'Behaviour after attributes expiration';
$string['whenexpired_help'] = 'What to do with users that don\'t match the attribute rule anymore.';
$string['whenexpireddonothing'] = 'Leave user enrolled';
$string['whenexpiredremove'] = 'Unenroll user';
$string['whenexpiredsuspend'] = 'Suspend user';
$string['observelogins'] = 'Enrol users immediately at login';
$string['observelogins_desc'] = 'Try to enrol users immediately when they log in. This can have a performance impact on your site, deactivate this if lots of users log in at the same time and their being enrolled at once becomes a bottleneck.';
$string['cachedef_dbquerycache'] = 'DB query cache';

