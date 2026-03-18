<?php

declare(strict_types=1);

namespace Drupal\page_analytics\StackMiddleware;

use Drupal\page_analytics\PathExclusion\PageAnalyticsExclusion;
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
   * Constructs the middleware.
   *
   * @param \Symfony\Component\HttpKernel\HttpKernelInterface|\Closure $httpKernel
   *   The inner kernel or a closure that returns it.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Queue\QueueFactory $queueFactory
   *   The queue factory.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   * @param \Drupal\page_analytics\PathExclusion\PageAnalyticsExclusion $pathExclusion
   *   The path exclusion service.
   */
  public function __construct(
    protected HttpKernelInterface|\Closure $httpKernel,
    protected ConfigFactoryInterface $configFactory,
    protected QueueFactory $queueFactory,
    protected TimeInterface $time,
    protected AccountProxyInterface $currentUser,
    protected ?PageAnalyticsExclusion $pathExclusion = NULL,
  ) {
    $this->httpKernel = $httpKernel instanceof HttpKernelInterface
      ? fn () => $httpKernel
      : $httpKernel;
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

    $settings = $this->configFactory->get('page_analytics.settings');
    if ($this->pathExclusion !== NULL && $this->pathExclusion->isPathExcludedByCurrentRules($path)) {
      return $response;
    }

    $excluded_roles = $settings->get('excluded_roles');
    if (!is_array($excluded_roles)) {
      $excluded_roles = [];
    }
    $excluded_roles = array_values(array_filter($excluded_roles, static fn ($role): bool => is_string($role) && $role !== ''));

    if ($excluded_roles !== [] && array_intersect($this->currentUser->getRoles(), $excluded_roles)) {
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

}
