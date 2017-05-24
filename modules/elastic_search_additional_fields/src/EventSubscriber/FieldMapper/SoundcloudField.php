<?php

namespace Drupal\elastic_search_additional_fields\EventSubscriber\FieldMapper;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class SoundcloudField
 *
 * @package Drupal\elastic_search_additional_fields\EventSubscriber\FieldMapper
 */
class SoundcloudField implements EventSubscriberInterface {

  /**
   * @return mixed
   */
  public static function getSubscribedEvents() {
    $events['elastic_search.field_mapper.supports.text'][] = [
      'alterSupports',
      0,
    ];
    $events['elastic_search.field_mapper.supports.keyword'][] = [
      'alterSupports',
      0,
    ];
    return $events;
  }

  /**
   * @param $event
   */
  public function alterSupports($event) {

    $config = $event->getSupported();

    $config[] = 'soundcloud';

    $event->setSupported($config);
  }

}
