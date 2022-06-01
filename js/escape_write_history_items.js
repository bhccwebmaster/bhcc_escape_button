/**
 * @file
 * A JavaScript file which rewrites browser history for SITC escape.
 */

(function ($, Drupal) {

  Drupal.behaviors.sitc_rewrite_history = {

    attach: function (context) {

      // Store some URLs to cycle through, creating a safe browser history.
      // There needs to be at least 15 to clear through the History items
      // visible on the menu bar History of the browser.
      const historyItems = [
        'https://bhcclocalgov.ddev.site/rubbish-recycling-and-streets/recycling/how-use-your-recycling-bins-or-boxes',
        'https://bhcclocalgov.ddev.site/rubbish-recycling-and-streets/rubbish/report-missed-bin-or-box-collection',
        'https://bhcclocalgov.ddev.site/rubbish-recycling-and-streets/rubbish/get-new-or-different-sized-bin-or-box',
        'https://bhcclocalgov.ddev.site/rubbish-recycling-and-streets/recycling/how-use-communal-recycling-bins',
        'https://bhcclocalgov.ddev.site/rubbish-recycling-and-streets/rubbish/how-dispose-business-and-trade-rubbish-and-recycling',
        'https://bhcclocalgov.ddev.site/rubbish-recycling-and-streets/recycling/what-you-can-take-our-recycling-sites-and-where-find-them',
        'https://bhcclocalgov.ddev.site/rubbish-recycling-and-streets/recycling/recycle-clothes-and-shoes',
        'https://bhcclocalgov.ddev.site/rubbish-recycling-and-streets/recycling/recycle-electrical-items',
        'https://bhcclocalgov.ddev.site/rubbish-recycling-and-streets/recycling',
        'https://bhcclocalgov.ddev.site/rubbish-recycling-and-streets/recycling-points-0',
        'https://bhcclocalgov.ddev.site/rubbish-recycling-and-streets/recycling/what-you-can-take-our-recycling-sites-and-where-find-them',
        'https://bhcclocalgov.ddev.site/rubbish-recycling-and-streets/recycling/waste-we-cant-collect-or-accept-our-recycling-sites',
        'https://bhcclocalgov.ddev.site/rubbish-recycling-and-streets/recycling/recycle-clothes-and-shoes',
        'https://bhcclocalgov.ddev.site/rubbish-recycling-and-streets/recycling/recycle-electrical-items',
        'https://bhcclocalgov.ddev.site/rubbish-recycling-and-streets/recycling/recycling-z',
      ];

      const doRedirect = function(e) {

        // if (e.key && e.key !== 'Escape') {
        //   return;
        // }

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

        // Redirect the page to the selected history item.
        window.location.replace(historyItems[historyItemKey]);
      };

      // Grab the current key from session storage. Could be empty.
      let historyItemKey = window.sessionStorage.getItem('historyItemKey');

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

      // Run the history re-write if the button is clicked.
      $('#escape-button', context).on('click', doRedirect);
      // Or if a keyboard button is pressed (we check whether it's the Esc key).
      $(document).on('keydown', doRedirect);
    }
  }
})(jQuery, Drupal);
