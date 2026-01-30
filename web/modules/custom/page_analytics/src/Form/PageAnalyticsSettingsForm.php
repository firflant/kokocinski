<?php

namespace Drupal\page_analytics\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Settings form for Page analytics.
 */
class PageAnalyticsSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'page_analytics_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['page_analytics.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('page_analytics.settings');

    $form['sampling_rate'] = [
      '#type' => 'number',
      '#title' => $this->t('Record 1 in N page views'),
      '#min' => 1,
      '#default_value' => $config->get('sampling_rate'),
      '#description' => $this->t('Only a fraction of page views are recorded. Stored counts are multiplied by N for display, so numbers are approximate. Fewer queue items and merges. Default 3 = record 1 in 3.'),
    ];

    $form['retention_days'] = [
      '#type' => 'number',
      '#title' => $this->t('Keep data for (days)'),
      '#min' => 1,
      '#default_value' => $config->get('retention_days'),
      '#description' => $this->t('Rows older than this many days are deleted on cron. Default 365.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('page_analytics.settings')
      ->set('sampling_rate', (int) $form_state->getValue('sampling_rate'))
      ->set('retention_days', (int) $form_state->getValue('retention_days'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
