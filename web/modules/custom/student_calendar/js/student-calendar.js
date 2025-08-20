(function ($, Drupal) {
  'use strict';

  Drupal.behaviors.studentCalendar = {
    attach: function (context, settings) {
      // Calendar day interactions
      $('.calendar-day', context).once('student-calendar-day').each(function() {
        var $day = $(this);

        // Add click handler for days with menu items
        if ($day.hasClass('has-menu')) {
          $day.addClass('clickable');

          // Add tooltip functionality
          $day.hover(
            function() {
              var menuItems = $(this).find('.menu-day-link');
              if (menuItems.length > 0) {
                var tooltipText = 'Menu items: ';
                var titles = [];
                menuItems.each(function() {
                  var title = $(this).attr('title') || $(this).text();
                  titles.push(title);
                });
                tooltipText += titles.join(', ');
                $(this).attr('title', tooltipText);
              }
            }
          );
        }
      });

      // Menu day link interactions
      $('.menu-day-link', context).once('student-calendar-link').each(function() {
        $(this).hover(
          function() {
            $(this).closest('.menu-item').addClass('highlighted');
          },
          function() {
            $(this).closest('.menu-item').removeClass('highlighted');
          }
        );
      });

      // Restricted menu item interactions
      $('.menu-item.restricted', context).once('restricted-menu-item').on('click', function(e) {
        e.preventDefault();

        // Show tooltip or alert
        var message = $(this).attr('title') || 'This menu item will be available from next Monday';

        // Create a temporary tooltip
        var $tooltip = $('<div class="restriction-tooltip">' + message + '</div>');
        $('body').append($tooltip);

        var offset = $(this).offset();
        $tooltip.css({
          position: 'absolute',
          top: offset.top - 30,
          left: offset.left,
          background: '#333',
          color: 'white',
          padding: '5px 10px',
          borderRadius: '4px',
          fontSize: '12px',
          zIndex: 1000
        });

        // Remove tooltip after 2 seconds
        setTimeout(function() {
          $tooltip.fadeOut(300, function() {
            $(this).remove();
          });
        }, 2000);
      });

      // Add menu day button interactions
      $('.add-menu-btn', context).once('add-menu-btn').each(function() {
        $(this).on('click', function(e) {
          // Add a subtle animation
          $(this).addClass('clicked');
          setTimeout(() => {
            $(this).removeClass('clicked');
          }, 200);
        });
      });

      // Highlight today's date
      var today = new Date();
      var todayStr = today.getDate();
      $('.calendar-day', context).each(function() {
        var dayNumber = $(this).find('.day-number').text();
        if (parseInt(dayNumber) === todayStr && !$(this).hasClass('other-month')) {
          $(this).addClass('today');
        }
      });

      // Add loading indicator during AJAX requests
      $('#edit-student-select', context).once('student-calendar-select').on('change', function() {
        var $wrapper = $('#menu-days-wrapper');
        if ($wrapper.length) {
          $wrapper.addClass('loading');
          $wrapper.append('<div class="calendar-loading">Loading calendar...</div>');
        }
      });

      // Remove loading indicator when AJAX completes
      $(document).ajaxComplete(function(event, xhr, settings) {
        if (settings.url && settings.url.indexOf('student_calendar') !== -1) {
          $('.calendar-loading').remove();
          $('#menu-days-wrapper').removeClass('loading');
        }
      });

      // Prevent AJAX on navigation links
      $('.month-nav, .today-btn', context).once('nav-no-ajax').on('click', function(e) {
        // Force regular page navigation instead of AJAX
        if ($(this).attr('href')) {
          window.location.href = $(this).attr('href');
          e.preventDefault();
          return false;
        }
      });

      // Also prevent form submission on navigation clicks
      $(document).on('click', '.month-nav, .today-btn', function(e) {
        e.stopPropagation();
        if ($(this).attr('href')) {
          window.location.href = $(this).attr('href');
          e.preventDefault();
          return false;
        }
      });
    }
  };

})(jQuery, Drupal);
