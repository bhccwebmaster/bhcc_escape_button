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

  private const VISIBLE_PATH = '/visible-escape-button-path/*';
  private const NOT_VISIBLE_PATH = '/not-visible-escape-button-path/*';
  private const SCREENSHOT_PATH = 'sites/simpletest/browser_output/';
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
        'paths' => self::VISIBLE_PATH,
      ],
      'new_tab' => [
        'url' => self::NEW_TAB_URL,
      ],
      'region' => 'content',
      'history' => [
        // This needs to be initialised to an empty string.
        '',
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
    $historyUrl = '';
    // Wait until the last history tab is visible.
    $session->wait(3000);
    while ($index) {
      // Put in a wait here to prevent occasional 'page not found' errors.
      $session->wait(3000);
      $current_node = $session->getCurrentUrl();
      $historyUrl = $this->baseUrl . "/node/" . $this->escapeButtonSettings['history'][$index];
      $this->assertEquals($historyUrl, $current_node, "History tab of " . $historyUrl . " does not match current tab of " . $current_node);
      $session->executeScript("history.back();");
      $index--;
    }

    // Switch to the new_tab and verify the we're on the right url.
    $windowNames = $session->getWindowNames();
    assertCount(2, $windowNames, 'Exactly 2 tabs should be open, ' . count($windowNames) . ' tabs were found.');
    if (count($windowNames) > 1) {
      $session->switchToWindow($windowNames[1]);
    }

    $newTabUrl = $session->getCurrentUrl();
    $this->assertEquals(self::NEW_TAB_URL, $newTabUrl, "New tab url is wrong");

  }

  /**
   * Creates the escape button page.
   *
   * @var boolean displayEscapeButton
   */
  private function createEscapePage($displayEscapeButton): void {

    $aliasPath = '';
    if ($displayEscapeButton) {
      $aliasPath = self::VISIBLE_PATH;
    }
    else {
      $aliasPath = self::NOT_VISIBLE_PATH;
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
      'alias' => $aliasPath . $node->id(),
    ])->save();

    // Place a block.
    $this->drupalPlaceBlock('escape_button_block', $this->escapeButtonSettings);

    // Load page.
    $this->drupalGet('/node/' . $node->id());

  }

}
