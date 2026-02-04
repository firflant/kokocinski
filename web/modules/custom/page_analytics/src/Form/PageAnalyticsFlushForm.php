<?php

declare(strict_types=1);

namespace Drupal\page_analytics\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Confirmation form to flush all page analytics data.
 */
class PageAnalyticsFlushForm extends ConfirmFormBase implements ContainerInjectionInterface {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $connection;

  /**
   * Constructs the form.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   */
  public function __construct(Connection $connection) {
    $this->connection = $connection;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('database'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'page_analytics_flush_confirm';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): string {
    return $this->t('Are you sure you want to flush all page analytics data?');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): string {
    return $this->t('All recorded page view counts will be permanently deleted. This action cannot be undone.');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText(): string {
    return $this->t('Flush analytics');
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
    $this->connection->truncate('page_analytics_daily')->execute();
    $this->messenger()->addStatus($this->t('All page analytics data has been flushed.'));
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
