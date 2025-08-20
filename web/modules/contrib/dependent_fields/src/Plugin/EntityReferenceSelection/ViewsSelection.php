<?php

namespace Drupal\dependent_fields\Plugin\EntityReferenceSelection;

use Drupal\Component\Utility\Html;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityReferenceSelection\SelectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\dependent_fields\Ajax\UpdateOptionsCommand;
use Drupal\field\Entity\FieldConfig;
use Drupal\views\ViewExecutable;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Plugin override of the 'selection' entity_reference.
 *
 * @package Drupal\dependent_fields\Plugin\EntityReferenceSelection
 *
 * @EntityReferenceSelection(
 *   id = "dependent_fields_selection",
 *   label = @Translation("Make field dependent using views"),
 *   group = "dependent_fields_selection",
 *   weight = 0
 * )
 */
class ViewsSelection extends PluginBase implements SelectionInterface, ContainerFactoryPluginInterface {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The loaded View object.
   *
   * @var \Drupal\views\ViewExecutable
   */
  protected $view;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The field type plugin manager.
   *
   * @var \Drupal\Core\Field\FieldTypePluginManagerInterface
   */
  protected $fieldTypePluginManager;

  /**
   * Constructs a new ViewsSelection object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity manager service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Field\FieldTypePluginManagerInterface $field_type_plugin_manager
   *   The field type plugin manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, ModuleHandlerInterface $module_handler, AccountInterface $current_user, MessengerInterface $messenger, RequestStack $request_stack, EntityFieldManagerInterface $entity_field_manager, FieldTypePluginManagerInterface $field_type_plugin_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityTypeManager = $entity_type_manager;
    $this->moduleHandler = $module_handler;
    $this->currentUser = $current_user;
    $this->messenger = $messenger;
    $this->requestStack = $request_stack;
    $this->entityFieldManager = $entity_field_manager;
    $this->fieldTypePluginManager = $field_type_plugin_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('module_handler'),
      $container->get('current_user'),
      $container->get('messenger'),
      $container->get('request_stack'),
      $container->get('entity_field.manager'),
      $container->get('plugin.manager.field.field_type')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'target_type' => '',
      'handler' => $this->getPluginId(),
      'entity' => '',
      'parent_field' => '',
      'reference_parent_by_uuid' => '',
    ];
  }

  /**
   * Element validate; Check View is valid.
   */
  public static function settingsFormValidate($element, FormStateInterface $form_state, $form) {
    // Split view name and display name from the 'view_and_display' value.
    if (!empty($element['view_and_display']['#value'])) {
      [$view, $display] = explode(':', $element['view_and_display']['#value']);
    }
    else {
      $form_state->setError($element, t('The views entity selection mode requires a view.'));

      return;
    }

    // Explode the 'arguments' string into an actual array. Beware, explode()
    // turns an empty string into an array with one empty string. We'll need an
    // empty array instead.
    $arguments_string = trim($element['arguments']['#value']);
    if ($arguments_string === '') {
      $arguments = [];
    }
    else {
      // array_map() is called to trim whitespaces from the arguments.
      $arguments = array_map('trim', explode(',', $arguments_string));
    }

    $value = [
      'view_name' => $view,
      'display_name' => $display,
      'arguments' => $arguments,
      'parent_field' => $element['parent_field']['#value'],
      'reference_parent_by_uuid' => $element['reference_parent_by_uuid']['#value'],
    ];
    $form_state->setValueForElement($element, $value);
  }

  /**
   * Update the dependent field options.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return mixed
   *   The updated field.
   */
  public static function updateDependentField(array $form, FormStateInterface $form_state) {
    $entity = $form_state->getFormObject()->getEntity();
    $subform = $form;
    $trigger_field = $form_state->getTriggeringElement();

    // Inline form marker.
    $inline_form = FALSE;
    if (isset($trigger_field['#parents'])) {
      $parents = $trigger_field['#parents'];
      // If paragraphs are used, find the most deeply nested paragraph.
      while (TRUE) {
        if (!in_array('subform', $parents, TRUE)) {
          break;
        }
        $parent_field_key = array_shift($parents);
        /** @var \Drupal\field\Entity\FieldConfig $definition */
        $definition = $entity->getFieldDefinition($parent_field_key);
        if ($definition->getSetting('target_type') !== 'paragraph') {
          break;
        }
        $delta = array_shift($parents);
        $widget = $subform[$parent_field_key]['widget'][$delta];
        if (!isset($widget['#paragraph_type'])) {
          break;
        }
        // Create a new paragraph instead of loading the actual paragraphs here,
        // as the actual paragraph can be empty.
        $entity = \Drupal::entityTypeManager()->getStorage('paragraph')->create([
          'type' => $widget['#paragraph_type'],
        ]);

        $subform = $subform[$parent_field_key]['widget'][$delta]['subform'];
        // Remove 'subform' corresponding with the current paragraph from array.
        array_shift($parents);
      }
    }

    // Update children.
    $children = $trigger_field['#ajax']['dependent_field_children'];
    $field_definition = $entity->getFieldDefinitions();
    $response = new AjaxResponse();
    foreach ($children as $child) {
      if ($field_definition[$child]->getSetting('handler') == 'dependent_fields_selection') {
        $handler_settings = $field_definition[$child]->getSetting('handler_settings');
        $view = Views::getView($handler_settings['dependent_fields_view']['view_name']);

        $parent_field_value = $trigger_field['#value'];
        // If the field widget is entity autocomplete, the returned value is a.
        if ($trigger_field['#type'] === 'entity_autocomplete' && preg_match('/\((\d )\)$/', $parent_field_value, $matches)) {
          // String which contains the entity id.
          $parent_field_value = $matches[1];
        }

        if (!empty($handler_settings['dependent_fields_view']['reference_parent_by_uuid'])) {
          $parent_field_value = static::convertEntityIdsToUuids($parent_field_value, $view->getBaseEntityType()->id());
        }

        // If we have an array with values we should implode those values and
        // enable Allow multiple values into our contextual filter.
        if (is_array($parent_field_value)) {
          $parent_field_value = implode(",", $parent_field_value);
        }

        // Get values from the view.
        $arguments = $handler_settings['dependent_fields_view']['arguments'];
        $view->setArguments(!empty($parent_field_value) ? [$parent_field_value] + $arguments : $arguments);
        $view->setDisplay($handler_settings['dependent_fields_view']['display_name']);
        $view->preExecute();
        $view->build();
        $options = static::getViewOptions($view);

        $form_field = $subform[$child];
        $form_field['widget']['#options'] = $options;
        $html_field_id = explode('-wrapper-', $form_field['#id'])[0];

        // Fix html_field_id last char when it ends with _.
        $html_field_id = substr($child, strlen($child) - 1, 1) == '_' ? $html_field_id . '-' : $html_field_id;

        $formatter = $form_field['widget']['#type'];
        // Check if field is multiple or not.
        $multiple = FALSE;
        /** @var \Drupal\field\Entity\FieldStorageConfig $storage_config */
        $storage_config = $field_definition[$child]->getFieldStorageDefinition();
        if ($storage_config->getCardinality() === -1) {
          $multiple = TRUE;
        }
        $response->addCommand(new UpdateOptionsCommand($html_field_id, $options, $formatter, $multiple));
      }
    }
    return $response;
  }

  /**
   * Transforms entity ids into uuids.
   *
   * @param array|string $entity_ids
   *   The entity ID list.
   * @param string $entity_type
   *   The entity type (for example, node)
   *
   * @return array
   *   Returns the corresponding uuids for given entity IDs.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected static function convertEntityIdsToUuids($entity_ids, string $entity_type) {
    if (!is_array($entity_ids)) {
      $entity_ids = [$entity_ids];
    }

    $uuids = [];
    foreach ($entity_ids as $entity_id) {
      $uuids[] = \Drupal::entityTypeManager()->getStorage($entity_type)->load($entity_id)->uuid();
    }
    return $uuids;
  }

  /**
   * Function getViewOptions.
   */
  protected static function getViewOptions(ViewExecutable $view) {
    $options = [];
    if ($view->execute()) {
      $renderer = \Drupal::service('renderer');
      $render_array = $view->style_plugin->render();
      foreach ($render_array as $key => $value) {
        $rendered_value = (string) $renderer->render($value);
        $options[] = [
          'key' => $key,
          'value' => Html::decodeEntities(strip_tags($rendered_value)),
        ];
      }
    }

    uasort($options, function ($a, $b) {
      return $a['value'] < $b['value'] ? -1 : 1;
    });

    array_unshift($options, [
      'key' => '_none',
      'value' => t('-Select-'),
    ]);
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $view_settings = !empty($this->configuration['dependent_fields_view']) ? $this->configuration['dependent_fields_view'] : [];
    $displays = Views::getApplicableViews('entity_reference_display');
    // Filter views that list the entity type we want, and group the separate
    // displays by view.
    $entity_type = $this->entityTypeManager->getDefinition($this->configuration['target_type']);
    $view_storage = $this->entityTypeManager->getStorage('view');

    $options = [];
    foreach ($displays as $data) {
      [$view_id, $display_id] = $data;
      $view = $view_storage->load($view_id);
      if (in_array($view->get('base_table'), [
        $entity_type->getBaseTable(),
        $entity_type->getDataTable(),
      ])) {
        $display = $view->get('display');
        $options[$view_id . ':' . $display_id] = $view_id . ' - ' . $display[$display_id]['display_title'];
      }
    }

    // The value of the 'view_and_display' select below will need to be split
    // into 'view_name' and 'view_display' in the final submitted values, so
    // we massage the data at validate time on the wrapping element (not
    // ideal).
    $form['dependent_fields_view']['#element_validate'] = [
      [
        get_called_class(),
        'settingsFormValidate',
      ],
    ];

    if ($options) {

      $form['dependent_fields_view']['help']['#markup'] = t('This plugin do not works for autocomplete form widget. Make sure you have selected "Select list" or "Check boxes/radio buttons" at "Manage form display" tab.');

      $default = !empty($view_settings['view_name']) ? $view_settings['view_name'] . ':' . $view_settings['display_name'] : NULL;

      $form['dependent_fields_view']['view_and_display'] = [
        '#type' => 'select',
        '#title' => $this->t('View used to select the entities'),
        '#required' => TRUE,
        '#options' => $options,
        '#default_value' => $default,
        '#description' => '<p>' . $this->t('Choose the view and display that select the entities that can be referenced.<br />Only views with a display of type "Entity Reference" are eligible.') . '</p>',
      ];

      /** @var \Drupal\field\Entity\FieldConfig $field_config */
      $field_config = $this->requestStack->getCurrentRequest()->get('field_config');
      $fields = $this->getBundleEditableFields($field_config->getTargetEntityTypeId(), $field_config->getTargetBundle());

      $fields_options = [];
      if (count($fields)) {
        foreach ($fields as $key => $name) {
          // Do not include the dependent field itself.
          if ($key !== 'title' && $key !== $field_config->getName()) {
            $fields_options[$key] = $name;
          }
        }
      }

      $default = !empty($view_settings['parent_field']) ? $view_settings['parent_field'] : NULL;
      $form['dependent_fields_view']['parent_field'] = [
        '#type' => 'select',
        '#title' => t('Parent field'),
        '#options' => $fields_options,
        '#required' => TRUE,
        '#description' => t('The field which this field depends. When the parent field value is changed, the available options for this field will be updated using the parent field value as the first argument followed by any particular other argument imputed in the "Views arguments".'),
        '#default_value' => $default,
      ];

      $default = !empty($view_settings['reference_parent_by_uuid']) ? $view_settings['reference_parent_by_uuid'] : FALSE;
      $form['dependent_fields_view']['reference_parent_by_uuid'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Reference parent by UUID instead of entity ID?'),
        '#default_value' => $default,
        '#description' => $this->t('Required if the parent argument in your view (selected above) accepts UUIDs instead of entity IDs (to ensure configuration portability between sites).'),
      ];

      $default = !empty($view_settings['arguments']) ? implode(', ', $view_settings['arguments']) : '';
      $form['dependent_fields_view']['arguments'] = [
        '#type' => 'textfield',
        '#title' => $this->t('View arguments'),
        '#default_value' => $default,
        '#required' => FALSE,
        '#description' => $this->t('Provide a comma separated list of arguments to pass to the view.'),
      ];
    }
    else {
      if ($this->currentUser->hasPermission('administer views') && $this->moduleHandler->moduleExists('views_ui')) {
        $form['dependent_fields_view']['no_view_help'] = [
          '#markup' => '<p>' . $this->t('No eligible views were found. <a href=":create">Create a view</a> with an <em>Entity Reference</em> display, or add such a display to an <a href=":existing">existing view</a>.',
              [
                ':create' => Url::fromRoute('views_ui.add')->toString(),
                ':existing' => Url::fromRoute('entity.view.collection')
                  ->toString(),
              ]) . '</p>',
        ];
      }
      else {
        $form['dependent_fields_view']['no_view_help']['#markup'] = '<p>' . $this->t('No eligible views were found.') . '</p>';
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {}

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {}

  /**
   * Initializes a view.
   *
   * @param string|null $match
   *   (Optional) Text to match the label against. Defaults to NULL.
   * @param string $match_operator
   *   (Optional) The operation the matching should be done with. Defaults
   *   to "CONTAINS".
   * @param int $limit
   *   Limit the query to a given number of items. Defaults to 0, which
   *   indicates no limiting.
   * @param array|null $ids
   *   Array of entity IDs. Defaults to NULL.
   *
   * @return bool
   *   Return TRUE if the view was initialized, FALSE otherwise.
   */
  protected function initializeView($match = NULL, $match_operator = 'CONTAINS', $limit = 0, $ids = NULL) {
    $view_name = $this->configuration['dependent_fields_view']['view_name'];
    $display_name = $this->configuration['dependent_fields_view']['display_name'];

    // Check that the view is valid and the display still exists.
    $this->view = Views::getView($view_name);
    if (!$this->view || !$this->view->access($display_name)) {
      $this->messenger->addWarning(t('The reference view %view_name cannot be found.', ['%view_name' => $view_name]));

      return FALSE;
    }
    $this->view->setDisplay($display_name);

    // Pass options to the display handler to make them available later.
    $entity_reference_options = [
      'match' => $match,
      'match_operator' => $match_operator,
      'limit' => $limit,
      'ids' => $ids,
    ];
    $this->view->displayHandlers->get($display_name)
      ->setOption('entity_reference_options', $entity_reference_options);

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getReferenceableEntities($match = NULL, $match_operator = 'CONTAINS', $limit = 0) {
    $display_name = $this->configuration['dependent_fields_view']['display_name'];
    $arguments = $this->configuration['dependent_fields_view']['arguments'];
    $entity = $this->configuration['entity'];

    if ($entity instanceof FieldableEntityInterface) {
      $parent_field_value = $this->getParentFieldValue($entity);
    }
    else {
      $parent_field_value = $this->getParentFieldValue();
    }
    if (is_array($parent_field_value) && !empty($parent_field_value['target_id']) && preg_match('/\((\d+)\)$/', $parent_field_value['target_id'], $matches)) {
      // If the field widget is entity autocomplete, the returned value is a
      // string which contains the entity id.
      $parent_field_value = $matches[1];
    }
    // If we have an array with values we should implode those values and enable
    // Allow multiple values into our contextual filter.
    if (is_array($parent_field_value)) {
      $parent_field_value = implode(",", $parent_field_value);
    }
    $arguments = !empty($parent_field_value) ? [$parent_field_value] + $arguments : $arguments;
    $result = [];
    if ($this->initializeView($match, $match_operator, $limit)) {
      // Get the results.
      $result = $this->view->executeDisplay($display_name, $arguments);
    }

    $return = [];
    if ($result) {
      foreach ($this->view->result as $row) {
        $entity = $row->_entity;
        $return[$entity->bundle()][$entity->id()] = $entity->label();
      }
    }

    return $return;
  }

  /**
   * {@inheritdoc}
   */
  public function countReferenceableEntities($match = NULL, $match_operator = 'CONTAINS') {
    $this->getReferenceableEntities($match, $match_operator);

    return $this->view->pager->getTotalItems();
  }

  /**
   * {@inheritdoc}
   */
  public function validateReferenceableEntities(array $ids) {
    $display_name = $this->configuration['dependent_fields_view']['display_name'];
    $arguments = $this->configuration['dependent_fields_view']['arguments'];
    $entity = $this->configuration['entity'];

    if ($entity instanceof FieldableEntityInterface) {
      $parent_field_value = $this->getParentFieldValue($entity);
    }
    else {
      $parent_field_value = $this->getParentFieldValue();
    }

    // If we have an array with values we should implode those values and enable
    // Allow multiple values into our contextual filter.
    if (is_array($parent_field_value)) {
      $parent_field_value = implode(",", $parent_field_value);
    }

    $arguments = !empty($parent_field_value) ? [$parent_field_value] + $arguments : $arguments;
    $result = [];
    $ids = $this->getValidIds($parent_field_value);
    if ($this->initializeView(NULL, 'CONTAINS', 0, $ids)) {
      // Get the results.
      $entities = $this->view->executeDisplay($display_name, $arguments);
      $result = is_array($entities) ? array_keys($entities) : [];
    }

    return $result;
  }

  /**
   * Return valid ids for validation.
   *
   * @param string $parent_field_value
   *   The parent field value.
   *
   * @return array
   *   Array with valid ids.
   */
  protected function getValidIds($parent_field_value) {
    $display_name = $this->configuration['dependent_fields_view']['display_name'];
    $arguments = $this->configuration['dependent_fields_view']['arguments'];
    $arguments = !empty($parent_field_value) ? [$parent_field_value] + $arguments : $arguments;
    $result = [];
    if ($this->initializeView()) {
      // Get the results.
      $result = $this->view->executeDisplay($display_name, $arguments);
    }
    $return = [];
    if ($result) {
      foreach ($this->view->result as $row) {
        $entity = $row->_entity;
        $return[] = $entity->id();
      }
    }

    return $return;
  }

  /**
   * {@inheritdoc}
   */
  public function entityQueryAlter(SelectInterface $query) {}

  /**
   * Get the parent field value.
   *
   * @param \Drupal\Core\Entity\EntityInterface|null $entity
   *   The fallback entity to extract the value from.
   *
   * @return mixed
   *   The parent field value.
   */
  protected function getParentFieldValue(?EntityInterface $entity = NULL) {
    $field = $this->configuration['dependent_fields_view']['parent_field'];
    $value = $this->requestStack->getCurrentRequest()->get($field);

    if (!$value && $entity && $entity->get($field)) {
      $value = $entity->get($field)->getString();
    }
    if (!$value && !$entity) {
      // Try to extract values from nested entities.
      if (isset($this->configuration['entity']) && $this->configuration['entity'] instanceof EntityInterface) {
        // Handle paragraphs.
        if ($this->configuration['entity']->getEntityTypeId() === 'paragraph') {
          $value = $this->configuration['entity']->get($field)->getString();
        }
        // Here may be processors for another entity types.
      }
    }
    if (is_array($value) && !empty($value[0]['target_id']) && preg_match('/\((\d+)\)$/', $value[0]['target_id'], $matches)) {
      // If the field widget is entity autocomplete, the returned value is a
      // string which contains the entity id.
      $value = $matches[1];
    }

    return $value;
  }

  /**
   * Helper function to return all editable fields from one bundle.
   *
   * @param string $entityType
   *   The entity type.
   * @param string $bundle
   *   The entity bundle.
   * @param array $field_types_ids
   *   Array of field types ids if you want to get specifics field types.
   *
   * @return array
   *   Array of fields ['type' => 'description']
   */
  protected function getBundleEditableFields($entityType, $bundle, array $field_types_ids = []) {
    if (empty($entityType) || empty($bundle)) {
      return [];
    }

    $fields = $this->entityFieldManager->getFieldDefinitions($entityType, $bundle);
    $field_types = $this->fieldTypePluginManager->getDefinitions();
    $options = [];
    foreach ($fields as $field_name => $field_storage) {
      // Do not show: non-configurable field storages but title.
      $field_type = $field_storage->getType();
      if (($field_storage instanceof FieldConfig || ($field_storage instanceof BaseFieldDefinition && $field_name == 'title'))
      ) {
        if (count($field_types_ids) == 0 || in_array($field_type, $field_types_ids)) {
          $options[$field_name] = $this->t('@type: @field', [
            '@type'  => $field_types[$field_type]['label'],
            '@field' => $field_storage->getLabel() . " [$field_name]",
          ]);
        }
      }

    }
    asort($options);

    return $options;
  }

}
