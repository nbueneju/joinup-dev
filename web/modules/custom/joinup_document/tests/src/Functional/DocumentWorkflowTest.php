<?php

namespace Drupal\Tests\joinup_document\Functional;

use Drupal\Core\Session\AnonymousUserSession;
use Drupal\og\Entity\OgRole;
use Drupal\og\OgGroupAudienceHelper;
use Drupal\rdf_entity\Entity\Rdf;
use Drupal\rdf_entity\RdfInterface;
use Drupal\Tests\joinup_core\JoinupWorkflowTestBase;

/**
 * Tests CRUD operations and workflow transitions for the document node.
 */
class DocumentWorkflowTest extends JoinupWorkflowTestBase {

  const PRE_MODERATION = 1;
  const POST_MODERATION = 0;
  const ELIBRARY_ONLY_FACILITATORS = 0;
  const ELIBRARY_MEMBERS_FACILITATORS = 1;
  const ELIBRARY_REGISTERED_USERS = 2;

  /**
   * A user assigned as an owner to document entities.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $userOwner;

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
   * A user with the administrator role in the parent rdf entity.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $userOgAdministrator;

  /**
   * A user with the facilitator role in the parent rdf entity.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $userOgFacilitator;

  /**
   * A user with the member role in the parent rdf entity.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $userOgMember;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->userOwner = $this->createUser();
    $this->userAnonymous = new AnonymousUserSession();
    $this->userAuthenticated = $this->createUser();
    $this->userModerator = $this->createUserWithRoles(['moderator']);
    $this->userOgMember = $this->createUser();
    $this->userOgFacilitator = $this->createUser();
    $this->userOgAdministrator = $this->createUser();
  }

  /**
   * Tests the CRUD operations for the asset release entities.
   *
   * Since the browser test is a slow test, both create access and read/update/
   * delete access are tested below.
   */
  public function testCrudAccess() {
    // The owner, when it comes to 'create' operation, is just an authenticated
    // user.
    $test_roles = array_diff($this->getAvailableUsers(), ['owner']);

    // Test create access.
    foreach ($this->createAccessProvider() as $parent_bundle => $elibrary_data) {
      foreach ($elibrary_data as $elibrary => $allowed_roles) {
        $parent = $this->createParent($parent_bundle, 'validated', NULL, $elibrary);
        $content = $this->createNode([
          'type' => 'document',
          OgGroupAudienceHelper::DEFAULT_FIELD => $parent->id(),
        ]);

        $non_allowed_roles = array_diff($test_roles, $allowed_roles);
        $operation = 'create';
        foreach ($allowed_roles as $user_var) {
          $access = $this->entityAccess->access($content, $operation, $this->{$user_var});
          $access = $this->ogAccess->userAccessEntity('create', $content, $this->{$user_var})->isAllowed();
          $message = "User {$user_var} should have {$operation} access for bundle 'document' with a {$parent_bundle} parent with eLibrary: {$elibrary}.";
          $this->assertEquals(TRUE, $access, $message);
        }
        foreach ($non_allowed_roles as $user_var) {
          $access = $this->ogAccess->userAccessEntity('create', $content, $this->{$user_var})->isAllowed();
          $message = "User {$user_var} should not have {$operation} access for bundle 'document' with a {$parent_bundle} parent with eLibrary: {$elibrary}.";
          $this->assertEquals(FALSE, $access, $message);
        }
      }
    }

    // Test view, update, delete access.
    foreach (['collection', 'solution'] as $parent_bundle) {
      foreach ($this->readUpdateDeleteAccessProvider() as $parent_state => $content_data) {
        $parent = $this->createParent($parent_bundle, $parent_state);

        foreach ($content_data as $content_state => $test_data_arrays) {
          // Initialize the document entity as it is going to be used in all
          // sub cases.
          $content = $this->createNode([
            'type' => 'document',
            OgGroupAudienceHelper::DEFAULT_FIELD => $parent->id(),
            'field_state' => $content_state,
            'status' => $this->isPublishedState($content_state),
          ]);

          foreach ($test_data_arrays as $test_data_array) {
            $operation = $test_data_array[0];
            $user_var = $test_data_array[1];
            $expected_result = $test_data_array[2];

            $this->userProvider->setUser($this->{$user_var});
            $access = $this->entityAccess->access($content, $operation, $this->{$user_var});
            $result = $expected_result ? t('have') : t('not have');
            $message = "User {$user_var} should {$result} {$operation} access for entity {$content->label()} ({$content_state}) with the parent {$parent_bundle} entity in {$parent_state} state.";
            $this->assertEquals($expected_result, $access, $message);
          }
        }
      }
    }
  }

  /**
   * Provides data for create access check.
   *
   * The access to create a release is checked against the parent entity as it
   * is dependant to og permissions.
   * The structure of the array is:
   * @code
   * $access_array = [
   *  'parent_bundle' => [
   *    'elibrary_status' => [
   *      'role_allowed'
   *      'role_allowed'
   *     ],
   *   ],
   * ];
   * @code
   * The user variable represents the variable defined in the test.
   * No parent state needs to be checked as it does not affect the possibility
   * to create document.
   */
  protected function createAccessProvider() {
    return [
      'collection' => [
        self::ELIBRARY_ONLY_FACILITATORS => [
          'userModerator',
          'userOgFacilitator',
        ],
        self::ELIBRARY_MEMBERS_FACILITATORS => [
          'userModerator',
          'userOgMember',
          'userOgFacilitator',
          'userOgAdministrator',
        ],
        self::ELIBRARY_REGISTERED_USERS => [
          'userAuthenticated',
          'userModerator',
          'userOgMember',
          'userOgFacilitator',
          'userOgAdministrator',
        ],
      ],
      'solution' => [
        self::ELIBRARY_ONLY_FACILITATORS => [
          'userModerator',
          'userOgFacilitator',
        ],
        self::ELIBRARY_MEMBERS_FACILITATORS => [
          'userModerator',
          'userOgFacilitator',
        ],
        self::ELIBRARY_REGISTERED_USERS => [
          'userModerator',
          'userAuthenticated',
          'userOgFacilitator',
        ],
      ]
    ];
  }

  /**
   * Creates a parent entity and initializes memberships.
   *
   * @param string $bundle
   *   The bundle of the entity to create.
   * @param string $state
   *    The state of the entity.
   * @param string $moderation
   *    whether the parent is pre or post moderated.
   * @param string $elibrary
   *    The 'eLibrary_creation' value of the parent entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *    The created entity.
   */
  protected function createParent($bundle, $state = 'validated', $moderation = NULL, $elibrary = NULL) {
    $field_identifier = [
      'collection' => 'field_ar_',
      'solution' => 'field_is_',
    ];

    $parent = Rdf::create([
      'label' => $this->randomMachineName(),
      'rid' => $bundle,
      $field_identifier[$bundle] . 'state' => $state,
      $field_identifier[$bundle] . 'moderation' => $moderation,
      $field_identifier[$bundle] . 'elibrary_creation' => $elibrary,
    ]);
    $parent->save();
    $this->assertInstanceOf(RdfInterface::class, $parent, "The $bundle group was created.");

    $member_role = OgRole::getRole('rdf_entity', $bundle, 'member');
    $facilitator_role = OgRole::getRole('rdf_entity', $bundle, 'facilitator');
    $administrator_role = OgRole::getRole('rdf_entity', $bundle, 'administrator');
    $this->createOgMembership($parent, $this->userOgMember, [$member_role]);
    $this->createOgMembership($parent, $this->userOgFacilitator, [$facilitator_role]);
    $this->createOgMembership($parent, $this->userOgAdministrator, [$administrator_role]);

    return $parent;
  }

  /**
   * Provides data for access check.
   *
   * The structure of the array is:
   * @code
   * $access_array = [
   *   'parent_state' => [
   *     'entity_state' => [
   *        ['operation', 'user1', 'expected_result'],
   *        ['operation', 'user2', 'expected_result'],
   *     ],
   *   ],
   * ];
   * @code
   * Only two parent states need to be tested as the expected result might
   * differ depending on whether the parent is published or not.
   *
   * The reason that this is just an array and not a proper provider is that it
   * would take a lot of time to reinstall an instance of the site for each
   * entry.
   */
  protected function readUpdateDeleteAccessProvider() {
    return [
      // Unpublished parent.
      'draft' => [
        'validated' => [
          ['view', 'userAnonymous', FALSE],
          ['view', 'userAuthenticated', FALSE],
          ['view', 'userModerator', TRUE],
          ['view', 'userOgMember', FALSE],
          ['view', 'userOgFacilitator', TRUE],
          ['view', 'userOgAdministrator', FALSE],
          ['update', 'userAnonymous', FALSE],
          ['update', 'userAuthenticated', FALSE],
          ['update', 'userModerator', TRUE],
          ['update', 'userOgMember', FALSE],
          ['update', 'userOgFacilitator', TRUE],
          ['update', 'userOgAdministrator', FALSE],
          ['delete', 'userAnonymous', FALSE],
          ['delete', 'userAuthenticated', FALSE],
          ['delete', 'userModerator', TRUE],
          ['delete', 'userOgMember', FALSE],
          ['delete', 'userOgFacilitator', TRUE],
          ['delete', 'userOgAdministrator', FALSE],
        ],
        'in_assessment' => [
          ['view', 'userAnonymous', FALSE],
          ['view', 'userAuthenticated', FALSE],
          ['view', 'userModerator', TRUE],
          ['view', 'userOgFacilitator', TRUE],
          ['view', 'userOgAdministrator', FALSE],
          ['update', 'userAnonymous', FALSE],
          ['update', 'userAuthenticated', FALSE],
          ['update', 'userModerator', TRUE],
          ['update', 'userOgFacilitator', TRUE],
          ['update', 'userOgAdministrator', FALSE],
          ['delete', 'userAnonymous', FALSE],
          ['delete', 'userAuthenticated', FALSE],
          ['delete', 'userModerator', TRUE],
          ['delete', 'userOgFacilitator', TRUE],
          ['delete', 'userOgAdministrator', FALSE],
        ],
        'proposed' => [
          ['view', 'userAnonymous', FALSE],
          ['view', 'userAuthenticated', FALSE],
          ['view', 'userModerator', TRUE],
          ['view', 'userOgFacilitator', TRUE],
          ['view', 'userOgAdministrator', FALSE],
          ['update', 'userAnonymous', FALSE],
          ['update', 'userAuthenticated', FALSE],
          ['update', 'userModerator', TRUE],
          ['update', 'userOgFacilitator', TRUE],
          ['update', 'userOgAdministrator', FALSE],
          ['delete', 'userAnonymous', FALSE],
          ['delete', 'userAuthenticated', FALSE],
          ['delete', 'userModerator', TRUE],
          ['delete', 'userOgFacilitator', TRUE],
          ['delete', 'userOgAdministrator', FALSE],
        ],
        'archived' => [
          ['view', 'userAnonymous', FALSE],
          ['view', 'userAuthenticated', FALSE],
          ['view', 'userModerator', TRUE],
          ['view', 'userOgFacilitator', TRUE],
          ['view', 'userOgAdministrator', FALSE],
          ['update', 'userAnonymous', FALSE],
          ['update', 'userAuthenticated', FALSE],
          ['update', 'userModerator', TRUE],
          ['update', 'userOgFacilitator', FALSE],
          ['update', 'userOgAdministrator', FALSE],
          ['delete', 'userAnonymous', FALSE],
          ['delete', 'userAuthenticated', FALSE],
          ['delete', 'userModerator', TRUE],
          ['delete', 'userOgFacilitator', TRUE],
          ['delete', 'userOgAdministrator', FALSE],
        ],
      ],
      // Published parent.
      'validated' => [
        'validated' => [
          ['view', 'userAnonymous', TRUE],
          ['view', 'userAuthenticated', TRUE],
          ['view', 'userModerator', TRUE],
          ['view', 'userOgFacilitator', TRUE],
          ['view', 'userOgAdministrator', TRUE],
          ['update', 'userAnonymous', FALSE],
          ['update', 'userAuthenticated', FALSE],
          ['update', 'userModerator', TRUE],
          ['update', 'userOgFacilitator', TRUE],
          ['update', 'userOgAdministrator', FALSE],
          ['delete', 'userAnonymous', FALSE],
          ['delete', 'userAuthenticated', FALSE],
          ['delete', 'userModerator', TRUE],
          ['delete', 'userOgFacilitator', TRUE],
          ['delete', 'userOgAdministrator', FALSE],
        ],
        'in_assessment' => [
          ['view', 'userAnonymous', FALSE],
          ['view', 'userAuthenticated', FALSE],
          ['view', 'userModerator', TRUE],
          ['view', 'userOgFacilitator', TRUE],
          ['view', 'userOgAdministrator', FALSE],
          ['update', 'userAnonymous', FALSE],
          ['update', 'userAuthenticated', FALSE],
          ['update', 'userModerator', TRUE],
          ['update', 'userOgFacilitator', TRUE],
          ['update', 'userOgAdministrator', FALSE],
          ['delete', 'userAnonymous', FALSE],
          ['delete', 'userAuthenticated', FALSE],
          ['delete', 'userModerator', TRUE],
          ['delete', 'userOgFacilitator', TRUE],
          ['delete', 'userOgAdministrator', FALSE],
        ],
        'proposed' => [
          ['view', 'userAnonymous', FALSE],
          ['view', 'userAuthenticated', FALSE],
          ['view', 'userModerator', TRUE],
          ['view', 'userOgFacilitator', TRUE],
          ['view', 'userOgAdministrator', FALSE],
          ['update', 'userAnonymous', FALSE],
          ['update', 'userAuthenticated', FALSE],
          ['update', 'userModerator', TRUE],
          ['update', 'userOgFacilitator', TRUE],
          ['update', 'userOgAdministrator', FALSE],
          ['delete', 'userAnonymous', FALSE],
          ['delete', 'userAuthenticated', FALSE],
          ['delete', 'userModerator', TRUE],
          ['delete', 'userOgFacilitator', TRUE],
          ['delete', 'userOgAdministrator', FALSE],
        ],
        'archived' => [
          ['view', 'userAnonymous', TRUE],
          ['view', 'userAuthenticated', TRUE],
          ['view', 'userModerator', TRUE],
          ['view', 'userOgFacilitator', TRUE],
          ['view', 'userOgAdministrator', TRUE],
          ['update', 'userAnonymous', FALSE],
          ['update', 'userAuthenticated', FALSE],
          ['update', 'userModerator', TRUE],
          ['update', 'userOgFacilitator', FALSE],
          ['update', 'userOgAdministrator', FALSE],
          ['delete', 'userAnonymous', FALSE],
          ['delete', 'userAuthenticated', FALSE],
          ['delete', 'userModerator', TRUE],
          ['delete', 'userOgFacilitator', TRUE],
          ['delete', 'userOgAdministrator', FALSE],
        ],
      ],
    ];
  }

  /**
   * Determines if a state should be published in the document workflow.
   *
   * When programmatically creating a node in a certain state, there is no
   * state_machine transition fired. The state_machine_revisions subscriber has
   * the code to handle publishing of states, but it won't kick in. This
   * function is used to determine if the node should be created as published.
   *
   * @param string $state
   *   The state to check.
   *
   * @return bool
   *   If the state is published or not.
   */
  protected function isPublishedState($state) {
    $states = [
      'validated',
      'archived',
    ];

    return in_array($state, $states);
  }

  /**
   * Tests the document workflow.
   */
  public function testWorkflow() {
    foreach ($this->workflowTransitionsProvider() as $content_state => $workflow_data) {
      foreach (['collection', 'solution'] as $parent_bundle) {
        $parent = $this->createParent($parent_bundle, 'validated');

        foreach ($workflow_data as $user_var => $transitions) {
          $content = $this->createNode([
            'type' => 'document',
            'field_state' => $content_state,
            OgGroupAudienceHelper::DEFAULT_FIELD => $parent->id(),
            'status' => $this->isPublishedState($content_state),
          ]);
          $content->save();

          // Solution group has a member state that is coming from OG, but it
          // has no privileges.
          // @todo no better way? This is U G L Y. Argh, my eyes!
          if ($parent_bundle === 'solution' && $user_var === 'userOgMember') {
            $transitions = [];
          }

          // Override the user to be checked for the allowed transitions.
          $this->userProvider->setUser($this->{$user_var});
          $actual_transitions = $content->get('field_state')
            ->first()
            ->getTransitions();
          $actual_transitions = array_map(function ($transition) {
            return $transition->getId();
          }, $actual_transitions);
          sort($actual_transitions);
          sort($transitions);

          $this->assertEquals($transitions, $actual_transitions, "Transitions do not match for user $user_var, state $content_state and parent $parent_bundle.");
        }
      }
    }
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
   */
  protected function workflowTransitionsProvider() {
    return [
      '__new__' => [
        'userAuthenticated' => [],
        'userOgMember' => [
          'validate',
        ],
        'userOgFacilitator' => [
          'validate',
        ],
        'userModerator' => [
          'validate',
        ],
      ],
      'validated' => [
        'userAuthenticated' => [],
        'userOgMember' => [
          'update_published',
        ],
        'userOgFacilitator' => [
          'update_published',
          'request_changes',
          'report',
          'disable',
        ],
        'userModerator' => [
          'update_published',
          'request_changes',
          'report',
          'disable',
        ],
      ],
      'in_assessment' => [
        'userAuthenticated' => [],
        'userOgMember' => [],
        'userOgFacilitator' => [
          'approve_report',
        ],
        'userModerator' => [
          'approve_report',
        ],
      ],
      'proposed' => [
        'userAuthenticated' => [],
        'userOgMember' => [
          'update_proposed',
        ],
        'userOgFacilitator' => [
          'update_proposed',
          'approve_proposed',
        ],
        'userModerator' => [
          'update_proposed',
          'approve_proposed',
        ],
      ],
      // Once the node is in archived state, no actions can be taken anymore.
      'archived' => [
        'userAuthenticated' => [],
        'userOgMember' => [],
        'userOgFacilitator' => [],
        'userModerator' => [],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityType() {
    return 'node';
  }

  /**
   * Returns a list of users to be used for the tests.
   *
   * @return array
   *    A list of user variables.
   */
  protected function getAvailableUsers() {
    return [
      'userOwner',
      'userAnonymous',
      'userAuthenticated',
      'userModerator',
      'userOgMember',
      'userOgFacilitator',
      'userOgAdministrator',
    ];
  }
}
