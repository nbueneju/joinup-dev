<?php

/**
 * @file
 * Contains \JoinupSubContext.
 */

use Behat\Gherkin\Node\TableNode;
use Drupal\DrupalExtension\Context\DrupalSubContextBase;
use Drupal\DrupalExtension\Context\DrupalSubContextInterface;

/**
 * Behat step definitions for functionalities provided by Joinup core module.
 */
class JoinupCoreSubContext extends DrupalSubContextBase implements DrupalSubContextInterface {

  /**
   * Asserts that only the expected chips are shown in the page.
   *
   * @param \Behat\Gherkin\Node\TableNode $table
   *   The table containing the expected chip labels.
   *
   * @Then the page should show( only) the chips:
   */
  public function assertChipElements(TableNode $table) {
    $chips = $this->getSession()->getPage()->findAll('css', '.mdl-chip__text');
    $found = array_map(function ($element) {
      /** @var \Behat\Mink\Element\NodeElement $element */
      return $element->getText();
    }, $chips);

    $expected = $table->getColumn(0);
    \PHPUnit_Framework_Assert::assertEquals($expected, $found, "The expected chip elements don't match the ones found in the page", 0.0, 10, TRUE);
  }

  /**
   * Clicks the remove button in a chip.
   *
   * @param string $text
   *   The text or partial text of the chip.
   *
   * @throws \Exception
   *   Thrown when either the chip or its remove button are not found.
   *
   * @Then I press the remove button on the chip :text
   */
  public function clickRemoveChipButton($text) {
    // Find the element that contains the user name.
    $xpath = "//span[contains(concat(' ', normalize-space(@class), ' '), ' mdl-chip__text ')][contains(text(), '$text')]";
    $chip = $this->getSession()->getPage()->find('xpath', $xpath);
    if (!$chip) {
      throw new Exception("The chip containing the label '$text' was not found on the page.");
    }

    // Find the related button.
    $xpath = "/following-sibling::button[@type='submit'][contains(concat(' ', normalize-space(@class), ' '), ' mdl-chip__action ')]";
    $button = $chip->find('xpath', $xpath);
    if (!$button) {
      throw new \Exception("Couldn't find the button to remove the chip '$text'.");
    }

    $button->press();
  }

}