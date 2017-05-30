<?php

namespace Drupal\elastic_search\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Url;
use Drupal\elastic_search\Elastic\ElasticIndexGenerator;
use Drupal\elastic_search\Elastic\ElasticIndexManager;
use Drupal\elastic_search\Entity\ElasticIndex;
use Drupal\elastic_search\Entity\ElasticIndexInterface;
use Drupal\elastic_search\ValueObject\BatchDefinition;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Class IndexController.
 *
 * @package Drupal\elastic_search\Controller
 */
class IndexController extends ControllerBase {

  /**
   * @var \Drupal\elastic_search\Elastic\ElasticIndexManager
   */
  protected $indexManager;

  /**
   * @var EntityStorageInterface
   */
  protected $indexStorage;

  /**
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $loggerChannel;

  /**
   * @var \Drupal\elastic_search\Elastic\ElasticIndexGenerator
   */
  protected $indexGenerator;

  /**
   * @var int
   */
  protected $batchChunkSize = 10;

  /**
   * @inheritDoc
   */
  public function __construct(ElasticIndexManager $indexManager,
                              LoggerChannelInterface $loggerChannel,
                              EntityStorageInterface $indexStorage,
                              ElasticIndexGenerator $indexGenerator
  ) {
    $this->indexManager = $indexManager;
    $this->loggerChannel = $loggerChannel;
    $this->indexStorage = $indexStorage;
    $this->indexGenerator = $indexGenerator;
  }

  /**
   * @inheritDoc
   *
   * @throws \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
   * @throws \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('elastic_search.indices.manager'),
                      $container->get('logger.factory')
                                ->get('elastic_search.indices.controller'),
                      $container->get('entity_type.manager')
                                ->getStorage('elastic_index'),
                      $container->get('elastic_search.indices.generator'));
  }

  /**
   * @return int
   */
  public function getBatchChunkSize(): int {
    return $this->batchChunkSize;
  }

  /**
   * @param int $batchChunkSize
   */
  public function setBatchChunkSize(int $batchChunkSize) {
    $this->batchChunkSize = $batchChunkSize;
  }

  /**
   * Update.
   *
   * @param \Drupal\elastic_search\Entity\ElasticIndexInterface $elastic_index
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   */
  public function updateMapping(ElasticIndexInterface $elastic_index): RedirectResponse {
    return $this->updateMappings([$elastic_index]);
  }

  /**
   * UpdateIndices.
   *
   * Updates Indices on server
   *
   * @param array $elasticIndices
   *
   * @return null|\Symfony\Component\HttpFoundation\RedirectResponse
   */
  public function updateMappings(array $elasticIndices = []) {

    if (empty($elasticIndices)) {
      $elasticIndices = $this->indexStorage->loadMultiple();
    }
    $chunks = array_chunk($elasticIndices, $this->batchChunkSize);
    return $this->executeBatch($chunks,
                               '\Drupal\elastic_search\Controller\IndexController::processUpdateMappingBatch',
                               '\Drupal\elastic_search\Controller\IndexController::finishBatch',
                               'update');
  }

  /**
   * @param array $indices
   * @param array $context
   *
   * @throws \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
   * @throws \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
   */
  public static function processUpdateMappingBatch(array $indices, array &$context) {

    //static function so cannot use DI :'(
    $indexManager = \Drupal::getContainer()->get('elastic_search.indices.manager');

    /** @var \Drupal\elastic_search\Entity\ElasticIndex $index */
    foreach ($indices as $index) {
      try {
        if ($indexManager->updateIndexOnServer($index)) {
          $indexManager->markIndexAsUpdated($index);
          drupal_set_message("Updated index: " . $index->id());
        }
      } catch (\Throwable $t) {
        IndexController::printErrorMessage($t);
      }
      $context['sandbox']['progress']++;
      $context['results'][] = $index;
    }

    //Optional pause
    $serverConfig = \Drupal::config('elastic_search.server');
    if ($serverConfig->get('advanced.pause') !== NULL) {
      sleep((int) $serverConfig->get('advanced.pause'));
    }

  }

  /**
   * @param \Drupal\elastic_search\Entity\ElasticIndexInterface $elastic_index
   *
   * @return null|\Symfony\Component\HttpFoundation\RedirectResponse
   */
  public function deleteIndex(ElasticIndexInterface $elastic_index) {
    return $this->deleteIndices([$elastic_index]);
  }

  /**
   * @param ElasticIndexInterface[] $elasticIndices
   *
   * @return null|\Symfony\Component\HttpFoundation\RedirectResponse
   */
  public function deleteIndices(array $elasticIndices = []) {

    if (empty($elasticIndices)) {
      $elasticIndices = $this->indexStorage->loadMultiple();
    }
    $chunks = array_chunk($elasticIndices, $this->batchChunkSize);
    return $this->executeBatch($chunks,
                               '\Drupal\elastic_search\Controller\IndexController::processDeleteBatch',
                               '\Drupal\elastic_search\Controller\IndexController::finishBatch',
                               'deletion');

  }

  /**
   * @param ElasticIndexInterface[] $elasticIndices
   * @param array                   $context
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public static function processDeleteBatch(array $elasticIndices, array &$context) {

    //static function so cannot use DI :'(
    $indexManager = \Drupal::getContainer()->get('elastic_search.indices.manager');

    $deleteIndexEntity = FALSE;

    //Process the indices
    /** @var ElasticIndexInterface $index */
    foreach ($elasticIndices as $index) {
      $result = [
        'index_status'  => 'available',
        'entity_status' => 'available',
        'id'            => $index->id(),
      ];

      $future = $indexManager->queueIndexForDeletion($index);
      try {
        // access future's values, causing resolution if necessary
        if ($future['acknowledged']) {
          $deleteIndexEntity = TRUE;
          $result['index_status'] = 'deleted';
        }
      } catch (\Throwable $t) {
        $error = json_decode($t->getMessage());
        if ($error->status === 404) {
          //If it doesn't exist on the server we can still delete locally
          $deleteIndexEntity = TRUE;
          $result['index_status'] = '404';
        } else {
          //If it some other kind of error when we try to delete we should log it and we will not delete the local index
          $result['index_status'] = $t->getMessage();
        }
      }
      if ($deleteIndexEntity) {
        $index->delete();
        $result['entity_status'] = 'deleted';
      }
      drupal_set_message($index->id() . ' Status: ' . $result['index_status'] . ' Entity Status: ' .
                         $result['entity_status']);
      $context['sandbox']['progress']++;
      $context['results'][] = $result;
    }

    //Optional pause
    $serverConfig = \Drupal::config('elastic_search.server');
    if ($serverConfig->get('advanced.pause') !== NULL) {
      sleep((int) $serverConfig->get('advanced.pause'));
    }
  }

  /**
   * @param \Throwable $t
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|string
   */
  private static function printErrorMessage(\Throwable $t) {
    $message = $t->getMessage();
    $decoded = json_decode($message);
    if ($decoded) {
      drupal_set_message(t('Error: @type : @reason ',
                           [
                             '@type'   => $decoded->error->type,
                             '@reason' => $decoded->error->reason,
                           ]),
                         'error');
      $message = $decoded->error->type . ' <pre>' . print_r($decoded, TRUE) . '</pre>';
    } else {
      $message = t($t->getMessage());
      drupal_set_message($message);
    }
    return $message;
  }

  /**
   * Called as an action on the index list route
   *
   * @param array $maps
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *
   * @throws \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
   * @throws \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\elastic_search\Exception\IndexGeneratorBundleNotFoundException
   *
   */
  public function generateIndexEntities(array $maps = []): RedirectResponse {

    $indices = $this->indexGenerator->generate($maps);

    $chunks = array_chunk($indices, $this->batchChunkSize);
    return $this->executeBatch($chunks,
                               '\Drupal\elastic_search\Controller\IndexController::processGenerateBatch',
                               '\Drupal\elastic_search\Controller\IndexController::finishBatch',
                               'creation');
  }

  /**
   * @param array $indices
   * @param array $context
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public static function processGenerateBatch(array $indices, array &$context) {
    /** @var ElasticIndex $index */
    foreach ($indices as $index) {
      $index->save();
      $context['sandbox']['progress']++;
      $context['results'][] = $index;
    }
    //As this is only local we do not offer the optional pause
  }

  /**
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   */
  public function bulkClearIndexDocuments(): RedirectResponse {
    $indices = $this->indexStorage->loadMultiple();
    $chunks = array_chunk($indices, $this->batchChunkSize);
    return $this->executeBatch($chunks,
                               '\Drupal\elastic_search\Controller\IndexController::processClearBatch',
                               '\Drupal\elastic_search\Controller\IndexController::finishBatch',
                               'clearing');

  }

  /**
   * @param \Drupal\elastic_search\Entity\ElasticIndexInterface $elastic_index
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   */
  public function clearIndexDocuments(ElasticIndexInterface $elastic_index): RedirectResponse {
    return $this->executeBatch([[$elastic_index]],
                               '\Drupal\elastic_search\Controller\IndexController::processClearBatch',
                               '\Drupal\elastic_search\Controller\IndexController::finishBatch',
                               'clearing');

  }

  /**
   * @param $indices
   * @param $context
   *
   * @throws \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
   * @throws \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
   */
  public static function processClearBatch($indices, &$context) {

    $indexManager = \Drupal::getContainer()->get('elastic_search.indices.manager');

    /** @var ElasticIndex $index */
    foreach ($indices as $index) {
      //TODO - response handling
      $indexManager->clearIndexDocuments($index->getIndexName(), $index->getIndexId());
      $context['sandbox']['progress']++;
    }
    //Optional pause
    $serverConfig = \Drupal::config('elastic_search.server');
    if ($serverConfig->get('advanced.pause') !== NULL) {
      sleep((int) $serverConfig->get('advanced.pause'));
    }

  }

  /**
   * @param \Drupal\elastic_search\Entity\ElasticIndexInterface $elastic_index
   *
   * @return null|\Symfony\Component\HttpFoundation\RedirectResponse
   */
  public function documentUpdate(ElasticIndexInterface $elastic_index) {
    return $this->documentUpdates([$elastic_index]);
  }

  /**
   * @param array $elasticIndices
   *
   * @return null|\Symfony\Component\HttpFoundation\RedirectResponse
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public function documentUpdates($elasticIndices = []) {

    if (empty($elasticIndices)) {
      $elasticIndices = $this->indexStorage->loadMultiple();
    }

    $processed = [];
    foreach ($elasticIndices as $elasticIndex) {
      $entities = $this->indexManager->getDocumentsThatShouldBeInIndex($elasticIndex);
      $chunks = array_chunk($entities, $this->batchChunkSize);
      foreach ($chunks as &$chunk) {
        array_unshift($chunk, $elasticIndex);
      }
      $processed = array_merge($processed, $chunks);
    }

    return $this->executeBatch($processed,
                               '\Drupal\elastic_search\Controller\IndexController::processDocumentIndexBatch',
                               '\Drupal\elastic_search\Controller\IndexController::finishBatch',
                               'document update');
  }

  /**
   * @param array $entities
   * @param array $context
   *
   * @throws \Exception
   */
  public static function processDocumentIndexBatch(array $entities, array &$context) {

    //static function so cannot use DI :'(
    $indexManager = \Drupal::getContainer()->get('elastic_search.indices.manager');

    $index = array_shift($entities);

    $indexManager->documentUpdate($index, $entities);
    $context['results'][] = $index;
    //Optional pause
    $serverConfig = \Drupal::config('elastic_search.server');
    if ($serverConfig->get('advanced.pause') !== NULL) {
      sleep((int) $serverConfig->get('advanced.pause'));
    }

  }

  /**
   * @param array  $chunks
   * @param string $opCallback
   * @param string $finishCallback
   * @param string $messageKey
   *
   * @return null|\Symfony\Component\HttpFoundation\RedirectResponse
   */
  protected function executeBatch(array $chunks, string $opCallback, string $finishCallback, string $messageKey = '') {

    $ops = [];
    foreach ($chunks as $chunkedIndices) {
      $ops[] = [$opCallback, [$chunkedIndices]];
    }
    $batch = new BatchDefinition($ops,
                                 $finishCallback,
                                 $this->t('Processing index ' . $messageKey . ' batch'),
                                 $this->t('Index ' . $messageKey . ' is starting.'),
                                 $this->t('Processed @current out of @total.'),
                                 $this->t('Encountered an error.')
    );
    batch_set($batch->getDefinitionArray());
    return batch_process(Url::fromRoute('entity.elastic_index.collection'));

  }

  /**
   * @param bool  $success
   * @param array $results
   * @param       $operations
   */
  public static function finishBatch(bool $success, array $results, $operations) {

    if ($success) {
      // Here we do something meaningful with the results.
      $message = t('@count items processed', ['@count' => count($results)]);
      drupal_set_message($message);
    } else {
      // An error occurred.
      // $operations contains the operations that remained unprocessed.
      $error_operation = reset($operations);
      $message = t('An error occurred while processing %error_operation with arguments: @arguments',
                   ['%error_operation' => $error_operation[0], '@arguments' => print_r($error_operation[1], TRUE)]);
      drupal_set_message($message, 'error');
    }

  }

}
