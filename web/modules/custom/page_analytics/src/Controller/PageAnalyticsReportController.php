<?php

declare(strict_types=1);

namespace Drupal\page_analytics\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Url;
use Drupal\Component\Datetime\TimeInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for the page analytics report.
 */
class PageAnalyticsReportController extends ControllerBase {

  /**
   * Allowed "top N" limit options for the report (query param: top).
   */
  public const TOP_LIMIT_OPTIONS = [30, 100, 300];

  /**
   * Default number of top paths to display.
   */
  protected const DEFAULT_TOP_LIMIT = 30;

  /**
   * Days to use for "top paths" aggregation (always last 30 days).
   */
  protected const TOP_DAYS = 30;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $connection;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected TimeInterface $time;

  /**
   * Constructs the controller.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(Connection $connection, TimeInterface $time) {
    $this->connection = $connection;
    $this->time = $time;
  }

  /**
   * Builds the page analytics report.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request (for period query param).
   *
   * @return array
   *   A render array.
   */
  public function report(Request $request): array {
    $period = (int) $request->query->get('period', 7);
    if ($period !== 7 && $period !== 30) {
      $period = 7;
    }

    $top_limit = (int) $request->query->get('top', self::DEFAULT_TOP_LIMIT);
    if (!in_array($top_limit, self::TOP_LIMIT_OPTIONS, TRUE)) {
      $top_limit = self::DEFAULT_TOP_LIMIT;
    }

    $request_time = $this->time->getRequestTime();
    $today = date('Y-m-d', $request_time);
    $top_from = date('Y-m-d', strtotime('-' . self::TOP_DAYS . ' days', $request_time));
    $chart_from = date('Y-m-d', strtotime('-' . $period . ' days', $request_time));

    $settings = $this->config('page_analytics.settings');
    $sampling_rate = max(1, (int) $settings->get('sampling_rate'));
    $sampling_note = $sampling_rate > 1
      ? (string) $this->t('Totals are estimated from sampled page views.')
      : '';
    $top_options = $this->buildTopOptions($period);

    $query = $this->connection->select('page_analytics_daily', 'r');
    $query->addField('r', 'path');
    $query->addExpression('SUM(r.view_count)', 'total');
    $query->condition('r.stat_date', $top_from, '>=');
    $query->condition('r.stat_date', $today, '<=');
    $query->groupBy('r.path');
    $query->orderBy('total', 'DESC');
    $query->range(0, $top_limit);

    $top_paths = $query->execute()->fetchAllKeyed(0, 1);

    if (empty($top_paths)) {
      return [
        '#theme' => 'page_analytics_report',
        '#rows' => [],
        '#period' => $period,
        '#period_7_url' => Url::fromRoute('page_analytics.report', [], ['query' => ['period' => 7, 'top' => $top_limit]])->toString(),
        '#period_30_url' => Url::fromRoute('page_analytics.report', [], ['query' => ['period' => 30, 'top' => $top_limit]])->toString(),
        '#top_limit' => $top_limit,
        '#top_options' => $top_options,
        '#sampling_rate' => $sampling_rate,
        '#sampling_note' => $sampling_note,
        '#attached' => [
          'library' => ['page_analytics/page_analytics.report'],
        ],
        '#cache' => [
          'max-age' => 0,
          'contexts' => ['url.query_args'],
        ],
      ];
    }

    $daily_query = $this->connection->select('page_analytics_daily', 'r');
    $daily_query->addField('r', 'path');
    $daily_query->addField('r', 'stat_date');
    $daily_query->addField('r', 'view_count');
    $daily_query->condition('r.path', array_keys($top_paths), 'IN');
    $daily_query->condition('r.stat_date', $chart_from, '>=');
    $daily_query->condition('r.stat_date', $today, '<=');
    $daily_query->orderBy('r.stat_date', 'ASC');

    $daily_rows = $daily_query->execute()->fetchAll();

    $daily_by_path = [];
    foreach ($daily_rows as $row) {
      $daily_by_path[$row->path][$row->stat_date] = (int) $row->view_count;
    }

    $date_labels = [];
    for ($i = $period - 1; $i >= 0; $i--) {
      $d = date('Y-m-d', strtotime("-$i days", $request_time));
      $date_labels[] = $d;
    }

    $rows = [];
    $index = 0;
    foreach ($top_paths as $path => $total_30) {
      $period_total = 0;
      $values = [];
      foreach ($date_labels as $d) {
        $v = $daily_by_path[$path][$d] ?? 0;
        $values[] = $v;
        $period_total += $v;
      }

      $rows[] = [
        'path' => $path,
        'total' => $period_total,
        'chart_index' => $index,
        'chart_labels' => $date_labels,
        'chart_values' => $values,
      ];
      $index++;
    }

    return [
      '#theme' => 'page_analytics_report',
      '#rows' => $rows,
      '#period' => $period,
      '#period_7_url' => Url::fromRoute('page_analytics.report', [], ['query' => ['period' => 7, 'top' => $top_limit]])->toString(),
      '#period_30_url' => Url::fromRoute('page_analytics.report', [], ['query' => ['period' => 30, 'top' => $top_limit]])->toString(),
      '#top_limit' => $top_limit,
      '#top_options' => $top_options,
      '#sampling_rate' => $sampling_rate,
      '#sampling_note' => $sampling_note,
      '#attached' => [
        'library' => ['page_analytics/page_analytics.report'],
      ],
      '#cache' => [
        'max-age' => 0,
        'contexts' => ['url.query_args'],
      ],
    ];
  }

  /**
   * Builds the top_options array for the report template.
   *
   * @param int $period
   *   The selected period (7 or 30 days).
   *
   * @return array<int, array{value: int, url: string}>
   *   Options for the top N selector.
   */
  protected function buildTopOptions(int $period): array {
    $options = [];
    foreach (self::TOP_LIMIT_OPTIONS as $n) {
      $options[] = [
        'value' => $n,
        'url' => Url::fromRoute('page_analytics.report', [], [
          'query' => ['period' => $period, 'top' => $n],
        ])->toString(),
      ];
    }
    return $options;
  }

}
