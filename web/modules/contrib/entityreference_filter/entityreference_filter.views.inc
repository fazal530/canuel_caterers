<?php

/**
 * @file
 * Contains views hooks.
 */

use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Implements hook_views_data_alter().
 */
function entityreference_filter_views_data_alter(array &$data) {

  // Apply to entity reference fields, nid, uid, tid, gid and commerce
  // variations.
  foreach ($data as $table_name => $table_info) {
    foreach ($table_info as $field_name => $field_info) {
      $base_table = NULL;
      $target_entity_type = NULL;

      if ($field_name === 'nid') {
        $target_entity_type = 'node';
      }
      elseif ($field_name === 'uid') {
        $target_entity_type = 'user';
      }
      elseif ($field_name === 'tid') {
        $target_entity_type = 'taxonomy_term';
      }
      elseif ($field_name === 'gid') {
        $target_entity_type = 'group';
      }
      elseif (mb_strpos($table_name, 'commerce_product') !== FALSE) {
        // Commerce product variations.
        if ($field_name === 'variation_id' || (mb_substr($field_name, -10) === '_target_id' && $field_info['filter']['field_name'] === 'variations')) {
          $target_entity_type = 'commerce_product_variation';
        }
        // Commerce product.
        if ($field_name === 'product_id') {
          $target_entity_type = 'commerce_product';
        }
      }
      // Other entityreference fields.
      elseif (!empty($field_info['filter']['field_name']) && !empty($field_info['filter']['entity_type']) && mb_substr($field_name, -10) === '_target_id') {
        $entity_type = $field_info['filter']['entity_type'];
        $entity_field_name = $field_info['filter']['field_name'];
        $field_storage_config = FieldStorageConfig::loadByName($entity_type, $entity_field_name);
        if (!is_null($field_storage_config)) {
          $target_entity_type = $field_storage_config->getSetting('target_type');
        }
      }

      if (!$target_entity_type) {
        continue;
      }

      /** @var \Drupal\Core\Entity\EntityTypeInterface $entity_type_info */
      $target_entity_type_info = Drupal::entityTypeManager()->getDefinition($target_entity_type);

      // Content entities.
      if ($target_entity_type_info instanceof ContentEntityTypeInterface) {
        $base_table = $target_entity_type_info->getDataTable();
        if (!$base_table) {
          $base_table = $target_entity_type_info->getBaseTable();
        }
      }

      // @todo Config entities support.
      if (empty($base_table)) {
        continue;
      }

      if ($base_table && !empty($field_info['filter']['id']) && $field_info['filter']['id'] !== 'entityreference_filter_view_result') {
        $filter = $field_info;

        // Filter title.
        /** @var \Drupal\Core\StringTranslation\TranslatableMarkup $title */
        $title = !empty($field_info['filter']['title']) ?
            $field_info['filter']['title'] : $field_info['title'] ?? "";

        $filter['title'] = t('@title (entityreference filter)', ['@title' => $title]);

        // Filter title short.
        /** @var \Drupal\Core\StringTranslation\TranslatableMarkup $title */
        $title_short = !empty($field_info['filter']['title short']) ?
          $field_info['filter']['title short'] : $field_info['title'] ?? "";

        $filter['title short'] = t('@title (ef)', ['@title' => $title_short]);

        if (mb_strpos($table_name, 'search_api_index') !== FALSE && mb_substr($field_info['filter']['id'], 0, 11) === 'search_api_') {
          $filter['filter']['id'] = 'entityreference_filter_view_result_search_api';
        }
        else {
          $filter['filter']['id'] = 'entityreference_filter_view_result';
        }

        $filter['filter']['field'] = $field_name;
        $filter['filter']['filter_base_table'] = $base_table;

        // Adds only filter field.
        unset($filter['filter']['title'], $filter['argument'], $filter['field'], $filter['relationship'], $filter['sort']);

        $data[$table_name][$field_name . '_entityreference_filter'] = $filter;
      }
    }
  }
}
