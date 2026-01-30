<?php

namespace Drupal\page_analytics\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\AdminContext;
use Drupal\Component\Datetime\TimeInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Drupal\Core\Queue\QueueFactory;

/**
 * Subscribes to kernel response to enqueue non-admin page views for analytics.
 */
class PageAnalyticsSubscriber implements EventSubscriberInterface {

  /**
   * Buffer of (path, date) to push to the queue after the response is sent.
   *
   * @var array<int, array{path: string, date: string}>
   */
  protected static array $buffer = [];

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
   */
  public function __construct(
    ConfigFactoryInterface $configFactory,
    AdminContext $adminContext,
    QueueFactory $queueFactory,
    TimeInterface $time,
  ) {
    $this->configFactory = $configFactory;
    $this->adminContext = $adminContext;
    $this->queueFactory = $queueFactory;
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      KernelEvents::RESPONSE => ['onKernelResponse', -100],
      KernelEvents::TERMINATE => ['onKernelTerminate'],
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

    if (static::isImagePath($path)) {
      return;
    }

    if (strlen($path) > 255) {
      $path = substr($path, 0, 255);
    }

    $rate = (int) $this->configFactory->get('page_analytics.settings')->get('sampling_rate');
    if ($rate >= 2 && mt_rand(1, $rate) !== 1) {
      return;
    }

    $date = date('Y-m-d', $this->time->getRequestTime());

    self::$buffer[] = [
      'path' => $path,
      'date' => $date,
    ];
  }

  /**
   * Flushes the buffer to the queue after the response has been sent.
   *
   * @param \Symfony\Component\HttpKernel\Event\TerminateEvent $event
   *   The terminate event.
   */
  public function onKernelTerminate(TerminateEvent $event): void {
    if (self::$buffer === []) {
      return;
    }

    $queue = $this->queueFactory->get('page_analytics');
    foreach (self::$buffer as $item) {
      $queue->createItem($item);
    }
    self::$buffer = [];
  }

  /**
   * Checks if the path looks like an image file request.
   *
   * @param string $path
   *   The request path (e.g. /sites/default/files/photo.jpg).
   *
   * @return bool
   *   TRUE if the path appears to be an image file, FALSE otherwise.
   */
  protected static function isImagePath(string $path): bool {
    $extensions = [
      'avif', 'bmp', 'gif', 'ico', 'jpeg', 'jpg', 'png', 'svg', 'webp',
    ];
    $lower = strtolower($path);
    foreach ($extensions as $ext) {
      if (str_ends_with($lower, '.' . $ext)) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
