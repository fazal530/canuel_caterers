/**
 * CCMarket theme JS
 * Location: ccmarket/assets/js/script.js
 */
(function (Drupal, drupalSettings) {
  'use strict';
  Drupal.behaviors.ccmarketHello = {
    attach: function (context, settings) {
      if (!context.querySelector('.ccmarket-init')) {
        const el = document.createElement('div');
        el.className = 'ccmarket-init';
        el.style.display = 'none';
        document.body.appendChild(el);
        // Example: console log once per page load.
        // eslint-disable-next-line no-console
        console.log('CCMarket theme behaviors attached.');
      }
    }
  };
})(Drupal, drupalSettings);
