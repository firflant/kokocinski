<?php

namespace Drupal\page_analytics\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for the page analytics report.
 */
class PageAnalyticsReportController extends ControllerBase {

  /**
   * Number of top paths to display.
   */
  protected const TOP_LIMIT = 50;

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
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactoryService;

  /**
   * Constructs the controller.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   */
  public function __construct(Connection $connection, ConfigFactoryInterface $configFactory) {
    $this->connection = $connection;
    $this->configFactoryService = $configFactory;
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

    $sampling_rate = (int) $this->configFactoryService->get('page_analytics.settings')->get('sampling_rate');
    if ($sampling_rate < 1) {
      $sampling_rate = 1;
    }
    $sampling_note = $sampling_rate > 1
      ? $this->t('Numbers are estimated from 1 in @rate sampling.', ['@rate' => $sampling_rate])
      : '';

    $today = date('Y-m-d');
    $top_from = date('Y-m-d', strtotime('-' . self::TOP_DAYS . ' days'));
    $chart_from = date('Y-m-d', strtotime('-' . $period . ' days'));

    $query = $this->connection->select('page_analytics_daily', 'r');
    $query->addField('r', 'path');
    $query->addExpression('SUM(r.view_count)', 'total');
    $query->condition('r.stat_date', $top_from, '>=');
    $query->condition('r.stat_date', $today, '<=');
    $query->groupBy('r.path');
    $query->orderBy('total', 'DESC');
    $query->range(0, self::TOP_LIMIT);

    $top_paths = $query->execute()->fetchAllKeyed(0, 1);

    if (empty($top_paths)) {
      return [
        '#theme' => 'page_analytics_report',
        '#rows' => [],
        '#period' => $period,
        '#period_7_url' => Url::fromRoute('page_analytics.report', ['query' => ['period' => 7]])->toString(),
        '#period_30_url' => Url::fromRoute('page_analytics.report', ['query' => ['period' => 30]])->toString(),
        '#sampling_rate' => $sampling_rate,
        '#sampling_note' => $sampling_note,
        '#attached' => [
          'library' => ['page_analytics/page_analytics.report'],
        ],
        '#cache' => [
          'max-age' => 0,
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
      $d = date('Y-m-d', strtotime("-$i days"));
      $date_labels[] = $d;
    }

    $rows = [];
    $index = 0;
    foreach ($top_paths as $path => $total_30) {
      $period_total = 0;
      $values = [];
      foreach ($date_labels as $d) {
        $v = $daily_by_path[$path][$d] ?? 0;
        $values[] = $v * $sampling_rate;
        $period_total += $v * $sampling_rate;
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
      '#period_7_url' => Url::fromRoute('page_analytics.report', [], ['query' => ['period' => 7]])->toString(),
      '#period_30_url' => Url::fromRoute('page_analytics.report', [], ['query' => ['period' => 30]])->toString(),
      '#sampling_rate' => $sampling_rate,
      '#sampling_note' => $sampling_note,
      '#attached' => [
        'library' => ['page_analytics/page_analytics.report'],
      ],
      '#cache' => [
        'max-age' => 0,
      ],
    ];
  }

}
