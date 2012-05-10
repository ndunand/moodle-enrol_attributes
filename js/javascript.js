

var enrol_attributes_purge = enrol_attributes_force = function(){ alert('please wait for page to finish loading'); };


(function($) {

    $(document).ready(function(){

        var $shib_rules = $('<div>').attr('id', 'shib-rules'),
            $textarea = $("#id_customtext1");

        try {
            var shib_boolconfig = eval('(' + $textarea.val() + ')');
        }
        catch(e) {
            var shib_boolconfig = {"rules": ''};
        }

        $textarea
            .hide()
            .parent().append($shib_rules);

        $shib_rules.booleanEditor({
            rules: shib_boolconfig.rules,
            change: enrol_attributes_updateExpr
        });

        if ($('input[name=id]').val() && $('input[name=courseid]').val()) {
            // "Purge" button
            enrol_attributes_purge = function(msg){
                if (confirm(msg)) {
                    var datasend = 'courseid=' + $('input[name=courseid]').val() + '&sesskey=' + M.cfg.sesskey + '&instanceid=' + $('input[name=id]').val();
                    $.post('purge.php', datasend, function(data){
                        alert(data);
                    });
                }
            }
            // "Force" button
            enrol_attributes_force = function(msg){
                if (confirm(msg)) {
                    var datasend = 'courseid=' + $('input[name=courseid]').val() + '&sesskey=' + M.cfg.sesskey + '&instanceid=' + $('input[name=id]').val();
                    $.post('force.php', datasend, function(data){
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
        var expressionStr   = $(this).booleanEditor('getExpression'),
            serializedObj   = $(this).booleanEditor('serialize'),
            serializedJson  = $(this).booleanEditor('serialize', {mode:'json'} );

        $("#id_customtext1").val(serializedJson);
    }

})(jQuery)
