<?php

declare(strict_types = 1);

namespace Drupal\Tests\joinup_core\ExistingSite;

use Drush\TestTraits\DrushTestTrait;

/**
 * Tests the Joinup configuration.
 *
 * @group joinup_core
 */
class ConfigTest extends JoinupExistingSiteTestBase {

  use DrushTestTrait;

  /**
   * {@inheritdoc}
   */
  protected $disableAntibot = FALSE;

  /**
   * Tests that the active and sync stores are the same.
   */
  public function testConfig(): void {
    $this->drush('config:status', [], ['format' => 'json']);
    $diff = array_keys((array) $this->getOutputFromJSON());

    // @todo Remove this line in #3099674.
    // @see https://www.drupal.org/project/config_ignore/issues/3099674
    $diff = array_diff($diff, $this->getIgnoredConfigs());

    // Check that there are no differences between database and config sync.
    $this->assertEmpty($diff, 'Differences between active and sync stores for: ' . implode(', ', $diff));
  }

  /**
   * Returns a list of ignored configuration names.
   *
   * @return string[]
   *   A list of ignored configuration names.
   *
   * @todo Remove this method in #3099674.
   *
   * @see https://www.drupal.org/project/config_ignore/issues/3099674
   */
  protected function getIgnoredConfigs(): array {
    $ignored_configs = (array) $this->container->get('config.factory')->get('config_ignore.settings')->get('ignored_config_entities');
    // Allow hooks to alter the list.
    $this->container->get('module_handler')->invokeAll('config_ignore_settings_alter', [&$ignored_configs]);
    return $ignored_configs;
  }

}
