(function ($, Drupal) {

    /**
     * Registers behaviours for analyzer json codestyle
     */
    Drupal.behaviors.elastic_json_readonly = {
        attach: function (context) {
            $(context).find('textarea[data-editor]').each(function () {
                var textarea = $(this);
                var mode = textarea.data('editor');
                var theme = textarea.data('editor-theme');
                var editDiv = $('<div>', {
                    position: 'absolute',
                    width: textarea.width(),
                    height: textarea.height() * 3,
                    'class': textarea.attr('class')
                }).insertBefore(textarea);
                textarea.css('display', 'none');
                var editor = ace.edit(editDiv[0]);
                editor.renderer.setShowGutter(true);
                editor.getSession().setValue(textarea.val());
                editor.getSession().setMode("ace/mode/" + mode);
                editor.setTheme("ace/theme/" + theme);
                editor.setReadOnly(true);
            });
        }
    };

}(jQuery, Drupal));