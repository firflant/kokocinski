<?php

declare(strict_types=1);

namespace Drupal\page_analytics\Plugin\QueueWorker;

use Drupal\Core\Database\Connection;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Processes page analytics queue items: upserts daily view count.
 */
#[QueueWorker(
  id: 'page_analytics',
  title: new TranslatableMarkup('Page analytics'),
  cron: ['time' => 15]
)]
class PageAnalyticsQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * Maximum number of additional items to claim and process in one batch.
   */
  protected const BATCH_SIZE = 99;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $connection;

  /**
   * The queue factory.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected QueueFactory $queueFactory;

  /**
   * Constructs the queue worker.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param array $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param \Drupal\Core\Queue\QueueFactory $queueFactory
   *   The queue factory.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    array $plugin_definition,
    Connection $connection,
    QueueFactory $queueFactory,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->connection = $connection;
    $this->queueFactory = $queueFactory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('database'),
      $container->get('queue'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    if (!isset($data['path'], $data['date']) || !is_string($data['path']) || !is_string($data['date'])) {
      return;
    }

    $path = $data['path'];
    $date = $data['date'];

    if (strlen($path) > 255) {
      $path = substr($path, 0, 255);
    }

    // Weight = estimated views for this sampled hit (1 sample at rate N => N views).
    $weight = isset($data['sampling_rate']) ? max(1, (int) $data['sampling_rate']) : 1;

    // Aggregate: key "path|date" => sum of weights (estimated total views).
    $aggregate = [];
    $key = $path . '|' . $date;
    $aggregate[$key] = ['path' => $path, 'date' => $date, 'count' => $weight];

    // Claim additional items from the queue (current item is deleted by cron).
    $queue = $this->queueFactory->get('page_analytics');
    $claimed = [];
    $leaseTime = 60;

    for ($i = 0; $i < self::BATCH_SIZE; $i++) {
      $item = $queue->claimItem($leaseTime);
      if ($item === FALSE) {
        break;
      }

      $claimed[] = $item;
      $itemData = $item->data;
      if (!isset($itemData['path'], $itemData['date']) || !is_string($itemData['path']) || !is_string($itemData['date'])) {
        continue;
      }

      $p = $itemData['path'];
      $d = $itemData['date'];
      if (strlen($p) > 255) {
        $p = substr($p, 0, 255);
      }

      $itemWeight = isset($itemData['sampling_rate']) ? max(1, (int) $itemData['sampling_rate']) : 1;

      $k = $p . '|' . $d;
      if (!isset($aggregate[$k])) {
        $aggregate[$k] = ['path' => $p, 'date' => $d, 'count' => 0];
      }
      $aggregate[$k]['count'] += $itemWeight;
    }

    // One merge per unique (path, date) with the summed count.
    foreach ($aggregate as $entry) {
      $count = $entry['count'];
      $this->connection->merge('page_analytics_daily')
        ->keys([
          'path' => $entry['path'],
          'stat_date' => $entry['date'],
        ])
        ->insertFields([
          'path' => $entry['path'],
          'stat_date' => $entry['date'],
          'view_count' => $count,
        ])
        ->expression('view_count', 'view_count + :inc', [':inc' => $count])
        ->execute();
    }

    // Delete the items we claimed (the current item is deleted by cron).
    foreach ($claimed as $item) {
      $queue->deleteItem($item);
    }
  }

}
