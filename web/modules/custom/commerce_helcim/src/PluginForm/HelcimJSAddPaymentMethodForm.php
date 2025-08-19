<?php

namespace Drupal\commerce_helcim\PluginForm;

use Drupal\commerce_payment\PluginForm\PaymentMethodAddForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides the HelcimJS add payment method form.
 */
class HelcimJSAddPaymentMethodForm extends PaymentMethodAddForm {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    /** @var \Drupal\commerce_helcim\Plugin\Commerce\PaymentGateway\HelcimJS $plugin */
    $plugin = $this->plugin;
    $configuration = $plugin->getConfiguration();

    // Add HelcimJS library.
    $form['#attached']['library'][] = 'commerce_helcim/helcimjs';
    
    // Pass configuration to JavaScript.
    $form['#attached']['drupalSettings']['commerceHelcim'] = [
      'secretKey' => $configuration['secret_key'],
      'mode' => $plugin->getMode(),
    ];

    // Hidden field to store the Helcim token.
    $form['helcim_token'] = [
      '#type' => 'hidden',
      '#attributes' => ['id' => 'helcim-token'],
    ];

    // Card details container.
    $form['card_details'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['helcim-card-container']],
    ];

    $form['card_details']['card_number'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Card Number'),
      '#attributes' => [
        'id' => 'helcim-card-number',
        'class' => ['helcim-card-field'],
        'autocomplete' => 'cc-number',
        'placeholder' => '1234 5678 9012 3456',
      ],
      '#required' => TRUE,
    ];

    $form['card_details']['expiry'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['form-inline']],
    ];

    $form['card_details']['expiry']['exp_month'] = [
      '#type' => 'select',
      '#title' => $this->t('Month'),
      '#options' => [
        '' => $this->t('Month'),
        '01' => '01',
        '02' => '02',
        '03' => '03',
        '04' => '04',
        '05' => '05',
        '06' => '06',
        '07' => '07',
        '08' => '08',
        '09' => '09',
        '10' => '10',
        '11' => '11',
        '12' => '12',
      ],
      '#attributes' => [
        'id' => 'helcim-exp-month',
        'class' => ['helcim-card-field'],
      ],
      '#required' => TRUE,
    ];

    $form['card_details']['expiry']['exp_year'] = [
      '#type' => 'select',
      '#title' => $this->t('Year'),
      '#options' => $this->getYearOptions(),
      '#attributes' => [
        'id' => 'helcim-exp-year',
        'class' => ['helcim-card-field'],
      ],
      '#required' => TRUE,
    ];

    $form['card_details']['cvv'] = [
      '#type' => 'textfield',
      '#title' => $this->t('CVV'),
      '#attributes' => [
        'id' => 'helcim-cvv',
        'class' => ['helcim-card-field'],
        'autocomplete' => 'cc-csc',
        'placeholder' => '123',
        'maxlength' => 4,
      ],
      '#required' => TRUE,
    ];

    $form['card_details']['cardholder_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Cardholder Name'),
      '#attributes' => [
        'id' => 'helcim-cardholder-name',
        'class' => ['helcim-card-field'],
        'autocomplete' => 'cc-name',
        'placeholder' => 'John Doe',
      ],
      '#required' => TRUE,
    ];

    // Processing indicator.
    $form['processing'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'helcim-processing',
        'class' => ['helcim-processing', 'hidden'],
      ],
      '#markup' => '<div class="processing-message">' . $this->t('Processing payment...') . '</div>',
    ];

    // Error container.
    $form['errors'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'helcim-errors',
        'class' => ['helcim-errors'],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::validateConfigurationForm($form, $form_state);

    $values = $form_state->getValue($form['#parents']);
    
    // Check if Helcim token was generated.
    if (empty($values['helcim_token'])) {
      $form_state->setError($form['helcim_token'], $this->t('Payment processing failed. Please try again.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    $values = $form_state->getValue($form['#parents']);
    
    // Prepare payment details for the gateway.
    $payment_details = [
      'helcim_token' => $values['helcim_token'],
    ];

    // Add card details if available.
    if (!empty($values['card_type'])) {
      $payment_details['card_type'] = $values['card_type'];
    }
    if (!empty($values['last4'])) {
      $payment_details['last4'] = $values['last4'];
    }
    if (!empty($values['exp_month'])) {
      $payment_details['exp_month'] = $values['exp_month'];
    }
    if (!empty($values['exp_year'])) {
      $payment_details['exp_year'] = $values['exp_year'];
    }

    $this->entity = $this->plugin->createPaymentMethod($this->entity, $payment_details);
  }

  /**
   * Get year options for expiry year field.
   *
   * @return array
   *   Array of year options.
   */
  protected function getYearOptions() {
    $options = ['' => $this->t('Year')];
    $current_year = (int) date('Y');
    
    for ($i = 0; $i <= 10; $i++) {
      $year = $current_year + $i;
      $options[$year] = $year;
    }
    
    return $options;
  }

}
