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
        //'paths' => self::VISIBLE_PATH . '*',
        'paths' => self::VISIBLE_PATH,
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
   * Tests presence of exit button.
   */
  public function testVisible() {

    // Create the escape test node and display button.
    $node = $this->createEscapePage(self::VISIBLE_PATH);

    // Load page.
    $this->drupalGet('/node/' . $node->id());

    // Check the exit button is visible.
    $this->assertSession()->pageTextContainsOnce('Exit this page');
  }

  /**
   * Tests presence of exit button.
   */
  public function testWildcardsInPath() {

    // Set up the test case path.
    $this->escapeButtonSettings['display']['paths'] = '/path*';

    $alias_paths = ['/path', '/path-with-page-1', '/path-with-page-2', '/path/child-page-1', '/path/child-page-2'];

    // Loop through the values.
    foreach ($alias_paths as $alias_path) {

      // Create the escape test node and display button.
      $node = $this->createEscapePage($alias_path);

      // Load page.
      $this->drupalGet('/node/' . $node->id());

      $this->getSession()->wait(3000);

      // Check the exit button is visible.
      $this->assertSession()->pageTextContainsOnce('Exit this page');
    }

    // Set up the test case path.
    $this->escapeButtonSettings['display']['paths'] = '/path/sub-path/*';

    // This should NOT show the exit button.
    $alias_paths = '/path/sub-path';

    // Create the escape test node and display button.
    $node = $this->createEscapePage($alias_paths);

    // Load page.
    $this->drupalGet('/node/' . $node->id());

    // Check the exit button is visible.
    $this->assertSession()->pageTextNotContains('Exit this page');

    // These should show the exit button.
    $alias_paths = ['/path/sub-path/child-page-1', '/path/sub-path/sub-path/child-page-1'];

    // Loop through the values.
    foreach ($alias_paths as $alias_path) {

      // Create the escape test node and display button.
      $node = $this->createEscapePage($alias_path);

      // Load page.
      $this->drupalGet('/node/' . $node->id());

      // Check the exit button is visible.
      $this->assertSession()->pageTextContainsOnce('Exit this page');
    }
  }

  /**
   * Tests absence of exit button.
   */
  public function testNotVisible() {

    // Create the escape test node and do not display button.
    $node = $this->createEscapePage(self::NOT_VISIBLE_PATH);

    // Load page.
    $this->drupalGet('/node/' . $node->id());

    // Check the exit button is not visible.
    $this->assertSession()->pageTextNotContains('Exit this page');

  }

  /**
   * Tests exit button click.
   */
  public function testPressExitButton() {

    $session = $this->getSession();

    // Create a bunch of nodes for history.
    $count = 10;
    $historyTitle = $this->randomMachineName(8);
    $this->escapeButtonSettings['history'] = [];

    for ($i = 1; $i <= $count; $i++) {

      $node = $this->createNode([
        'title' => "History page " . $historyTitle,
        'type' => 'page',
        'body' => ["value" => "History page node id = " . $historyTitle],
        'status' => NodeInterface::PUBLISHED,
      ]);
      $this->escapeButtonSettings['history'][] = $node->id();
      $node->save();
    }

    // Create the escape test node and display button.
    // @todo remove '/' below and anywhere else
    $node = $this->createEscapePage(self::VISIBLE_PATH . '/');

    // Load page.
    $this->drupalGet('/node/' . $node->id());

    $page = $session->getPage();

    // Find the escape button and click.
    $link = $page->findLink('Exit this page');
    $link->click();

    // Navigate to previous tab and use history.
    $index = $count - 1;
    $current_node = $session->getCurrentUrl();
    $history_url = '';

    // Wait until the last history tab is visible.
    $session->wait(3000);
    for ($i = ($count - 1); $i >= 0; $i--) {
      // Put in a wait here to prevent occasional 'page not found' errors.
      $session->wait(3000);
      $current_node = $session->getCurrentUrl();
      $history_url = $this->baseUrl . "/node/" . $this->escapeButtonSettings['history'][$index];
      $this->assertEquals($history_url, $current_node, "History tab of " . $history_url . " does not match current tab of " . $current_node);
      $session->executeScript("history.back();");
      $index--;
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
   * Tests absence of paths configuration.
   *
   * If this happens the escape button should
   * not display.
   */
  public function testNoPaths() {

    // Remove the paths setting.
    $this->escapeButtonSettings['display']['paths'] = '';

    // Create the escape test node.
    $node = $this->createEscapePage(self::VISIBLE_PATH);

    // Load page.
    $this->drupalGet('/node/' . $node->id());

    // Check the exit button is not visible.
    $this->assertSession()->pageTextNotContains('Exit this page');

  }

  /**
   * Tests absence of display configuration.
   *
   * If this happens the escape button should
   * not display.
   */
  public function testNoDisplay() {

    // Remove the display setting.
    $this->escapeButtonSettings['display'] = [];

    // Create the escape test node.
    $node = $this->createEscapePage(self::VISIBLE_PATH);

    // Load page.
    $this->drupalGet('/node/' . $node->id());

    // Check the exit button is not visible.
    $this->assertSession()->pageTextNotContains('Exit this page');

  }

  /**
   * Tests absence of region configuration.
   *
   * If this happens the escape button should
   * not display.
   */
  public function testNoRegion() {

    // Remove the region setting.
    $this->escapeButtonSettings['region'] = '';

    // Create the escape test node.
    $node = $this->createEscapePage(self::VISIBLE_PATH);

    // Load page.
    $this->drupalGet('/node/' . $node->id());

    // Check the exit button is not visible.
    $this->assertSession()->pageTextNotContains('Exit this page');

  }

  /**
   * Tests absence of history configuration.
   *
   * If this happens the escape button should
   * not display.
   */
  public function testNoHistory() {

    // Empty the history.
    $this->escapeButtonSettings['history'] = [];

    // Create the escape test node.
    $node = $this->createEscapePage(self::VISIBLE_PATH);

    // Load page.
    $this->drupalGet('/node/' . $node->id());

    // Check the exit button is not visible.
    $this->assertSession()->pageTextNotContains('Exit this page');

  }

  /**
   * Tests absence of configuration.
   *
   * If this happens the escape button should
   * not display.
   */
  public function testNoconfiguration() {

    // Empty the history.
    $this->escapeButtonSettings = [];

    // Create the escape test node.
    $node = $this->createEscapePage(self::VISIBLE_PATH);

    // Load page.
    $this->drupalGet('/node/' . $node->id());

    // Check the exit button is not visible.
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
        // 'alias' => $alias_path . $node->id(),
        'alias' => $alias_path,
      ])->save();
    }
    $this->getSession()->wait(3000);

    // Place the escape block.
    $this->drupalPlaceBlock('escape_button_block', $this->escapeButtonSettings);

    return $node;

  }

}
