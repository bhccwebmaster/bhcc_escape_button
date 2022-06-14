/**
 * @file
 * A JavaScript file which writes some dummy browser history on escape.
 */

(function ($, Drupal) {

  Drupal.behaviors.escape_write_history_items = {

    attach: function (context) {

      let settings = drupalSettings.bhccEscapeButton;

      if (context !== document) {
        return;
      }

      const _doRedirect = function(e) {
        $.post('/ajax/history', { history_write_in_progress: true }, function(data) {

          $.each(data, function(index, value) {
            // Also add the item to the browser's state history.
            history.pushState({}, '', value.url)

            // Run the actual redirect command.
            Drupal.AjaxCommands.prototype.redirect($.ajax, value);
          })
        });
      };

      // If we're in the process of running the redirects,
      // continue with the next one.
      if (settings.history_write_in_progress) {
        _doRedirect();
      }

      // Register listener for click of escape button.
      $('#escape-button', context).on('click', _doRedirect);

      // @todo: Or if a keyboard button is pressed (we check whether it's the Esc key).
      // $(document).on('keydown', doRedirect);
    }
  }
})(jQuery, Drupal);
