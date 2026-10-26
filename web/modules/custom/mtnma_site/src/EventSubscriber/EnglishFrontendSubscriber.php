<?php

namespace Drupal\mtnma_site\EventSubscriber;

use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Routing\AdminContext;
use Drupal\Core\Url;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Keeps English as an administration language only.
 *
 * The public site is published in Arabic and French; there is no English
 * content. English public pages (/en, /en/about...) redirect permanently to
 * the same page in French, while /en/admin and the other admin pages stay
 * available for editors who prefer an English interface.
 */
class EnglishFrontendSubscriber implements EventSubscriberInterface {

  public function __construct(
    protected LanguageManagerInterface $languageManager,
    protected AdminContext $adminContext,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      // After routing (32), so admin routes can be recognised.
      KernelEvents::REQUEST => ['onRequest', 31],
      // English aliases do not exist, so /en/about is a 404 before that.
      KernelEvents::EXCEPTION => ['onException', 100],
    ];
  }

  /**
   * Redirects English public pages to their French version.
   */
  public function onRequest(RequestEvent $event): void {
    if (!$event->isMainRequest() || !$this->isEnglish() || $this->adminContext->isAdminRoute()) {
      return;
    }
    $request = $event->getRequest();
    $route = $request->attributes->get('_route');
    if ($route === NULL || str_starts_with($route, 'system.') || $route === 'user.logout') {
      return;
    }
    $url = \Drupal::service('path.matcher')->isFrontPage() ? Url::fromRoute('<front>') : Url::fromRouteMatch(\Drupal::routeMatch());
    $url->setOption('language', $this->languageManager->getLanguage('fr'))
      ->setOption('query', $request->query->all());
    $event->setResponse(new RedirectResponse($url->toString(), 301));
  }

  /**
   * Redirects English URLs that match no page to the French path.
   */
  public function onException(ExceptionEvent $event): void {
    if (!$event->isMainRequest() || !$event->getThrowable() instanceof NotFoundHttpException || !$this->isEnglish()) {
      return;
    }
    $request = $event->getRequest();
    $path = preg_replace('#^/en(?=/|$)#', '', $request->getPathInfo());
    $query = $request->getQueryString();
    $event->setResponse(new RedirectResponse($request->getBasePath() . '/fr' . $path . ($query ? "?$query" : ''), 301));
  }

  /**
   * Checks whether the current request was negotiated as English.
   */
  protected function isEnglish(): bool {
    return $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_URL)->getId() === 'en';
  }

}
