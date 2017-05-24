<?php
/**
 * Created by PhpStorm.
 * User: twhiston
 * Date: 12.02.17
 * Time: 17:08
 */

namespace Drupal\elastic_search\Elastic;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\elastic_search\Entity\FieldableEntityMap;
use Drupal\elastic_search\Exception\ElasticDocumentBuilderSkipException;
use Drupal\elastic_search\Exception\ElasticDocumentManagerRecursionException;
use Drupal\elastic_search\Exception\FieldMapperFlattenSkipException;
use Drupal\elastic_search\Exception\IndexNotFoundException;
use Drupal\elastic_search\Exception\MapNotFoundException;
use Elasticsearch\Client;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class ElasticDocumentManager
 *
 * @package Drupal\elastic_search\Elastic
 */
class ElasticDocumentManager implements ContainerInjectionInterface {

  /**
   * @var  EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var Client
   */
  protected $elasticClient;

  /**
   * @var LoggerInterface
   */
  protected $logger;

  /**
   * @var PluginManagerInterface
   */
  protected $fieldMapperManager;

  /**
   * @var int
   */
  private $currentDepth = 0;

  /**
   * @var int
   */
  private $activeMaxDepth = 1;

  /**
   * ElasticDocumentBuilder constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface  $entityTypeManager
   * @param \Elasticsearch\Client                           $elasticClient
   * @param \Psr\Log\LoggerInterface                        $logger
   * @param \Drupal\Component\Plugin\PluginManagerInterface $fieldMapperManager
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager,
                              Client $elasticClient,
                              LoggerInterface $logger,
                              PluginManagerInterface $fieldMapperManager) {
    $this->entityTypeManager = $entityTypeManager;
    $this->elasticClient = $elasticClient;
    $this->logger = $logger;
    $this->fieldMapperManager = $fieldMapperManager;
  }

  /**
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *
   * @return static
   *
   * @throws \Exception
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('elastic_search.connection_factory')
                ->getElasticConnection(),
      $container->get('logger.factory')->get('elastic.document.manager'),
      $container->get('plugin.manager.elastic_field_mapper_plugin')
    );
  }

  /**
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *
   * @return array|\Drupal\elastic_search\Exception\ElasticDocumentBuilderSkipException
   * @throws \Exception
   */
  public function buildMappingPayload(EntityInterface $entity) {

    if ($this->isInternalType($entity)) {
      throw new ElasticDocumentBuilderSkipException();
    }

    $indices = $this->getIndices($entity);
    if (empty($indices)) {
      throw new IndexNotFoundException('No Indices were found for processing by Elastic Document Manager');
    }

    //For each index update it with the new node details
    /** @var \Drupal\elastic_search\Entity\ElasticIndexInterface $firstIndex */
    $firstIndex = reset($indices);
    $mappingEntityId = $firstIndex->getMappingEntityId();

    $fieldData = $entity->toArray();

    $mapData = $this->executeDocumentMappingProcess($mappingEntityId, $fieldData);

    return $this->createPayload($mapData, $indices, $entity->uuid());
  }

  /**
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *
   * @return \Drupal\elastic_search\Entity\ElasticIndexInterface[]
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  protected function getIndices(EntityInterface $entity) {
    $id = FieldableEntityMap::getMachineName($entity->getEntityTypeId(),
                                             $entity->bundle());

    /** @var \Drupal\Core\Entity\EntityStorageInterface $storage */
    $indexStorage = $this->entityTypeManager->getStorage('elastic_index');
    $language = $entity->language();
    /** @var \Drupal\elastic_search\Entity\ElasticIndexInterface[] $indices */
    $indices = $indexStorage->loadByProperties([
                                                 'mappingEntityId' => $id,
                                                 'indexLanguage'   => $language->getId(),
                                               ]);
    return $indices;
  }

  /**
   * @param array  $mapData
   * @param array  $indices
   * @param string $uuid
   *
   * @return array
   */
  protected function createPayload(array $mapData,
                                   array $indices,
                                   string $uuid) {
    $payloads = ['body' => []];
    foreach ($indices as $index) {
      $payloads['body'][] = [
        'index' => [
          '_index' => $index->getIndexName(),
          '_type'  => $index->getIndexId(),
          '_id'    => $uuid,
        ],
      ];
      $payloads['body'][] = $mapData;
    }
    return $payloads;
  }

  /**
   * @param string $mapId
   * @param array  $fieldData
   *
   * @return array
   * @throws \Exception
   */
  protected function executeDocumentMappingProcess(string $mapId, array $fieldData) {

    $this->currentDepth = 0;
    $fieldableEntityMapStorage = $this->entityTypeManager->getStorage('fieldable_entity_map');
    /** @var \Drupal\elastic_search\Entity\FieldableEntityMapInterface $fieldMap */
    $fieldMap = $fieldableEntityMapStorage->load($mapId);
    $this->activeMaxDepth = $fieldMap->getRecursionDepth();

    return $this->buildDataFromMap($mapId, $fieldData);
  }

  /**
   * @param string $mapId
   * @param array  $fieldData
   *
   * @return array
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\elastic_search\Exception\ElasticDocumentManagerRecursionException
   * @throws \Drupal\elastic_search\Exception\MapNotFoundException
   */
  public function buildDataFromMap(string $mapId, array $fieldData) {

    $fieldableEntityMapStorage = $this->entityTypeManager->getStorage('fieldable_entity_map');
    /** @var \Drupal\elastic_search\Entity\FieldableEntityMapInterface $fieldMap */
    $fieldMap = $fieldableEntityMapStorage->load($mapId);

    $output = [];

    if ($this->currentDepth > $this->activeMaxDepth) {
      //If we recurse above our max limit, throw an exception, which we catch below, and process as a simple_reference instead
      throw new ElasticDocumentManagerRecursionException('nesting depth exceeded');
    }

    ++$this->currentDepth;

    if (!$fieldMap) {
      throw new MapNotFoundException('Map not found: ' . $mapId);
    }
    $fieldMappingData = $fieldMap->getFields();

    foreach ($fieldData as $id => $data) {
      if (array_key_exists($id, $fieldMappingData)) {
        try {
          /** @var \Drupal\elastic_search\Plugin\FieldMapperInterface $fieldMapper */
          $fieldMapper = $this->fieldMapperManager->createInstance($fieldMappingData[$id]['map'][0]['type']);
          try {
            $flattened = $fieldMapper->normalizeFieldData($id,
                                                          $data,
                                                          $fieldMappingData[$id]);
          } catch (ElasticDocumentManagerRecursionException $e) {
            /** @var \Drupal\elastic_search\Plugin\FieldMapperInterface $fieldMapper */
            $fieldMapper = $this->fieldMapperManager->createInstance('simple_reference');
            $flattened = $fieldMapper->normalizeFieldData($id,
                                                          $data,
                                                          $fieldMappingData[$id]);

          }

          $output[$id] = $flattened;
        } catch (FieldMapperFlattenSkipException $e) {
          //Do nothing if this type of exception is thrown as it means skip adding the data
          continue;
        }
      }
    }
    --$this->currentDepth;
    return $output;
  }

  /**
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *
   * @return bool
   */
  protected function isInternalType(EntityInterface $entity) {

    $id = $entity->getEntityTypeId();
    if ($id === 'elastic_index' || $id === 'fieldable_entity_map') {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * @param array $payloads
   *
   * @return bool
   */
  public function sendDocuments(array $payloads) {
    $response = $this->elasticClient->bulk($payloads);
    if ($response['errors']) {
      $this->logger->warning(json_encode($response));
    }
    return !$response['errors'];
  }

  /**
   * @param \Drupal\Core\Entity\EntityInterface $entity
   */
  public function deleteEntity(EntityInterface $entity) {

    $indices = $this->getIndices($entity);
    $responses = [];
    foreach ($indices as $index) {
      $params = [
        'index' => $index->getIndexName(),
        'type'  => $index->getIndexId(),
        'id'    => $entity->uuid(),
      ];
      $responses[] = $this->elasticClient->delete($params);
    }
    //TODO - do something with responses? most real 'fails' throw anyway
  }

}