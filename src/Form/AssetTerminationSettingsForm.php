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
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config(AssetTerminationInterface::CONFIG_ID);

    $form['archive_assets'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Archive assets on termination log completion'),
      '#default_value' => $config->get('archive_assets') ?? TRUE,
    ];

    // Termination category.
    $logCategories = $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties(['vid' => 'log_category']);
    $logCategoriesOptions = array_map(fn($category) => $category->label(), $logCategories);

    $form['category'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Termination log category'),
    ];
    $form['category']['category'] = [
      '#type' => 'select',
      '#description' => $this->t('Category used to mark termination logs. If left empty no category will be automatically assigned to termination logs.'),
      '#description_display' => 'before',
      '#options' => $logCategoriesOptions,
      '#default_value' => $config->get('category'),
      '#required' => FALSE,
      '#empty_value' => '',
    ];
    // Mark all logs with termination category as termination logs.
    if (!empty($config->get('category'))) {
      $form['category']['mark_existing_by_category'] = [
        '#type' => 'submit',
        '#value' => $this->t('Mark existing logs with termination category as termination logs'),
        '#name' => 'mark_existing_by_category',
      ];
    }

    // Default termination log types.
    $logTypes = $this->entityTypeManager->getStorage('log_type')->loadMultiple();
    $logTypesOptions = array_map(fn($logType) => $logType->label(), $logTypes);
    $form['default_termination_log_types'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Default termination log types'),
    ];
    $form['default_termination_log_types']['default_termination_log_types'] = [
      '#type' => 'select',
      '#multiple' => TRUE,
      '#description' => $this->t('Log types that will be marked as termination by default.'),
      '#description_display' => 'before',
      '#options' => $logTypesOptions,
      '#default_value' => $config->get('default_termination_log_types'),
    ];
    $form['default_termination_log_types']['enforce_default_termination_log_types'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enforce default termination log types'),
      '#description' => $this->t("By default, configuration of default log types only affects 'Is termination' field on the log creation form, it is checked by default, but can be manually unchecked. Selecting this option will force configured log types to always be marked as termination, without the option to manually change that on a per log basis."),
      '#default_value' => $config->get('enforce_default_termination_log_types'),
    ];
    // Mark all logs with termination category as termination logs.
    if (!empty($config->get('default_termination_log_types'))) {
      $form['default_termination_log_types']['mark_existing_by_type'] = [
        '#type' => 'submit',
        '#value' => $this->t('Mark existing logs of default termination log types as termination'),
        '#name' => 'mark_existing_by_type',
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $formState): void {
    if (
      is_array($formState->getTriggeringElement())
      && isset($formState->getTriggeringElement()['#name'])
      && $formState->getTriggeringElement()['#name'] === 'mark_existing_by_category'
      && empty($formState->getValue('category'))
    ) {
      $formState->setErrorByName(
        'category',
        $this->t('To mark existing logs with termination category as termination you need to first configure a termination category.')
      );
    }
    if (
      is_array($formState->getTriggeringElement())
      && isset($formState->getTriggeringElement()['#name'])
      && $formState->getTriggeringElement()['#name'] === 'mark_existing_by_type'
      && empty($formState->getValue('default_termination_log_types'))
    ) {
      $formState->setErrorByName(
        'default_termination_log_types',
        $this->t('To mark existing logs of default termination log types as termination you need to first configure default termination log types.')
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
      ->set('enforce_default_termination_log_types', boolval($formState->getValue('enforce_default_termination_log_types', FALSE)))
      ->set('archive_assets', boolval($formState->getValue('archive_assets', TRUE)))
      ->save();
    parent::submitForm($form, $formState);

    // Mark all logs with termination category as termination logs.
    if (
      is_array($formState->getTriggeringElement())
      && isset($formState->getTriggeringElement()['#name'])
      && $formState->getTriggeringElement()['#name'] === 'mark_existing_by_category'
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

    // Mark all logs with termination category as termination logs.
    if (
      is_array($formState->getTriggeringElement())
      && isset($formState->getTriggeringElement()['#name'])
      && $formState->getTriggeringElement()['#name'] === 'mark_existing_by_type'
    ) {

      $terminationLogTypes = $this->config(AssetTerminationInterface::CONFIG_ID)->get('default_termination_log_types');
      if (empty($terminationLogTypes) || !is_array($terminationLogTypes)) {
        return;
      }

      // Get logs with termination category.
      $query = $this->entityTypeManager->getStorage('log')->getQuery();
      $query->condition('type', $terminationLogTypes, 'IN');
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
        'title' => $this->t('Marking existing logs of default termination log types as termination...'),
        'operations' => $operations,
        'finished' => '_farm_asset_termination_mark_as_termination_finished',
      ];
      \batch_set($batch);
    }

  }

}
