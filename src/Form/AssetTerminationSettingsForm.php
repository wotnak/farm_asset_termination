<?php

declare(strict_types=1);

namespace Drupal\farm_asset_termination\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\farm_asset_termination\AssetTerminationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The config form for the farm_asset_termination module.
 */
class AssetTerminationSettingsForm extends ConfigFormBase {

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The asset termination service.
   */
  protected AssetTerminationInterface $assetTermination;

  /**
   * Constructs an AssetTerminationSettingsForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\farm_asset_termination\AssetTerminationInterface $assetTermination
   *   The asset termination service.
   */
  public function __construct(
    ConfigFactoryInterface $configFactory,
    EntityTypeManagerInterface $entityTypeManager,
    AssetTerminationInterface $assetTermination,
  ) {
    parent::__construct($configFactory);
    $this->entityTypeManager = $entityTypeManager;
    $this->assetTermination = $assetTermination;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('farm_asset_termination.asset_termination'),
    );
  }

  /**
   * {@inheritDoc}
   */
  protected function getEditableConfigNames(): array {
    return [
      AssetTerminationInterface::CONFIG_ID,
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function getFormId(): string {
    return 'farm_asset_termination_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $formState): array {
    $config = $this->config(AssetTerminationInterface::CONFIG_ID);

    // Termination category.
    $logCategories = $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties(['vid' => 'log_category']);
    $logCategoriesOptions = array_map(fn($category) => $category->label(), $logCategories);
    $form['category'] = [
      '#type' => 'select',
      '#title' => $this->t('Termination log category'),
      '#description' => $this->t('Category used to mark termination logs. If left empty no category will be automatically assigned to termination logs.'),
      '#description_display' => 'before',
      '#options' => $logCategoriesOptions,
      '#default_value' => $config->get('category'),
      '#required' => FALSE,
      '#empty_value' => '',
    ];

    // Default termination log types.
    $logTypes = $this->entityTypeManager->getStorage('log_type')->loadMultiple();
    $logTypesOptions = array_map(fn($logType) => $logType->label(), $logTypes);
    $form['default_termination_log_types'] = [
      '#type' => 'select',
      '#multiple' => TRUE,
      '#title' => $this->t('Default termination log types'),
      '#description' => $this->t('Log types that will be marked as termination by default.'),
      '#description_display' => 'before',
      '#options' => $logTypesOptions,
      '#default_value' => $config->get('default_termination_log_types'),
    ];

    // Mark all logs with termination category as termination logs.
    $form['mark_as_termination'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Mark existing logs with termination category as termination logs'),
      '#disabled' => empty($config->get('category')),
    ];
    if (empty($config->get('category'))) {
      $form['mark_as_termination']['info'] = [
        '#type' => 'markup',
        '#markup' => $this->t('To mark existing logs with termination category as termination you need to first configure a termination category.'),
      ];
    }
    else {
      $form['mark_as_termination']['batch'] = [
        '#type' => 'submit',
        '#value' => $this->t('Execute'),
        '#name' => 'mark_as_termination',
      ];
    }

    return parent::buildForm($form, $formState);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $formState): void {
    if (
      is_array($formState->getTriggeringElement())
      && isset($formState->getTriggeringElement()['#name'])
      && $formState->getTriggeringElement()['#name'] === 'mark_as_termination'
      && empty($formState->getValue('category'))
    ) {
      $formState->setErrorByName(
        'category',
        $this->t('To mark existing logs with termination category as termination you need to first configure a termination category.')
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $formState): void {

    // Save config changes.
    $this->config(AssetTerminationInterface::CONFIG_ID)
      ->set('category', $formState->getValue('category'))
      ->set('default_termination_log_types', $formState->getValue('default_termination_log_types', []))
      ->save();
    parent::submitForm($form, $formState);

    // Mark all logs with termination category as termination logs.
    if (
      is_array($formState->getTriggeringElement())
      && isset($formState->getTriggeringElement()['#name'])
      && $formState->getTriggeringElement()['#name'] === 'mark_as_termination'
    ) {

      $terminationCategory = $this->assetTermination->getTerminationLogCategory();
      if ($terminationCategory === NULL) {
        return;
      }

      // Get logs with termination category.
      $query = $this->entityTypeManager->getStorage('log')->getQuery();
      $query->condition('category', $terminationCategory->id());
      $group = $query->orConditionGroup();
      $group->condition(AssetTerminationInterface::TERMINATION_LOG_FIELD, NULL, 'IS NULL');
      $group->condition(AssetTerminationInterface::TERMINATION_LOG_FIELD, FALSE);
      $query->condition($group);
      $logIds = $query->execute();
      if (!is_array($logIds)) {
        return;
      }

      $batches = array_chunk($logIds, 50);
      $operations = [
        ['_farm_asset_termination_mark_as_termination', $batches],
      ];
      $batch = [
        'title' => $this->t('Marking existing logs with termination category as termination logs...'),
        'operations' => $operations,
        'finished' => '_farm_asset_termination_mark_as_termination_finished',
      ];
      \batch_set($batch);
    }

  }

}
