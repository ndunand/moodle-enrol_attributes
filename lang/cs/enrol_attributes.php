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

$string['pluginname'] = 'Zápis podle profilových polí';
$string['defaultrole'] = 'Standardní role';
$string['defaultrole_desc'] = 'Standardní role je použita pro zápis uživatelů (každá instance pluginu ji může změnit).';
$string['attrsyntax'] = 'Pravidla pro zápis podle profilových polí';
$string['attrsyntax_help'] = '<p>Tato pravidla mohou být definována pouze pro vlastní pole profilu.</p>';
$string['attributes:config'] = 'Nastavení instancí pluginu';
$string['attributes:manage'] = 'Správa zapsaných uživatelů';
$string['attributes:unenrol'] = 'Vyškrtnout uživatele z kurzu';
$string['attributes:unenrolself'] = 'Vyškrtnout sebe sama z kurzu';
$string['ajax-error'] = 'Neočekávaná chyba';
$string['ajax-okpurged'] = 'Všechny zápisy byly odstraněny';
$string['ajax-okforced'] = 'Uživatele byli zapsáni (počet: {$a})';
$string['purge'] = 'Odstranit zápisy';
$string['force'] = 'Okamžité vynucení zápisů';
$string['confirmforce'] = 'Tímto znovu zapíšete všechny uživatele podle následujícího pravidla';
$string['confirmpurge'] = 'Tímto smažete veškeré zápisy, které odpovídají následujícímu pravidlu.';
$string['mappings'] = 'Mapování Shibboleth atributů';
$string['mappings_desc'] =
        'Pokud používáte Shibboleth pro autentikaci, umožňuje tento plugin automatické propagování atributů do polí uživatelského profilu při každém loginu.<br><br>Například pokud chcete aktualizovat uživatelské pole <code>homeorganizationtype</code> Shibboleth atributem <code>Shib-HomeOrganizationType</code> (tato proměná musí být k dispozici serveru v průběhu loginu), můžete vložit řádek: <code>Shib-HomeOrganizationType:homeorganizationtype</code><br>Můžete vložit libovolný počet řádek.<br><br>Pokud nepoužíváte Shibboleth nebo si nechcete tuto funkcionalitu používat, nechte pole prázdné.';
$string['profilefields'] = 'Profilová pole, která budou použita pro výběr';
$string['profilefields_desc'] =
        'Specifikuje, která pole uživatelského profilu budou použita při konfiguraci jednotlivých pluginů?<br><br><b>Pokud nevyberete žádná pole, bude plugin v kurzech nedostupný.</b><br>Následující funkcionalita může přesto být použita.';
$string['removewhenexpired'] = 'Odhlásit, pokud uživatel nesplňuje podmínky (atribut není nadále přítomen)';
$string['removewhenexpired_help'] = 'Pokud uživatel nesplňuje dané pravidlo, je při přihlášení vyškrtnut z kurzu.';

