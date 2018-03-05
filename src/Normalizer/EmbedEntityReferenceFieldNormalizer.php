<?php

namespace Drupal\jsonapi_embed\Normalizer;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\jsonapi\LinkManager\LinkManager;
use Drupal\jsonapi\Normalizer\EntityNormalizer;
use Drupal\jsonapi\Normalizer\EntityReferenceFieldNormalizer;
use Drupal\jsonapi\Normalizer\Value\FieldItemNormalizerValue;
use Drupal\jsonapi\Normalizer\Value\FieldNormalizerValue;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;
use Drupal\serialization\EntityResolver\EntityResolverInterface;

/**
 * Embeds Paragraphs reference item into entity.
 */
class EmbedEntityReferenceFieldNormalizer extends EntityReferenceFieldNormalizer {

  /**
   * The interface or class that this Normalizer supports.
   *
   * @var string
   */
  protected $supportedInterfaceOrClass = EntityReferenceFieldItemListInterface::class;

  /**
   * The entity resolver.
   *
   * @var \Drupal\serialization\EntityResolver\EntityResolverInterface
   */
  protected $entityResolver;

  /**
   * Constructor.
   *
   * @param \Drupal\jsonapi\LinkManager\LinkManager $link_manager
   *   The link manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Field\FieldTypePluginManagerInterface $plugin_manager
   *   The plugin manager for fields.
   * @param \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface $resource_type_repository
   *   The JSON API resource type repository.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   * @param \Drupal\jsonapi\Normalizer\EntityNormalizer $entity_normalizer
   *   The entity normalizer.
   * @param \Drupal\serialization\EntityResolver\EntityResolverInterface $entity_Resolver
   *   The entity resolver.
   */
  public function __construct(
    LinkManager $link_manager,
    EntityFieldManagerInterface $field_manager,
    FieldTypePluginManagerInterface $plugin_manager,
    ResourceTypeRepositoryInterface $resource_type_repository,
    EntityRepositoryInterface $entity_repository,
    EntityNormalizer $entity_normalizer,
    EntityResolverInterface $entity_Resolver) {
    $this->linkManager = $link_manager;
    $this->fieldManager = $field_manager;
    $this->pluginManager = $plugin_manager;
    $this->resourceTypeRepository = $resource_type_repository;
    $this->entityRepository = $entity_repository;
    $this->entityNormalizer = $entity_normalizer;
    $this->entityResolver = $entity_Resolver;
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($field, $format = NULL, array $context = []) {
    $normalizer_items = [];
    $non_rasterized_values = [];
    if (!$field->isEmpty()) {
      foreach ($field as $field_item) {
        $target_entity = $field_item->get('entity')->getValue();
        $value = $this->entityNormalizer->normalize($target_entity, 'api_json', $context);
        $rasterized_value = $value->rasterizeValue();
        $normalizer_items[] = new FieldItemNormalizerValue($rasterized_value);
        $non_rasterized_values[] = $value;
      }
    }
    $cardinality = $field->getFieldDefinition()
      ->getFieldStorageDefinition()
      ->getCardinality();

    $output = new FieldNormalizerValue($normalizer_items, $cardinality);
    // Bubble cacheable dependencies up.
    foreach ($non_rasterized_values as $value) {
      $output->addCacheableDependency($value);
      foreach ($value->getValues() as $field) {
        $output->addCacheableDependency($field);
      }
    }

    return $output;
  }

  /**
   * Check if class is supported for normalization by this normalizer.
   *
   * @param mixed $data
   *   Data to normalize.
   * @param string $format
   *   The format being (de-)serialized from or into.
   *
   * @return bool
   *   FALSE if data is not correct type, TRUE if normalization is supported.
   */
  public function supportsNormalization($data, $format = NULL) {
    if (!$data instanceof EntityReferenceFieldItemList) {
      return FALSE;
    }

    return (boolean) $this->getJsonapiEntityReferenceNormalizerSetting($data, 'embedded', 0);
  }

  /**
   * Extracts single third-party setting from a field.
   *
   * @param Drupal\Core\Field\EntityReferenceFieldItemList $field
   *   The field.
   * @param string $key
   *   Setting key.
   * @param string|mixed $default
   *   Default value if the setting does not exist.
   *
   * @return mixed
   *   The extracted settings or default value.
   */
  protected function getJsonapiEntityReferenceNormalizerSetting(EntityReferenceFieldItemList $field, $key, $default = '') {
    $definition = $field->getFieldDefinition();
    if (!$definition instanceof FieldConfig) {
      return $default;
    }

    return $definition->getThirdPartySetting('jsonapi_embed', $key, $default);
  }

  /**
   * Extracts third-party settings from a field.
   *
   * @param Drupal\Core\Field\EntityReferenceFieldItemList $field
   *   The field.
   * @param array $default
   *   Default value if settings don't exist.
   *
   * @return mixed
   *   The extracted settings or null.
   */
  protected function getJsonapiEntityReferenceNormalizerSettings(EntityReferenceFieldItemList $field, array $default = []) {
    $definition = $field->getFieldDefinition();
    if (!$definition instanceof FieldConfig) {
      return $default;
    }

    return $definition->getThirdPartySettings('jsonapi_embed');
  }

}
