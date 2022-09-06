<?php

namespace Drupal\bhcc_escape_button\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;

/**
 * Provides an escape button block.
 *
 * @Block(
 *   id = "escape_button_block",
 *   admin_label = @Translation("Escape button"),
 * )
 */
class EscapeButtonBlock extends BlockBase {

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

      $current_path = \Drupal::service('path.current')->getPath();
      $current_path_alias = \Drupal::service('path_alias.manager')->getAliasByPath($current_path);

      // Split the content of the paths field into an array.
      $paths = preg_split("(\r\n?|\n)", $display['paths']);

      // Loop through the values.
      foreach ($paths as $path) {

        // Generate the regular expression to match the given path.
        $path = str_replace('*', '.*', $path);
        $pattern = '#^' . ltrim($path, '/') . '$#';

        // If the expression matches against the current path, allow the block.
        if (preg_match($pattern, ltrim($current_path_alias, '/'))) {
          return AccessResult::allowed();
        }
      }
    }

    $node = \Drupal::request()->attributes->get('node');

    // If the node we're on isn't one of the history items, hide the block.
    // Also hide if not a node. (History can only be nodes?)
    if (!$node instanceof NodeInterface || !in_array($node->id(), $history)) {
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
    $node = \Drupal::routeMatch()->getParameter('node');
    if ($node instanceof NodeInterface) {
      if (in_array($node->id(), $history)) {
        return $build;
      }
    }

    // Generate link to news page.
    $link_url = Url::fromRoute('entity.node.canonical', ['node' => 23086], [
      'absolute' => TRUE
    ]);
    $link_title = Markup::create('<span class="escape-button__title">Leave site</span><span class="escape-button__subtitle font-weight-light">Click or press Esc</span>');
    $link = Link::fromTextAndUrl($link_title, $link_url)->toRenderable();

    // Add attributes to the link.
    $link['#attributes'] = [
      'target' => '_blank',
      'id' => 'escape-button',
      'class' => [
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

    $form = parent::blockForm($form, $form_state);
    $config = $this->getConfiguration();

    if (!empty($config['history'])) {
      $history = $config['history'];
    }

    if (!empty($config['display'])) {
      $display = $config['display'];
    }

    $form['display'] = [
      '#type' => 'fieldset',
      '#title' => t('Display'),
      '#collapsible' => FALSE,
      '#collapsed' => FALSE,
    ];

    $form['display']['paths'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Show the escape button on these paths'),
      '#description' => "Specify pages by using their paths. Enter one path per line. The '*' character is a wildcard. An example path is /news/* for every news page.",
      '#default_value' => $display['paths'] ?? NULL,
    ];

    $form['history'] = [
      '#type' => 'fieldset',
      '#title' => t('History pages'),
      '#collapsible' => FALSE,
      '#collapsed' => FALSE,
    ];

    for ($i = 0; $i < 15; $i++) {

      $node = NULL;
      if (!empty($history[$i])) {

        // Populate the item if it has a value.
        $node = Node::load($history[$i]);
      }

      $form['history'][$i] = [
        '#type' => 'entity_autocomplete',
        '#target_type' => 'node',
        '#default_value' => $node ?? NULL,
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

    $node = \Drupal::routeMatch()->getParameter('node');
    if ($node instanceof NodeInterface) {
      return Cache::mergeTags(parent::getCacheTags(), $node->getCacheTags());
    }

    return parent::getCacheTags();
  }

}
