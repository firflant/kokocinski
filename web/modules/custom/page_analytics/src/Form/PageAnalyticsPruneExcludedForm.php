<?php

declare(strict_types=1);

namespace Drupal\page_analytics\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\page_analytics\PathExclusion\PageAnalyticsExclusion;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Confirmation form to remove analytics data for paths that match exclusion rules.
 */
class PageAnalyticsPruneExcludedForm extends ConfirmFormBase implements ContainerInjectionInterface {

  /**
   * Maximum number of paths to list on the confirmation page.
   */
  protected const LIST_LIMIT = 200;

  /**
   * Batch size for delete queries.
   */
  protected const DELETE_BATCH_SIZE = 500;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $connection;

  /**
   * The path exclusion service.
   *
   * @var \Drupal\page_analytics\PathExclusion\PageAnalyticsExclusion
   */
  protected PageAnalyticsExclusion $pathExclusion;

  /**
   * Paths that will be removed (excluded by current rules).
   *
   * @var string[]|null
   */
  protected ?array $excludedPaths = NULL;

  /**
   * Constructs the form.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param \Drupal\page_analytics\PathExclusion\PageAnalyticsExclusion $pathExclusion
   *   The path exclusion service.
   */
  public function __construct(Connection $connection, PageAnalyticsExclusion $pathExclusion) {
    $this->connection = $connection;
    $this->pathExclusion = $pathExclusion;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('database'),
      $container->get('page_analytics.path_exclusion'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'page_analytics_prune_excluded_confirm';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): string {
    return (string) $this->t('Flush analytics data for excluded paths?');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): string {
    $paths = $this->getExcludedPaths();
    $count = count($paths);
    if ($count === 0) {
      return (string) $this->t('No stored paths match the current exclusion rules. Nothing will be removed.');
    }
    return (string) $this->t('The following @count path(s) will have their analytics data permanently removed:', [
      '@count' => $count,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildForm($form, $form_state);

    $paths = $this->getExcludedPaths();
    if ($paths === []) {
      return $form;
    }

    $display = array_slice($paths, 0, self::LIST_LIMIT);
    $remaining = count($paths) - count($display);

    $items = array_map(
      fn (string $path) => ['#markup' => htmlspecialchars($path, ENT_QUOTES, 'UTF-8')],
      $display,
    );
    if ($remaining > 0) {
      $items[] = [
        '#markup' => $this->t('and @count more.', ['@count' => $remaining]),
        '#wrapper_attributes' => ['class' => ['prune-excluded-more']],
      ];
    }

    $form['path_list'] = [
      '#theme' => 'item_list',
      '#items' => $items,
      '#title' => $this->t('Paths to remove'),
      '#weight' => 5,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText(): string {
    return (string) $this->t('Flush excluded paths');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    return new Url('page_analytics.settings');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $paths = $this->getExcludedPaths();
    if ($paths === []) {
      $this->messenger()->addStatus($this->t('No paths matched the exclusion rules.'));
      $form_state->setRedirectUrl($this->getCancelUrl());
      return;
    }

    $batches = array_chunk($paths, self::DELETE_BATCH_SIZE);
    foreach ($batches as $chunk) {
      $this->connection->delete('page_analytics_daily')
        ->condition('path', $chunk, 'IN')
        ->execute();
    }

    $this->messenger()->addStatus($this->t('Analytics data for @count excluded path(s) has been removed.', [
      '@count' => count($paths),
    ]));
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

  /**
   * Returns stored paths that match the current exclusion rules.
   *
   * @return string[]
   *   Paths that will be removed.
   */
  protected function getExcludedPaths(): array {
    if ($this->excludedPaths !== NULL) {
      return $this->excludedPaths;
    }

    $all = $this->connection->select('page_analytics_daily', 'd')
      ->distinct()
      ->fields('d', ['path'])
      ->execute()
      ->fetchCol();

    $this->excludedPaths = array_values(array_filter($all, function (string $path): bool {
      return $this->pathExclusion->isPathExcluded($path);
    }));

    return $this->excludedPaths;
  }

}
