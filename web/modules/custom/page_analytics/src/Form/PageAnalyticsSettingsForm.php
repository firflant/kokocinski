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
    $form['performance'] = [
      '#type' => 'details',
      '#title' => $this->t('Performance settings'),
      '#open' => TRUE,
    ];
    $form['performance']['sampling_rate'] = [
      '#type' => 'number',
      '#title' => $this->t('Sampling rate (1 in N)'),
      '#min' => 1,
      '#config_target' => 'page_analytics.settings:sampling_rate',
      '#description' => $this->t('Record only a random fraction of page views instead of every view. For example, 3 means 1 in 3 views are recorded. You still see which pages are popular and how traffic changes over time, but with fewer queue items and fewer database writes the system stays lighter under high traffic. The report shows estimated totals (each recorded view is scaled to represent the full traffic for that sample). Numbers are approximate, not exact. Higher N means better performance and less accuracy; 1 means record every view (exact counts).'),
    ];
    $form['performance']['retention_days'] = [
      '#type' => 'number',
      '#title' => $this->t('Keep data for (days)'),
      '#min' => 1,
      '#config_target' => 'page_analytics.settings:retention_days',
      '#description' => $this->t('Rows older than this many days are deleted on cron. Default 365.'),
    ];

    $form['excluding'] = [
      '#type' => 'details',
      '#title' => $this->t('Excluding'),
      '#open' => TRUE,
    ];
    $form['excluding']['exclude_authenticated_users'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Exclude logged-in users'),
      '#config_target' => 'page_analytics.settings:exclude_authenticated_users',
      '#description' => $this->t('When enabled, page views by authenticated users are not counted. Use this to exclude admin or staff traffic from analytics.'),
    ];
    $form['excluding']['excluded_paths'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Excluded paths'),
      '#config_target' => 'page_analytics.settings:excluded_paths',
      '#rows' => 6,
      '#description' => $this->t('One path per line, use <code>*</code> as wildcard. E.g. /admin*, /user/login.'),
    ];

    try {
      Url::fromRoute('page_analytics.flush')->toString();
      $form['flush'] = [
        '#type' => 'details',
        '#title' => $this->t('Reset data'),
        '#open' => FALSE,
      ];
      $form['flush']['flush_all'] = [
        '#type' => 'container',
      ];
      $form['flush']['flush_all']['description'] = [
        '#markup' => '<p>' . $this->t('Permanently delete all recorded page analytics data.') . '</p>',
      ];
      $form['flush']['flush_all']['link'] = [
        '#type' => 'link',
        '#title' => $this->t('Flush all analytics'),
        '#url' => Url::fromRoute('page_analytics.flush'),
        '#attributes' => ['class' => ['button', 'button--danger']],
      ];
      try {
        Url::fromRoute('page_analytics.prune_excluded')->toString();
        $form['flush']['flush_excluded'] = [
          '#type' => 'container',
          '#weight' => 10,
        ];
        $form['flush']['flush_excluded']['description'] = [
          '#markup' => '<p>' . $this->t('Remove analytics data only for paths that match the current exclusion rules. A confirmation page will list the paths to be removed.') . '</p>',
        ];
        $form['flush']['flush_excluded']['link'] = [
          '#type' => 'link',
          '#title' => $this->t('Flush excluded paths'),
          '#url' => Url::fromRoute('page_analytics.prune_excluded'),
          '#attributes' => ['class' => ['button']],
        ];
      }
      catch (RouteNotFoundException $e) {
        // Prune route not available.
      }
    }
    catch (RouteNotFoundException $e) {
      $this->getLogger('page_analytics')->debug('Flush route not available: @message', [
        '@message' => $e->getMessage(),
      ]);
    }

    return parent::buildForm($form, $form_state);
  }

}
