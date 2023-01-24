<?php

declare(strict_types=1);

namespace Drupal\farm_asset_termination\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\farm_asset_termination\AssetTerminationInterface;

/**
 * The config form for the farm_asset_termination module.
 */
class AssetTerminationSettingsForm extends ConfigFormBase {

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs an AssetTerminationSettingsForm object.
   * 
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    EntityTypeManagerInterface $entity_type_manager,
  ) {
    parent::__construct($config_factory);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
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

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config(AssetTerminationInterface::CONFIG_ID)
      ->set('category', $form_state->getValue('category'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
