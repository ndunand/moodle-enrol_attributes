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

var enrol_attributes_purge = enrol_attributes_force = function () {
    alert('please wait for page to finish loading');
};

(function ($) {

    $(document).ready(function () {

        var $shib_rules = $('<div>').attr('id', 'shib-rules'),
            $textarea = $("#id_customtext1");

        try {
            var shib_boolconfig = eval('(' + $textarea.val() + ')');
        }
        catch (e) {
            var shib_boolconfig = {"rules": ''};
        }

        $textarea
            .hide()
            .parent().append($shib_rules);

        $shib_rules.booleanEditor({
            rules:  shib_boolconfig.rules,
            change: enrol_attributes_updateExpr
        });

        if ($('input[name=id]').val() && $('input[name=courseid]').val()) {
            // "Purge" button
            enrol_attributes_purge = function (msg) {
                if (confirm(msg)) {
                    var datasend = 'courseid=' + $('input[name=courseid]').val() + '&sesskey=' + M.cfg.sesskey + '&instanceid=' + $('input[name=id]').val();
                    $.post('purge.php', datasend, function (data) {
                        alert(data);
                    });
                }
            }
            // "Force" button
            enrol_attributes_force = function (msg) {
                if (confirm(msg)) {
                    var datasend = 'courseid=' + $('input[name=courseid]').val() + '&sesskey=' + M.cfg.sesskey + '&instanceid=' + $('input[name=id]').val();
                    $.post('force.php', datasend, function (data) {
                        alert(data);
                    });
                }
            }
        }
        else {
            $('#id_purge, #id_force').remove();
        }

    });


    function enrol_attributes_updateExpr() {
        var expressionStr = $(this).booleanEditor('getExpression'),
            serializedObj = $(this).booleanEditor('serialize'),
            serializedJson = $(this).booleanEditor('serialize', {mode: 'json'});

        $("#id_customtext1").val(serializedJson);
    }

})(jQuery)

