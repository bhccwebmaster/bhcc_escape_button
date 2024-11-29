/**
 * @file
 * A JavaScript file which writes some dummy browser history on escape.
 */

(function ($, Drupal) {

  let keypressCounter = 0;
  let lastKeyWasModified = false;
  let timeoutTime = 5000; // milliseconds
  let keypressTimeoutId = null;
  let overlay;
  let indicatorContainer = $('#js-exit-keypress-indicator');

  /**
   * Display the overlay.
   */
  function updateIndicator() {

    if (!indicatorContainer) {
      return;
    }

    // Make the container visible/hidden.
    $(indicatorContainer).toggleClass('hidden', keypressCounter === 0);

    // Loop through the 3 lights and update according to keypress index.
    const indicators = $('.exit-keypress-indicator__light', indicatorContainer);
    $.each(indicators, function(index, indicator) {

      // Switch from displaying the inactive to the active icon.
      if (index < keypressCounter) {
        $('.shift-indicator-icon--inactive', indicator).addClass('hidden');
        $('.shift-indicator-icon--active', indicator).removeClass('hidden');
      }
    })
  }

  /**
   * Reset keypress timer.
   */
  function resetKeypressTimer() {

    clearTimeout(keypressTimeoutId);
    keypressTimeoutId = null;

    keypressCounter = 0;

    // Reset keypress indicator.
    updateIndicator();
  }

  /**
   * Set keypress timer.
   */
  function setKeypressTimer() {

    // Clear any existing timeout. This is so only one timer is running even if
    // there are multiple key presses in quick succession.
    clearTimeout(keypressTimeoutId);

    // Set a fresh timeout
    keypressTimeoutId = setTimeout(
      resetKeypressTimer,
      timeoutTime
    );
  }

  /**
   * Display the overlay.
   */
  function displayBlankOverlay() {

    $('#bhcc-escape-overlay').removeClass('hidden');
  }

  /**
   * Initialise the overlay.
   *   - Creates markup.
   *   - Adds to body.
   */
  function initBlankOverlay() {

    overlay = $('<div id="bhcc-escape-overlay"></div>')
      .addClass([
        'hidden',
      ])
      .attr('style', [
        'background: white;',
        'position: absolute;',
        'top: 0;',
        'left: 0;',
        'width: 100%;',
        'height: 100%;',
        'z-index: 1000;',
      ].join(' '));

    $('body').prepend(overlay);
  }

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

      // Initialise the blank overlay to hide the page with while the following
      // one loads in.
      // This is particularly important for users with slow network connections.
      initBlankOverlay();

      // Run the history re-write if the button is clicked.
      $('#escape-button', context).on('click', {openNewTab: true}, function(e) {
        e.preventDefault();
        displayBlankOverlay(e);
        doRedirect(e);
      });

      document.addEventListener('keyup', function(event) {

        // Or if a keyboard button is pressed (we check whether it's the Esc key).
        if (event.key && event.key === 'Escape') {
          displayBlankOverlay();

          // Trigger a click event on the button so the new window opens.
          $('#escape-button', context).trigger('click');
        }

        // Or the shift key is pressed 3 times in quick succession.
        // Detect if the 'Shift' key has been pressed. We want to only do things if it
        // was pressed by itself and not in a combination with another key—so we keep
        // track of whether the preceding keyup had shiftKey: true on it, and if it
        // did, we ignore the next Shift keyup event.
        //
        // This works because using Shift as a modifier key (e.g. pressing Shift + A)
        // will fire TWO keyup events, one for A (with e.shiftKey: true) and the other
        // for Shift (with e.shiftKey: false).
        if (
          (event.key === 'Shift' || event.keyCode === 16 || event.which === 16) &&
          !lastKeyWasModified
        ) {
          keypressCounter += 1;
          updateIndicator();

          if (keypressCounter >= 3) {
            keypressCounter = 0;

            displayBlankOverlay();

            // Trigger a click event on the button so the new window opens.
            $('#escape-button', context).trigger('click');
          }

          setKeypressTimer();
        } else if (keypressTimeoutId !== null) {
          // If the user pressed any key other than 'Shift', after having pressed
          // 'Shift' and activating the timer, stop and reset the timer.
          resetKeypressTimer();
        }

        // Keep track of whether the Shift modifier key was held during this keypress
        lastKeyWasModified = event.shiftKey;
      }, true);

    }
  }
})(jQuery, Drupal);
