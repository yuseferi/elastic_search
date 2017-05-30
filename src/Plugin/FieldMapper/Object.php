<?php
/**
 * Created by PhpStorm.
 * User: twhiston
 * Date: 12/10/16
 * Time: 13:21
 */

namespace Drupal\elastic_search\Plugin\FieldMapper;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\elastic_search\Annotation\FieldMapper;
use Drupal\elastic_search\Elastic\ElasticDocumentManager;
use Drupal\elastic_search\Entity\FieldableEntityMap;
use Drupal\elastic_search\Exception\FieldMapperFlattenSkipException;
use Drupal\elastic_search\Plugin\FieldMapper\FormHelper\IncludeInAllField;
use Drupal\elastic_search\Plugin\FieldMapperBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class NodeEntityMapper
 * This is special type of entity mapper, which will be used if a specific
 * class is not implemented for the type you are using
 *
 * @FieldMapper(
 *   id = "object",
 *   label = @Translation("Object")
 * )
 */
class Object extends FieldMapperBase {

  use StringTranslationTrait;

  use IncludeInAllField;

  /**
   * @var  EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var ElasticDocumentManager
   */
  protected $documentManager;

  /**
   * @inheritdoc
   */
  public function getSupportedTypes() {
    return [
      'entity_reference',
      'entity_reference_revisions',
      'file',
      'image',
    ];
  }

  /**
   * FieldMapperBase constructor.
   *
   * @param array  $configuration
   * @param string $plugin_id
   * @param mixed  $plugin_definition
   */
  public function __construct(array $configuration,
                              $plugin_id,
                              $plugin_definition,
                              EntityTypeManagerInterface $entityTypeManager,
                              ElasticDocumentManager $documentManager) {

    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entityTypeManager;
    $this->documentManager = $documentManager;
  }

  /**
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   * @param array                                                     $configuration
   * @param string                                                    $plugin_id
   * @param mixed                                                     $plugin_definition
   *
   * @return static
   *
   * @throws \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
   * @throws \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
   */
  public static function create(ContainerInterface $container,
                                array $configuration,
                                $plugin_id,
                                $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('elastic_search.document.manager')
    );
  }

  /**
   * @inheritdoc
   */
  public function getFormFields(array $defaults, int $depth = 0): array {
    $options = ['true', 'false', 'strict'];
    $dynOptions = array_combine($options, $options);
    $form['dynamic'] = [
      '#type'          => 'select',
      '#title'         => $this->t('Dynamic'),
      '#description'   => $this->t('Whether or not new properties should be added dynamically to an existing object. Accepts true (default), false and strict'),
      '#options'       => $dynOptions,
      '#default_value' => $defaults['dynamic'] ?? 'true',
    ];
    $form['enabled'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Enabled'),
      '#description'   => $this->t('Whether the JSON value given for the object field should be parsed and indexed (true, default) or completely ignored (false).'),
      '#default_value' => $defaults['enabled'] ?? TRUE,
    ];
    //Properties is dealt with via the merge of the other types fields.
    return array_merge($form,
                       $this->getIncludeInAllField($defaults[$this->getIncludeInAllFieldId()]
                                                   ??
                                                   $this->getIncludeInAllFieldDefault()));
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
   * @throws \Drupal\Core\DependencyInjection\ContainerNotInitializedException
   * @throws \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\elastic_search\Exception\ElasticDocumentManagerRecursionException
   * @throws \Drupal\elastic_search\Exception\MapNotFoundException
   */
  public function normalizeFieldData(string $id,
                                     array $data,
                                     array $fieldMappingData) {
    if (empty($data) || !array_key_exists('target_id', $data[0])) {
      throw new FieldMapperFlattenSkipException();
    }
    $entityStorage = $this->entityTypeManager->getStorage($fieldMappingData['map'][0]['target_type']);
    $originalEntity = $entityStorage->load($data[0]['target_id']);
    if ($originalEntity) {
      try {
        if (array_key_exists('langcode', $data)) {
          $trans = $originalEntity->getTranslation($data['langcode']);
          $originalEntity = $trans;
        }
      } catch (\Throwable $t) {
        //exception might be caused by there being no translation on the interface or the object not being translated
        // so we ignore it and use the original entity if an exception happens
      }

      $originalId = FieldableEntityMap::getMachineName($originalEntity->getEntityTypeId(),
                                                       $originalEntity->bundle());
      $originalData = $originalEntity->toArray();
      $objectMapping = $this->documentManager->buildDataFromMap($originalId, $originalData);
      if (!empty($objectMapping)) {
        return $objectMapping;
      }
    }
    throw new FieldMapperFlattenSkipException('No map exists for this type so data cannot be mapped');
  }

}