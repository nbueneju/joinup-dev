<?php

namespace Drupal\joinup_federation_test\Plugin\pipeline\Pipeline;

use Drupal\joinup_federation\JoinupFederationPipelinePluginBase;

/**
 * Provides a pipline testing plugin.
 *
 * @PipelinePipeline(
 *   id = "joinup_federation_testing_pipeline",
 *   label = @Translation("Joinup federation testing pipeline"),
 *   steps = {},
 * )
 */
class JoinupFederationTestingPipeline extends JoinupFederationPipelinePluginBase {}