<?php

namespace Drupal\jsonapi_embed\Normalizer;

use Drupal\Core\Access\AccessibleInterface;
use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\field\Entity\FieldConfig;
use Drupal\jsonapi\Normalizer\ContentEntityNormalizer;
use Drupal\jsonapi\Normalizer\Value\EntityNormalizerValue;
use Drupal\jsonapi\Normalizer\Value\FieldNormalizerValueInterface;
use Drupal\jsonapi\Normalizer\Value\NullFieldNormalizerValue;

/**
 * Converts the Drupal content entity object to a JSON API array structure.
 */
class EmbedContentEntityNormalizer extends ContentEntityNormalizer {

  /**
   * {@inheritdoc}
   *
   * @see https://www.drupal.org/project/jsonapi/issues/2946968
   * @patch https://www.drupal.org/files/issues/2946968-pass-field-level-cacheablity-metadata.patch
   */
  public function normalize($entity, $format = NULL, array $context = []) {
    // If the fields to use were specified, only output those field values.
    $context['resource_type'] = $resource_type = $this->resourceTypeRepository->get(
      $entity->getEntityTypeId(),
      $entity->bundle()
    );
    $resource_type_name = $resource_type->getTypeName();
    // Get the bundle ID of the requested resource. This is used to determine if
    // this is a bundle level resource or an entity level resource.
    $bundle = $resource_type->getBundle();
    if (!empty($context['sparse_fieldset'][$resource_type_name])) {
      $field_names = $context['sparse_fieldset'][$resource_type_name];
    }
    else {
      $field_names = $this->getFieldNames($entity, $bundle, $resource_type);
    }
    /* @var \Drupal\jsonapi\Normalizer\Value\FieldNormalizerValueInterface[] $normalizer_values */
    $normalizer_values = [];
    foreach ($this->getFields($entity, $bundle, $resource_type) as $field_name => $field) {
      if (!in_array($field_name, $field_names)) {
        continue;
      }
      $normalizer_values[$field_name] = $this->serializeField($field, $context, $format);
    }

    $link_context = ['link_manager' => $this->linkManager];
    $output = new EntityNormalizerValue($normalizer_values, $context, $entity, $link_context);
    // Add the entity level cacheability metadata.
    $output->addCacheableDependency($entity);
    $output->addCacheableDependency($output);
    // Add the field level cacheability metadata.
    array_walk($normalizer_values, function ($normalizer_value) use ($output) {
      if ($normalizer_value instanceof RefinableCacheableDependencyInterface) {
        $output->addCacheableDependency($normalizer_value);
      }
    });

    return $output;
  }

  /**
   * Serializes a given field.
   *
   * @param mixed $field
   *   The field to serialize.
   * @param array $context
   *   The normalization context.
   * @param string $format
   *   The serialization format.
   *
   * @return Drupal\jsonapi\Normalizer\Value\FieldNormalizerValueInterface
   *   The normalized value.
   */
  protected function serializeField($field, array $context, $format) {
    /* @var \Drupal\Core\Field\FieldItemListInterface|\Drupal\jsonapi\Normalizer\Relationship $field */
    // Continue if the current user does not have access to view this field.
    $access = $field->access('view', $context['account'], TRUE);
    $context['cacheable_metadata']->addCacheableDependency($access);
    if ($field instanceof AccessibleInterface && !$access->isAllowed()) {
      return (new NullFieldNormalizerValue())->addCacheableDependency($access);
    }
    /** @var \Drupal\jsonapi\Normalizer\Value\FieldNormalizerValue $output */
    $output = $this->serializer->normalize($field, $format, $context);
    if (!$output instanceof FieldNormalizerValueInterface) {
      return new NullFieldNormalizerValue();
    }
    $is_relationship = $this->isRelationship($field);
    $property_type = $is_relationship ? 'relationships' : 'attributes';
    $output->setPropertyType($property_type);

    if ($output instanceof RefinableCacheableDependencyInterface) {
      // Add the cache dependency to the field level object because we want to
      // allow the field normalizers to add extra cacheability metadata.
      $output->addCacheableDependency($access);

      // Add field config as a cacheable dependency.
      $field_config = $field->getFieldDefinition();
      if ($field_config instanceof RefinableCacheableDependencyInterface) {
        $output->addCacheableDependency($field_config);
      }
    }

    return $output;
  }

  /**
   * {@inheritdoc}
   */
  protected function isRelationship($field) {
    if ($field instanceof EntityReferenceFieldItemList) {
      $definition = $field->getFieldDefinition();
      if ($definition instanceof FieldConfig) {
        $is_embedded = $definition->getThirdPartySetting('jsonapi_embed', 'embedded', 0);
        if ($is_embedded) {
          return FALSE;
        }
      }
    }

    return parent::isRelationship($field);
  }

}
