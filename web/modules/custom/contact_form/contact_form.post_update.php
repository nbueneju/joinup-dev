<?php

/**
 * @file
 * Post-update scripts for Joinup Contact Form module.
 */

declare(strict_types = 1);

/**
 * Remove 'Comments on EIRA' & 'New version of Joinup' support form categories.
 */
function contact_form_post_update_remove_categories(array &$sandbox) {
  $storage = \Drupal::entityTypeManager()->getStorage('message');
  if (!isset($sandbox['ids'])) {
    $sandbox['ids'] = array_values($storage->getQuery()
      ->condition('template', 'contact_form_submission')
      ->condition('field_contact_category', ['version', 'eira'], 'IN')
      ->sort('mid')
      ->execute());
    $sandbox['count'] = 0;
  }

  $ids_to_process = array_splice($sandbox['ids'], 0, 25);
  /** @var \Drupal\message\MessageInterface $message */
  foreach ($storage->loadMultiple($ids_to_process) as $message) {
    $message->set('field_contact_category', 'other')->save();
    $sandbox['count']++;
  }

  $sandbox['#finished'] = $sandbox['ids'] ? 0 : 1;
  if ($sandbox['#finished']) {
    return "Finished processing {$sandbox['count']} messages.";
  }
  return "Processed {$sandbox['count']} messages so far.";
}
