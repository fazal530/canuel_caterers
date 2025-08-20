<?php

namespace Drupal\commerce_helcim\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_order\Entity\OrderInterface;

use Drupal\commerce_payment\Entity\PaymentInterface;

use Drupal\commerce_payment\Exception\DeclineException;
use Drupal\commerce_payment\Exception\PaymentGatewayException;

use Drupal\commerce_payment\PaymentTypeManager;
use Drupal\commerce_payment\PaymentMethodTypeManager;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\ManualPaymentGatewayBase;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsAuthorizationsInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsRefundsInterface;
use Drupal\commerce_price\Price;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\commerce_helcim\HelcimApiService;

use Symfony\Component\HttpFoundation\Request;

/**
 * Provides the Helcim HelcimJS payment gateway.
 *
 * @CommercePaymentGateway(
 *   id = "helcim_js",
 *   label = @Translation("Helcim (HelcimJS)"),
 *   display_label = @Translation("Credit Card"),
 *   modes = {
 *     "test" = @Translation("Test"),
 *     "live" = @Translation("Live"),
 *   },
 *   forms = {
 *     "receive-payment" = "Drupal\commerce_helcim\PluginForm\HelcimJSCheckoutForm",
 *   },
 *   credit_card_types = {
 *     "amex", "dinersclub", "discover", "jcb", "maestro", "mastercard", "visa",
 *   },
 *   requires_billing_information = FALSE,
 * )
 */
class HelcimJS extends ManualPaymentGatewayBase implements SupportsAuthorizationsInterface, SupportsRefundsInterface {

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * The Helcim API service.
   *
   * @var \Drupal\commerce_helcim\HelcimApiService
   */
  protected $helcimApi;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, PaymentTypeManager $payment_type_manager, PaymentMethodTypeManager $payment_method_type_manager, ClientInterface $http_client, HelcimApiService $helcim_api) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $payment_type_manager, $payment_method_type_manager);
    $this->httpClient = $http_client;
    $this->helcimApi = $helcim_api;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.commerce_payment_type'),
      $container->get('plugin.manager.commerce_payment_method_type'),
      $container->get('http_client'),
      $container->get('commerce_helcim.api')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'display_label' => 'Credit Card',
      'mode' => 'test',
      'api_token' => '',
      'secret_key' => '',
      'terminal_id' => '',
      'instructions' => [
        'value' => 'Enter your credit card information to complete the payment.',
        'format' => 'basic_html',
      ],
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    // Ensure configuration is an array and merge with defaults
    $config = is_array($this->configuration) ? $this->configuration : [];
    return $config + $this->defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = $configuration + $this->defaultConfiguration();

    // Ensure display_label is always set
    if (empty($this->configuration['display_label'])) {
      $this->configuration['display_label'] = 'Credit Card';
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $configuration = $this->getConfiguration();

    $form['api_token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Token'),
      '#description' => $this->t('Your Helcim API Token.'),
      '#default_value' => $configuration['api_token'],
      '#required' => TRUE,
    ];

    $form['secret_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Secret Key'),
      '#description' => $this->t('Your Helcim Secret Key for HelcimJS.'),
      '#default_value' => $configuration['secret_key'],
      '#required' => TRUE,
    ];

    $form['terminal_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Terminal ID'),
      '#description' => $this->t('Your Helcim Terminal ID (optional).'),
      '#default_value' => $configuration['terminal_id'],
      '#required' => FALSE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::validateConfigurationForm($form, $form_state);

    $values = $form_state->getValue($form['#parents']);

    // Validate API token format
    if (!empty($values['api_token']) && strlen($values['api_token']) < 10) {
      $form_state->setError($form['api_token'], $this->t('API Token appears to be invalid. Please check your Helcim account settings.'));
    }

    // Validate secret key format
    if (!empty($values['secret_key']) && strlen($values['secret_key']) < 20) {
      $form_state->setError($form['secret_key'], $this->t('Secret Key appears to be invalid. Please check your Helcim account settings.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);
      $this->configuration['api_token'] = $values['api_token'] ?? '';
      $this->configuration['secret_key'] = $values['secret_key'] ?? '';
      $this->configuration['terminal_id'] = $values['terminal_id'] ?? '';

      // Add mode to configuration for API service
      $this->configuration['mode'] = $this->getMode();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function createPayment(PaymentInterface $payment, $capture = TRUE) {
    $this->assertPaymentState($payment, ['new']);

    // Get the Helcim token from the payment's remote ID.
    $helcim_token = $payment->getRemoteId();
    if (empty($helcim_token)) {
      throw new PaymentGatewayException('No Helcim token found for this payment.');
    }

    $order = $payment->getOrder();
    $amount = $payment->getAmount();

    try {
      $payment_data = [
        'token' => $helcim_token,
        'amount' => $amount->getNumber(),
        'currency' => $amount->getCurrencyCode(),
        'type' => $capture ? 'purchase' : 'preauth',
        'orderId' => $order->id(),
        'customerCode' => $order->getCustomerId(),
      ];

      $response = $this->helcimApi->processPayment($payment_data, $this->configuration);

      if ($response['status'] === 'APPROVED') {
        $payment->setState($capture ? 'completed' : 'authorization');
        $payment->setRemoteId($response['transactionId']);
        $payment->setRemoteState($response['status']);
        $payment->setAuthorizedTime(\Drupal::time()->getRequestTime());
        if ($capture) {
          $payment->setCapturedTime(\Drupal::time()->getRequestTime());
        }
        $payment->save();
      }
      else {
        throw new DeclineException($response['message'] ?? 'Payment was declined.');
      }
    }
    catch (\Exception $e) {
      throw new PaymentGatewayException('Payment processing failed: ' . $e->getMessage());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function capturePayment(PaymentInterface $payment, Price $amount = NULL) {
    $this->assertPaymentState($payment, ['authorization']);
    $amount = $amount ?: $payment->getAmount();

    try {
      $capture_data = [
        'transactionId' => $payment->getRemoteId(),
        'amount' => $amount->getNumber(),
      ];

      $response = $this->helcimApi->capturePayment($capture_data, $this->configuration);

      if ($response['status'] === 'APPROVED') {
        $payment->setState('completed');
        $payment->setAmount($amount);
        $payment->setCapturedTime(\Drupal::time()->getRequestTime());
        $payment->save();
      }
      else {
        throw new PaymentGatewayException($response['message'] ?? 'Capture failed.');
      }
    }
    catch (\Exception $e) {
      throw new PaymentGatewayException('Capture failed: ' . $e->getMessage());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function voidPayment(PaymentInterface $payment) {
    $this->assertPaymentState($payment, ['authorization']);

    try {
      $void_data = [
        'transactionId' => $payment->getRemoteId(),
      ];

      $response = $this->helcimApi->voidPayment($void_data, $this->configuration);

      if ($response['status'] === 'APPROVED') {
        $payment->setState('authorization_voided');
        $payment->save();
      }
      else {
        throw new PaymentGatewayException($response['message'] ?? 'Void failed.');
      }
    }
    catch (\Exception $e) {
      throw new PaymentGatewayException('Void failed: ' . $e->getMessage());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function refundPayment(PaymentInterface $payment, Price $amount = NULL) {
    $this->assertPaymentState($payment, ['completed', 'partially_refunded']);
    $amount = $amount ?: $payment->getAmount();

    try {
      $refund_data = [
        'transactionId' => $payment->getRemoteId(),
        'amount' => $amount->getNumber(),
      ];

      $response = $this->helcimApi->refundPayment($refund_data, $this->configuration);

      if ($response['status'] === 'APPROVED') {
        $old_refunded_amount = $payment->getRefundedAmount();
        $new_refunded_amount = $old_refunded_amount->add($amount);
        if ($new_refunded_amount->lessThan($payment->getAmount())) {
          $payment->setState('partially_refunded');
        }
        else {
          $payment->setState('refunded');
        }
        $payment->setRefundedAmount($new_refunded_amount);
        $payment->save();
      }
      else {
        throw new PaymentGatewayException($response['message'] ?? 'Refund failed.');
      }
    }
    catch (\Exception $e) {
      throw new PaymentGatewayException('Refund failed: ' . $e->getMessage());
    }
  }







  /**
   * {@inheritdoc}
   */
  public function getDisplayLabel() {
    $configuration = $this->getConfiguration();
    return $configuration['display_label'] ?? 'Credit Card';
  }

}
