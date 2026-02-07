<?php

declare(strict_types=1);

namespace Drupal\page_analytics\PathExclusion;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Path\PathMatcherInterface;

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
   * Constructs the service.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Path\PathMatcherInterface $pathMatcher
   *   The path matcher (core path.matcher).
   */
  public function __construct(ConfigFactoryInterface $configFactory, PathMatcherInterface $pathMatcher) {
    $this->configFactory = $configFactory;
    $this->pathMatcher = $pathMatcher;
  }

  /**
   * Checks whether a path should be excluded from analytics.
   *
   * @param string $path
   *   Normalized path (e.g. /foo/bar or /).
   *
   * @return bool
   *   TRUE if the path matches any exclusion rule.
   */
  public function isPathExcluded(string $path): bool {
    $raw = (string) $this->configFactory->get('page_analytics.settings')->get('excluded_paths');
    $patterns = $this->normalizePatterns($raw);
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
