<?php

declare(strict_types=1);

namespace Drupal\page_analytics\Controller;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\PagerSelectExtender;
use Drupal\Core\Url;
use Drupal\page_analytics\Form\PageAnalyticsFilterForm;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for the page analytics report.
 */
class PageAnalyticsReportController extends ControllerBase {

  /**
   * Number of rows per page (pager limit).
   */
  protected const LIMIT_PER_PAGE = 25;

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
    $path_filter = trim((string) $request->query->get('filter', ''));

    $period_query_7 = array_filter(['period' => 7, 'filter' => $path_filter]);
    $period_7_url = Url::fromRoute('page_analytics.report', [], ['query' => $period_query_7])->toString();
    $period_query_30 = array_filter(['period' => 30, 'filter' => $path_filter]);
    $period_30_url = Url::fromRoute('page_analytics.report', [], ['query' => $period_query_30])->toString();

    $build = [
      'period_switcher' => [
        '#theme' => 'page_analytics_period_switcher',
        '#period' => $period,
        '#week_url' => $period_7_url,
        '#month_url' => $period_30_url,
      ],
      'filter' => $this->formBuilder()->getForm(PageAnalyticsFilterForm::class),
    ];

    $request_time = $this->time->getRequestTime();
    $today = date('Y-m-d', $request_time);
    $top_from = date('Y-m-d', strtotime('-' . self::TOP_DAYS . ' days', $request_time));
    $chart_from = date('Y-m-d', strtotime('-' . $period . ' days', $request_time));

    $query = $this->connection->select('page_analytics_daily', 'r')
      ->extend(PagerSelectExtender::class);
    $query->addField('r', 'path');
    $query->addExpression('SUM(r.view_count)', 'total');
    $query->condition('r.stat_date', $top_from, '>=');
    $query->condition('r.stat_date', $today, '<=');
    $query->groupBy('r.path');
    $query->orderBy('total', 'DESC');
    $query->limit(self::LIMIT_PER_PAGE);
    if ($path_filter !== '') {
      $query->condition('r.path', '%' . $this->connection->escapeLike($path_filter) . '%', 'LIKE');
    }

    $top_paths = $query->execute()->fetchAllKeyed(0, 1);

    if (empty($top_paths)) {
      $build['report'] = [
        '#theme' => 'page_analytics_report',
        '#rows' => [],
        '#attached' => [
          'library' => ['page_analytics/page_analytics.report'],
        ],
      ];
      $build['pager'] = ['#type' => 'pager'];
      $build['report']['#cache'] = ['max-age' => 0, 'contexts' => ['url.query_args']];
      return $build;
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

    $build['report'] = [
      '#theme' => 'page_analytics_report',
      '#rows' => $rows,
      '#attached' => [
        'library' => ['page_analytics/page_analytics.report'],
      ],
    ];
    $build['pager'] = ['#type' => 'pager'];
    $build['report']['#cache'] = ['max-age' => 0, 'contexts' => ['url.query_args']];
    return $build;
  }

}
