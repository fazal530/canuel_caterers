<?php

namespace Drupal\student_calendar\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for calendar AJAX operations.
 */
class CalendarAjaxController extends ControllerBase {

  /**
   * Refresh calendar after modal form submission.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The AJAX response.
   */
  public function refreshCalendar(Request $request) {
    $response = new AjaxResponse();
    
    // Close the modal dialog
    $response->addCommand(new CloseModalDialogCommand());
    
    // Add a success message
    $message = $this->t('Calendar updated successfully.');
    $response->addCommand(new HtmlCommand('.messages', '<div class="messages messages--status">' . $message . '</div>'));
    
    // Refresh the page to show updated calendar
    $current_url = Url::fromRoute('<current>');
    $response->addCommand(new \Drupal\Core\Ajax\RedirectCommand($current_url->toString()));
    
    return $response;
  }

  /**
   * Handle successful node save and refresh calendar.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The AJAX response.
   */
  public function nodeSaveSuccess(Request $request) {
    $response = new AjaxResponse();
    
    // Close the modal dialog
    $response->addCommand(new CloseModalDialogCommand());
    
    // Add success message
    $message = $this->t('Menu day saved successfully.');
    $response->addCommand(new HtmlCommand('.messages', '<div class="messages messages--status">' . $message . '</div>'));
    
    // Refresh the calendar page
    $calendar_url = Url::fromRoute('student_calendar.calendar_form');
    $response->addCommand(new \Drupal\Core\Ajax\RedirectCommand($calendar_url->toString()));
    
    return $response;
  }

  /**
   * Handle successful node deletion and refresh calendar.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The AJAX response.
   */
  public function nodeDeleteSuccess(Request $request) {
    $response = new AjaxResponse();
    
    // Close the modal dialog
    $response->addCommand(new CloseModalDialogCommand());
    
    // Add success message
    $message = $this->t('Menu day deleted successfully.');
    $response->addCommand(new HtmlCommand('.messages', '<div class="messages messages--status">' . $message . '</div>'));
    
    // Refresh the calendar page
    $calendar_url = Url::fromRoute('student_calendar.calendar_form');
    $response->addCommand(new \Drupal\Core\Ajax\RedirectCommand($calendar_url->toString()));
    
    return $response;
  }

}
