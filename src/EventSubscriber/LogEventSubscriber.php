<?php

declare(strict_types=1);

namespace Drupal\farm_asset_termination\EventSubscriber;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\farm_asset_termination\AssetTerminationInterface;
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
   * Cache tag invalidator service.
   */
  protected CacheTagsInvalidatorInterface $cacheTagsInvalidator;

  /**
   * The Asset Termination service.
   */
  protected AssetTerminationInterface $assetTermination;

  /**
   * Constructs a LogEventSubscriber object.
   */
  public function __construct(
    CacheTagsInvalidatorInterface $cache_tags_invalidator,
    AssetTerminationInterface $asset_termination,
  ) {
    $this->cacheTagsInvalidator = $cache_tags_invalidator;
    $this->assetTermination = $asset_termination;
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

    // If log is not yet completed we heave nothing to do.
    if ($log->get('status')->getString() !== 'done') {
      return;
    }

    // If log is not marked as termination we have nothing to do.
    if (
      $log->get('is_termination')->isEmpty()
      || !boolval($log->get('is_termination')->getString())
    ) {
      return;
    }

    // Assign termination log category.
    if ($this->assetTermination->hasTerminationCategory()) {
      $this->assetTermination->assignTerminationCategory($log, save: FALSE);
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

    // Archive assets referenced by termination log.
    $tags = [];
    /** @var \Drupal\asset\Entity\AssetInterface[] */
    $terminatedAssets = $assetsField->referencedEntities();
    foreach ($terminatedAssets as $asset) {
      $asset->setArchivedTime(intval($log->get('timestamp')->getString()));
      $asset->save();
      array_push($tags, ...$asset->getCacheTags());
    }

    // Invalidate cache of terminated assets.
    $this->cacheTagsInvalidator->invalidateTags($tags);

  }

}
