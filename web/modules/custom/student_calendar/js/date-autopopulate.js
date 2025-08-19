(function ($, Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.studentCalendarDateAutopopulate = {
    attach: function (context, settings) {
      // Check if we have an auto date to populate
      if (drupalSettings.studentCalendar && drupalSettings.studentCalendar.autoDate) {
        var autoDate = drupalSettings.studentCalendar.autoDate;
        
        // Try different field selectors for the date field
        var dateSelectors = [
          'input[name="field_date[0][value][date]"]',
          'input[name="field_date[0][value]"]',
          'input[data-drupal-selector*="field-date"]',
          '#edit-field-date-0-value-date',
          '#edit-field-date-0-value'
        ];
        
        // Wait a bit for the form to fully load
        setTimeout(function() {
          var fieldFound = false;
          
          $.each(dateSelectors, function(index, selector) {
            var $field = $(selector, context);
            if ($field.length > 0 && !fieldFound) {
              console.log('Found date field with selector:', selector);
              
              // Set the value
              $field.val(autoDate);
              
              // Trigger change event to ensure Drupal recognizes the change
              $field.trigger('change');
              $field.trigger('blur');
              
              // Mark as found
              fieldFound = true;
              
              // Add visual indication
              $field.css('border', '2px solid #28a745');
              setTimeout(function() {
                $field.css('border', '');
              }, 2000);
              
              // Show success message
              if ($('.messages--status').length === 0) {
                $('<div class="messages messages--status">' +
                  '<div role="contentinfo" aria-label="Status message">' +
                  'Date automatically set to ' + autoDate + ' from calendar.' +
                  '</div></div>').prependTo('.region-content, main, body');
              }
            }
          });
          
          if (!fieldFound) {
            console.log('Date field not found. Available inputs:', $('input[type="date"], input[name*="date"]', context));
            
            // Try to find any date-related input
            $('input[type="date"], input[name*="date"]', context).each(function() {
              if ($(this).attr('name').indexOf('field_date') !== -1) {
                console.log('Found date field by type:', this);
                $(this).val(autoDate);
                $(this).trigger('change');
                fieldFound = true;
                return false; // break the loop
              }
            });
          }
          
        }, 500);
      }
    }
  };

})(jQuery, Drupal, drupalSettings);
