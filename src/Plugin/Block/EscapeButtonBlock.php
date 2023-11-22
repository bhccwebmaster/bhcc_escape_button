<?php

namespace Drupal\bhcc_escape_button\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\core\Entity\EntityTypeManager;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\path_alias\AliasManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an escape button block.
 *
 * @Block(
 *   id = "escape_button_block",
 *   admin_label = @Translation("Escape button"),
 * )
 */
class EscapeButtonBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Assign the node block.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * Current path service.
   *
   * @var \Drupal\Core\Path\CurrentPathStack
   */
  protected $currentPath;

  /**
   * Entity Type Manager.
   *
   * @var Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * Route match service.
   *
   * @var \Drupal\Core\Routing\CurrentRouteMatch
   */
  protected $routeMatch;


  /**
   * Path alias manager.
   *
   * @var \Drupal\path_alias\AliasManagerInterface
   */
  protected $pathAliasManager;

  /**
   * Escape Button constructor.
   *
   * @param array $configuration
   *   The configuration to use.
   * @param string $plugin_id
   *   The plugin id.
   * @param array $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   * @param \Drupal\path_alias\AliasManagerInterface $alias_manager
   *   The path alias manager.
   * @param \Drupal\Core\Path\CurrentPathStack $current_path
   *   The current path stack.
   * @param \Drupal\Core\Entity\EntityTypeManager $entity_type_manager
   *   The entity type manager.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, RouteMatchInterface $route_match, AliasManagerInterface $alias_manager, CurrentPathStack $current_path, EntityTypeManager $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->routeMatch = $route_match;
    $this->currentPath = $current_path;
    $this->pathAliasManager = $alias_manager;
    $this->entityTypeManager = $entity_type_manager;

    if ($this->routeMatch->getParameter('node')) {
      $this->node = $this->routeMatch->getParameter('node');
      if (!$this->node instanceof NodeInterface) {
        $node_storage = $this->entityTypeManager->getStorage('node');
        $this->node = $node_storage->load($this->node);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match'),
      $container->get('path_alias.manager'),
      $container->get('path.current'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {

    $config = $this->getConfiguration();

    // If no config was entered, don't show the block.
    if (empty($config['display']) || empty($config['history'])) {
      return AccessResult::forbidden();
    }

    $history = array_filter($config['history']);
    $display = array_filter($config['display']);

    if (!empty($display['paths'])) {

      $current_path = $this->currentPath->getPath();
      $current_path_alias = $this->pathAliasManager->getAliasByPath($current_path);

      // Split the content of the paths field into an array.
      $paths = preg_split("(\r\n?|\n)", $display['paths']);

      // Loop through the values.
      foreach ($paths as $path) {

        // Ignore empty paths, causes false positives.
        if (empty($path)) {
          continue;
        }

        // Generate the regular expression to match the given path.
        $path = str_replace('*', '.*', $path);
        $pattern = '#^' . ltrim($path, '/') . '$#';

        // If the expression matches against the current path, allow the block.
        if (preg_match($pattern, ltrim($current_path_alias, '/'))) {
          return AccessResult::allowed();
        }
      }
    }

    // If the node we're on isn't one of the history items, hide the block.
    // Also hide if not a node. (History can only be nodes?)
    if (!$this->node instanceof NodeInterface || !in_array($this->node->id(), $history)) {
      return AccessResult::forbidden();
    }

    return parent::blockAccess($account);
  }

  /**
   * {@inheritdoc}
   */
  public function build() {

    $build['#attached']['library'][] = 'bhcc_escape_button/rewrite_history';

    $config = $this->getConfiguration();
    $history = array_filter($config['history']);

    // Pass through storage data to js library.
    // Used for testing whether redirecting is in progress.
    $build['#attached']['drupalSettings']['bhccEscapeButton']['historyItems'] = $history;

    // If we're on one of the redirect pages, don't show the escape button.
    $node = $this->routeMatch->getParameter('node');
    if ($node instanceof NodeInterface) {
      if (in_array($node->id(), $history)) {
        return $build;
      }
    }

    // Generate link to the first page in the history.
    if (!empty($config['new_tab'])) {
      // Use the url from config if possible.
      $link_url = Url::fromUri($config['new_tab']['url']);
    }
    else {
      // Otherwise default to the front page.
      $link_url = Url::fromRoute('<front>');
    }

    $link_title = Markup::create('Exit this page');
    $link = Link::fromTextAndUrl($link_title, $link_url)->toRenderable();

    // Add attributes to the link.
    $link['#attributes'] = [
      'target' => '_blank',
      'id' => 'escape-button',
      'class' => [
        'button',
        'button--warning',
        'escape-button',
      ],
    ];

    $build['link'] = $link;

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {

    $node_storage = $this->entityTypeManager->getStorage('node');
    $form = parent::blockForm($form, $form_state);
    $config = $this->getConfiguration();

    if (!empty($config['history'])) {
      $history = $config['history'];
    }

    if (!empty($config['display'])) {
      $display = $config['display'];
    }

    if (!empty($config['new_tab'])) {
      $new_tab = $config['new_tab'];
    }

    $form['display'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Display'),
      '#collapsible' => FALSE,
      '#collapsed' => FALSE,
    ];

    $form['display']['paths'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Show the escape button on these paths'),
      '#description' => $this->t("Specify pages by using their paths. Enter one path per line. The '*' character is a wildcard. An example path is /news/* for every news page."),
      '#default_value' => $display['paths'] ?? NULL,
    ];

    $form['new_tab'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Page to open in a new tab'),
      '#collapsible' => FALSE,
      '#collapsed' => FALSE,
    ];

    $form['new_tab']['url'] = [
      '#type' => 'url',
      '#title' => $this->t('Full URL'),
      '#description' => $this->t('The full URL to open in a new tab when the escape button is triggered.'),
      '#default_value' => $new_tab['url'] ?? NULL,
    ];

    $form['history'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('History pages'),
      '#collapsible' => FALSE,
      '#collapsed' => FALSE,
    ];

    for ($i = 0; $i < 15; $i++) {

      $node = NULL;
      if (!empty($history[$i])) {

        // Populate the item if it has a value.
        $node = $node_storage->load($history[$i]);
      }

      $form['history'][$i] = [
        '#type' => 'entity_autocomplete',
        '#target_type' => 'node',
        '#default_value' => $node ?? NULL,
        '#maxlength' => NULL,
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {

    $this->setConfigurationValue('display', $form_state->getValue('display'));
    $this->setConfigurationValue('history', $form_state->getValue('history'));
    $this->setConfigurationValue('new_tab', $form_state->getValue('new_tab'));
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return Cache::mergeContexts(parent::getCacheContexts(), ['route']);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {

    $node = $this->routeMatch->getParameter('node');
    if ($node instanceof NodeInterface) {
      return Cache::mergeTags(parent::getCacheTags(), $node->getCacheTags());
    }

    return parent::getCacheTags();
  }

}
