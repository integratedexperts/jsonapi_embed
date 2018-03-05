<?php

namespace Drupal\jsonapi_embed;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/**
 * Modifies the jsonapi entity normalizer service.
 */
class JsonapiEmbedServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    // Overrides serializer.normalizer.entity.jsonapi class to test domain
    // language negotiation.
    $definition = $container->getDefinition('serializer.normalizer.entity.jsonapi');
    $definition->setClass('Drupal\jsonapi_embed\Normalizer\EmbedContentEntityNormalizer');
  }

}
