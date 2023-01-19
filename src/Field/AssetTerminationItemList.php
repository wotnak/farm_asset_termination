<?php

declare(strict_types=1);

namespace Drupal\farm_asset_termination\Field;

use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * Computes the termination value for assets.
 */
class AssetTerminationItemList extends EntityReferenceFieldItemList {

  use ComputedItemListTrait;

  /**
   * Computes the termination value for the asset.
   */
  protected function computeValue(): void {
    // Get the asset entity.
    $entity = $this->getEntity();

    // Get the asset's termination log.
    /** @var \Drupal\Core\Entity\Query\QueryInterface $query */
    $query = \Drupal::service('farm.log_query')->getQuery([
      'asset' => $entity,
      'timestamp' => time(),
      'limit' => 1,
      'status' => 'done',
    ]);
    $query->condition('is_termination', TRUE);
    $logIds = $query->execute();
    if (!is_array($logIds) || empty($logIds)) {
      return;
    }
    $terminationLogId = reset($logIds);

    // Assign termination log reference as field value.
    $this->list[0] = $this->createItem(0, ['target_id' => $terminationLogId]);
  }

}
