<?php

declare(strict_types=1);

namespace Drupal\joinup_group;

use Drupal\Core\Entity\EntityInterface;
use Drupal\rdf_entity\RdfInterface;

/**
 * Helper methods for dealing with groups in Joinup.
 */
class JoinupGroupHelper {

  /**
   * Returns whether the entity is one of the rdf groups.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check.
   *
   * @return bool
   *   True if the entity is an rdf of bundle collection or solution, false
   *   otherwise.
   */
  public static function isGroup(EntityInterface $entity): bool {
    return self::isCollection($entity) || self::isSolution($entity);
  }

  /**
   * Returns whether the entity is an rdf collection.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check.
   *
   * @return bool
   *   True if the entity is an rdf of bundle collection, false otherwise.
   */
  public static function isCollection(EntityInterface $entity): bool {
    return self::isRdfEntityOfBundle($entity, 'collection');
  }

  /**
   * Returns whether the entity is an rdf solution.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check.
   *
   * @return bool
   *   True if the entity is an rdf of bundle solution, false otherwise.
   */
  public static function isSolution(EntityInterface $entity): bool {
    return self::isRdfEntityOfBundle($entity, 'solution');
  }

  /**
   * Returns whether the entity is an rdf entity of a specific bundle.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check.
   * @param string $bundle
   *   The bundle the entity should be.
   *
   * @return bool
   *   True if the entity is an rdf of bundle collection, false otherwise.
   */
  protected static function isRdfEntityOfBundle(EntityInterface $entity, $bundle): bool {
    return $entity instanceof RdfInterface && $entity->bundle() === $bundle;
  }

}
