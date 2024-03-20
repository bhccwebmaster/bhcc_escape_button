<?php

namespace Drupal\Tests\bhcc_escape_button\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\node\NodeInterface;

use function PHPUnit\Framework\assertCount;

/**
 * Tests bhcc_escape_button javascript functionality.
 *
 * @group bhcc_escape_button
 */
class EscapeButtonTest extends WebDriverTestBase {

  private const VISIBLE_PATH = '/visible-escape-button-path/';
  private const NOT_VISIBLE_PATH = '/not-visible-escape-button-path/';
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

    $this->escapeButtonSettings = [
      'display' => [
        'paths' => self::VISIBLE_PATH . '*',
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
  public function testEscapeButtonVisible() {

    // Create the escape test node and display button.
    $this->createEscapePage(TRUE);

    // Check the exit button is visible.
    $this->assertSession()->pageTextContains('Exit this page');

  }

  /**
   * Tests absence of exit button.
   */
  public function testEscapeButtonNotVisible() {

    // Create the escape test node and do not display button.
    $this->createEscapePage(FALSE);

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
    $this->createEscapePage(TRUE);

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
   * Creates the escape button page.
   *
   * @var boolean display_escape_button
   */
  private function createEscapePage(bool $display_escape_button): void {

    $alias_path = '';
    if ($display_escape_button) {
      $alias_path = self::VISIBLE_PATH;
    }
    else {
      $alias_path = self::NOT_VISIBLE_PATH;
    }

    $title = $this->randomMachineName(8);
    $node = $this->createNode([
      'title' => $title,
      'type' => 'page',
      'body' => ["value" => "Escape button test page"],
      'status' => NodeInterface::PUBLISHED,
    ]);
    $node->save();
    // Create the node with an alias path where the exit button will be visible.
    $this->container->get('entity_type.manager')->getStorage('path_alias')->create([
      'path' => '/node/' . $node->id(),
      'alias' => $alias_path . $node->id(),
    ])->save();

    // Place a block.
    $this->drupalPlaceBlock('escape_button_block', $this->escapeButtonSettings);

    // Load page.
    $this->drupalGet('/node/' . $node->id());

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
    $this->createEscapePage(TRUE);

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
    $this->createEscapePage(TRUE);

    // Check the exit button is not visible.
    $this->assertSession()->pageTextNotContains('Exit this page');

  }

  /**
   * Tests absence of url configuration.
   *
   * If this happens the escape button should
   * not display.
   */
  public function testNoUrl() {

    // Remove the url setting.
    $this->escapeButtonSettings['new_tab']['url'] = '';

    // Create the escape test node.
    $this->createEscapePage(TRUE);

    // Check the exit button is not visible.
    $this->assertSession()->pageTextNotContains('Exit this page');

  }

  /**
   * Tests absence of new_tab configuration.
   *
   * If this happens the escape button should
   * not display.
   */
  public function testNoNewTab() {

    // Remove the new_tab setting.
    $this->escapeButtonSettings['new_tab'] = [];

    // Create the escape test node.
    $this->createEscapePage(TRUE);

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
    $this->createEscapePage(TRUE);

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
    $this->createEscapePage(TRUE);

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
    $this->createEscapePage(TRUE);

    // Check the exit button is not visible.
    $this->assertSession()->pageTextNotContains('Exit this page');

  }

}
