<?php

namespace Drupal\bhcc_escape_button\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

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
   * Core request_stack service.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Route match service.
   *
   * @var Drupal\Core\Routing\CurrentRouteMatch
   */
  protected $routeMatch;

  /**
   * {@inheritdoc}
   */
  public function __construct(RouteMatchInterface $route_match, RequestStack $request_stack) {
    $this->routeMatch = $route_match;
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_route_match'),
      $container->get('request_stack'),
    );
  }

  /**
   * Runs through a set of redirects until it has performed them all.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The ajax response.
   */
  public function writeHistory(): AjaxResponse {

    // Grab the parameter passed through from the js library.
    $node_id = $this->requestStack->getCurrentRequest()->get('nodeID');

    // Set up the response we'll be adding to.
    $response = new AjaxResponse();

    // Build the URL for the redirect command.
    $url = Url::fromRoute('entity.node.canonical', ['node' => $node_id], [
      'absolute' => TRUE,
    ])->toString();

    return $response->addCommand(new RedirectCommand($url));
  }

}
