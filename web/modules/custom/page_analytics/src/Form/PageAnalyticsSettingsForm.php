<?php

declare(strict_types=1);

namespace Drupal\page_analytics\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

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
      '#title' => $this->t('Sampling rate (1 in N)'),
      '#min' => 1,
      '#default_value' => $config->get('sampling_rate'),
      '#description' => $this->t('Record only a random fraction of page views instead of every view. For example, 3 means 1 in 3 views are recorded. You still see which pages are popular and how traffic changes over time, but with fewer queue items and fewer database writes the system stays lighter under high traffic. The report shows estimated totals (each recorded view is scaled to represent the full traffic for that sample). Numbers are approximate, not exact. Higher N means better performance and less accuracy; 1 means record every view (exact counts).'),
    ];

    $form['retention_days'] = [
      '#type' => 'number',
      '#title' => $this->t('Keep data for (days)'),
      '#min' => 1,
      '#default_value' => $config->get('retention_days'),
      '#description' => $this->t('Rows older than this many days are deleted on cron. Default 365.'),
    ];

    $form['exclude_authenticated_users'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Exclude logged-in users'),
      '#default_value' => $config->get('exclude_authenticated_users'),
      '#description' => $this->t('When enabled, page views by authenticated users are not counted. Use this to exclude admin or staff traffic from analytics.'),
    ];

    try {
      Url::fromRoute('page_analytics.flush')->toString();
      $form['flush'] = [
        '#type' => 'details',
        '#title' => $this->t('Reset data'),
        '#open' => FALSE,
        '#description' => $this->t('Permanently delete all recorded page analytics data.'),
      ];
      $form['flush']['link'] = [
        '#type' => 'link',
        '#title' => $this->t('Flush analytics'),
        '#url' => Url::fromRoute('page_analytics.flush'),
        '#attributes' => ['class' => ['button', 'button--danger']],
      ];
    }
    catch (RouteNotFoundException $e) {
      $this->getLogger('page_analytics')->debug('Flush route not available: @message', [
        '@message' => $e->getMessage(),
      ]);
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('page_analytics.settings')
      ->set('sampling_rate', (int) $form_state->getValue('sampling_rate'))
      ->set('retention_days', (int) $form_state->getValue('retention_days'))
      ->set('exclude_authenticated_users', (bool) $form_state->getValue('exclude_authenticated_users'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
