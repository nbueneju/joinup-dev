<?php

declare(strict_types = 1);

namespace Drupal\joinup_core;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\node\NodeStorageInterface;
use Drupal\og\Entity\OgMembership;
use Drupal\og\MembershipManagerInterface;
use Drupal\rdf_entity\RdfInterface;

/**
 * Access handler for entities with a workflow.
 *
 * @todo: More information should be inserted here.
 * @todo: If we are going with a unified way, a readme should include the
 *  workflow creation process.
 * @todo: Add cacheability to all access.
 *
 * All parameters for the permissions are described in the permission scheme.
 *
 * @see joinup_community_content.permission_scheme.yml
 */
class NodeWorkflowAccessControlHandler {

  /**
   * The state field machine name.
   */
  const STATE_FIELD = 'field_state';

  /**
   * Flag for pre-moderated groups.
   */
  const PRE_MODERATION = 1;

  /**
   * Flag for post-moderated groups.
   */
  const POST_MODERATION = 0;

  /**
   * The machine name of the default workflow for groups.
   *
   * @todo: Change the group workflows to 'default'.
   */
  const WORKFLOW_DEFAULT = 'default';

  /**
   * The machine name of the pre moderated workflow for group content.
   *
   * @todo: Backport this to entity types other than document.
   */
  const WORKFLOW_PRE_MODERATED = 'pre_moderated';

  /**
   * The machine name of the post moderated workflow for group content.
   *
   * @todo: Backport this to entity types other than document.
   */
  const WORKFLOW_POST_MODERATED = 'post_moderated';

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The membership manager.
   *
   * @var \Drupal\og\MembershipManagerInterface
   */
  protected $membershipManager;

  /**
   * The discussions relation manager.
   *
   * @var \Drupal\joinup_core\JoinupRelationManagerInterface
   */
  protected $relationManager;

  /**
   * The current logged in user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The workflow helper class.
   *
   * @var \Drupal\joinup_core\WorkflowHelperInterface
   */
  protected $workflowHelper;

  /**
   * The permission scheme stored in configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $permissionScheme;

  /**
   * Constructs a JoinupDocumentRelationManager object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\og\MembershipManagerInterface $og_membership_manager
   *   The OG membership manager service.
   * @param \Drupal\joinup_core\JoinupRelationManagerInterface $relation_manager
   *   The relation manager service.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current logged in user.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory service.
   * @param \Drupal\joinup_core\WorkflowHelperInterface $workflow_helper
   *   The workflow helper service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, MembershipManagerInterface $og_membership_manager, JoinupRelationManagerInterface $relation_manager, AccountInterface $current_user, ConfigFactoryInterface $config_factory, WorkflowHelperInterface $workflow_helper) {
    $this->entityTypeManager = $entity_type_manager;
    $this->membershipManager = $og_membership_manager;
    $this->relationManager = $relation_manager;
    $this->currentUser = $current_user;
    $this->workflowHelper = $workflow_helper;
    $this->configFactory = $config_factory;
  }

  /**
   * Main handler for access checks for group content in Joinup.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The group content entity object.
   * @param string $operation
   *   The CRUD operation.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The result of the access check.
   */
  public function entityAccess(EntityInterface $entity, $operation, AccountInterface $account = NULL): AccessResult {
    if ($account === NULL) {
      $account = $this->currentUser;
    }

    if (!$entity instanceof NodeInterface) {
      return AccessResult::neutral();
    }

    // In case of neutral (no parent) or forbidden (no access), return the
    // result.
    $access = $this->hasParentViewAccess($entity, $account);
    if (!$access->isAllowed()) {
      return $access;
    }

    // For entities that do not have a published version and are in draft state,
    // only the owner has access. This access restriction does not apply to
    // moderators.
    if (
      !$account->hasPermission('access draft community content')
      && !$this->hasPublishedVersion($entity)
      && $this->getEntityState($entity) === 'draft'
      && $entity->getOwnerId() !== $account->id()
    ) {
      return AccessResult::forbidden();
    }

    switch ($operation) {
      case 'view':
        return $this->entityViewAccess($entity, $account);

      case 'create':
        return $this->entityCreateAccess($entity, $account);

      case 'update':
        return $this->entityUpdateAccess($entity, $account);

      case 'delete':
        return $this->entityDeleteAccess($entity, $account);

      case 'post comments':
        $parent_state = $this->relationManager->getParentState($entity);
        $entity_state = $this->getEntityState($entity);
        // Commenting on content of an archived group is not allowed.
        if ($parent_state === 'archived' || $entity_state === 'archived') {
          return AccessResult::forbidden();
        }
        $parent = $this->relationManager->getParent($entity);
        $membership = $this->membershipManager->getMembership($parent, $account->id());
        if ($membership instanceof OgMembership) {
          return AccessResult::allowedIf($membership->hasPermission($operation));
        }
    }

    return AccessResult::neutral();
  }

  /**
   * Returns whether the user has view permissions to the parent of the entity.
   *
   * @param \Drupal\node\NodeInterface $entity
   *   The group content entity.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user that the permission access is checked.
   *
   * @return \Drupal\Core\Access\AccessResult|\Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  protected function hasParentViewAccess(NodeInterface $entity, AccountInterface $account): AccessResult {
    $parent = $this->getEntityParent($entity);
    // Let parent-less nodes (e.g. newsletters) be handled by the core access.
    if (empty($parent)) {
      return AccessResult::neutral();
    }

    $access_handler = $this->entityTypeManager->getAccessControlHandler('rdf_entity');
    $access = $access_handler->access($parent, 'view', $account);
    return $access ? AccessResult::allowed() : AccessResult::forbidden();
  }

  /**
   * Access check for the 'view' operation.
   *
   * @param \Drupal\node\NodeInterface $entity
   *   The group content entity.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result check.
   */
  protected function entityViewAccess(NodeInterface $entity, AccountInterface $account): AccessResult {
    $view_scheme = $this->getPermissionScheme('view');
    $workflow_id = $this->getEntityWorkflowId($entity);
    $state = $this->getEntityState($entity);
    return $this->workflowHelper->userHasOwnAnyRoles($entity, $account, $view_scheme[$workflow_id][$state]) ? AccessResult::allowed() : AccessResult::forbidden();
  }

  /**
   * Access check for the 'create' operation.
   *
   * @param \Drupal\node\NodeInterface $entity
   *   The group content entity.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result check.
   */
  protected function entityCreateAccess(NodeInterface $entity, AccountInterface $account): AccessResult {
    $create_scheme = $this->getPermissionScheme('create');
    $workflow_id = $this->getEntityWorkflowId($entity);
    $e_library = $this->getEntityElibrary($entity);

    foreach ($create_scheme[$workflow_id][$e_library] as $ownership_data) {
      // There is no check whether the transition is allowed as only allowed
      // transitions are mapped in the permission scheme configuration object.
      if ($this->workflowHelper->userHasRoles($entity, $account, $ownership_data)) {
        return AccessResult::allowed();
      }
    }
    return AccessResult::forbidden();
  }

  /**
   * Access check for the 'update' operation.
   *
   * @param \Drupal\node\NodeInterface $entity
   *   The group content entity.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result check.
   */
  protected function entityUpdateAccess(NodeInterface $entity, AccountInterface $account): AccessResult {
    $allowed_states = $this->workflowHelper->getAvailableTargetStates($entity, $account);
    if (empty($allowed_states)) {
      return AccessResult::forbidden();
    }
    return AccessResult::allowed();
  }

  /**
   * Access check for 'delete' operation.
   *
   * @param \Drupal\node\NodeInterface $entity
   *   The entity object.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  protected function entityDeleteAccess(NodeInterface $entity, AccountInterface $account): AccessResult {
    $delete_scheme = $this->getPermissionScheme('delete');
    $workflow_id = $this->getEntityWorkflowId($entity);
    $state = $this->getEntityState($entity);

    if (isset($delete_scheme[$workflow_id][$state]) && $this->workflowHelper->userHasOwnAnyRoles($entity, $account, $delete_scheme[$workflow_id][$state])) {
      return AccessResult::allowed();
    }

    return AccessResult::forbidden();
  }

  /**
   * Helper method to retrieve the parent of the entity.
   *
   * @param \Drupal\node\NodeInterface $entity
   *   The group content entity.
   *
   * @return \Drupal\rdf_entity\RdfInterface|null
   *   The rdf entity the entity belongs to, or NULL when no group is found.
   */
  protected function getEntityParent(NodeInterface $entity): ?RdfInterface {
    $groups = $this->membershipManager->getGroups($entity);

    if (empty($groups['rdf_entity'])) {
      return NULL;
    }

    return reset($groups['rdf_entity']);
  }

  /**
   * Returns the appropriate workflow to use for the passed entity.
   *
   * @param \Drupal\node\NodeInterface $entity
   *   The group content entity.
   *
   * @return string
   *   The id of the workflow to use.
   */
  protected function getEntityWorkflowId(NodeInterface $entity): string {
    $workflow = $entity->{self::STATE_FIELD}->first()->getWorkflow();
    return $workflow->getId();
  }

  /**
   * Returns the appropriate workflow to use for the passed entity.
   *
   * @param \Drupal\node\NodeInterface $entity
   *   The group content entity.
   *
   * @return string
   *   The id of the workflow to use.
   */
  protected function getEntityState(NodeInterface $entity): string {
    return $entity->{self::STATE_FIELD}->first()->value;
  }

  /**
   * Returns the value of the eLibrary settings of the parent of an entity.
   *
   * @param \Drupal\node\NodeInterface $entity
   *   The group content entity.
   *
   * @return array
   *   The eLibrary value.
   */
  protected function getEntityElibrary(NodeInterface $entity): string {
    $parent = $this->relationManager->getParent($entity);
    $e_library_name = $this->getParentElibraryName($parent);
    return $parent->{$e_library_name}->value;
  }

  /**
   * Returns the eLibrary creation field's machine name.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The parent entity.
   *
   * @return string
   *   The machine name of the eLibrary creation field.
   */
  protected function getParentElibraryName(EntityInterface $entity): string {
    $field_array = [
      'collection' => 'field_ar_elibrary_creation',
      'solution' => 'field_is_elibrary_creation',
    ];

    return $field_array[$entity->bundle()];
  }

  /**
   * Checks whether the entity has a published version.
   *
   * @param \Drupal\node\NodeInterface $entity
   *   The entity object.
   *
   * @return bool
   *   Whether the entity has a published version.
   */
  protected function hasPublishedVersion(NodeInterface $entity): bool {
    if ($entity->isNew()) {
      return FALSE;
    }
    if ($entity->isPublished()) {
      return TRUE;
    }
    $published = $this->getNodeStorage()->load($entity->id());
    if (!empty($published) && $published instanceof EntityPublishedInterface) {
      return $published->isPublished();
    }
    return FALSE;
  }

  /**
   * Returns the configured permission scheme for the given operation.
   *
   * @param string $operation
   *   The operation for which to return the permission scheme. Can be one of
   *   'create', 'view', 'update', 'delete'.
   *
   * @return array
   *   The permission scheme.
   */
  protected function getPermissionScheme(string $operation): array {
    \assert(\in_array($operation, ['create', 'view', 'update', 'delete']), 'A valid operation should be passed');
    return $this->configFactory->get('joinup_community_content.permission_scheme')->get($operation);
  }

  /**
   * Returns the storage handler for nodes.
   *
   * @return \Drupal\node\NodeStorageInterface
   *   The storage handler.
   */
  protected function getNodeStorage(): NodeStorageInterface {
    return $this->entityTypeManager->getStorage('node');
  }

}
