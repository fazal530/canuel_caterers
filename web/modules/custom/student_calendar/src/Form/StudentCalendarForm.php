<?php

namespace Drupal\student_calendar\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\node\Entity\Node;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\Core\Link;

/**
 * Student Calendar Form.
 */
class StudentCalendarForm extends FormBase {

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
   * Constructs a new StudentCalendarForm object.
   *
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(AccountInterface $current_user, EntityTypeManagerInterface $entity_type_manager) {
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'student_calendar_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Attach CSS and JS libraries
    $form['#attached']['library'][] = 'student_calendar/calendar_styles';
    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';

    // Get current user's child nodes
    $child_options = $this->getCurrentUserChildren();

    // Check for URL parameter to auto-select student
    $request = \Drupal::request();
    $auto_select_id = $request->query->get('id');

    // Check if user is admin or has permission to administer nodes
    $is_admin = $this->currentUser->hasPermission('administer nodes') ||
                $this->currentUser->hasPermission('create menu_day content') ||
                $this->currentUser->hasPermission('edit any menu_day content');

    if (empty($child_options) && !$is_admin) {
      $form['no_children'] = [
        '#markup' => '<div class="messages messages--warning">' .
          $this->t('You have no students assigned to your account. Please contact an administrator to assign students to your account.') .
          '</div>',
      ];
      return $form;
    }

    // For admins with no children, provide option to view all students or just calendar
    if (empty($child_options) && $is_admin) {
      $child_options = $this->getAllChildren();

      if (empty($child_options)) {
        // Show admin calendar without student selection
        $form['admin_info'] = [
          '#markup' => '<div class="messages messages--info">' .
            $this->t('Admin view: No students found. You can still use the calendar to manage menu days.') .
            '</div>',
        ];

        $form['menu_days'] = [
          '#type' => 'container',
          '#attributes' => ['id' => 'menu-days-wrapper'],
        ];

        $form['menu_days']['content'] = $this->buildAdminCalendar();
        return $form;
      }
    }

    // Add "All Students" option for admins
    if ($is_admin && !empty($child_options)) {
      $child_options = ['all' => $this->t('All Students')] + $child_options;
    }

    // Set default value if auto-select ID is provided and valid
    $default_value = '';
    if ($auto_select_id && isset($child_options[$auto_select_id])) {
      $default_value = $auto_select_id;
    } elseif ($is_admin) {
      // Default to "All Students" for admins when no auto-select ID
      $default_value = 'all';
    }

    // Add "All Students" option for admins
    $select_options = [];
    if ($is_admin) {
      $select_options['all'] = $this->t('All Students');
    }
    $select_options += $child_options;

    $form['student_select'] = [
      '#type' => 'select',
      '#title' => $this->t('Select Student'),
      '#options' => $select_options,
      '#empty_option' => $this->t('- Select a student -'),
      '#default_value' => $default_value,
      '#weight' => -10,
      '#ajax' => [
        'callback' => '::updateMenuDays',
        'wrapper' => 'menu-days-wrapper',
        'event' => 'change',
      ],
    ];

    // Add month navigation outside AJAX wrapper to prevent AJAX issues
    $current_month = $request->query->get('month', date('Y-m'));

    // Build navigation URLs
    $current_url = $request->getRequestUri();
    $base_url = strtok($current_url, '?');
    $query_params = $request->query->all();

    // Remove AJAX parameters
    unset($query_params['ajax_form']);
    unset($query_params['_wrapper_format']);

    // Calculate navigation months
    try {
      $calendar_date = new \DateTime($current_month . '-01');
    } catch (\Exception $e) {
      $calendar_date = new \DateTime();
    }

    $prev_month = clone $calendar_date;
    $prev_month->modify('-1 month');
    $next_month = clone $calendar_date;
    $next_month->modify('+1 month');

    // Build URLs
    $prev_params = $query_params;
    $prev_params['month'] = $prev_month->format('Y-m');
    $prev_url = $base_url . '?' . http_build_query($prev_params);

    $next_params = $query_params;
    $next_params['month'] = $next_month->format('Y-m');
    $next_url = $base_url . '?' . http_build_query($next_params);

    $today_params = $query_params;
    $today_params['month'] = date('Y-m');
    $today_url = $base_url . '?' . http_build_query($today_params);

    // Add navigation outside AJAX wrapper
    $form['calendar_navigation'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['calendar-navigation-wrapper']],
      '#weight' => -10,
      '#markup' => '<div class="calendar-header">' .
        '<div class="nav-left">' .
        '<a href="' . $prev_url . '" class="month-nav prev">&larr; ' . $prev_month->format('M Y') . '</a>' .
        '</div>' .
        '<div class="nav-center">' .
        '<h4>' . $calendar_date->format('F Y') . '</h4>' .
        '<a href="' . $today_url . '" class="today-btn">Today</a>' .
        '</div>' .
        '<div class="nav-right">' .
        '<a href="' . $next_url . '" class="month-nav next">' . $next_month->format('M Y') . ' &rarr;</a>' .
        '</div>' .
        '</div>',
    ];

    $form['menu_days'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'menu-days-wrapper'],
    ];

    // If a student is selected, show the menu days
    $selected_child_id = $form_state->getValue('student_select');

    // If no selection from form state, check for auto-select from URL
    if (!$selected_child_id && $default_value) {
      $selected_child_id = $default_value;
    }

    // Add student info wrapper (always present for AJAX)
    $form['student_info'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['student-info-wrapper'], 'id' => 'student-info-wrapper'],
      '#weight' => -5,
    ];

    // Add student title and access info above calendar
    if ($selected_child_id) {
      if ($selected_child_id === 'all') {
        // Show title for all students view
        $form['student_info']['title'] = [
          '#markup' => '<h3 class="student-calendar-title">' .
            $this->t('Menu Calendar - All Students') .
            '</h3>',
        ];

        $form['student_info']['admin_info'] = [
          '#markup' => '<div class="access-restriction-info">' .
            '<p><strong>' . $this->t('Admin View:') . '</strong> ' .
            $this->t('You are viewing all menu days. You can add, edit, and delete menu items.') .
            '</p></div>',
        ];
      } else {
        $child_node = Node::load($selected_child_id);
        if ($child_node) {
        // Calculate next Monday for access info
        $today = new \DateTime();
        $next_monday = clone $today;
        $current_day_of_week = (int) $today->format('N');

        if ($current_day_of_week === 1) {
          $next_monday->modify('+7 days');
        } else {
          $days_until_monday = 8 - $current_day_of_week;
          $next_monday->modify('+' . $days_until_monday . ' days');
        }

        $form['student_info']['title'] = [
          '#markup' => '<h3 class="student-calendar-title">' .
            $this->t('Menu Calendar for @student', ['@student' => $child_node->getTitle()]) .
            '</h3>',
        ];

        $form['student_info']['access_info'] = [
          '#markup' => '<div class="access-restriction-info">' .
            '<p><strong>' . $this->t('Menu Access:') . '</strong> ' .
            $this->t('You can view and purchase menu items starting from @date (next Monday).', [
              '@date' => $next_monday->format('l, F j, Y')
            ]) . '</p>' .
            '</div>',
        ];
        }
      }
    }

    if ($selected_child_id) {
      if ($selected_child_id === 'all') {
        // Show all menu days for admin
        $form['menu_days']['content'] = $this->buildAdminCalendar();
      }
      else {
        $form['menu_days']['content'] = $this->buildMenuDaysContent($selected_child_id);
      }
    }
    else {
      // Always show a basic calendar even when no student is selected
      $form['menu_days']['content'] = $this->buildBasicCalendar();
    }

    return $form;
  }

  /**
   * AJAX callback to update menu days.
   */
  public function updateMenuDays(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    // Update student info if it exists
    if (isset($form['student_info'])) {
      $response->addCommand(new ReplaceCommand('#student-info-wrapper', $form['student_info']));
    }

    // Update menu days
    $response->addCommand(new ReplaceCommand('#menu-days-wrapper', $form['menu_days']));

    return $response;
  }

  /**
   * Get current user's child nodes.
   *
   * @return array
   *   Array of child node options.
   */
  protected function getCurrentUserChildren() {
    $options = [];
    
    $node_storage = $this->entityTypeManager->getStorage('node');
    
    // Query for child nodes authored by current user
    $query = $node_storage->getQuery()
      ->condition('type', 'child')
      ->condition('uid', $this->currentUser->id())
      ->condition('status', 1)
      ->accessCheck(TRUE)
      ->sort('title', 'ASC');
    
    $child_nids = $query->execute();
    
    if (!empty($child_nids)) {
      $child_nodes = $node_storage->loadMultiple($child_nids);
      
      foreach ($child_nodes as $child_node) {
        $options[$child_node->id()] = $child_node->getTitle();
      }
    }
    
    return $options;
  }

  /**
   * Get all child nodes (for admins).
   *
   * @return array
   *   Array of child node options.
   */
  protected function getAllChildren() {
    $options = [];

    $node_storage = $this->entityTypeManager->getStorage('node');

    // Query for all child nodes
    $query = $node_storage->getQuery()
      ->condition('type', 'child')
      ->condition('status', 1)
      ->accessCheck(TRUE)
      ->sort('title', 'ASC');

    $child_nids = $query->execute();

    if (!empty($child_nids)) {
      $child_nodes = $node_storage->loadMultiple($child_nids);

      foreach ($child_nodes as $child_node) {
        $options[$child_node->id()] = $child_node->getTitle();
      }
    }

    return $options;
  }

  /**
   * Build admin calendar without student selection.
   *
   * @return array
   *   Render array for admin calendar.
   */
  protected function buildAdminCalendar() {
    $content = [];

    $content['info'] = [
      '#markup' => '<div class="calendar-info">' .
        '<p>' . $this->t('Admin Calendar View - Showing all menu days') . '</p>' .
        '</div>',
    ];

    // Get all menu days for admin view
    $node_storage = $this->entityTypeManager->getStorage('node');
    $query = $node_storage->getQuery()
      ->condition('type', 'menu_day')
      ->condition('status', 1)
      ->accessCheck(TRUE)
      ->sort('field_date', 'ASC');

    $menu_day_nids = $query->execute();
    $menu_day_nodes = [];

    if (!empty($menu_day_nids)) {
      $menu_day_nodes = $node_storage->loadMultiple($menu_day_nids);
    }

    // Show calendar with all menu days
    $content['calendar'] = $this->buildSimpleCalendarView($menu_day_nodes);
    $content['calendar']['#attributes']['class'][] = 'admin-calendar';

    return $content;
  }

  /**
   * Build menu days content for selected child.
   *
   * @param int $child_id
   *   The child node ID.
   *
   * @return array
   *   Render array for menu days content.
   */
  protected function buildMenuDaysContent($child_id) {
    $content = [];

    // Load the child node
    $child_node = Node::load($child_id);
    if (!$child_node) {
      return $this->buildBasicCalendar();
    }

    // Get school and room references from child node
    $school_ref = $child_node->get('field_school_ref')->target_id;
    $room_ref = $child_node->get('field_ref_room')->target_id;

    // Query for menu_day nodes matching school and room
    $node_storage = $this->entityTypeManager->getStorage('node');
    $query = $node_storage->getQuery()
      ->condition('type', 'menu_day')
      ->condition('status', 1)
      ->accessCheck(TRUE)
      ->sort('field_date', 'ASC');

    // Add school condition if school is specified
    if ($school_ref) {
      $query->condition('field_school_ref', $school_ref);
    }

    // Add room condition if room is specified
    if ($room_ref) {
      $query->condition('field_ref_room', $room_ref);
    }

    $menu_day_nids = $query->execute();
    $menu_day_nodes = [];

    if (!empty($menu_day_nids)) {
      $menu_day_nodes = $node_storage->loadMultiple($menu_day_nids);
    }

    // Always build calendar view, even if no menu days found
    $content['calendar'] = $this->buildSimpleCalendarView($menu_day_nodes);

    return $content;
  }

  /**
   * Build basic calendar when no student is selected.
   *
   * @return array
   *   Render array for basic calendar.
   */
  protected function buildBasicCalendar() {
    $content = [];

    $content['info'] = [
      '#markup' => '<div class="calendar-info">' .
        '<p>' . $this->t('Please select a student to view their menu calendar.') . '</p>' .
        '</div>',
    ];

    // Show current month calendar
    $content['calendar'] = $this->buildSimpleCalendarView([]);

    return $content;
  }

  /**
   * Build simple calendar view for menu days.
   *
   * @param array $menu_day_nodes
   *   Array of menu day nodes.
   *
   * @return array
   *   Render array for calendar view.
   */
  protected function buildSimpleCalendarView($menu_day_nodes) {
    // Get current date and requested month
    $request = \Drupal::request();
    $requested_month = $request->query->get('month', date('Y-m'));

    try {
      $calendar_date = new \DateTime($requested_month . '-01');
    } catch (\Exception $e) {
      $calendar_date = new \DateTime();
    }

    $current_month = $calendar_date->format('F');
    $current_year = $calendar_date->format('Y');

    // Group menu days by date
    $menu_days_by_date = [];

    foreach ($menu_day_nodes as $menu_day) {
      $date_field = $menu_day->get('field_date');
      if (!$date_field->isEmpty()) {
        $date_value = $date_field->value;
        $date_obj = new \DateTime($date_value);
        $date_key = $date_obj->format('Y-m-d');

        if (!isset($menu_days_by_date[$date_key])) {
          $menu_days_by_date[$date_key] = [];
        }
        $menu_days_by_date[$date_key][] = $menu_day;
      }
    }

    // Build calendar structure
    $calendar = [
      '#type' => 'container',
      '#attributes' => ['class' => ['calendar-container']],
    ];

    // Navigation is now handled at form level to prevent AJAX issues

    // Calendar grid
    $calendar['grid'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['calendar-grid']],
    ];

    // Day headers
    $days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    $calendar['grid']['day_headers'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['calendar-day-headers']],
    ];

    foreach ($days as $day) {
      $calendar['grid']['day_headers'][$day] = [
        '#markup' => '<div class="calendar-day-header">' . $this->t($day) . '</div>',
      ];
    }

    // Build calendar for selected month
    $calendar_start = clone $calendar_date;
    $calendar_start->modify('first day of this month');

    $calendar_end = clone $calendar_date;
    $calendar_end->modify('last day of this month');

    // Adjust to start on Sunday
    while ($calendar_start->format('w') != 0) {
      $calendar_start->modify('-1 day');
    }

    // Adjust to end on Saturday
    while ($calendar_end->format('w') != 6) {
      $calendar_end->modify('+1 day');
    }

    // Build calendar days
    $calendar['grid']['days'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['calendar-days']],
    ];

    $current_date = clone $calendar_start;
    $day_count = 0;

    // Check if current user can create menu days
    $can_create_menu_days = $this->currentUser->hasPermission('create menu_day content') ||
                           $this->currentUser->hasPermission('administer nodes');

    while ($current_date <= $calendar_end) {
      $date_key = $current_date->format('Y-m-d');
      $day_number = $current_date->format('j');
      $is_current_month = $current_date->format('m') == $calendar_date->format('m');

      $day_classes = ['calendar-day'];
      if (!$is_current_month) {
        $day_classes[] = 'other-month';
      }
      if (isset($menu_days_by_date[$date_key])) {
        $day_classes[] = 'has-menu';
      }

      $day_content = '<div class="day-number">' . $day_number . '</div>';

      // Add menu days for this date
      if (isset($menu_days_by_date[$date_key])) {
        $day_content .= '<div class="menu-items">';
        foreach ($menu_days_by_date[$date_key] as $menu_day) {
          $meal_title = '';
          $meal_field = $menu_day->get('field_meal');
          if (!$meal_field->isEmpty() && $meal_field->entity) {
            $meal_title = $meal_field->entity->getTitle();
          }

          // Check if user can access this menu day (next week only)
          $can_access = $this->canAccessMenuDayNextWeek($current_date);

          if ($can_access) {
            // Create clickable link to menu day node
            $url = Url::fromRoute('entity.node.canonical', ['node' => $menu_day->id()]);
            $link = Link::fromTextAndUrl($menu_day->getTitle(), $url);
            $link = $link->toRenderable();
            $link['#attributes']['class'][] = 'menu-day-link';
            $link['#attributes']['title'] = $meal_title;

            $day_content .= '<div class="menu-item">' . \Drupal::service('renderer')->render($link) . '</div>';
          }
          else {
            // Show menu day but without link (restricted access)
            $day_content .= '<div class="menu-item restricted" title="' .
              $this->t('Available from next Monday') . '">' .
              '<span class="menu-day-title">' . $menu_day->getTitle() . '</span>' .
              '</div>';
          }
        }
        $day_content .= '</div>';
      }

      // Add admin action buttons for current month
      if ($can_create_menu_days && $is_current_month) {
        $add_url = Url::fromRoute('node.add', ['node_type' => 'menu_day'], [
          'query' => [
            'field_date' => $date_key,
            'destination' => \Drupal::request()->getRequestUri(),
          ],
        ]);

        $admin_actions = '<div class="admin-day-actions">';

        // Add button
        $admin_actions .= '<a href="' . $add_url->toString() . '" class="action-btn add-btn use-ajax" ' .
          'data-dialog-type="modal" ' .
          'data-dialog-options=\'{"width":"90%","height":"90%","title":"Add Menu Day for ' . $current_date->format('M j, Y') . '"}\' ' .
          'title="' . $this->t('Add menu day for @date', ['@date' => $current_date->format('M j, Y')]) . '">+</a>';

        // Edit/Delete buttons for existing menu days
        if (isset($menu_days_by_date[$date_key])) {
          foreach ($menu_days_by_date[$date_key] as $menu_day) {
            $current_uri = \Drupal::request()->getRequestUri();

            $edit_url = Url::fromRoute('entity.node.edit_form', ['node' => $menu_day->id()], [
              'query' => ['destination' => $current_uri],
            ]);
            $delete_url = Url::fromRoute('entity.node.delete_form', ['node' => $menu_day->id()], [
              'query' => ['destination' => $current_uri],
            ]);

            $admin_actions .= '<a href="' . $edit_url->toString() . '" class="action-btn edit-btn use-ajax" ' .
              'data-dialog-type="modal" ' .
              'data-dialog-options=\'{"width":"90%","height":"90%","title":"Edit Menu Day"}\' ' .
              'title="Edit ' . $menu_day->getTitle() . '">✏️</a>';

            $admin_actions .= '<a href="' . $delete_url->toString() . '" class="action-btn delete-btn use-ajax" ' .
              'data-dialog-type="modal" ' .
              'data-dialog-options=\'{"width":"600px","height":"400px","title":"Delete Menu Day"}\' ' .
              'title="Delete ' . $menu_day->getTitle() . '">🗑️</a>';
          }
        }

        $admin_actions .= '</div>';
        $day_content .= $admin_actions;
      }

      $calendar['grid']['days']['day_' . $day_count] = [
        '#markup' => '<div class="' . implode(' ', $day_classes) . '">' . $day_content . '</div>',
      ];

      $current_date->modify('+1 day');
      $day_count++;
    }

    return $calendar;
  }

  /**
   * Build calendar view for menu days (original complex version).
   *
   * @param array $menu_day_nodes
   *   Array of menu day nodes.
   *
   * @return array
   *   Render array for calendar view.
   */
  protected function buildCalendarView($menu_day_nodes) {
    // Group menu days by date
    $menu_days_by_date = [];
    $current_month = null;
    $current_year = null;

    // Get current date and day of week for access control
    $today = new \DateTime();
    $current_day_of_week = (int) $today->format('N'); // 1 = Monday, 7 = Sunday

    foreach ($menu_day_nodes as $menu_day) {
      $date_field = $menu_day->get('field_date');
      if (!$date_field->isEmpty()) {
        $date_value = $date_field->value;
        $date_obj = new \DateTime($date_value);
        $date_key = $date_obj->format('Y-m-d');

        // Track current month/year for calendar header
        if ($current_month === null) {
          $current_month = $date_obj->format('F');
          $current_year = $date_obj->format('Y');
        }

        if (!isset($menu_days_by_date[$date_key])) {
          $menu_days_by_date[$date_key] = [];
        }
        $menu_days_by_date[$date_key][] = $menu_day;
      }
    }

    // If no menu days found, show current month calendar
    if (empty($menu_days_by_date)) {
      $current_month = $today->format('F');
      $current_year = $today->format('Y');
    }

    // Build calendar structure
    $calendar = [
      '#type' => 'container',
      '#attributes' => ['class' => ['calendar-container']],
    ];

    // Calendar header
    $calendar['header'] = [
      '#markup' => '<div class="calendar-header"><h4>' . $current_month . ' ' . $current_year . '</h4></div>',
    ];

    // Legend removed for cleaner calendar view

    // Calendar grid
    $calendar['grid'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['calendar-grid']],
    ];

    // Day headers
    $days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    $calendar['grid']['day_headers'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['calendar-day-headers']],
    ];

    foreach ($days as $day) {
      $calendar['grid']['day_headers'][$day] = [
        '#markup' => '<div class="calendar-day-header">' . $this->t($day) . '</div>',
      ];
    }

    // Determine calendar range
    if (!empty($menu_days_by_date)) {
      // Get first and last dates to build calendar grid
      $dates = array_keys($menu_days_by_date);
      sort($dates);
      $first_date = new \DateTime($dates[0]);
      $last_date = new \DateTime(end($dates));

      // Start from the beginning of the month
      $calendar_start = clone $first_date;
      $calendar_start->modify('first day of this month');

      // End at the end of the month
      $calendar_end = clone $last_date;
      $calendar_end->modify('last day of this month');
    }
    else {
      // Show current month if no menu days
      $calendar_start = clone $today;
      $calendar_start->modify('first day of this month');

      $calendar_end = clone $today;
      $calendar_end->modify('last day of this month');
    }

    // Adjust to start on Sunday
    while ($calendar_start->format('w') != 0) {
      $calendar_start->modify('-1 day');
    }

    // Adjust to end on Saturday
    while ($calendar_end->format('w') != 6) {
      $calendar_end->modify('+1 day');
    }

    // Build calendar days
    $calendar['grid']['days'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['calendar-days']],
    ];

    $current_date = clone $calendar_start;
    $day_count = 0;

    while ($current_date <= $calendar_end) {
      $date_key = $current_date->format('Y-m-d');
      $day_number = $current_date->format('j');
      $is_current_month = $current_date->format('m') == $first_date->format('m');

      $day_classes = ['calendar-day'];
      if (!$is_current_month) {
        $day_classes[] = 'other-month';
      }
      if (isset($menu_days_by_date[$date_key])) {
        $day_classes[] = 'has-menu';
      }

      $day_content = '<div class="day-number">' . $day_number . '</div>';

      // Add menu days for this date
      if (isset($menu_days_by_date[$date_key])) {
        $day_content .= '<div class="menu-items">';
        foreach ($menu_days_by_date[$date_key] as $menu_day) {
          $meal_title = '';
          $meal_field = $menu_day->get('field_meal');
          if (!$meal_field->isEmpty() && $meal_field->entity) {
            $meal_title = $meal_field->entity->getTitle();
          }

          // Check weekday access restriction
          $menu_day_of_week = (int) $current_date->format('N'); // 1 = Monday, 7 = Sunday
          $can_access = $this->canAccessMenuDay($current_day_of_week, $menu_day_of_week, $current_date, $today);

          if ($can_access) {
            // Create link to menu day node
            $url = Url::fromRoute('entity.node.canonical', ['node' => $menu_day->id()]);
            $link = Link::fromTextAndUrl($menu_day->getTitle(), $url);
            $link = $link->toRenderable();
            $link['#attributes']['class'][] = 'menu-day-link';
            $link['#attributes']['title'] = $meal_title;

            $day_content .= '<div class="menu-item">' . \Drupal::service('renderer')->render($link) . '</div>';
          }
          else {
            // Show menu day but without link (restricted access)
            $day_content .= '<div class="menu-item restricted" title="' . $this->t('Access restricted until Monday') . '">' .
              '<span class="menu-day-title">' . $menu_day->getTitle() . '</span>' .
              '</div>';
          }
        }
        $day_content .= '</div>';
      }

      // Add admin action buttons for current month
      if ($is_current_month) {
        $add_url = Url::fromRoute('node.add', ['node_type' => 'menu_day'], [
          'query' => [
            'field_date' => $date_key,
            'destination' => \Drupal::request()->getRequestUri(),
          ],
        ]);

        $admin_actions = '<div class="admin-day-actions">';

        // Add button
        $admin_actions .= '<a href="' . $add_url->toString() . '" class="action-btn add-btn use-ajax" ' .
          'data-dialog-type="modal" ' .
          'data-dialog-options=\'{"width":"90%","height":"90%","title":"Add Menu Day for ' . $current_date->format('M j, Y') . '"}\' ' .
          'title="' . $this->t('Add menu day for @date', ['@date' => $current_date->format('M j, Y')]) . '">+</a>';

        // Edit/Delete buttons for existing menu days
        if (isset($menu_days_by_date[$date_key])) {
          foreach ($menu_days_by_date[$date_key] as $menu_day) {
            $current_uri = \Drupal::request()->getRequestUri();

            $edit_url = Url::fromRoute('entity.node.edit_form', ['node' => $menu_day->id()], [
              'query' => ['destination' => $current_uri],
            ]);
            $delete_url = Url::fromRoute('entity.node.delete_form', ['node' => $menu_day->id()], [
              'query' => ['destination' => $current_uri],
            ]);

            $admin_actions .= '<a href="' . $edit_url->toString() . '" class="action-btn edit-btn use-ajax" ' .
              'data-dialog-type="modal" ' .
              'data-dialog-options=\'{"width":"90%","height":"90%","title":"Edit Menu Day"}\' ' .
              'title="Edit ' . $menu_day->getTitle() . '">✏️</a>';

            $admin_actions .= '<a href="' . $delete_url->toString() . '" class="action-btn delete-btn use-ajax" ' .
              'data-dialog-type="modal" ' .
              'data-dialog-options=\'{"width":"600px","height":"400px","title":"Delete Menu Day"}\' ' .
              'title="Delete ' . $menu_day->getTitle() . '">🗑️</a>';
          }
        }

        $admin_actions .= '</div>';
        $day_content .= $admin_actions;
      }

      $calendar['grid']['days']['day_' . $day_count] = [
        '#markup' => '<div class="' . implode(' ', $day_classes) . '">' . $day_content . '</div>',
      ];

      $current_date->modify('+1 day');
      $day_count++;
    }

    return $calendar;
  }

  /**
   * Check if user can access a menu day based on weekday restrictions.
   *
   * @param int $current_day_of_week
   *   Current day of week (1 = Monday, 7 = Sunday).
   * @param int $menu_day_of_week
   *   Menu day's day of week (1 = Monday, 7 = Sunday).
   * @param \DateTime $menu_date
   *   The menu day date.
   * @param \DateTime $today
   *   Today's date.
   *
   * @return bool
   *   TRUE if user can access the menu day, FALSE otherwise.
   */
  protected function canAccessMenuDay($current_day_of_week, $menu_day_of_week, $menu_date, $today) {
    // If the menu day is in the past, always allow access
    if ($menu_date < $today) {
      return TRUE;
    }

    // If the menu day is today, allow access
    if ($menu_date->format('Y-m-d') === $today->format('Y-m-d')) {
      return TRUE;
    }

    // For future dates, apply weekday restrictions
    // Users can only access Monday-Saturday menu days if it's currently Monday or later

    // If today is Sunday (7), restrict access to all future menu days
    if ($current_day_of_week === 7) {
      return FALSE;
    }

    // If menu day is Sunday (7), always restrict access
    if ($menu_day_of_week === 7) {
      return FALSE;
    }

    // If today is Monday (1) or later in the week (2-6),
    // allow access to Monday-Saturday menu days for the current week and future weeks
    if ($current_day_of_week >= 1 && $current_day_of_week <= 6) {
      // Allow access to Monday-Saturday menu days
      if ($menu_day_of_week >= 1 && $menu_day_of_week <= 6) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Check if user can access a menu day based on "next week only" rule.
   *
   * @param \DateTime $menu_date
   *   The menu day date.
   *
   * @return bool
   *   TRUE if user can access the menu day, FALSE otherwise.
   */
  protected function canAccessMenuDayNextWeek($menu_date) {
    $today = new \DateTime();

    // Calculate next Monday from today
    $next_monday = clone $today;

    // Get current day of week (1 = Monday, 7 = Sunday)
    $current_day_of_week = (int) $today->format('N');

    if ($current_day_of_week === 1) {
      // If today is Monday, next Monday is in 7 days
      $next_monday->modify('+7 days');
    } else {
      // Calculate days until next Monday
      $days_until_monday = 8 - $current_day_of_week;
      $next_monday->modify('+' . $days_until_monday . ' days');
    }

    // Set time to start of day for accurate comparison
    $next_monday->setTime(0, 0, 0);
    $menu_date_start = clone $menu_date;
    $menu_date_start->setTime(0, 0, 0);

    // Allow access only if menu date is on or after next Monday
    return $menu_date_start >= $next_monday;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // This form doesn't need a submit handler as it's display-only
  }

}
