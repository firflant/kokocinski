<?php

declare(strict_types=1);

namespace Drupal\page_analytics\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Filter form for the page analytics report (GET, same pattern as admin/modules).
 */
class PageAnalyticsFilterForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'page_analytics_filter';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $request = $this->getRequest();
    $path_filter = trim((string) ($request ? $request->query->get('filter', '') : ''));
    $period = (int) ($request ? $request->query->get('period', 7) : 7);
    if ($period !== 7 && $period !== 30) {
      $period = 7;
    }

    $form['#method'] = 'get';
    $form['#action'] = Url::fromRoute('page_analytics.report')->toString();
    $form['#attached']['library'][] = 'page_analytics/filter';

    $form['filters'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['page-analytics-filter'],
      ],
    ];

    $form['filters']['filter'] = [
      '#type' => 'search',
      '#name' => 'filter',
      '#title' => $this->t('Filter'),
      '#title_display' => 'invisible',
      '#size' => 30,
      '#placeholder' => $this->t('Filter by page'),
      '#default_value' => $path_filter,
      '#attributes' => [
        'autocomplete' => 'off',
      ],
    ];

    $form['filters']['period'] = [
      '#type' => 'hidden',
      '#name' => 'period',
      '#value' => $period,
    ];

    $form['filters']['actions'] = [
      '#type' => 'actions',
      '#attributes' => [
        'class' => ['page-analytics-filter__actions'],
      ],
    ];

    $form['filters']['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Filter'),
    ];

    if ($path_filter !== '') {
      $form['filters']['actions']['clear'] = [
        '#type' => 'link',
        '#title' => $this->t('Clear'),
        '#url' => Url::fromRoute('page_analytics.report', [], ['query' => ['period' => $period]]),
        '#attributes' => ['class' => ['button']],
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // GET form: no server-side submit handling; form submits to current URL.
  }

}
