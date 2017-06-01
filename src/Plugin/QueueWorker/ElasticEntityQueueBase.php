<?php
/**
 * Created by PhpStorm.
 * User: twhiston
 * Date: 31.05.17
 * Time: 10:18
 */

namespace Drupal\elastic_search\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\elastic_search\Elastic\ElasticDocumentManager;
use Drupal\elastic_search\ValueObject\QueueItem;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Queue Base
 */
abstract class ElasticEntityQueueBase extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The node storage.
   *
   * @var \Drupal\elastic_search\Elastic\ElasticDocumentManager
   */
  protected $documentManager;

  /**
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Creates a new NodePublishBase object.
   *
   * @param array                                                 $configuration
   * @param string                                                $plugin_id
   * @param mixed                                                 $plugin_definition
   * @param \Drupal\elastic_search\Elastic\ElasticDocumentManager $documentManager
   * @param \Psr\Log\LoggerInterface                              $logger
   */
  public function __construct(array $configuration,
                              $plugin_id,
                              $plugin_definition,
                              ElasticDocumentManager $documentManager,
                              LoggerInterface $logger) {
    $this->documentManager = $documentManager;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
   * @throws \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('elastic_search.document.manager'),
      $container->get('logger.factory')->get('elastic.queue.entity')
    );
  }

  /**
   * {@inheritdoc}
   *
   * @var QueueItem[] $data
   */
  public function processItem($data) {
    $data = is_array($data) ? $data : [$data];
    $hydrated = ElasticEntityQueueBase::hydrateItems($data);
    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    $this->process($hydrated, $this->documentManager, $this->logger);
  }

  /**
   * Actual callback to do the processing
   *
   * @param array                                                 $entities
   * @param \Drupal\elastic_search\Elastic\ElasticDocumentManager $documentManager
   * @param \Psr\Log\LoggerInterface                              $logger
   *
   * @return mixed
   */
  abstract protected function process(array $entities, ElasticDocumentManager $documentManager, LoggerInterface $logger);

  /**
   * @param array $data
   *
   * @return array
   */
  public static function hydrateItems(array $data) {

    //hydrate the entities
    $hydrated = [];
    foreach ($data as $datum) {
      /** @var \Drupal\Core\Entity\EntityInterface $entity */
      $entity = \Drupal::service('entity.repository')->loadEntityByUuid($datum->getEntityType(), $datum->getUuid());
      try {
        $hydrated[] = $entity->getTranslation($datum->getLanguage());
      } catch (\Throwable $t) {
        $hydrated[] = $entity;
      }
    }

    return $hydrated;

  }

}