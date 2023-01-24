<?php

declare(strict_types=1);

namespace Drupal\farm_asset_termination\EventSubscriber;

use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\farm_asset_termination\AssetTerminationInterface;
use Drupal\log\Entity\LogInterface;
use Drupal\log\Event\LogEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Archive asset on termination logs.
 */
class LogEventSubscriber implements EventSubscriberInterface {

  /**
   * The name of the log asset field.
   *
   * @var string
   */
  const LOG_FIELD_ASSET = 'asset';

  /**
   * The Asset Termination service.
   */
  protected AssetTerminationInterface $assetTermination;

  /**
   * Constructs a LogEventSubscriber object.
   */
  public function __construct(
    AssetTerminationInterface $assetTermination,
  ) {
    $this->assetTermination = $assetTermination;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      LogEvent::PRESAVE => 'logPresave',
    ];
  }

  /**
   * Perform actions on log presave.
   */
  public function logPresave(LogEvent $event): void {

    // Get the log entity from the event.
    $log = $event->log;

    // Enforce default termination log types.
    if (
      $this->assetTermination->shouldEnforceDefaultTerminationLogTypes()
      && $this->assetTermination->isDefaultTerminationLogType($log->bundle())
    ) {
      $log->set(AssetTerminationInterface::TERMINATION_LOG_FIELD, TRUE);
    }

    // If log is not marked as termination we have nothing to do.
    if (
      $log->get(AssetTerminationInterface::TERMINATION_LOG_FIELD)->isEmpty()
      || !boolval($log->get(AssetTerminationInterface::TERMINATION_LOG_FIELD)->getString())
    ) {
      return;
    }

    // Assign termination log category.
    if ($this->assetTermination->hasTerminationCategory()) {
      $this->assetTermination->assignTerminationCategory($log, save: FALSE);
    }

    // If log is not yet completed we heave nothing more to do.
    if ($log->get('status')->getString() !== 'done') {
      return;
    }

    // Get assets field from the log.
    $assetsField = $log->get(self::LOG_FIELD_ASSET);
    if (!($assetsField instanceof EntityReferenceFieldItemListInterface)) {
      return;
    }

    // If log does not reference any assets we have nothing to do.
    if ($assetsField->isEmpty()) {
      return;
    }

    // Log was already marked as termination and completed nothing more to do.
    if (
      isset($log->original)
      && $log->original instanceof LogInterface
      && $log->original->get('status')->getString() === 'done'
      && !$log->original->get(AssetTerminationInterface::TERMINATION_LOG_FIELD)->isEmpty()
      && boolval($log->original->get(AssetTerminationInterface::TERMINATION_LOG_FIELD)->getString())
    ) {
      return;
    }

    // Archive assets referenced by termination log.
    if ($this->assetTermination->shouldArchiveAssetsOnTerminationLogCompletion()) {
      /** @var \Drupal\asset\Entity\AssetInterface[] */
      $terminatedAssets = $assetsField->referencedEntities();
      foreach ($terminatedAssets as $asset) {
        $asset->setArchivedTime(intval($log->get('timestamp')->getString()));
        $asset->save();
      }
    }

  }

}
