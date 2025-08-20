(function ($, Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.commerceHelcimJS = {
    attach: function (context, settings) {
      if (!settings.commerceHelcim) {
        console.log('HelcimJS: No settings found');
        return;
      }

      var $form = $('.helcim-card-container', context).closest('form');
      var $tokenField = $('#helcim-token', context);
      var $errorContainer = $('#helcim-errors', context);
      var $processingContainer = $('#helcim-processing', context);

      // Initialize HelcimJS only once
      if ($form.hasClass('helcim-initialized')) {
        return;
      }
      $form.addClass('helcim-initialized');

      // Try to initialize real HelcimJS
      var helcimInitialized = false;

      if (typeof window.helcim !== 'undefined') {
        try {
          window.helcim.configure({
            token: settings.commerceHelcim.secretKey,
            test: settings.commerceHelcim.mode === 'test'
          });
          helcimInitialized = true;
          console.log('HelcimJS initialized successfully with token:', settings.commerceHelcim.secretKey.substring(0, 10) + '...');
        } catch (error) {
          console.error('HelcimJS initialization failed:', error);
        }
      } else {
        console.warn('HelcimJS library not loaded, using mock tokens');
      }

      // Handle form submission
      $form.on('submit', function (e) {
        // Check if we already have a token
        if ($tokenField.val()) {
          return true; // Allow form submission
        }

        e.preventDefault();
        e.stopPropagation();

        // Clear previous errors
        clearErrors();
        showProcessing(true);

        // Validate form fields
        if (!validateForm()) {
          showProcessing(false);
          return false;
        }

        // Create payment data object
        var paymentData = {
          cardNumber: $('#helcim-card-number').val().replace(/\s/g, ''),
          expiryMonth: $('#helcim-exp-month').val(),
          expiryYear: $('#helcim-exp-year').val(),
          cvv: $('#helcim-cvv').val(),
          cardHolderName: $('#helcim-cardholder-name').val()
        };

        // Try real HelcimJS tokenization first
        if (helcimInitialized && window.helcim && window.helcim.createToken) {
          console.log('Using real HelcimJS tokenization');

          window.helcim.createToken({
            cardNumber: paymentData.cardNumber,
            expiryMonth: paymentData.expiryMonth,
            expiryYear: paymentData.expiryYear,
            cvv: paymentData.cvv,
            cardHolderName: paymentData.cardHolderName
          }).then(function(response) {
            if (response && response.token) {
              console.log('Real HelcimJS token created:', response.token.substring(0, 10) + '...');
              $tokenField.val(response.token);
              submitFormWithToken();
            } else {
              console.error('HelcimJS tokenization failed:', response);
              showError('Payment processing failed. Please try again.');
              showProcessing(false);
            }
          }).catch(function(error) {
            console.error('HelcimJS tokenization error:', error);
            showError('Payment processing failed. Please try again.');
            showProcessing(false);
          });

          return; // Exit here if using real HelcimJS
        }

        // Fallback: Create a mock token for testing
        console.log('Using mock token for testing');
        var mockToken = 'test_token_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        console.log('Creating mock token:', mockToken);
        $tokenField.val(mockToken);
        submitFormWithToken();

      // Function to submit form with token and card details
      function submitFormWithToken() {
        // Store card details for display
        $('<input>').attr({
          type: 'hidden',
          name: 'card_type',
          value: detectCardType(paymentData.cardNumber)
        }).appendTo($form);

        $('<input>').attr({
          type: 'hidden',
          name: 'last4',
          value: paymentData.cardNumber.slice(-4)
        }).appendTo($form);

        // Add expiry details
        $('<input>').attr({
          type: 'hidden',
          name: 'exp_month',
          value: paymentData.expiryMonth
        }).appendTo($form);

        $('<input>').attr({
          type: 'hidden',
          name: 'exp_year',
          value: paymentData.expiryYear
        }).appendTo($form);

        showProcessing(false);

        // Submit the form
        $form.off('submit').submit();
      }

        return false;
      });

      // Real-time card number formatting
      $('#helcim-card-number').on('input', function () {
        var value = $(this).val().replace(/\s/g, '');
        var formattedValue = value.replace(/(.{4})/g, '$1 ').trim();
        $(this).val(formattedValue);
        
        // Detect card type
        var cardType = detectCardType(value);
        $(this).removeClass('visa mastercard amex discover').addClass(cardType);
      });

      // CVV length validation based on card type
      $('#helcim-card-number').on('input', function () {
        var cardNumber = $(this).val().replace(/\s/g, '');
        var $cvv = $('#helcim-cvv');
        
        if (cardNumber.startsWith('34') || cardNumber.startsWith('37')) {
          // American Express
          $cvv.attr('maxlength', 4).attr('placeholder', '1234');
        } else {
          // Other cards
          $cvv.attr('maxlength', 3).attr('placeholder', '123');
        }
      });

      // Helper functions
      function validateForm() {
        var isValid = true;
        var errors = [];

        // Validate card number
        var cardNumber = $('#helcim-card-number').val().replace(/\s/g, '');
        if (!cardNumber || cardNumber.length < 13) {
          errors.push('Please enter a valid card number.');
          isValid = false;
        }

        // Validate expiry
        var expMonth = $('#helcim-exp-month').val();
        var expYear = $('#helcim-exp-year').val();
        if (!expMonth || !expYear) {
          errors.push('Please select expiry month and year.');
          isValid = false;
        } else {
          var currentDate = new Date();
          var expiryDate = new Date(expYear, expMonth - 1);
          if (expiryDate <= currentDate) {
            errors.push('Card has expired.');
            isValid = false;
          }
        }

        // Validate CVV
        var cvv = $('#helcim-cvv').val();
        if (!cvv || cvv.length < 3) {
          errors.push('Please enter a valid CVV.');
          isValid = false;
        }

        // Validate cardholder name
        var cardholderName = $('#helcim-cardholder-name').val();
        if (!cardholderName || cardholderName.trim().length < 2) {
          errors.push('Please enter the cardholder name.');
          isValid = false;
        }

        if (!isValid) {
          showError(errors.join('<br>'));
        }

        return isValid;
      }

      function detectCardType(cardNumber) {
        if (cardNumber.startsWith('4')) return 'visa';
        if (cardNumber.startsWith('5') || cardNumber.startsWith('2')) return 'mastercard';
        if (cardNumber.startsWith('34') || cardNumber.startsWith('37')) return 'amex';
        if (cardNumber.startsWith('6')) return 'discover';
        return '';
      }

      function showError(message) {
        $errorContainer.html('<div class="error-message">' + message + '</div>').show();
      }

      function clearErrors() {
        $errorContainer.empty().hide();
      }

      function showProcessing(show) {
        if (show) {
          $processingContainer.removeClass('hidden').show();
          $form.find('input[type="submit"], button[type="submit"]').prop('disabled', true);
        } else {
          $processingContainer.addClass('hidden').hide();
          $form.find('input[type="submit"], button[type="submit"]').prop('disabled', false);
        }
      }
    }
  };

})(jQuery, Drupal, drupalSettings);
