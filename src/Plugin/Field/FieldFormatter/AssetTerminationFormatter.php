<?php

declare(strict_types=1);

namespace Drupal\farm_asset_termination\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\TimestampFormatter;
use Drupal\log\Entity\LogInterface;

/**
 * Plugin implementation of the 'timestamp' formatter.
 *
 * @FieldFormatter(
 *   id = "asset_termination",
 *   label = @Translation("Default"),
 *   field_types = {
 *     "entity_reference",
 *   }
 * )
 */
class AssetTerminationFormatter extends TimestampFormatter {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];

    if (!($items instanceof EntityReferenceFieldItemListInterface)) {
      return $elements;
    }

    $date_format = strval($this->getSetting('date_format'));
    $custom_date_format = '';
    $timezone = $this->getSetting('timezone') ?: NULL;
    $langcode = NULL;

    // If an RFC2822 date format is requested, then the month and day have to
    // be in English. @see http://www.faqs.org/rfcs/rfc2822.html
    if ($date_format === 'custom' && ($custom_date_format = $this->getSetting('custom_date_format')) === 'r') {
      $langcode = 'en';
    }

    if (!is_string($custom_date_format)) {
      $custom_date_format = strval($custom_date_format);
    }
    if (!is_null($timezone) && !is_string($timezone)) {
      $timezone = strval($timezone);
    }

    foreach ($items->referencedEntities() as $delta => $log) {
      if (!($log instanceof LogInterface)) {
        continue;
      }
      $timestamp = intval($log->get('timestamp')->getString());
      $elements[$delta] = [
        '#cache' => [
          'contexts' => [
            'timezone',
          ],
        ],
        '#type' => 'link',
        '#title' => $this->dateFormatter->format($timestamp, $date_format, $custom_date_format, $timezone, $langcode),
        '#url' => $log->toUrl(),
        '#attributes' => ['title' => $log->label()],
      ];
    }

    return $elements;
  }

}
