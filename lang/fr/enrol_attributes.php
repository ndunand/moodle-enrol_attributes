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

$string['pluginname'] = 'Inscription par champs du profil utilisateur';
$string['defaultrole'] = 'Rôle par défaut';
$string['defaultrole_desc'] = 'Rôle à attribuer par défaut avec ce plugin (chaque instance peut avoir un réglage différent).';
$string['attrsyntax'] = 'Règles sur les champs du profil utilisateur';
$string['attrsyntax_help'] = '<p>Ces règles ne peuvent utiliser que des champs du profil ajoutés.</p>';
$string['attributes:config'] = 'Configurer des instances du plugin';
$string['attributes:manage'] = 'Gérer les utilisateurs inscrits';
$string['attributes:unenrol'] = 'Désinscrire des utilisateurs du cours';
$string['attributes:unenrolself'] = 'Se désinscrire du cours';
$string['ajax-error'] = 'Une erreur est survenue';
$string['ajax-okpurged'] = 'OK, les inscriptions ont été purgées';
$string['ajax-okforced'] = 'OK, {$a} personnes ont été inscrites';
$string['purge'] = 'Purger les inscriptions';
$string['force'] = 'Forcer les inscriptions';
$string['confirmforce'] = 'Ceci (ré)inscrira tous les utilisateurs correspondant à cette règle.';
$string['confirmpurge'] = 'Ceci supprimera toutes les inscriptions correspondant à cette règle.';
$string['mappings'] = 'Liens entre les attributs';
$string['mappings_desc'] = 'Liste de valeurs (une par ligne), <br>de la forme : <code>variable d\'environnement du serveur : nom abrégé du champ du profil utilisateur étendu</code>, p.ex. : <br /><code>Shib-SwissEP-HomeOrganizationType:homeorganizationtype</code>';
$string['removewhenexpired'] = 'Désinscrire après expiration des attributs';
$string['removewhenexpired_help'] = 'Désinscrire les utilisateurs à la connexion si leurs attributs ne correspondent plus à la règle.';
