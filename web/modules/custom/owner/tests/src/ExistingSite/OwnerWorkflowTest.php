<?php

declare(strict_types = 1);

namespace Drupal\Tests\owner\ExistingSite;

use Drupal\Core\Session\AnonymousUserSession;
use Drupal\Tests\joinup_core\ExistingSite\JoinupWorkflowExistingSiteTestBase;

/**
 * Tests crud operations and the workflow for the owner rdf entity.
 *
 * @group owner
 */
class OwnerWorkflowTest extends JoinupWorkflowExistingSiteTestBase {

  /**
   * A non authenticated user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $userAnonymous;

  /**
   * A user with the authenticated role.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $userAuthenticated;

  /**
   * A user with the moderator role.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $userModerator;

  /**
   * A user that will be set as owner of the entity.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $userOwner;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->userAnonymous = new AnonymousUserSession();
    $this->userAuthenticated = $this->createUser();
    $this->userModerator = $this->createUser([], NULL, FALSE, ['roles' => ['moderator']]);
    $this->userOwner = $this->createUser();
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityType(): string {
    return 'rdf_entity';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityBundle(): string {
    return 'owner';
  }

  /**
   * Tests the CRUD operations for the asset release entities.
   *
   * Since the browser test is a slow test, both create access and read/update/
   * delete access are tested below.
   */
  public function testCrudAccess(): void {
    // Test create access.
    foreach ($this->createAccessProvider() as $user_var => $expected_result) {
      $access = $this->entityAccess->createAccess('owner', $this->$user_var);
      $result = $expected_result ? $this->t('have') : $this->t('not have');
      $message = "User {$user_var} should {$result} create access for bundle 'owner'.";
      $this->assertEquals($expected_result, $access, $message);
    }

    // A list of possible users.
    $available_users = [
      'userAnonymous',
      'userAuthenticated',
      'userModerator',
      'userOwner',
    ];

    // Test view, update, delete access.
    foreach ($this->readUpdateDeleteAccessProvider() as $entity_state => $test_data) {
      $content = $this->createRdfEntity([
        'rid' => 'owner',
        'label' => $this->randomMachineName(),
        'field_owner_state' => $entity_state,
        'uid' => $this->userOwner->id(),
      ]);

      foreach ($test_data as $operation => $allowed_users) {
        foreach ($available_users as $user_var) {
          // If the current user is found in the allowed list, the expected
          // access result is true, otherwise false.
          $expected_result = in_array($user_var, $allowed_users);

          $access = $this->entityAccess->access($content, $operation, $this->$user_var);
          $result = $expected_result ? $this->t('have') : $this->t('not have');
          $message = "User {$user_var} should {$result} {$operation} access for entity {$content->label()} ({$entity_state}).";
          $this->assertEquals($expected_result, $access, $message);
        }
      }

      // To save code (or for lazyness?) we are reusing the same owner entity,
      // referencing it in a collection. The entity access handler has static
      // caching that needs to be cleared to properly run the access checks on
      // the content.
      $this->entityAccess->resetCache();

      // Owner entities that are referenced in other ones cannot be deleted.
      $this->createRdfEntity([
        'rid' => 'collection',
        'label' => $this->randomMachineName(),
        'uid' => $this->userOwner->id(),
        'field_ar_state' => 'draft',
        'field_ar_owner' => $content->id(),
      ]);
      foreach ($available_users as $user_var) {
        $this->assertFalse($this->entityAccess->access($content, 'delete', $this->$user_var), "User {$user_var} should not have delete access for entity {$content->label()} ({$entity_state}).");
      }
    }
  }

  /**
   * Tests the owner workflow.
   */
  public function testWorkflow(): void {
    foreach ($this->workflowTransitionsProvider() as $entity_state => $workflow_data) {
      foreach ($workflow_data as $user_var => $expected_target_states) {
        $content = $this->createRdfEntity([
          'rid' => 'owner',
          'label' => $this->randomMachineName(),
        ]);
        // Override the default state of 'validated' that is set during entity
        // creation.
        // @see owner_rdf_entity_create()
        $content->set('field_owner_state', $entity_state);
        $content->save();

        // Override the user to be checked for the allowed transitions.
        $actual_target_states = $this->workflowHelper->getAvailableTargetStates($content, $this->$user_var);

        sort($actual_target_states);
        sort($expected_target_states);

        $this->assertEquals($expected_target_states, $actual_target_states, "Transitions do not match for user $user_var, state $entity_state.");
      }
    }
  }

  /**
   * Provides data for create access check.
   *
   * @return array
   *   Test cases.
   */
  public function createAccessProvider(): array {
    return [
      'userAnonymous' => FALSE,
      'userAuthenticated' => TRUE,
      'userModerator' => TRUE,
    ];
  }

  /**
   * Provides data for access check.
   *
   * The structure of the array is:
   * @code
   * $access_array = [
   *   'entity_state' => [
   *     'operation' => [
   *        'allowed_role1',
   *        'allowed_role2',
   *     ],
   *   ],
   * ];
   * @code
   *
   * @return array
   *   Test cases.
   */
  public function readUpdateDeleteAccessProvider(): array {
    return [
      'validated' => [
        'view' => [
          'userAnonymous',
          'userAuthenticated',
          'userModerator',
          'userOwner',
        ],
        'edit' => [
          'userModerator',
          'userOwner',
        ],
        'delete' => [
          'userModerator',
        ],
      ],
      'needs_update' => [
        'view' => [
          'userAnonymous',
          'userAuthenticated',
          'userModerator',
          'userOwner',
        ],
        'edit' => [
          'userModerator',
          'userOwner',
        ],
        'delete' => [
          'userModerator',
        ],
      ],
      'deletion_request' => [
        'view' => [
          'userAnonymous',
          'userAuthenticated',
          'userModerator',
          'userOwner',
        ],
        'edit' => [
          'userModerator',
        ],
        'delete' => [
          'userModerator',
        ],
      ],
    ];
  }

  /**
   * Provides data for transition checks.
   *
   * The structure of the array is:
   * @code
   * $workflow_array = [
   *   'entity_state' => [
   *     'user' => [
   *       'transition',
   *       'transition',
   *     ],
   *   ],
   * ];
   * @code
   * There can be multiple transitions that can lead to a specific state, so
   * the check is being done on allowed transitions.
   *
   * @return array
   *   Test cases.
   */
  public function workflowTransitionsProvider(): array {
    return [
      '__new__' => [
        'userAuthenticated' => [
          'validated',
        ],
        'userModerator' => [
          'validated',
        ],
      ],
      'validated' => [
        'userAuthenticated' => [
          'validated',
          'deletion_request',
        ],
        'userModerator' => [
          'validated',
          'needs_update',
          'deletion_request',
        ],
      ],
      'needs_update' => [
        'userAuthenticated' => [
          'needs_update',
        ],
        'userModerator' => [
          'needs_update',
          'validated',
        ],
      ],
    ];
  }

}
