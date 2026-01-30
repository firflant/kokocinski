<?php

namespace Drupal\page_analytics\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Confirmation form to flush all page analytics data.
 */
class PageAnalyticsFlushForm extends ConfirmFormBase {

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
    \Drupal::database()->truncate('page_analytics_daily')->execute();
    $this->messenger()->addStatus($this->t('All page analytics data has been flushed.'));
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
