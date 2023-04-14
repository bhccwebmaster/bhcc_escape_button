<?php

namespace Drupal\bhcc_escape_button\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\node\NodeInterface;

/**
 * Class bhcc escape button WriteHistoryController.
 */
class WriteHistoryController extends ControllerBase {

  /**
   * Entity Type Manager.
   *
   * @var Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * Route match service.
   *
   * @var Drupal\Core\Routing\CurrentRouteMatch
   */
  protected $routeMatch;

  /**
   * Assign the node block.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, RouteMatchInterface $route_match) {
    $this->routeMatch = $route_match;
    // $this->entityTypeManager = $entity_type_manager;
    if ($this->routeMatch->getParameter('node')) {
      $this->node = $this->routeMatch->getParameter('node');
      if (!$this->node instanceof NodeInterface) {
        $node_storage = $this->entityTypeManager->getStorage('node');
        $this->node = $node_storage->load($this->node);
      }
    }
  }

  /**
   * Runs through a set of redirects until it has performed them all.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The ajax response.
   */
  public function writeHistory(): AjaxResponse {

    // Grab the parameter passed through from the js library.
    $node_id = $this->routeMatch->getParameter('nodeID');

    // Set up the response we'll be adding to.
    $response = new AjaxResponse();

    // Build the URL for the redirect command.
    $url = Url::fromRoute('entity.node.canonical', ['node' => $node_id], [
      'absolute' => TRUE,
    ])->toString();

    return $response->addCommand(new RedirectCommand($url));
  }

}
