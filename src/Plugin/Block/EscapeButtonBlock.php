<?php

namespace Drupal\bhcc_escape_button\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
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
   * Array of history items.
   *
   * These are the URLs to cycle through, creating a safe browser history.
   * There needs to be at least 15 to clear through the History items
   * visible on the menu bar History of the browser.
   *
   * @var array
   */
  const HISTORY_ITEMS = [
    '251',
    '256',
    '431',
    '671',
    '606',
    '726',
    '2004036',
    '11621',
    '46051',
    '46016',
    '44616',
    '47881',
    '1979026',
    '1979031',
    '1979041',
  ];

  /**
   * {@inheritdoc}
   */
  public function build() {

    $build['#attached']['library'][] = 'bhcc_escape_button/rewrite_history';

    // Pass through storage data to js library.
    // Used for testing whether redirecting is in progress.
    $build['#attached']['drupalSettings']['bhccEscapeButton']['historyItems'] = self::HISTORY_ITEMS;

    // If we're on one of the redirect pages, don't show the escape button.
    $node = \Drupal::routeMatch()->getParameter('node');
    if ($node instanceof NodeInterface) {
      if (in_array($node->id(), self::HISTORY_ITEMS)) {
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
