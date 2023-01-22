<?php

declare(strict_types=1);

namespace Drupal\farm_asset_termination;

use Drupal\log\Entity\LogInterface;
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
   * Assign 'Termination' category to given log(s).
   *
   * @param \Drupal\log\Entity\LogInterface[]|\Drupal\log\Entity\LogInterface $logs
   *   Log entity or array of them that should have assigned 'Termination' log category.
   * @param boolean $save
   *   Determines if termination category assignment should be immediately saved
   *   to the database. When using it in log entity presave event subscriber
   *   should be set to FALSE to not trigger infinite loop of presave events.
   */
  public function assignTerminationCategory(array|LogInterface $logs, bool $save = TRUE): void;

  /**
   * Get 'Termination' log category term.
   */
  public function getTerminationLogCategory(): TermInterface;

  /**
   * Mark given logs as terminating assigned assets.
   *
   * @param \Drupal\log\Entity\LogInterface[]|\Drupal\log\Entity\LogInterface $logs
   *   Log entity or array of them that should be marked as terminating assigned assets.
   */
  public function markLogsAsTermination(array|LogInterface $logs): void;

  /**
   * Get all termination logs.
   *
   * @return \Drupal\log\Entity\LogInterface[]
   *   Termination logs list.
   */
  public function getTerminationLogs(): array;

}
