<?php

declare(strict_types=1);

namespace Drupal\farm_asset_termination;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\log\Entity\LogInterface;
use Drupal\log\Entity\LogTypeInterface;
use Drupal\taxonomy\TermInterface;

/**
 * {@inheritdoc}
 */
class AssetTermination implements AssetTerminationInterface {

  use StringTranslationTrait;

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The asset termination settings.
   */
  protected ImmutableConfig $config;

  /**
   * Constructs a new AssetTermination object.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    TranslationInterface $stringTranslation,
    ConfigFactoryInterface $configFactory,
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->stringTranslation = $stringTranslation;
    $this->config = $configFactory->get(self::CONFIG_ID);
  }

  /**
   * {@inheritdoc}
   */
  public function assignTerminationCategory(array|LogInterface $logs, bool $save = TRUE): void {
    if (!is_array($logs)) {
      $logs = [$logs];
    }

    $terminationCategory = $this->getTerminationLogCategory();
    if ($terminationCategory === NULL) {
      return;
    }
    foreach ($logs as $log) {
      $values = [];
      // Get existing log category field values.
      if (!$log->get('category')->isEmpty()) {
        /** @var array */
        $values = $log->get('category')->getValue();
      }
      // Make sure we don't duplicate the category.
      $currentCategories = array_map(fn($cat) => $cat['target_id'], $values);
      if (in_array($terminationCategory->id(), $currentCategories)) {
        continue;
      }
      // Assign termination category.
      $values[] = ['target_id' => $terminationCategory->id()];
      $log->set('category', $values);
      if ($save) {
        $log->save();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getTerminationLogCategory(): ?TermInterface {
    $categoryId = $this->config->get('category');
    if (empty($categoryId)) {
      return NULL;
    }
    return $this->entityTypeManager->getStorage('taxonomy_term')->load($categoryId);
  }

  /**
   * {@inheritdoc}
   */
  public function markLogsAsTermination(array|LogInterface $logs): array {
    $results = [];
    if (!is_array($logs)) {
      $logs = [$logs];
    }

    foreach ($logs as $log) {
      $log->set(self::TERMINATION_LOG_FIELD, TRUE);
      $results[] = $log->save();
    }

    return $results;
  }

  /**
   * {@inheritdoc}
   */
  public function getTerminationLogs(): array {
    /** @var \Drupal\log\Entity\LogInterface[] */
    return $this->entityTypeManager->getStorage('log')->loadByProperties([
      self::TERMINATION_LOG_FIELD => TRUE,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function hasTerminationCategory(): bool {
    return $this->getTerminationLogCategory() !== NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function isDefaultTerminationLogType(LogTypeInterface|string $logType): bool {
    if ($logType instanceof LogTypeInterface) {
      $logType = strval($logType->id());
    }
    $defaultTerminationLogTypes = $this->config->get('default_termination_log_types');
    if (!is_array($defaultTerminationLogTypes)) {
      return FALSE;
    }
    return in_array($logType, $defaultTerminationLogTypes);
  }

  /**
   * {@inheritdoc}
   */
  public function shouldEnforceDefaultTerminationLogTypes(): bool {
    return boolval($this->config->get('enforce_default_termination_log_types') ?? FALSE);
  }

  /**
   * {@inheritdoc}
   */
  public function shouldArchiveAssetsOnTerminationLogCompletion(): bool {
    return boolval($this->config->get('archive_assets') ?? TRUE);
  }

}
