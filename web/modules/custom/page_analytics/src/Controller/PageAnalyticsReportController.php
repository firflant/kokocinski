<?php

declare(strict_types=1);

namespace Drupal\page_analytics\Controller;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
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
   * Allowed period values (days); 0 = Max (use retention_days from config).
   */
  protected const ALLOWED_PERIODS = [0, 7, 30, 90];

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
   * The config factory (for page_analytics.settings).
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $settingsConfigFactory;

  /**
   * Constructs the controller.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(Connection $connection, TimeInterface $time, ConfigFactoryInterface $config_factory) {
    $this->connection = $connection;
    $this->time = $time;
    $this->settingsConfigFactory = $config_factory;
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
    if (!in_array($period, self::ALLOWED_PERIODS, TRUE)) {
      $period = 7;
    }
    $path_filter = trim((string) $request->query->get('filter', ''));

    $effective_days = $period === 0
      ? (int) $this->settingsConfigFactory->get('page_analytics.settings')->get('retention_days') ?: 365
      : $period;
    if ($effective_days < 1) {
      $effective_days = 365;
    }

    $base_query = array_filter(['filter' => $path_filter]);
    $period_7_url = Url::fromRoute('page_analytics.report', [], ['query' => $base_query + ['period' => 7]])->toString();
    $period_30_url = Url::fromRoute('page_analytics.report', [], ['query' => $base_query + ['period' => 30]])->toString();
    $period_90_url = Url::fromRoute('page_analytics.report', [], ['query' => $base_query + ['period' => 90]])->toString();
    $period_max_url = Url::fromRoute('page_analytics.report', [], ['query' => $base_query + ['period' => 0]])->toString();

    $build = [
      'period_switcher' => [
        '#theme' => 'page_analytics_period_switcher',
        '#current_period' => $period,
        '#url_7' => $period_7_url,
        '#url_30' => $period_30_url,
        '#url_90' => $period_90_url,
        '#url_max' => $period_max_url,
      ],
      'filter' => $this->formBuilder()->getForm(PageAnalyticsFilterForm::class),
    ];

    $request_time = $this->time->getRequestTime();
    $today = date('Y-m-d', $request_time);
    $chart_from = date('Y-m-d', strtotime('-' . $effective_days . ' days', $request_time));

    $query = $this->connection->select('page_analytics_daily', 'r')
      ->extend(PagerSelectExtender::class);
    $query->addField('r', 'path');
    $query->addExpression('SUM(r.view_count)', 'total');
    $query->condition('r.stat_date', $chart_from, '>=');
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
    for ($i = $effective_days - 1; $i >= 0; $i--) {
      $d = date('Y-m-d', strtotime("-$i days", $request_time));
      $date_labels[] = $d;
    }

    // Chart display: daily (≤30 days), weekly (e.g. Last 3 months), or monthly (Max).
    $monthly_mode = $period === 0;
    $weekly_mode = !$monthly_mode && $effective_days > 30;

    if ($monthly_mode) {
      $chart_date_labels = [];
      $month_ranges = [];
      $week_ranges = null;
      $by_month = [];
      foreach ($date_labels as $idx => $d) {
        $by_month[substr($d, 0, 7)][$idx] = true;
      }
      ksort($by_month, SORT_STRING);
      foreach ($by_month as $indices) {
        $idxs = array_keys($indices);
        $chart_date_labels[] = $date_labels[min($idxs)] . ' – ' . $date_labels[max($idxs)];
        $month_ranges[] = $idxs;
      }
    }
    elseif ($weekly_mode) {
      $chart_date_labels = [];
      $week_ranges = [];
      $month_ranges = null;
      for ($w = 0; $w * 7 < count($date_labels); $w++) {
        $start_idx = $w * 7;
        $end_idx = (int) min($start_idx + 6, count($date_labels) - 1);
        $chart_date_labels[] = $date_labels[$start_idx] . ' – ' . $date_labels[$end_idx];
        $week_ranges[] = [$start_idx, $end_idx];
      }
    }
    else {
      $chart_date_labels = $date_labels;
      $week_ranges = null;
      $month_ranges = null;
    }

    $rows = [];
    $index = 0;
    foreach ($top_paths as $path => $total) {
      $period_total = 0;
      $values_full = [];
      foreach ($date_labels as $d) {
        $v = $daily_by_path[$path][$d] ?? 0;
        $values_full[] = $v;
        $period_total += $v;
      }

      if ($monthly_mode) {
        $chart_values = array_map(
          static function (array $idxs) use ($values_full): int {
            $sum = 0;
            foreach ($idxs as $i) {
              $sum += $values_full[$i];
            }
            return $sum;
          },
          $month_ranges,
        );
      }
      elseif ($weekly_mode) {
        $chart_values = array_map(
          static function (array $range) use ($values_full): int {
            [$start_idx, $end_idx] = $range;
            $sum = 0;
            for ($i = $start_idx; $i <= $end_idx; $i++) {
              $sum += $values_full[$i];
            }
            return $sum;
          },
          $week_ranges,
        );
      }
      else {
        $chart_values = $values_full;
      }

      $rows[] = [
        'path' => $path,
        'total' => $period_total,
        'chart_index' => $index,
        'chart_labels' => $chart_date_labels,
        'chart_values' => $chart_values,
      ];
      $index++;
    }

    $build['report'] = [
      '#theme' => 'page_analytics_report',
      '#rows' => $rows,
      '#chart_wide' => $period === 30,
      '#attached' => [
        'library' => ['page_analytics/page_analytics.report'],
      ],
    ];
    $build['pager'] = ['#type' => 'pager'];
    $build['report']['#cache'] = ['max-age' => 0, 'contexts' => ['url.query_args']];
    return $build;
  }

}
