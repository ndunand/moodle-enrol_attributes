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
 * @author     Julien Furrer <Julien.Furrer@unil.ch>
 * @copyright  2012-2015 Université de Lausanne (@link http://www.unil.ch}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

;
(function ($) {

    $.booleanEditor = {
        defaults:     {
            rules:  [],
            change: null
        },
        paramList:    M.enrol_attributes.paramList,
        operatorList: [
            {label: " = ", value: "=="},
//             {label: "=/=", value: "!="},
//             {label: "contains", value: "contains"},
        ]
    };

    $.fn.extend({

        booleanEditor: function (options) {
            var isMethodCall = (typeof options == 'string'), // is it a method call or booleanEditor instantiation  ?
                args = Array.prototype.slice.call(arguments, 1);


            if (isMethodCall) switch (options) {
                case 'serialize':
                    var mode = ( args[0] ) ? args[0].mode : '',
                        ser_obj = serialize(this);

                    switch (mode) {
                        case 'json':
                            return $.toJSON(ser_obj);
                            break;

                        case 'object':
                        default:
                            return ser_obj;
                    }
                    break;
                case 'getExpression':
                    return getBooleanExpression(this);
                    break;
                default:
                    return;
            }

            settings = $.extend({}, $.booleanEditor.defaults, options);

            return this.each(function () {
                if (settings.change) {
                    $(this).data('change', settings.change)
                }
                $(this)
                    .addClass("boolean-editor")
                    .append(createRuleList($('<ul></ul>'), settings.rules));
                changed(this);
            });
        }

    });


    function serialize(root_elem) {
        var ser_obj = {rules: []};
        var group_c_op = $("select:first[name='cond-operator']", root_elem).val();
        if (group_c_op)
            ser_obj.cond_op = group_c_op;

        $("ul:first > li", root_elem).each(function () {
            r = $(this);
            if (r.hasClass('group')) {
                ser_obj['rules'].push(serialize(this));
            }
            else {
                var cond_obj = {
                    param:   $("select[name='comparison-param'] option:selected", r).val(),
                    comp_op: $("select[name='comparison-operator']", r).val(),
                    value:   $("input[name='value']", r).val()
                };
                var cond_op = $("select[name='cond-operator']", r).val();
                if (cond_op)
                    cond_obj.cond_op = cond_op;
                ser_obj['rules'].push(cond_obj);
            }
        });
        return ser_obj;
    }


    function getBooleanExpression(editor) {
        var expression = "";
        $("ul:first > li", editor).each(function () {
            r = $(this);
            var c_op = $("select[name='cond-operator']", r).val();
            if (c_op != undefined) c_op = '<span class="cond-op"> ' + c_op + ' </span>';

            if (r.hasClass('group')) {
                expression += c_op + '<span class="group-op group-group">(</span>' + getBooleanExpression(this) + '<span class="group-op group-group">)</span>';
            }
            else {
                expression += [
                    c_op,
                    '<span class="group-op group-cond">(</span>',
                    '<span class="comp-param">' + $("select[name='comparison-param'] option:selected", r).text() + '</span>',
                    '<span class="comp-op"> ' + $("select[name='comparison-operator']", r).val() + ' </span>',
                    '<span class="comp-val">' + '\'' + $("input[name='value']", r).val() + '\'' + '</span>',
                    '<span class="group-op group-cond">)</span>'
                ].join("");
            }
        });
        return expression;
    }


    function changed(o) {
        $o = $(o);
        if (!$o.hasClass('boolean-editor')) {
            $o = $o.parents('.boolean-editor').eq(0);
        }
        if ($o.data('change')) {
            $o.data('change').apply($o.get(0));
        }
    }


    function createRuleList(list_elem, rules) {
        //var list_elem = $(list_elem);

        if (list_elem.parent("li").eq(0).hasClass("group")) {
            console.log("inside a group");
            return;
        }

        if (rules.length == 0) {
            // No rules, create a new one
            list_elem.append(getRuleConditionElement({first: true}));

        } else {
            // Read all rules
            for (var r_idx = 0; r_idx < rules.length; r_idx++) {
                var r = rules[r_idx];
                r['first'] = (r_idx == 0);

                // If the rule is an array, create a group of rules
                if (r.rules && (typeof r.rules[0] == 'object')) {
                    r.group = true;
                    var rg = getRuleConditionElement(r);
                    list_elem.append(rg);
                    createRuleList($("ul:first", rg), r.rules);
                }
                else {
                    list_elem.append(getRuleConditionElement(r));
                }
            }
        }

        return list_elem;
    };


    /**
     *    Build the HTML code for editing a rule condition.
     *    A rule is composed of one or more rule conditions linked by boolean operators
     */
    function getRuleConditionElement(config) {
        config = $.extend({},
            {
                first:   false,
                group:   false,
                cond_op: null,
                param:   null,
                comp_op: null,
                value:   ''
            },
            config
        );


        // If group flag is set, wrap content with <ul></ul>, content is obtained by a recursive call
        // to the function, passing a copy of config with flag group set to false
        var cond_block_content = $('<div class="sre-condition-box"></div>');
        if (config.group) {
            cond_block_content.append('<ul></ul>');
        } else {
            cond_block_content
                .append(makeSelectList({                                    // The list of parameters to be compared
                    name:           'comparison-param',
                    params:         $.booleanEditor.paramList,
                    selected_value: config.param
                }).addClass("comp-param"))
                .append($('<span>').addClass("comp-op").text('='))
//                .append( makeSelectList({                                    // The comparison operator
//                    name: 'comparison-operator',
//                    params: $.booleanEditor.operatorList,
//                    selected_value: config.comp_op
//                }).addClass("comp-op"))
                .append($('<input type="text" name="value" value="' + config.value + '"/>')
                    .change(function () {
                        changed(this)
                    })
            );    // The value of the comparions
        }

        var ruleConditionElement = $('<li></li>')
            .addClass((config.group) ? 'group' : 'rule')
            .append(createRuleOperatorSelect(config))
            .append(cond_block_content)
            .append(createButtonPannel())


        return ruleConditionElement;
    };


    function createRuleOperatorSelect(config) {
        return (config.first) ? '' :
            makeSelectList({
                'name':         'cond-operator',
                params:         [
                    {label: 'AND', value: 'and'},
                    {label: 'OR', value: 'or'}
                ],
                selected_value: config.cond_op
            }).addClass('sre-condition-rule-operator');
    }


    function createButtonPannel() {
        var buttonPannel = $('<div class="button-pannel"></div>')
            .append($('<button type="button" class="button-add-cond">add condition</button>')
                .click(function () {
                    addNewConditionAfter($(this).parents('li').get(0));
                })
        )
            .append($('<button type="button" class="button-add-group">add group</button>')
                .click(function () {
                    addNewGroupAfter($(this).parents('li').get(0));
                })
        )
            .append($('<button type="button" class="button-del-cond">delete</button>')
                .click(function () {
                    deleteCondition($(this).parents('li').eq(0));
                })
        );
        $('button', buttonPannel).each(function () {
            $(this)
                .focus(function () {
                    this.blur()
                })
                .attr("title", $(this).text())
                .wrapInner('<span/>');
        });
        return buttonPannel;
    }


    function makeSelectList(config) {
        config = $.extend({},
            {
                name:           'list_name',
                params:         [{label: 'label', value: 'value'}],
                selected_value: null
            },
            config);

        var selectList = $('<select name="' + config.name + '"></select>')
            .change(function () {
                changed(this);
            });
        $.each(config.params, function (i, p) {
            var p_obj = $('<option></option>')
                .attr({label: p.label, value: p.value})
                .text(p.label);
            if (p.value == config.selected_value) {
                p_obj.attr("selected", "selected");
            }
            p_obj.appendTo(selectList);
        });

        return selectList;
    }


    //
    //    -->> Conditions manipulation <<--
    //
    function addNewConditionAfter(elem, config) {
        getRuleConditionElement(config)
            .hide()
            .insertAfter(elem)
            .fadeIn("normal", function () {
                changed(elem)
            });

    }

    function addNewGroupAfter(elem, config) {
        getRuleConditionElement({group: true})
            .hide()
            .insertAfter(elem)
            .find("ul:first")
            .append(getRuleConditionElement($.extend({}, config, {first: true})))
            .end()
            .fadeIn("normal", function () {
                changed(elem)
            });
    }

    /*
     *
     *  Supprimer une condition : supprimer éventuellement le parent si dernier enfant,
     *  mettre à jour le parent dans tous les cas.
     *
     */
    function deleteCondition(elem) {
        if (elem.parent().parent().hasClass('boolean-editor')) {
            // Level 1
            if (elem.siblings().length == 0) {
                return;
            }

        } else {
            // Higher level
            if (elem.siblings().length == 0) {
                // The last cond of the group, target the group itself, to be removed
                elem = elem.parents('li').eq(0);
            }
        }
        p = elem.parent();
        elem.fadeOut("normal", function () {
            $(this).remove();
            $("li:first .sre-condition-rule-operator", ".boolean-editor ul").remove();
            changed(p);
        });
    }


})(jQuery);

