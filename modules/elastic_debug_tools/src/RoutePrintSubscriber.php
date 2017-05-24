<?php
/**
 * Created by PhpStorm.
 * User: twhiston
 * Date: 09.05.17
 * Time: 15:43
 */

namespace Drupal\elastic_debug_tools;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Provides a MyModuleSubscriber.
 */
class RoutePrintSubscriber implements EventSubscriberInterface {

  public function PrintCurrentRoute(GetResponseEvent $event) {
    $route_name = \Drupal::service('current_route_match')->getRouteName();
    drupal_set_message($route_name);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::REQUEST][] = ['PrintCurrentRoute', 20];
    return $events;
  }

}