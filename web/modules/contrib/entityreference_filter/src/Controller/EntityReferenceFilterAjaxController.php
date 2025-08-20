<?php

namespace Drupal\entityreference_filter\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RedirectDestinationInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\better_exposed_filters\BetterExposedFiltersHelper;
use Drupal\entityreference_filter\Ajax\EntityReferenceFilterInsertNoWrapCommand;
use Drupal\views\ViewExecutable;
use Drupal\views\ViewExecutableFactory;
use Drupal\views\Views;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Defines a controller to build dependent entityreference filters.
 */
class EntityReferenceFilterAjaxController implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The entity storage for views.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $storage;

  /**
   * The factory to load a view executable with.
   *
   * @var \Drupal\views\ViewExecutableFactory
   */
  protected $executableFactory;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The current path.
   *
   * @var \Drupal\Core\Path\CurrentPathStack
   */
  protected $currentPath;

  /**
   * The redirect destination.
   *
   * @var \Drupal\Core\Routing\RedirectDestinationInterface
   */
  protected $redirectDestination;

  /**
   * Logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $loggerChannel;

  /**
   * Language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs a ViewAjaxController object.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage for views.
   * @param \Drupal\views\ViewExecutableFactory $executable_factory
   *   The factory to load a view executable with.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Drupal\Core\Path\CurrentPathStack $current_path
   *   The current path.
   * @param \Drupal\Core\Routing\RedirectDestinationInterface $redirect_destination
   *   The redirect destination.
   * @param \Psr\Log\LoggerInterface $logger_channel
   *   Logger channel.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   Language manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   Module handler.
   */
  public function __construct(EntityStorageInterface $storage, ViewExecutableFactory $executable_factory, RendererInterface $renderer, CurrentPathStack $current_path, RedirectDestinationInterface $redirect_destination, LoggerInterface $logger_channel, LanguageManagerInterface $language_manager, ModuleHandlerInterface $module_handler) {
    $this->storage = $storage;
    $this->executableFactory = $executable_factory;
    $this->renderer = $renderer;
    $this->currentPath = $current_path;
    $this->redirectDestination = $redirect_destination;
    $this->loggerChannel = $logger_channel;
    $this->languageManager = $language_manager;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // @phpstan-ignore-next-line
    return new static(
      $container->get('entity_type.manager')->getStorage('view'),
      $container->get('views.executable'),
      $container->get('renderer'),
      $container->get('path.current'),
      $container->get('redirect.destination'),
      $container->get('logger.factory')->get('entityreference_filter'),
      $container->get('language_manager'),
      $container->get('module_handler')
    );
  }

  /**
   * Loads and renders a view via AJAX.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request object.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The ajax response.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   Thrown when the view was not found.
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   Thrown when the view isn't accessible.
   *
   * @see \Drupal\views\Controller\ViewAjaxController::ajaxView()
   */
  public function ajaxFiltersValuesRebuild(Request $request) {
    $view_data = $request->request->all('view');
    $name = $view_data['view_name'] ?? FALSE;
    $display_id = $view_data['view_display_id'] ?? FALSE;
    $dependent_filters_data = $request->request->all('dependent_filters_data');
    $form_id = $request->request->get('form_id');

    if (!empty($name) && !empty($display_id) && !empty($dependent_filters_data) && !empty($form_id)) {
      $response = new AjaxResponse();

      // Load the view to rebuild the filters for.
      /** @var \Drupal\views\ViewEntityInterface $entity */
      if (!$entity = $this->storage->load($name)) {
        throw new NotFoundHttpException();
      }

      $view = $this->executableFactory->get($entity);

      if ($view && $view->access($display_id) && $view->setDisplay($display_id)) {

        /** @var \Drupal\views\Plugin\views\exposed_form\ExposedFormPluginInterface $exposed_plugin **/
        $exposed_plugin = $view->display_handler->getPlugin('exposed_form');
        $exposed_plugin_options = $exposed_plugin->options ?? NULL;

        // Rebuild filters options.
        foreach ($dependent_filters_data as $dependent_filter_name => $dependent_filter_data) {
          $filters = $view->getHandlers('filter', $display_id);

          $reference_display = !empty($filters[$dependent_filter_name]['reference_display']) ?
            $filters[$dependent_filter_name]['reference_display'] : FALSE;

          $reference_arguments = !empty($filters[$dependent_filter_name]['reference_arguments']) ?
            $filters[$dependent_filter_name]['reference_arguments'] : FALSE;

          if ($reference_display && $reference_arguments) {
            [$filter_view_name, $filter_display_id] = explode(':', $reference_display);

            $filter_view = Views::getView($filter_view_name);

            // No view or access.
            if (!$filter_view || !$filter_view->access($filter_display_id)) {
              $this->loggerChannel->warning('The view %view_name is no longer eligible for the filter.', ['%view_name' => $filter_view_name]);

              throw new NotFoundHttpException();
            }

            if ($filter_view instanceof ViewExecutable) {
              $new_options = [];
              $args = $this->extractViewArgs($request, $dependent_filter_name, $filters);
              // Cache is controlled by the filter view itself.
              $filter_view->setDisplay($filter_display_id);
              $filter_view->setItemsPerPage(0);

              // Set `entity_reference_options` for the new EntityReference.
              $entity_reference_options = ['limit' => NULL];
              $filter_view->displayHandlers->get($filter_display_id)->setOption('entity_reference_options', $entity_reference_options);

              $results = $filter_view->executeDisplay($filter_display_id, $args);

              $filter_is_required = $filters[$dependent_filter_name]['expose']['required'];
              $filter_is_multiple = $filters[$dependent_filter_name]['expose']['multiple'];
              $filter_type = $filters[$dependent_filter_name]['type'];

              // -Any- option
              if (!$filter_is_required && $filter_type === 'select' && !$filter_is_multiple) {
                $new_options['All'] = $this->t('- Any -');
              }

              foreach ($results as $renderable) {
                $entity = $renderable["#row"]->_entity;
                if (version_compare(\Drupal::VERSION, '10.3', '>=')) {
                  $option = $this->renderer->renderInIsolation($renderable);
                }
                else {
                  // @phpstan-ignore-next-line
                  $option = $this->renderer->renderPlain($renderable);
                }
                $new_options[$entity->id()] = strip_tags($option);
              }

              // Rewrite options with Better Exposed Filters.
              if ($exposed_plugin_options && $this->moduleHandler->moduleExists('better_exposed_filters')) {
                $rewrite_to = $exposed_plugin_options['bef']['filter'][$dependent_filter_name]['advanced']['rewrite']['filter_rewrite_values'] ?? NULL;
                if ($rewrite_to) {
                  $new_options = BetterExposedFiltersHelper::rewriteOptions($new_options, $rewrite_to);
                }
              }
              // Build options string, selector and add Ajax command to return.
              $options_str = '';
              foreach ($new_options as $val => $label) {
                $options_str .= "<option value=\"$val\">$label</option>";
              }

              // Build command and send.
              $selector = '#' . $form_id . ' [name="' . $dependent_filter_name . '"],#' . $form_id . ' [name="' . $dependent_filter_name . '[]"]';
              $has_values = !empty($results);
              $hide_empty_filter = $filters[$dependent_filter_name]['hide_empty_filter'] ?? FALSE;
              $command_options = [
                'hide_empty_filter' => $hide_empty_filter,
                'has_values'        => $has_values,
              ];
              $response->addCommand(new EntityReferenceFilterInsertNoWrapCommand($selector, $options_str, $command_options));

              // If chosen is applied, it can't be updated by attachBehavior().
              $response->addCommand(new InvokeCommand($selector, 'trigger', ['liszt:updated']));
              $response->addCommand(new InvokeCommand($selector, 'trigger', ['chosen:updated']));

              // Options are changed, so run 'change' handlers.
              $response->addCommand(new InvokeCommand($selector, 'trigger', ['change']));
            }
          }
        }

        return $response;
      }

      throw new AccessDeniedHttpException();
    }

    throw new NotFoundHttpException();
  }

  /**
   * Extract and convert filter arguments to the actual values.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request object.
   * @param string $dependent_filter_name
   *   Dependent filter name.
   * @param array $filters
   *   Views filter handlers.
   *
   * @return array
   *   Calculated filter arguments.
   */
  protected function extractViewArgs(Request $request, $dependent_filter_name, array $filters) {
    $args = [];

    $view_data = $request->request->all('view');
    $parent_view_args = $view_data['view_args'] ?? [];
    $parent_view_context_args = $view_data['view_context_args'] ?? [];
    $parent_view_args = Html::decodeEntities($parent_view_args);
    $parent_view_args = $parent_view_args !== '' ? explode('/', $parent_view_args) : [];

    // Arguments can be empty, make sure they are passed on as NULL so that
    // argument validation is not triggered.
    $parent_view_args = array_map(static function ($parent_view_arg) {
      return ($parent_view_arg === '' ? NULL : $parent_view_arg);
    }, $parent_view_args);

    $reference_arguments = $filters[$dependent_filter_name]['reference_arguments'];

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
              $args[$i] = $parent_view_args[$arg_no] ?? NULL;
            }
          }

          // Exposed filter as argument.
          if ($first_char === '[' && mb_substr($arg, -1) === ']') {
            $args[$i] = NULL;
            // Collect expose filters.
            $controlling_filter = mb_substr($arg, 1, -1);
            $controlling_filter_value = $request->request->get($controlling_filter);

            if (empty($filters[$controlling_filter]['exposed'])) {
              continue;
            }

            $args[$i] = !empty($controlling_filter_value) ? $controlling_filter_value : NULL;

            // Glue multiple values.
            if (is_array($args[$i]) && !empty($args[$i])) {
              $args[$i] = implode('+', $args[$i]);
            }
          }

          // Contextual filter as argument.
          if ($first_char === '#' && !empty($parent_view_context_args)) {
            $arg_no = (int) (mb_substr($arg, 1)) - 1;
            $args[$i] = $parent_view_context_args[$arg_no] ?? NULL;
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

}
