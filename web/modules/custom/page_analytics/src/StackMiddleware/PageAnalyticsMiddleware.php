<?php

declare(strict_types=1);

namespace Drupal\page_analytics\StackMiddleware;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Component\Datetime\TimeInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Records page views for analytics before the response is sent.
 *
 * Runs before the page cache (priority 210 > 200). So we run for every
 * request—after the inner kernel or page cache returns the response we
 * record the view. Cached pages (page cache HIT) never run the full
 * kernel, so an event subscriber would not run; this middleware records
 * both cache hits and cache misses.
 */
class PageAnalyticsMiddleware implements HttpKernelInterface {

  /**
   * The wrapped HTTP kernel (or closure that returns it).
   *
   * @var \Closure|\Symfony\Component\HttpKernel\HttpKernelInterface
   */
  protected \Closure|HttpKernelInterface $httpKernel;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

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
   * Constructs the middleware.
   *
   * @param \Symfony\Component\HttpKernel\HttpKernelInterface|\Closure $http_kernel
   *   The inner kernel or a closure that returns it.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Queue\QueueFactory $queueFactory
   *   The queue factory.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   */
  public function __construct(
    HttpKernelInterface|\Closure $http_kernel,
    ConfigFactoryInterface $configFactory,
    QueueFactory $queueFactory,
    TimeInterface $time,
    AccountProxyInterface $currentUser,
  ) {
    $this->httpKernel = $http_kernel instanceof HttpKernelInterface
      ? fn () => $http_kernel
      : $http_kernel;
    $this->configFactory = $configFactory;
    $this->queueFactory = $queueFactory;
    $this->time = $time;
    $this->currentUser = $currentUser;
  }

  /**
   * {@inheritdoc}
   */
  public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = TRUE): Response {
    $response = ($this->httpKernel)()->handle($request, $type, $catch);

    if ($type !== self::MAIN_REQUEST) {
      return $response;
    }

    if ($response->getStatusCode() !== 200) {
      return $response;
    }

    $path = $request->getPathInfo();
    $path = '/' . trim($path, '/');
    if ($path === '') {
      $path = '/';
    }

    // Exclude /admin and /[lang]/admin (lang = 2- or 3-letter code, e.g. /en/admin, /und/admin).
    if (preg_match('#^(?:/admin(?:/|$)|/[a-z]{2,3}/admin(?:/|$))#', $path)) {
      return $response;
    }

    $settings = $this->configFactory->get('page_analytics.settings');
    if ($settings->get('exclude_authenticated_users') && !$this->currentUser->isAnonymous()) {
      return $response;
    }

    if (self::isExcludedAssetPath($path)) {
      return $response;
    }

    if (strlen($path) > 255) {
      $path = substr($path, 0, 255);
    }

    $rate = (int) $settings->get('sampling_rate');
    if ($rate >= 2 && mt_rand(1, $rate) !== 1) {
      return $response;
    }
    $rate = max(1, $rate);

    $date = date('Y-m-d', $this->time->getRequestTime());

    $this->queueFactory->get('page_analytics')->createItem([
      'path' => $path,
      'date' => $date,
      'sampling_rate' => $rate,
    ]);

    return $response;
  }

  /**
   * Checks if the path looks like an excluded asset (e.g. image or JS).
   */
  protected static function isExcludedAssetPath(string $path): bool {
    return (bool) preg_match('/\.[a-zA-Z0-9]+$/', $path);
  }

}
