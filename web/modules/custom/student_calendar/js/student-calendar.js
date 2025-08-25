(function (Drupal, once, $) {
  'use strict';

  Drupal.behaviors.studentCalendar = {
    attach: function (context, settings) {
      // Calendar day interactions
      once('student-calendar-day', '.calendar-day', context).forEach(function (el) {
        var $day = $(el);

        // Add click handler for days with menu items
        if ($day.hasClass('has-menu')) {
          $day.addClass('clickable');

          // Add tooltip functionality
          $day.hover(
            function () {
              var menuItems = $(this).find('.menu-day-link');
              if (menuItems.length > 0) {
                var tooltipText = 'Menu items: ';
                var titles = [];
                menuItems.each(function () {
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
      once('student-calendar-link', '.menu-day-link', context).forEach(function (el) {
        $(el).hover(
          function () {
            $(this).closest('.menu-item').addClass('highlighted');
          },
          function () {
            $(this).closest('.menu-item').removeClass('highlighted');
          }
        );
      });

      // Restricted menu item interactions
      once('restricted-menu-item', '.menu-item.restricted', context).forEach(function (el) {
        $(el).on('click', function (e) {
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
          setTimeout(function () {
            $tooltip.fadeOut(300, function () {
              $(this).remove();
            });
          }, 2000);
        });
      });

      // Add menu day button interactions
      once('add-menu-btn', '.add-menu-btn', context).forEach(function (el) {
        $(el).on('click', function () {
          // Add a subtle animation
          $(this).addClass('clicked');
          setTimeout(function () {
            $(el).removeClass('clicked');
          }, 200);
        });
      });

      // Highlight today's date
      var today = new Date();
      var todayStr = today.getDate();
      $('.calendar-day', context).each(function () {
        var dayNumber = $(this).find('.day-number').text();
        if (parseInt(dayNumber) === todayStr && !$(this).hasClass('other-month')) {
          $(this).addClass('today');
        }
      });

      // Add loading indicator during AJAX requests (bind once per page)
      once('student-calendar-ajax', 'body', context).forEach(function () {
        $(document).on('ajaxComplete', function (event, xhr, settings) {
          if (settings.url && settings.url.indexOf('student_calendar') !== -1) {
            $('.calendar-loading').remove();
            $('#menu-days-wrapper').removeClass('loading');
          }
        });
      });

      // Trigger loading indicator when student is changed
      once('student-calendar-select', '#edit-student-select', context).forEach(function (el) {
        $(el).on('change', function () {
          var $wrapper = $('#menu-days-wrapper');
          if ($wrapper.length) {
            $wrapper.addClass('loading');
            $wrapper.append('<div class="calendar-loading">Loading calendar...</div>');
          }
        });
      });

      // Prevent AJAX on navigation links
      once('nav-no-ajax', '.month-nav, .today-btn', context).forEach(function (el) {
        $(el).on('click', function (e) {
          // Force regular page navigation instead of AJAX
          if ($(this).attr('href')) {
            window.location.href = $(this).attr('href');
            e.preventDefault();
            return false;
          }
        });
      });

      // Also prevent form submission on navigation clicks (bind once per page)
      once('nav-no-ajax-doc', 'body', context).forEach(function () {
        $(document).on('click', '.month-nav, .today-btn', function (e) {
          e.stopPropagation();
          if ($(this).attr('href')) {
            window.location.href = $(this).attr('href');
            e.preventDefault();
            return false;
          }
        });
      });
    }
  };

})(Drupal, once, jQuery);
