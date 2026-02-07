<?php

declare(strict_types=1);

namespace Drupal\page_analytics\Drush\Commands;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\State\StateInterface;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for Page Analytics diagnostics.
 */
final class PageAnalyticsCommands extends DrushCommands {

  /**
   * Constructs the commands.
   *
   * @param \Drupal\Core\Queue\QueueFactory $queueFactory
   *   The queue factory.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   */
  public function __construct(
    protected QueueFactory $queueFactory,
    protected Connection $connection,
    protected StateInterface $state,
    protected ConfigFactoryInterface $configFactory,
  ) {
    parent::__construct();
  }

  /**
   * Shows queue size, cron status and config (for production debugging).
   */
  #[CLI\Command(name: 'page-analytics:status', aliases: ['past'])]
  #[CLI\Usage(name: 'drush page-analytics:status', description: 'Show why data might not be collected.')]
  public function status(): void {
    $queue = $this->queueFactory->get('page_analytics');
    $queueCount = $queue->numberOfItems();

    $tableExists = $this->connection->schema()->tableExists('page_analytics_daily');
    $rowCount = 0;
    if ($tableExists) {
      $rowCount = (int) $this->connection->select('page_analytics_daily', 'pa')
        ->countQuery()
        ->execute()
        ->fetchField();
    }

    $lastCron = $this->state->get('system.cron_last');
    $lastCronStr = $lastCron
      ? date('Y-m-d H:i:s', (int) $lastCron) . ' (' . $this->formatTimeAgo($lastCron) . ')'
      : 'never';

    $config = $this->configFactory->get('page_analytics.settings');
    $samplingRate = (int) $config->get('sampling_rate') ?: 1;
    $excludeAuth = (bool) $config->get('exclude_authenticated_users');
    $excludedPaths = trim((string) $config->get('excluded_paths'));

    $this->output()->writeln('');
    $this->output()->writeln('Page Analytics status');
    $this->output()->writeln('--------------------');
    $this->output()->writeln('Queue (page_analytics):  ' . $queueCount . ' items');
    $this->output()->writeln('Table (page_analytics_daily): ' . ($tableExists ? $rowCount . ' rows' : 'MISSING'));
    $this->output()->writeln('Last cron run:           ' . $lastCronStr);
    $this->output()->writeln('Config: sampling_rate=' . $samplingRate . ', exclude_authenticated_users=' . ($excludeAuth ? 'yes' : 'no'));
    $this->output()->writeln('Excluded paths:');
    if ($excludedPaths !== '') {
      foreach (explode("\n", $excludedPaths) as $line) {
        $line = trim($line);
        if ($line !== '') {
          $this->output()->writeln('  ' . $line);
        }
      }
    }
    else {
      $this->output()->writeln('  (none)');
    }
    $this->output()->writeln('');

    if ($queueCount > 0 && $rowCount === 0 && !$lastCron) {
      $this->logger()->warning('Queue has items but cron has never run. Run cron (e.g. drush cron) so the queue is processed.');
    }
    elseif ($queueCount > 0 && $rowCount === 0) {
      $this->logger()->warning('Queue has items but table is empty. Cron may not be running often enough or the worker may be failing. Run drush cron and check logs.');
    }
    elseif ($queueCount === 0 && $rowCount === 0) {
      $this->logger()->notice('No queue items and no data. Either no eligible requests are reaching Drupal (e.g. all served from cache), or "Exclude logged-in users" is on and you are testing while logged in. Visit the site as an anonymous user and run this command again.');
    }
    if ($excludeAuth) {
      $this->logger()->notice('"Exclude logged-in users" is ON: logged-in visits are not counted. Test anonymously.');
    }
  }

  /**
   * Formats a timestamp as a human-readable "time ago" string.
   */
  private function formatTimeAgo(int $timestamp): string {
    $diff = time() - $timestamp;
    if ($diff < 60) {
      return $diff . 's ago';
    }
    if ($diff < 3600) {
      return (int) floor($diff / 60) . 'm ago';
    }
    if ($diff < 86400) {
      return (int) floor($diff / 3600) . 'h ago';
    }
    return (int) floor($diff / 86400) . 'd ago';
  }

}
