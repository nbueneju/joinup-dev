<?php

/**
 * @file
 * Contains \ContactInformationSubContext.
 */

use Behat\Gherkin\Node\TableNode;
use Drupal\DrupalExtension\Context\DrupalSubContextBase;
use Drupal\DrupalExtension\Context\DrupalSubContextInterface;
use Drupal\joinup\Traits\RdfEntityTrait;
use Drupal\joinup\Traits\SearchTrait;
use Drupal\joinup\Traits\UserTrait;

/**
 * Behat step definitions for testing contact information entities.
 */
class ContactInformationSubContext extends DrupalSubContextBase implements DrupalSubContextInterface {

  use RdfEntityTrait;
  use SearchTrait;
  use UserTrait;

  /**
   * Test contact information rdf entities.
   *
   * @var \Drupal\rdf_entity\Entity\Rdf[]
   */
  protected $contactInformations = [];

  /**
   * Navigates to the canonical page display of a contact information entity.
   *
   * @param string $label
   *   The label of the contact information entity.
   *
   * @When I go to (the homepage of )the :label contact
   * @When I visit (the homepage of )the :label contact
   * @When I go to the :label contact information page
   * @When I visit the :label contact information page
   */
  public function visitContactInformationPage($label) {
    /** @var \Drupal\rdf_entity\Entity\Rdf $entity */
    $entity = $this->getRdfEntityByLabel($label, 'contact_information');
    $this->visitPath($entity->url());
  }

  /**
   * Creates a contact information entity with data provided in a table.
   *
   * Table format:
   * | email | foo@bar.com, baz@qux.com |
   * | name  | Jack Smith               |
   *
   * @param \Behat\Gherkin\Node\TableNode $contact_table
   *   The contact table.
   *
   * @throws \Exception
   *   Thrown when a column name is incorrect.
   *
   * @Given (the following )contact:
   */
  public function givenContactEntity(TableNode $contact_table) {
    $values = [];

    foreach ($contact_table->getRowsHash() as $key => $value) {
      // Replace the column aliases with the actual field names.
      $key = self::translateFieldNameAlias($key);
      $values[$key] = $value;
    };

    $this->createContactInformation($values);
  }

  /**
   * Translates human readable field names to machine names.
   *
   * @param string $field_name
   *   The human readable field name. Case insensitive.
   *
   * @return string
   *   The machine name of the field.
   *
   * @throws \Exception
   *   Thrown when an unknown field name is passed.
   */
  protected static function translateFieldNameAlias($field_name) {
    $field_name = strtolower($field_name);
    $aliases = self::contactInformationFieldAliases();
    if (array_key_exists($field_name, $aliases)) {
      $field_name = $aliases[$field_name];
    }
    else {
      throw new \Exception("Unknown field name '$field_name'.");
    }

    return $field_name;
  }

  /**
   * Creates a number of contact information with data provided in a table.
   *
   * Table format:
   * @codingStandardsIgnoreStart
   * | name                   | email                            | website url                            | author      |
   * | John Irwin             | foo@example.com, bar@example.com | http://google.com                      | Author name |
   * | Jack Smith, John Irwin | baz@example.com                  | http://yahoo.com, http://altavista.com |             |
   * @codingStandardsIgnoreEnd
   *
   * @param \Behat\Gherkin\Node\TableNode $contact_table
   *   The contacts table.
   *
   * @throws \Exception
   *   Thrown when a column name is incorrect.
   *
   * @Given (the following )contacts:
   */
  public function givenContactEntities(TableNode $contact_table) {
    foreach ($contact_table->getColumnsHash() as $entity) {
      $values = [];

      foreach ($entity as $key => $value) {
        $key = self::translateFieldNameAlias($key);
        $values[$key] = $value;
      }

      $this->createContactInformation($values);
    }
  }

  /**
   * Checks the number of available contact information entities.
   *
   * @param int $number
   *   The expected number of entities.
   *
   * @throws \Exception
   *   Throws an exception when the expected number is not equal to the given.
   *
   * @Then I should have :number contact information(s)
   */
  public function assertContactInformationCount($number) {
    $this->assertRdfEntityCount($number, 'contact_information');
  }

  /**
   * Deletes an contact information entity.
   *
   * @param string $name
   *   The name of the contact information to delete.
   *
   * @When I delete the :contact contact information
   */
  public function deleteContactInformation($name) {
    $this->getRdfEntityByLabel($name, 'contact_information')->delete();
  }

  /**
   * Remove any created contact information entities.
   *
   * @AfterScenario
   */
  public function cleanContactInformationEntities() {
    if (empty($this->contactInformations)) {
      return;
    }

    // Since we might be cleaning up many informations, temporarily disable the
    // feature to commit the index after every query.
    $this->disableCommitOnUpdate();

    foreach ($this->contactInformations as $entity) {
      $entity->skip_notification = TRUE;
      $entity->delete();
    }
    $this->contactInformations = [];
    $this->enableCommitOnUpdate();
  }

  /**
   * Creates a contact information from the given property and field data.
   *
   * @param array $values
   *   An optional associative array of values, keyed by property name.
   *
   * @return \Drupal\rdf_entity\Entity\Rdf
   *   A new contact information entity.
   *
   * @throws \Exception
   *   When the author is specified but the related user doesn't exist.
   */
  protected function createContactInformation(array $values) {
    // The 'author' key was replaced by 'uid' in the calling function.
    if (!empty($values['uid'])) {
      $values['uid'] = $this->getUserByName($values['uid'])->id();
    }

    $entity = $this->createRdfEntity('contact_information', $this->parseRdfEntityFields($values));
    $this->contactInformations[$entity->id()] = $entity;

    return $entity;
  }

  /**
   * Field alias mapping.
   *
   * @return array
   *   Mapping.
   */
  protected static function contactInformationFieldAliases() {
    // Mapping alias - field name.
    return [
      'uri' => 'id',
      'email' => 'field_ci_email',
      'name' => 'field_ci_name',
      'website url' => 'field_ci_webpage',
      'author' => 'uid',
    ];
  }

}
