<?php

declare(strict_types=1);

namespace Drupal\farm_asset_termination;

use Drupal\log\Entity\LogInterface;
use Drupal\log\Entity\LogTypeInterface;
use Drupal\taxonomy\TermInterface;

/**
 * Helper methods for handling asset termination.
 */
interface AssetTerminationInterface {

  /**
   * Id of the field that marks log as termination.
   */
  public const TERMINATION_LOG_FIELD = 'is_termination';

  /**
   * Id of the module config.
   */
  public const CONFIG_ID = 'farm_asset_termination.settings';

  /**
   * Assign 'Termination' category to given log(s).
   *
   * @param \Drupal\log\Entity\LogInterface[]|\Drupal\log\Entity\LogInterface $logs
   *   Log entity or array of them that should have assigned 'Termination' log category.
   * @param bool $save
   *   Determines if termination category assignment should be immediately saved
   *   to the database. When using it in log entity presave event subscriber
   *   should be set to FALSE to not trigger infinite loop of presave events.
   */
  public function assignTerminationCategory(array|LogInterface $logs, bool $save = TRUE): void;

  /**
   * Get 'Termination' log category term.
   */
  public function getTerminationLogCategory(): ?TermInterface;

  /**
   * Mark given logs as terminating assigned assets.
   *
   * @param \Drupal\log\Entity\LogInterface[]|\Drupal\log\Entity\LogInterface $logs
   *   Log entity or array of them that should be marked as terminating assigned assets.
   */
  public function markLogsAsTermination(array|LogInterface $logs): array;

  /**
   * Get all termination logs.
   *
   * @return \Drupal\log\Entity\LogInterface[]
   *   Termination logs list.
   */
  public function getTerminationLogs(): array;

  /**
   * Checks if termination category was configured.
   */
  public function hasTerminationCategory(): bool;

  /**
   * Checks if given log type is by default treated as termination.
   */
  public function isDefaultTerminationLogType(LogTypeInterface|string $logType): bool;

}
