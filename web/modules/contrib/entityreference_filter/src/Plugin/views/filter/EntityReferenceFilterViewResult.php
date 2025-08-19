<?php

namespace Drupal\entityreference_filter\Plugin\views\filter;

use Drupal\Component\Utility\Html;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Url;
use Drupal\views\Entity\View;
use Drupal\views\Plugin\views\filter\ManyToOne;
use Drupal\views\ViewExecutable;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filter by entity id using items got from entity reference view.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("entityreference_filter_view_result")
 *
 * @see \Drupal\taxonomy\Plugin\views\filter\TaxonomyIndexTid
 */
class EntityReferenceFilterViewResult extends ManyToOne {

  /**
   * Stores the exposed input for this filter.
   *
   * @var mixed
   */
  public $validatedExposedInput = NULL;

  /**
   * Logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannel
   */
  protected $loggerChannel;

  /**
   * Language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Config view storage.
   *
   * @var \Drupal\Core\Config\Entity\ConfigEntityStorageInterface
   */
  protected $viewStorage;

  /**
   * Entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * Path current stack.
   *
   * @var \Drupal\Core\Path\CurrentPathStack
   */
  protected $pathCurrent;

  /**
   * Entity Type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->loggerChannel = $container->get('logger.factory')->get('entityreference_filter');
    $instance->languageManager = $container->get('language_manager');
    $instance->viewStorage = $container->get('entity_type.manager')->getStorage('view');
    $instance->entityRepository = $container->get('entity.repository');
    $instance->pathCurrent = $container->get('path.current');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['type'] = ['default' => 'select'];
    $options['reference_display'] = ['default' => ''];
    $options['reference_arguments'] = ['default' => ''];
    $options['hide_empty_filter'] = ['default' => TRUE];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function hasExtraOptions() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function buildExtraOptionsForm(&$form, FormStateInterface $form_state) {
    // @todo autocomplete support
    $form['type'] = [
      '#type' => 'select',
      '#title' => $this->t('Selection type'),
      '#options' => [
        'select' => $this->t('Dropdown'),
        'textfield' => $this->t('Autocomplete'),
      ],
      '#default_value' => $this->options['type'],
      '#disabled' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildExposeForm(&$form, FormStateInterface $form_state) {
    parent::buildExposeForm($form, $form_state);

    $form['hide_empty_filter'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Hide empty filter'),
      '#description' => $this->t('Hide the exposed widget if the entity list is empty.'),
      '#default_value' => $this->options['hide_empty_filter'],
    ];

    // Hide useless field.
    $form['expose']['reduce']['#access'] = FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getValueOptions() {
    if (isset($this->valueOptions)) {
      return $this->valueOptions;
    }

    $this->valueOptions = $this->getConfiguredViewsOptions();

    return $this->valueOptions;
  }

  /**
   * Returns options for the selected filter.
   *
   * @return array
   *   Options list.
   */
  public function getConfiguredViewsOptions() {
    $options = [];
    if (empty($this->options['reference_display'])) {
      return [];
    }

    [$view_name, $display_id] = explode(':', $this->options['reference_display']);

    $view = Views::getView($view_name);

    if (!$view || !$view->access($display_id)) {
      $this->loggerChannel->warning('The view %view_name is no longer eligible for the filter.', ['%view_name' => $view_name]);
      return $options;
    }

    if ($view instanceof ViewExecutable) {
      $args = $this->getFilterArgs();
      $view->setDisplay($display_id);
      $view->setItemsPerPage(0);

      // Set `entity_reference_options` for the new EntityReference view.
      $entity_reference_options = ['limit' => NULL];
      $view->displayHandlers->get($display_id)->setOption('entity_reference_options', $entity_reference_options);

      $results = $view->executeDisplay($display_id, $args);

      if ($results) {
        foreach ($results as $renderable) {
          $this->processOptions($renderable, $options);
        }
      }
    }

    return $options;
  }

  /**
   * Process options from the selected filter.
   *
   * @param array $renderable
   *   A view's result renderable.
   * @param array $options
   *   The options array.
   */
  protected function processOptions(array $renderable, array &$options = []): void {
    $entity = $renderable["#row"]->_entity;
    $render = $this->getRenderer();
    if (version_compare(\Drupal::VERSION, '10.3', '>=')) {
      $option = $render->renderInIsolation($renderable);
    }
    else {
      // @phpstan-ignore-next-line
      $option = $render->renderPlain($renderable);
    }
    if ($entity instanceof EntityBase) {
      $options[$entity->id()] = strip_tags($option);
    }
  }

  /**
   * Get the calculated filter arguments.
   *
   * @return array
   *   Calculated arguments.
   */
  protected function getFilterArgs() {
    $args = [];
    $filters_by_identifier = [];
    $view_args = $this->getViewArgs();
    $view_context_args = $this->getViewContextArgs();
    $reference_arguments = $this->options['reference_arguments'] ?? NULL;

    if (isset($reference_arguments)) {
      $arg_str = trim($reference_arguments);
      if ($arg_str !== '') {
        $args = explode('/', $arg_str);

        foreach ($args as $i => $arg) {
          $arg = trim($arg);
          $first_char = mb_substr($arg, 0, 1);

          // URL argument.
          if ($first_char === '!') {
            $arg_no = (int) (mb_substr($arg, 1)) - 1;
            if ($arg_no >= 0) {
              $args[$i] = $view_args[$arg_no] ?? NULL;
            }
          }

          // Exposed filter as argument.
          if ($first_char === '[' && mb_substr($arg, -1) === ']') {
            // Collect exposed filters.
            if ((empty($filters_by_identifier)) && (!empty($this->view->filter))) {
              foreach ($this->view->filter as $filter_handler) {
                if (!$filter_handler->isExposed()) {
                  continue;
                }
                $filters_by_identifier[$filter_handler->options['expose']['identifier']] = $filter_handler;
              }
            }

            $args[$i] = NULL;
            $filter_name = mb_substr($arg, 1, -1);

            // User input.
            $input = $this->view->getExposedInput();
            if (isset($input[$filter_name])) {
              $args[$i] = $input[$filter_name];
            }
            // Default filter values set in the filter settings.
            elseif (isset($filters_by_identifier[$filter_name])) {
              $args[$i] = $filters_by_identifier[$filter_name]->value;
            }

            // Glue multiple values.
            if (is_array($args[$i]) && !empty($args[$i])) {
              $args[$i] = implode('+', $args[$i]);
            }
          }

          // Contextual filter as argument.
          if ($first_char === '#' && !empty($view_context_args)) {
            $arg_no = (int) (mb_substr($arg, 1)) - 1;
            $args[$i] = $view_context_args[$arg_no] ?? NULL;
          }

          // Overwrite empty values to NULL.
          if (($args[$i] === 'All') || ($args[$i] === [])) {
            $args[$i] = NULL;
          }
        }
      }
    }

    return $args;
  }

  /**
   * Get current view arguments.
   *
   * @return array
   *   View arguments.
   */
  protected function getViewArgs() {
    return $this->view->args;
  }

  /**
   * Get current view contextual arguments.
   *
   * @return array
   *   View contextual arguments.
   */
  protected function getViewContextArgs() {
    $args = [];
    $arguments = $this->view->argument ?? [];

    foreach ($arguments as $handler) {
      $args[] = $handler->getValue();
    }

    return $args;
  }

  /**
   * Get the controlling filters which are set as arguments.
   *
   * @return array
   *   Controlling filters.
   */
  protected function getControllingFilters() {
    $filters = [];
    if (isset($this->options['reference_arguments'])) {
      $arg_str = trim($this->options['reference_arguments']);
      if ($arg_str !== '') {
        $args = explode('/', $arg_str);
        foreach ($args as $arg) {
          $arg = trim($arg);
          $first_char = mb_substr($arg, 0, 1);
          if (($first_char === '[') && mb_substr($arg, -1, 1) === ']') {
            $filter_name = mb_substr($arg, 1, -1);
            $filters[] = $filter_name;
          }
        }
      }
    }

    return $filters;
  }

  /**
   * After build form callback for exposed form with entity reference filters.
   */
  public function afterBuild(array $element, FormStateInterface $form_state) {
    $identifier = $this->options['expose']['identifier'];
    $form_id = $element['#id'];
    $controlling_filters = $this->getControllingFilters();

    // Prevent Firefox from remembering values between page reloads.
    foreach ($controlling_filters as $filter) {
      if (isset($element[$filter])) {
        if (!isset($element[$filter]['#attributes'])) {
          $element[$filter]['#attributes'] = [];
        }
        $element[$filter]['#attributes']['autocomplete'] = 'off';
        foreach (Element::children($element[$filter]) as $child) {
          if (!isset($element[$filter][$child]['#attributes'])) {
            $element[$filter][$child]['#attributes'] = [];
          }
          $element[$filter][$child]['#attributes']['autocomplete'] = 'off';
        }
      }
    }

    /** @var \Drupal\views\Plugin\views\exposed_form\ExposedFormPluginInterface $exposed_plugin **/
    $exposed_plugin = $this->view->display_handler->getPlugin('exposed_form');
    $exposed_plugin_options = $exposed_plugin->options ?? NULL;
    $autosubmit = $exposed_plugin_options['bef']['general']['autosubmit'] ?? FALSE;

    // Send dependent filters settings into drupalSettings.
    if (!$autosubmit && !empty($controlling_filters)) {
      $element['#attached']['drupalSettings']['entityreference_filter'][$form_id]['view'] = [
        'view_name' => $this->view->storage->id(),
        'view_display_id' => $this->view->current_display,
        'view_args' => Html::escape(implode('/', $this->getViewArgs())),
        'view_context_args' => $this->getViewContextArgs(),
        'view_path' => Html::escape($this->pathCurrent->getPath()),
        'view_base_path' => $this->view->getPath(),
        'view_dom_id' => $this->view->dom_id,
        'ajax_path' => Url::fromRoute('entityreference_filter.ajax')->toString(),
      ];
      $element['#attached']['drupalSettings']['entityreference_filter'][$form_id]['dependent_filters_data'][$identifier] = $controlling_filters;
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  protected function valueForm(&$form, FormStateInterface $form_state) {
    $values = $this->getValueOptions();
    $exposed = $form_state->get('exposed');
    $filter_is_required = $this->options['expose']['required'];
    $filter_is_multiple = $this->options['expose']['multiple'];
    $filter_empty_hide = $this->options['hide_empty_filter'];

    // Autocomplete widget.
    if ($this->options['type'] === 'textfield') {
      // @todo autocomplete widget support
    }
    // Select list widget.
    else {
      $default_value = (array) $this->value;

      // Run time.
      if ($exposed) {
        $identifier = $this->options['expose']['identifier'];
        $user_input = $form_state->getUserInput();

        // Dynamic dependent filters.
        if (isset($this->options['reference_arguments']) && strpos($this->options['reference_arguments'], '[') !== FALSE) {
          // This filter depends on other filters dynamically,
          // store data for configuring drupalSettings and attach the library.
          $form['#attached'] = ['library' => ['entityreference_filter/entityreference_filter']];

          // Build js settings on every form rebuild.
          $form['#after_build'][] = [$this, 'afterBuild'];
        }

        // Delete irrelevant default values.
        $default_value = $user_input[$identifier] ?? [];
        if (!is_array($default_value)) {
          $default_value = [$default_value];
        }
        $default_value = array_intersect($default_value, array_keys($values));

        // Single filter selection, recalculate default value.
        if (!$filter_is_multiple) {
          if (!$filter_is_required && empty($default_value)) {
            $default_value = 'All';
          }
          elseif (empty($default_value)) {
            $keys = array_keys($values);
            $default_value = array_shift($keys);
          }
          else {
            $copy = $default_value;
            $default_value = array_shift($copy);
          }
        }
      }

      $form['value'] = [
        '#type' => 'select',
        '#title' => $this->t('Reference filter'),
        '#multiple' => TRUE,
        '#options' => $values,
        '#size' => min(9, count($values)),
        '#default_value' => $default_value,
      ];

      // Set user input.
      if ($exposed && isset($identifier)) {
        $user_input[$identifier] = $default_value;
        $form_state->setUserInput($user_input);
      }
    }

    // Hide filter with empty options.
    if (empty($values) && $exposed && $filter_empty_hide) {
      $form['value']['#prefix'] = '<div class="hidden">';
      $form['value']['#suffix'] = '</div>';
    }

    // Views UI. Options form.
    if (!$exposed) {

      $options = [];
      $views_ids = [];

      // Don't show value selection widget.
      $form['value']['#access'] = FALSE;

      $filter_base_table = !empty($this->definition['filter_base_table']) ? $this->definition['filter_base_table'] : NULL;

      // Filter views that list the entity type we want and group the separate
      // displays by view.
      if ($filter_base_table) {
        $views_ids = $this->viewStorage->getQuery()
          ->condition('status', TRUE)
          ->condition('display.*.display_plugin', 'entity_reference')
          ->condition('base_table', $filter_base_table)
          ->execute();
      }

      foreach ($this->viewStorage->loadMultiple($views_ids) as $view) {
        // Check each display to see if it meets the criteria and it is enabled.
        foreach ($view->get('display') as $display_id => $display) {
          // If the key doesn't exist, enabled is assumed.
          $enabled = !empty($display['display_options']['enabled']) || !array_key_exists('enabled', $display['display_options']);
          if ($enabled && $display['display_plugin'] === 'entity_reference') {
            $options[$view->id() . ':' . $display_id] = $view->label() . ' - ' . $display['display_title'];
          }
        }
      }

      $show_reference_arguments_field = TRUE;
      $description = '<p>' . $this->t('Choose the view and display that select the entities that can be referenced. Only views with a display of type "Entity Reference" are eligible.') . '</p>';
      if (empty($options)) {
        $options = [$this->options['reference_display'] => $this->t('None')] + $options;
        $warning = '<em>' . $this->t('No views to use. At first, create a view display type "Entity Reference" with the same entity type as default filter values.') . '</em>';
        $description = $warning;
        $show_reference_arguments_field = FALSE;
      }

      $form['reference_display'] = [
        '#type' => 'select',
        '#title' => $this->t('View used to select the entities'),
        '#required' => TRUE,
        '#options' => $options,
        '#default_value' => $this->options['reference_display'],
        '#description' => $description,
      ];

      if (empty($this->options['reference_display'])) {
        $form['reference_display']['#description'] .= '<p>' . $this->t('Entity list will be available after saving this setting.') . '</p>';
      }

      $form['reference_arguments'] = [
        '#type' => 'textfield',
        '#size' => 50,
        '#maxlength' => 256,
        '#title' => $this->t('Arguments for the view'),
        '#default_value' => $this->options['reference_arguments'],
        '#description' => $this->t('Define arguments to send them to the selected entity reference view, they are received as contextual filter values in the same order.
        Format is arg1/arg2/...argN starting from position 1. Possible arguments types are:') . '<br>' .
        $this->t('!n - argument number n of the view dynamic URL argument %') . '<br>' .
        $this->t('#n - argument number n of the contextual filter value') . '<br>' .
        $this->t('[filter_identifier] - `Filter identifier` of the named exposed filter') . '<br>' .
        $this->t('and other strings are passed as is.'),
        '#access' => $show_reference_arguments_field,
      ];

      $this->helper->buildOptionsForm($form, $form_state);
    }

  }

  /**
   * {@inheritdoc}
   */
  protected function valueValidate($form, FormStateInterface $form_state) {
    // We only validate if they've chosen the text field style.
    if ($this->options['type'] !== 'textfield') {
      return;
    }

    // @todo autocomplete validate
  }

  /**
   * {@inheritdoc}
   */
  protected function valueSubmit($form, FormStateInterface $form_state) {
    // Set values as NULL.
    $form_state->setValue(['options', 'value'], NULL);
  }

  /**
   * {@inheritdoc}
   */
  public function validateExposed(&$form, FormStateInterface $form_state) {
    $exposed = $this->isExposed();
    $identifier = $this->options['expose']['identifier'];

    if (!$exposed || !$identifier) {
      return;
    }

    // Except autocomplete widget.
    // We only validate if they've chosen the text field style.
    if ($this->options['type'] !== 'textfield') {
      if ($form_state->getValue($identifier) !== 'All') {
        $this->validatedExposedInput = (array) $form_state->getValue($identifier);
      }
      return;
    }

    // Autocomplete widget.
    // @todo autocomplete widget support
  }

  /**
   * {@inheritdoc}
   */
  public function acceptExposedInput($input) {
    $exposed = $this->isExposed();
    $filter_is_required = $this->options['expose']['required'];

    if (!$exposed) {
      return TRUE;
    }
    // We need to know the operator, which is normally set in
    // \Drupal\views\Plugin\views\filter\FilterPluginBase::acceptExposedInput(),
    // before we actually call the parent version of ourselves.
    if (!empty($this->options['expose']['use_operator']) && !empty($this->options['expose']['operator_id']) && isset($input[$this->options['expose']['operator_id']])) {
      $this->operator = $input[$this->options['expose']['operator_id']];
    }

    // If view is an attachment and is inheriting exposed filters, then assume
    // exposed input has already been validated.
    if (!empty($this->view->is_attachment) && $this->view->display_handler->usesExposed()) {
      $this->validatedExposedInput = (array) $this->view->exposed_raw_input[$this->options['expose']['identifier']];
    }

    // If we're checking for EMPTY or NOT, we don't need any input, and we can
    // say that our input conditions are met by just having the right operator.
    if ($this->operator === 'empty' || $this->operator === 'not empty') {
      return TRUE;
    }

    // If it's non-required and there's no value don't bother filtering.
    if (!$filter_is_required && empty($this->validatedExposedInput)) {
      return FALSE;
    }

    // If we have previously validated input, override values and rewrite query.
    $rewrite_query = parent::acceptExposedInput($input);
    if ($rewrite_query && isset($this->validatedExposedInput)) {
      $this->value = $this->validatedExposedInput;
    }

    return $rewrite_query;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $exposed = $this->isExposed();
    // Recalculate values if the filter is not exposed.
    if (!$exposed) {
      $options = $this->getValueOptions();
      // If there are no filter options then add zero value item to ensure
      // there are no results.
      $values = !empty($options) ? array_keys($options) : ['0'];
      $this->value = $values;
    }

    parent::query();
  }

  /**
   * Detects if the filter is exposed in the form.
   *
   * @return bool
   *   Exposed state.
   */
  public function isExposed() {
    return !empty($this->options['exposed']) ? TRUE : FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function adminSummary() {
    $this->getValueOptions();

    return parent::adminSummary();
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    // @todo check cache contexts
    $contexts = parent::getCacheContexts();
    $contexts[] = 'user';

    return $contexts;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    $tags = parent::getCacheTags();
    // Adds entityreference view base entity as cache tag.
    $reference_display = $this->options['reference_display'] ?? FALSE;

    if ($reference_display) {
      [$view_name, $display_id] = explode(':', $reference_display);
      /** @var \Drupal\views\Entity\View $config */
      $config = $this->viewStorage->load($view_name);
      if ($config instanceof View) {
        $base_table_view = $config->get('base_table');
        $definitions = $this->entityTypeManager->getDefinitions();
        // Add base entity type tags.
        foreach ($definitions as $definition) {
          if ($definition instanceof ContentEntityTypeInterface) {
            $base_table_entity = $definition->getDataTable();
            if (!$base_table_entity) {
              $base_table_entity = $definition->getBaseTable();
            }
            if ($base_table_entity === $base_table_view) {
              $tags = Cache::mergeTags($tags, $definition->getListCacheTags());
            }
          }
        }

        // Add relationship entity type tags.
        $view_display_specific = $config->getDisplay($display_id);
        $view_display_default = $config->getDisplay('default');
        $relationships = $view_display_specific['display_options']['relationships']
          ?? $view_display_default['display_options']['relationships']
          ?? [];
        foreach ($relationships as $relationship) {
          if (isset($relationship['entity_type']) && isset($definitions[$relationship['entity_type']])) {
            $tags = Cache::mergeTags($tags, $definitions[$relationship['entity_type']]->getListCacheTags());
          }
          // Handle special case with reverse entity reference.
          if (isset($relationship['plugin_id']) && ($relationship['plugin_id'] == 'entity_reverse') && strpos($relationship['field'], '__')) {
            [, $relationship_entity_type] = explode('__', $relationship['field']);
            if (isset($definitions[$relationship_entity_type])) {
              $tags = Cache::mergeTags($tags, $definitions[$relationship_entity_type]->getListCacheTags());
            }
          }
        }
      }
    }

    return $tags;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $dependencies = parent::calculateDependencies();

    // Add referenced view config as dependency.
    $reference_display = $this->options['reference_display'] ?? FALSE;

    if ($reference_display) {
      [$view_name] = explode(':', $reference_display);
      /** @var \Drupal\views\Entity\View $config */
      $config = $this->viewStorage->load($view_name);
      if (!$config instanceof View) {
        return $dependencies;
      }
      $dependencies[$config->getConfigDependencyKey()][] = $config->getConfigDependencyName();
    }

    return $dependencies;
  }

}
