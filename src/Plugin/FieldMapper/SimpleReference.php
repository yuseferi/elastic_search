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
 * Class SimpleReference
 * Meta type for simple references
 *
 * @FieldMapper(
 *   id = "simple_reference",
 *   label = @Translation("Simple Reference")
 * )
 */
class SimpleReference extends FieldMapperBase {

  use StringTranslationTrait;

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
    ];
  }

  /**
   * An array of DSL that we use as a 'canned response' for when we have to return a simple reference
   *
   * @var array
   */
  public static $simpleReferenceDsl = [
    'type'                  => 'keyword',
    'boost'                 => 0,
    'doc_values'            => TRUE,
    'eager_global_ordinals' => FALSE,
    'include_in_all'        => TRUE,
    'index'                 => TRUE,
    'index_options'         => 'docs',
    'norms'                 => FALSE,
    'similarity'            => 'classic',
    'store'                 => FALSE,
  ];

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
   * @inheritDoc
   */
  public function getDslFromData(array $data): array {
    return self::$simpleReferenceDsl;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
   * @throws \Drupal\Core\DependencyInjection\ContainerNotInitializedException
   * @throws \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
   */
  public function normalizeFieldData(string $id,
                                     array $data,
                                     array $fieldMappingData) {
    if (empty($data) || !array_key_exists('target_id', $data[0])) {
      throw new FieldMapperFlattenSkipException();
    }
    return $data[0]['target_id'];
  }

}