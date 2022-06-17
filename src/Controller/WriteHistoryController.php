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

    // Grab the parameter passed through from the js library.
    $node_id = \Drupal::request()->get('nodeID');

    // Set up the response we'll be adding to.
    $response = new AjaxResponse();

    // Build the URL for the redirect command.
    $url = Url::fromRoute('entity.node.canonical', ['node' => $node_id], [
      'absolute' => TRUE,
    ])->toString();

    return $response->addCommand(new RedirectCommand($url));
  }

}
