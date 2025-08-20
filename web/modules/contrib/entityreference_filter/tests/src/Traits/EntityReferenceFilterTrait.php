<?php

namespace Drupal\Tests\entityreference_filter\Traits;

use Drupal\Core\Config\FileStorage;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Provides common helper methods for Entityreference filter module tests.
 */
trait EntityReferenceFilterTrait {

  /**
   * Prepares content for testing in views.
   *
   * Nodes and Terms referenced via entityreference fields.
   */
  protected function contentPrepare() {
    // Vocabulary 1.
    /** @var \Drupal\taxonomy\Entity\Vocabulary $vocabulary */
    $vocabulary = Vocabulary::create([
      'name' => 'test1',
      'vid'  => 'test1',
    ]);
    $vocabulary->save();

    // Id is 1.
    $term1 = Term::create([
      'name' => '1',
      'vid'  => $vocabulary->id(),
    ]);
    $term1->save();

    $term2 = Term::create([
      'name' => '2',
      'vid'  => $vocabulary->id(),
    ]);
    $term2->save();

    // Vocabulary 2.
    $vocabulary2 = Vocabulary::create([
      'name' => 'test2',
      'vid'  => 'test2',
    ]);
    $vocabulary2->save();

    $term3 = Term::create([
      'name' => '3',
      'vid'  => $vocabulary2->id(),
    ]);
    $term3->save();

    $term4 = Term::create([
      'name' => '4',
      'vid'  => $vocabulary2->id(),
    ]);
    $term4->save();

    $this->drupalCreateContentType([
      'type' => self::$nodeTypeArticle,
      'name' => 'Article',
    ]);

    // Create an entity reference field.
    $field_name = 'field_taxonomy_reference';
    $field_storage = FieldStorageConfig::create([
      'field_name'   => $field_name,
      'entity_type'  => 'node',
      'translatable' => FALSE,
      'settings'     => [
        'target_type' => 'taxonomy_term',
      ],
      'type'         => 'entity_reference',
      'cardinality'  => 1,
    ]);
    $field_storage->save();
    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'entity_type'   => 'node',
      'bundle'        => self::$nodeTypeArticle,
      'settings'      => [
        'handler'          => 'default',
        'handler_settings' => [
          // Restrict selection of terms to a single vocabulary.
          'target_bundles' => [
            $vocabulary->id()  => $vocabulary->id(),
            $vocabulary2->id() => $vocabulary2->id(),
          ],
        ],
      ],
    ]);
    $field->save();

    // Create 10 nodes.
    $node_values = [
      'type' => self::$nodeTypeArticle,
    ];
    for ($i = 0; $i < 10; $i++) {
      $node_values['taxonomy_reference'] = [];
      $node_values['taxonomy_reference'][] = ['target_id' => $term1->id()];
      $this->drupalCreateNode($node_values);
    }
  }

  /**
   * Create test views from config.
   *
   * @param array $views
   *   Views to create.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function createTestViews(array $views) {
    $storage = \Drupal::entityTypeManager()->getStorage('view');
    $module_handler = \Drupal::moduleHandler();
    $config_dir = \Drupal::service('extension.list.module')->getPath('entityreference_filter_test_config') . '/test_views';
    if (!is_dir($config_dir) || !$module_handler->moduleExists('entityreference_filter_test_config')) {
      return;
    }

    $file_storage = new FileStorage($config_dir);
    $available_views = $file_storage->listAll('views.view.');

    foreach ($views as $id) {
      $config_name = 'views.view.' . $id;
      if (in_array($config_name, $available_views, TRUE)) {
        $storage->create($file_storage->read($config_name))->save();
      }
    }

    // Rebuild the router once.
    \Drupal::service('router.builder')->rebuild();
  }

}
