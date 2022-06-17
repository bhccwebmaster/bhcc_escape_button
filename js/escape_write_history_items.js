/**
 * @file
 * A JavaScript file which writes some dummy browser history on escape.
 */

(function ($, Drupal) {

  Drupal.behaviors.escape_write_history_items = {

    attach: function (context) {

      let settings = drupalSettings.bhccEscapeButton;

      // Store some URLs to cycle through, creating a safe browser history.
      // There needs to be at least 15 to clear through the History items
      // visible on the menu bar History of the browser.
      const historyItems = settings.historyItems;

      const doRedirect = function(e) {

        if (typeof(e) != "undefined" && e.data.openNewTab === true) {
          window.open($('#escape-button').attr('href'), '_blank');
        }

        // Grab the stored value for the history item key to use.
        let historyItemKey = window.sessionStorage.getItem('historyItemKey');

        // If there's no value currently stored, initialise at 0.
        if (historyItemKey == null) {
          window.sessionStorage.setItem('historyItemKey', '0');
          historyItemKey = 0;
        }

        // Check for whether:
        // 1. we've finished running through the items, or
        // 2. somehow we've ended up on a higher key than exists in the array.
        // n.b. number 2 should never happen, but it did during development.
        if (historyItemKey >= historyItems.length) {
          window.sessionStorage.removeItem('historyItemKey');
          window.sessionStorage.setItem('inProcessOfRedirecting', 'false');

          // End the process.
          return;
        }

        // Set flag because we've started the redirecting process.
        window.sessionStorage.setItem('inProcessOfRedirecting', 'true');

        $.post('/ajax/history', {nodeID: historyItems[historyItemKey]}, function(data) {

          $.each(data, function(index, value) {
            // Also add the item to the browser's state history.
            history.pushState({'url': value.url}, '', value.url);

            // Run the actual redirect command.
            Drupal.AjaxCommands.prototype.redirect($.ajax, value);
          })
        });

        // Redirect the page to the selected history item.
        // window.location = historyItems[historyItemKey];
      };

      // Grab the current key from session storage. Could be empty.
      let historyItemKey = window.sessionStorage.getItem('historyItemKey');

      $(document).ready(function() {
        // Check whether we're in the middle of running the redirects.
        if (window.sessionStorage.getItem('inProcessOfRedirecting') === 'true') {

          if (context !== document) {
            return;
          }

          // Increment key for the next time around.
          historyItemKey++;

          // Store new key so it persists after refresh.
          window.sessionStorage.setItem('historyItemKey', historyItemKey);

          // Run the function again.
          doRedirect();
        }
      });

      // Run the history re-write if the button is clicked.
      $('#escape-button', context).on('click', {openNewTab: true}, doRedirect);

      // Or if a keyboard button is pressed (we check whether it's the Esc key).
      $(document).on('keydown', function(e) {
        if (e.key && e.key === 'Escape') {
          doRedirect();
        }
      });

    }
  }
})(jQuery, Drupal);
