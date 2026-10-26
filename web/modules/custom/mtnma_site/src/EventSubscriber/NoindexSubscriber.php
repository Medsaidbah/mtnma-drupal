<?php

namespace Drupal\mtnma_site\EventSubscriber;

use Drupal\Core\Site\Settings;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Keeps the prototype out of search engines.
 *
 * Like the reference site's preview (vercel.json), every response carries
 * "X-Robots-Tag: noindex, nofollow" so the redesign is not listed next to
 * the official Ministry website. A site that becomes the official one turns
 * it off in settings.php: $settings['mtnma_site_noindex'] = FALSE;
 */
class NoindexSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [KernelEvents::RESPONSE => ['onResponse']];
  }

  /**
   * Adds the robots header.
   */
  public function onResponse(ResponseEvent $event): void {
    if (Settings::get('mtnma_site_noindex', TRUE)) {
      $event->getResponse()->headers->set('X-Robots-Tag', 'noindex, nofollow');
    }
  }

}
