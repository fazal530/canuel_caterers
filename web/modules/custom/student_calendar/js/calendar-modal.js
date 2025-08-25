/**
 * @file
 * Calendar modal functionality for add/edit/delete operations.
 */

(function ($, Drupal) {
  'use strict';

  /**
   * Calendar modal behavior.
   */
  Drupal.behaviors.calendarModal = {
    attach: function (context, settings) {
      
      // Handle modal dialog events
      $(document).on('dialogcreate', function(event, dialog, $element) {
        // Add custom styling to modal dialogs
        var $dialog = $element.closest('.ui-dialog');
        $dialog.addClass('calendar-modal-dialog');
      });

      // Handle form submissions in modals
      $(document).on('submit', '.ui-dialog form', function(e) {
        var $form = $(this);
        var action = $form.attr('action');

        // Check if this is a node form
        if (action && (action.includes('/node/add/') || action.includes('/node/') && action.includes('/edit') || action.includes('/delete'))) {
          // Add a hidden field to indicate this is from calendar modal
          if (!$form.find('input[name="calendar_modal"]').length) {
            $form.append('<input type="hidden" name="calendar_modal" value="1">');
          }

          // Show loading state
          var $submitBtn = $form.find('input[type="submit"], button[type="submit"]');
          $submitBtn.prop('disabled', true);

          // Add loading text
          var originalText = $submitBtn.val() || $submitBtn.text();
          $submitBtn.data('original-text', originalText);
          $submitBtn.val('Saving...').text('Saving...');
        }
      });

      // Handle successful AJAX responses
      $(document).on('ajaxSuccess', function(event, xhr, settings) {
        // Check if this was a node operation from calendar modal
        if (settings.url && (settings.url.includes('/node/') && (settings.url.includes('/edit') || settings.url.includes('/delete')))) {
          // Check if the response contains redirect command
          try {
            var response = JSON.parse(xhr.responseText);
            if (response && Array.isArray(response)) {
              var hasRedirect = response.some(function(command) {
                return command.command === 'redirect';
              });

              if (hasRedirect) {
                // The AJAX response will handle the redirect
                return;
              }
            }
          } catch (e) {
            // If parsing fails, continue with fallback
          }

          // Fallback: close modal and refresh
          setTimeout(function() {
            if ($('.ui-dialog').length) {
              $('.ui-dialog').find('.ui-dialog-titlebar-close').click();
            }
            window.location.reload();
          }, 500);
        }
      });

      // Handle form errors
      $(document).on('ajaxError', function(event, xhr, settings) {
        if (settings.data && settings.data.includes('calendar_modal=1')) {
          // Re-enable submit button on error
          var $form = $('.ui-dialog form');
          var $submitBtn = $form.find('input[type="submit"], button[type="submit"]');
          $submitBtn.prop('disabled', false);

          var originalText = $submitBtn.data('original-text');
          if (originalText) {
            $submitBtn.val(originalText).text(originalText);
          }
        }
      });

      // Handle delete confirmations
      $(document).on('click', '.delete-btn', function(e) {
        e.preventDefault();
        var $link = $(this);
        var href = $link.attr('href');
        
        // Open delete confirmation in modal
        var $dialog = $('<div>').appendTo('body');
        $dialog.dialog({
          title: 'Confirm Delete',
          modal: true,
          width: 400,
          height: 200,
          resizable: false,
          close: function() {
            $dialog.remove();
          },
          buttons: {
            'Delete': function() {
              // Redirect to delete URL
              window.location.href = href;
            },
            'Cancel': function() {
              $(this).dialog('close');
            }
          }
        });
        
        $dialog.html('<p>Are you sure you want to delete this menu day?</p><p>This action cannot be undone.</p>');
      });

      // Improve modal appearance
      $(document).on('dialogopen', function(event, ui) {
        var $dialog = $(event.target).closest('.ui-dialog');
        
        // Add loading indicator for forms
        $dialog.find('form').on('submit', function() {
          var $submitBtn = $dialog.find('input[type="submit"], button[type="submit"]');
          $submitBtn.prop('disabled', true).val('Saving...');
          
          // Add spinner
          if (!$dialog.find('.ajax-progress').length) {
            $submitBtn.after('<div class="ajax-progress ajax-progress-throbber"><div class="throbber">&nbsp;</div></div>');
          }
        });
      });

      // Handle calendar refresh after modal operations
      if (window.location.hash === '#calendar-updated') {
        // Remove hash and show success message
        history.replaceState(null, null, window.location.pathname + window.location.search);
        
        // Show success message
        var $messages = $('.messages');
        if (!$messages.length) {
          $messages = $('<div class="messages messages--status">Menu day updated successfully.</div>');
          $('.calendar-container').prepend($messages);
        }
        
        // Auto-hide message after 3 seconds
        setTimeout(function() {
          $messages.fadeOut();
        }, 3000);
      }
    }
  };

  /**
   * Custom AJAX command to refresh calendar.
   */
  Drupal.AjaxCommands.prototype.refreshCalendar = function (ajax, response, status) {
    // Close any open modals
    if ($('.ui-dialog').length) {
      $('.ui-dialog').find('.ui-dialog-titlebar-close').click();
    }
    
    // Refresh the page
    window.location.reload();
  };

})(jQuery, Drupal);
