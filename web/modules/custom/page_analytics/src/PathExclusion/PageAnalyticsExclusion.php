<?php

declare(strict_types=1);

namespace Drupal\page_analytics\PathExclusion;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Path\PathMatcherInterface;
use Drupal\Core\Routing\AdminContext;
use Drupal\Core\Routing\RouteProviderInterface;
use Symfony\Component\Routing\Exception\ExceptionInterface as RoutingExceptionInterface;
use Symfony\Component\Routing\Route;

/**
 * Determines if a path is excluded from analytics using configurable rules.
 *
 * Uses the same path pattern format as block "Pages" visibility (core
 * PathMatcher): one path per line, * wildcard, <front> for front page.
 * Compilation and caching are handled by PathMatcher (one regex per config).
 */
class PageAnalyticsExclusion {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The path matcher (same as block visibility "Pages").
   *
   * @var \Drupal\Core\Path\PathMatcherInterface
   */
  protected PathMatcherInterface $pathMatcher;

  /**
   * Route provider service.
   *
   * @var \Drupal\Core\Routing\RouteProviderInterface
   */
  protected ?RouteProviderInterface $routeProvider;

  /**
   * Admin route helper.
   *
   * @var \Drupal\Core\Routing\AdminContext
   */
  protected ?AdminContext $adminContext;

  /**
   * Constructs the service.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Path\PathMatcherInterface $pathMatcher
   *   The path matcher (core path.matcher).
   * @param \Drupal\Core\Routing\RouteProviderInterface|null $routeProvider
   *   Route provider service.
   * @param \Drupal\Core\Routing\AdminContext|null $adminContext
   *   Admin route helper service.
   */
  public function __construct(
    ConfigFactoryInterface $configFactory,
    PathMatcherInterface $pathMatcher,
    ?RouteProviderInterface $routeProvider = NULL,
    ?AdminContext $adminContext = NULL,
  ) {
    $this->configFactory = $configFactory;
    $this->pathMatcher = $pathMatcher;
    $this->routeProvider = $routeProvider;
    $this->adminContext = $adminContext;
  }

  /**
   * Checks whether a path is excluded by current rules.
   *
   * Includes admin route exclusion and custom excluded path patterns.
   *
   * @param string $path
   *   Normalized path (e.g. /foo/bar or /).
   *
   * @return bool
   *   TRUE if excluded by current exclusion rules.
   */
  public function isPathExcludedByCurrentRules(string $path): bool {
    $settings = $this->configFactory->get('page_analytics.settings');
    if ((bool) $settings->get('exclude_admin_paths') && $this->routeProvider !== NULL && $this->adminContext !== NULL) {
      try {
        $routes = $this->routeProvider->getRoutesByPattern($path);
        foreach ($routes as $route) {
          if ($route instanceof Route && $this->adminContext->isAdminRoute($route)) {
            return TRUE;
          }
        }
      }
      catch (RoutingExceptionInterface $e) {
        // Treat unresolved routes as non-admin here; path patterns may still match.
      }
    }

    $patterns = $this->normalizePatterns((string) $settings->get('excluded_paths'));
    if ($patterns === '') {
      return FALSE;
    }
    return $this->pathMatcher->matchPath($path, $patterns);
  }

  /**
   * Normalizes the pattern string: trim lines, remove empty lines.
   *
   * @param string $text
   *   Newline-separated patterns from config.
   *
   * @return string
   *   Normalized pattern string for PathMatcher.
   */
  protected function normalizePatterns(string $text): string {
    $lines = array_filter(array_map('trim', explode("\n", $text)));
    return implode("\n", $lines);
  }

}
