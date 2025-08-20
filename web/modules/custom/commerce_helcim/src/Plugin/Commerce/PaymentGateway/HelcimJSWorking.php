<?php

namespace Drupal\commerce_helcim\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides the Helcim HelcimJS payment gateway.
 *
 * @CommercePaymentGateway(
 *   id = "helcim_js_working",
 *   label = "Helcim HelcimJS",
 *   display_label = "Credit Card",
 *   forms = {
 *     "offsite-payment" = "Drupal\commerce_helcim\PluginForm\HelcimJSCheckoutForm",
 *   },
 *   payment_method_types = {"credit_card"},
 *   credit_card_types = {
 *     "amex", "dinersclub", "discover", "jcb", "maestro", "mastercard", "visa",
 *   },
 * )
 */
class HelcimJSWorking extends OffsitePaymentGatewayBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'api_token' => '',
      'secret_key' => '',
      'terminal_id' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['api_token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Token'),
      '#description' => $this->t('Your Helcim API Token.'),
      '#default_value' => $this->configuration['api_token'],
      '#required' => TRUE,
    ];

    $form['secret_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Secret Key'),
      '#description' => $this->t('Your Helcim Secret Key for HelcimJS.'),
      '#default_value' => $this->configuration['secret_key'],
      '#required' => TRUE,
    ];

    $form['terminal_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Terminal ID'),
      '#description' => $this->t('Your Helcim Terminal ID (optional).'),
      '#default_value' => $this->configuration['terminal_id'],
      '#required' => FALSE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);
      $this->configuration['api_token'] = $values['api_token'];
      $this->configuration['secret_key'] = $values['secret_key'];
      $this->configuration['terminal_id'] = $values['terminal_id'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function onReturn(OrderInterface $order, Request $request) {
    // Get the Helcim token from the request
    $helcim_token = $request->request->get('helcim_token') ?: $request->query->get('helcim_token');

    if (!empty($helcim_token)) {
      // Create a payment for this order
      $payment_storage = \Drupal::entityTypeManager()->getStorage('commerce_payment');
      $payment = $payment_storage->create([
        'state' => 'completed',
        'amount' => $order->getTotalPrice(),
        'payment_gateway' => $this->parentEntity->id(),
        'order_id' => $order->id(),
        'remote_id' => $helcim_token,
        'remote_state' => 'APPROVED',
      ]);
      $payment->save();

      \Drupal::messenger()->addMessage($this->t('Payment completed successfully.'));
    } else {
      \Drupal::messenger()->addMessage($this->t('Payment failed - no token received.'), 'error');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function onCancel(OrderInterface $order, Request $request) {
    // Handle payment cancellation
    \Drupal::messenger()->addMessage($this->t('Payment was cancelled.'), 'warning');
  }

}
