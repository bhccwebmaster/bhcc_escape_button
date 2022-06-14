<?php

namespace Drupal\bhcc_escape_button\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;

/**
 * Class WriteHistoryController.
 */
class WriteHistoryController extends ControllerBase {

  /**
   * Runs through a set of redirects until it has performed them all.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The ajax response.
   */
  public function writeHistory(): AjaxResponse {

    // Grab the key_value storage.
    $key_value_storage = \Drupal::keyValue('bhcc_escape_button');

    $history_write_in_progress = \Drupal::request()->get('history_write_in_progress');

    // Default to false for whether we're in progress.
    $key_value_storage->set('history_write_in_progress', FALSE);

    // Grab the parameter passed through from the js library.
    if (!empty($history_write_in_progress) && $history_write_in_progress) {
      $key_value_storage->set('history_write_in_progress', TRUE);
    }

    // Set up the response we'll be adding to.
    $response = new AjaxResponse();

    // Return out if we're not in progress.
    if (!$history_write_in_progress) {
      return $response;
    }

    // Reset these values if they're missing.
    $key_value_storage->setIfNotExists('key', 0);

    // Grab all the key-value pairs.
    $bhcc_escape_button = $key_value_storage->getAll();

    // Pull out the history node IDs to run through.
    $history_items = $bhcc_escape_button['history_node_ids'];

    // Check for whether:
    // 1. we've finished running through the items, or
    // 2. somehow we've ended up on a higher key than exists in the array.
    // n.b. number 2 should never happen, but it did during development.
    if ($bhcc_escape_button['key'] >= count($history_items)) {

      // Reset counter.
      $key_value_storage->set('key', 0);

      $key_value_storage->delete('history_write_in_progress');

      // We've finished the redirect process on this occasion.
      // Return blank response.
      return $response;
    }
    else {

      // If we get here, we still have redirect items to get through.
      // Increment the key so on the next go round we'll have the next page.
      $next_key = $bhcc_escape_button['key'];
      $next_key++;

      // Store the incremented value.
      $key_value_storage->set('key', $next_key);

      // Build the URL for the redirect command.
      $url = Url::fromRoute('entity.node.canonical', ['node' => $history_items[$bhcc_escape_button['key']]], [
        'absolute' => TRUE
      ])->toString();

      return $response->addCommand(new RedirectCommand($url));
    }

  }

}
