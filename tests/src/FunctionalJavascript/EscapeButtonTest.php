<?php

namespace Drupal\Tests\bhcc_escape_button\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\node\NodeInterface;
use Drupal\Tests\node\Traits\NodeCreationTrait;

use function PHPUnit\Framework\assertCount;

/**
 * Tests bhcc_escape_button javascript functionality.
 *
 * @group bhcc_escape_button
 */
class EscapeButtonTest extends WebDriverTestBase {

  use NodeCreationTrait;

  private const VISIBLE_PATH = '/visible-path';
  private const NOT_VISIBLE_PATH = '/not-visible-path';
  private const NEW_TAB_URL = 'https://www.google.co.uk/';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['block', 'node', 'path_alias', 'bhcc_escape_button'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * An array to hold the escape button configuration settings.
   *
   * @var string[]
   */
  private $escapeButtonSettings = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalCreateContentType(['type' => 'page']);

    // Sets up a basic default configuration.
    $this->escapeButtonSettings = [
      'display' => [
        'paths' => "/path1*\r\n/path2/sub-path/*\r\n/visible-path\r\n\r\n",
      ],
      'new_tab' => [
        'url' => self::NEW_TAB_URL,
      ],
      'region' => 'content',
      'history' => [
        // This needs to be initialised.
        '101',
      ],
    ];
  }

  /**
   * Consolidated tests for escape button visibility conditions.
   */
  public function testVisibilityConditions(): void {

    // Tests presence of exit button on visible path.
    $node = $this->createEscapePage(self::VISIBLE_PATH);
    $this->drupalGet('/node/' . $node->id());
    $this->assertSession()->pageTextContainsOnce('Exit this page');

    // A negative test of preconfigured path.
    $node = $this->createEscapePage(self::NOT_VISIBLE_PATH);
    $this->drupalGet('/node/' . $node->id());
    $this->assertSession()->pageTextNotContains('Exit this page');

    // A positive test of /path*.
    // This should show using the alias /path1-node-name.
    $alias = '/path1-node-name';

    // Create the escape test node and display button.
    $node = $this->createEscapePage($alias);
    $this->drupalGet('/node/' . $node->id());
    $this->assertSession()->pageTextContainsOnce('Exit this page');

    // A positive test of /path*.
    // This should show using the alias /path1/child-page'.
    $alias = '/path1/child-page';

    // Create the escape test node and display button.
    $node = $this->createEscapePage($alias);
    $this->drupalGet('/node/' . $node->id());
    $this->assertSession()->pageTextContainsOnce('Exit this page');

    // A positive test of /path2/sub-path/*.
    // This should show using the alias /path2/sub-path/child-page.
    // These should show the exit button.
    $alias = '/path2/sub-path/child-page';

    // Create the escape test node and display button.
    $node = $this->createEscapePage($alias);
    $this->drupalGet('/node/' . $node->id());
    $this->assertSession()->pageTextContainsOnce('Exit this page');

    // A negative test of /path2/sub-path/*.
    // This should NOT show using the alias /path2/sub-path.
    // This should NOT show the exit button.
    $alias = '/path2/sub-path';

    // Create the escape test node and display button.
    $node = $this->createEscapePage($alias);
    $this->drupalGet('/node/' . $node->id());
    $this->assertSession()->pageTextNotContains('Exit this page');
  }

  /**
   * Tests exit button click.
   */
  public function testPressExitButton(): void {

    $session = $this->getSession();

    // Create a bunch of nodes for history.
    $count = 10;
    $historyTitle = $this->randomMachineName(8);
    $this->escapeButtonSettings['history'] = [];

    for ($i = 1; $i <= $count; $i++) {

      $node = $this->createNode([
        'title' => "History page " . $this->randomMachineName(8),
        'type' => 'page',
        'body' => ["value" => "History page " . $historyTitle],
        'status' => NodeInterface::PUBLISHED,
      ]);
      $this->escapeButtonSettings['history'][] = $node->id();
      $node->save();
    }

    // Create the escape test node and display button.
    $node = $this->createEscapePage(self::VISIBLE_PATH);
    $this->drupalGet('/node/' . $node->id());

    $page = $session->getPage();

    // Find the escape button and click.
    $link = $page->findLink('Exit this page');
    $link->click();

    // Navigate to previous tab and use history.
    $current_node = $session->getCurrentUrl();
    $history_url = '';

    // Wait until the last history tab is visible.
    $session->wait(5000);
    for ($i = ($count - 1); $i >= 0; $i--) {
      // Put in a wait here to prevent occasional 'page not found' errors.
      $session->wait(5000);
      $current_node = $session->getCurrentUrl();
      $history_url = $this->baseUrl . "/node/" . $this->escapeButtonSettings['history'][$i];
      $this->assertEquals($history_url, $current_node, "History tab of " . $history_url . " does not match current tab of " . $current_node);
      $session->executeScript("history.back();");
    }

    // Switch to the new_tab and verify the we're on the right url.
    $window_names = $session->getWindowNames();
    assertCount(2, $window_names, 'Exactly 2 tabs should be open, ' . count($window_names) . ' tabs were found.');
    if (count($window_names) > 1) {
      $session->switchToWindow($window_names[1]);
    }

    $new_tab_url = $session->getCurrentUrl();
    $this->assertEquals(self::NEW_TAB_URL, $new_tab_url, "New tab url is wrong");

  }

  /**
   * Consolidated tests for escape button block configuration.
   */
  public function testBlockConfiguration(): void {

    // Tests absence of paths configuration.
    // If this happens the escape button should not display.
    // Remove the paths setting.
    $this->escapeButtonSettings['display']['paths'] = '';

    // Create the escape test node.
    $node = $this->createEscapePage(self::VISIBLE_PATH);
    $this->drupalGet('/node/' . $node->id());
    $this->assertSession()->pageTextNotContains('Exit this page');

    // Tests absence of display configuration.
    // If this happens the escape button should not display.
    // Remove the display setting.
    $this->escapeButtonSettings['display'] = [];

    // Create the escape test node.
    $node = $this->createEscapePage(self::VISIBLE_PATH);
    $this->drupalGet('/node/' . $node->id());
    $this->assertSession()->pageTextNotContains('Exit this page');

    // Tests absence of region configuration.
    // If this happens the escape button should not display.
    // Remove the region setting.
    $this->escapeButtonSettings['region'] = '';

    // Create the escape test node.
    $node = $this->createEscapePage(self::VISIBLE_PATH);
    $this->drupalGet('/node/' . $node->id());
    $this->assertSession()->pageTextNotContains('Exit this page');

    // Tests absence of history configuration.
    // If this happens the escape button should not display.
    // Empty the history.
    $this->escapeButtonSettings['history'] = [];

    // Create the escape test node.
    $node = $this->createEscapePage(self::VISIBLE_PATH);
    $this->drupalGet('/node/' . $node->id());
    $this->assertSession()->pageTextNotContains('Exit this page');

    // Tests absence of configuration.
    // If this happens the escape button should not display.
    // Empty the history.
    $this->escapeButtonSettings = [];

    // Create the escape test node.
    $node = $this->createEscapePage(self::VISIBLE_PATH);
    $this->drupalGet('/node/' . $node->id());
    $this->assertSession()->pageTextNotContains('Exit this page');
  }

  /**
   * Creates the escape button page.
   *
   * @var boolean alias_path
   */
  private function createEscapePage($alias_path = "") {

    $title = $this->randomMachineName(8);
    $node = $this->createNode([
      'title' => $title,
      'type' => 'page',
      'body' => ["value" => "Escape button test page"],
      'status' => NodeInterface::PUBLISHED,
    ]);
    $node->save();

    // Create the node with an alias path where the exit button will be visible.
    if (!empty($alias_path)) {
      $this->container->get('entity_type.manager')->getStorage('path_alias')->create([
        'path' => '/node/' . $node->id(),
        'alias' => $alias_path,
      ])->save();
    }
    $this->getSession()->wait(3000);

    // Remove any existing escape button block.
    $this->removeBlocksByPluginId('escape_button_block');

    // Place the escape block.
    $this->drupalPlaceBlock('escape_button_block', $this->escapeButtonSettings);

    return $node;

  }

  /**
   * Remove blocks by plugin id.
   *
   * Between test placements we need to remove the block so it can be replaced
   * with new settings.
   *
   * @param string $plugin_id
   *   The plugin ID of the blocks to remove.
   */
  private function removeBlocksByPluginId(string $plugin_id) :void {
    $block_storage = $this->container->get('entity_type.manager')->getStorage('block');
    $blocks = $block_storage->loadByProperties(['plugin' => $plugin_id]);
    foreach ($blocks as $block) {
      $block->delete();
    }
  }

}
