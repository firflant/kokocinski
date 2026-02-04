<?php

declare(strict_types=1);

namespace Drupal\page_analytics\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\AdminContext;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Component\Datetime\TimeInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Drupal\Core\Queue\QueueFactory;

/**
 * Subscribes to kernel response to enqueue non-admin page views for analytics.
 *
 * Items are queued during the response phase (not on TERMINATE) so that
 * collection still works when the client or reverse proxy closes the
 * connection after the response is sent—a common production scenario where
 * TERMINATE may never run.
 */
class PageAnalyticsSubscriber implements EventSubscriberInterface {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The admin context.
   *
   * @var \Drupal\Core\Routing\AdminContext
   */
  protected AdminContext $adminContext;

  /**
   * The queue factory.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected QueueFactory $queueFactory;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected TimeInterface $time;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected AccountProxyInterface $currentUser;

  /**
   * Constructs the subscriber.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Routing\AdminContext $adminContext
   *   The admin context.
   * @param \Drupal\Core\Queue\QueueFactory $queueFactory
   *   The queue factory.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   */
  public function __construct(
    ConfigFactoryInterface $configFactory,
    AdminContext $adminContext,
    QueueFactory $queueFactory,
    TimeInterface $time,
    AccountProxyInterface $currentUser,
  ) {
    $this->configFactory = $configFactory;
    $this->adminContext = $adminContext;
    $this->queueFactory = $queueFactory;
    $this->time = $time;
    $this->currentUser = $currentUser;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      KernelEvents::RESPONSE => ['onKernelResponse', -100],
    ];
  }

  /**
   * Enqueues a page view for non-admin 200 responses.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   The response event.
   */
  public function onKernelResponse(ResponseEvent $event): void {
    $response = $event->getResponse();
    if ($response->getStatusCode() !== 200) {
      return;
    }

    if ($this->adminContext->isAdminRoute()) {
      return;
    }

    $request = $event->getRequest();
    $path = $request->getPathInfo();
    $path = '/' . trim($path, '/');
    if ($path === '') {
      $path = '/';
    }

    if (str_starts_with($path, '/admin')) {
      return;
    }

    if ($this->configFactory->get('page_analytics.settings')->get('exclude_authenticated_users')
      && !$this->currentUser->isAnonymous()) {
      return;
    }

    if (static::isExcludedAssetPath($path)) {
      return;
    }

    if (strlen($path) > 255) {
      $path = substr($path, 0, 255);
    }

    $rate = (int) $this->configFactory->get('page_analytics.settings')->get('sampling_rate');
    if ($rate >= 2 && mt_rand(1, $rate) !== 1) {
      return;
    }
    $rate = max(1, $rate);

    $date = date('Y-m-d', $this->time->getRequestTime());

    $this->queueFactory->get('page_analytics')->createItem([
      'path' => $path,
      'date' => $date,
      'sampling_rate' => $rate,
    ]);
  }

  /**
   * Checks if the path looks like an excluded asset (e.g. image or JS).
   *
   * @param string $path
   *   The request path (e.g. /sites/default/files/photo.jpg or /themes/…/script.js).
   *
   * @return bool
   *   TRUE if the path appears to be an excluded asset file, FALSE otherwise.
   */
  protected static function isExcludedAssetPath(string $path): bool {
    // Exclude any path that ends with a file extension (.*).
    return (bool) preg_match('/\.[a-zA-Z0-9]+$/', $path);
  }

}
