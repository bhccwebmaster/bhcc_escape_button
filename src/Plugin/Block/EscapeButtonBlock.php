<?php

namespace Drupal\bhcc_escape_button\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Render\Markup;

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
  public function build() {

    $build['#attached']['library'][] = 'bhcc_escape_button/rewrite_history';

    // @todo: this value is gross.
    $build['button']  = [
      '#type' => 'html_tag',
      '#tag' => 'button',
      '#attributes' => [
        'id' => 'escape-button',
        'class' => [
          'escape-button',
        ]
      ],
      '#value' => Markup::create('<span class="escape-button__title">Leave site</span><span class="escape-button__subtitle font-weight-light">Click or press Esc</span>'),
    ];

    return $build;
  }

}
