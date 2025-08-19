<?php

namespace Drupal\commerce_helcim\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides Heclim "Hosted Pages" payment gateway.
 *
 * This file will add Helcim as payment method on
 * admin/commerce/config/payment-gateways/add.
 *
 * @link https://support.helcim.com/article/hosted-payment-pages-how-they-work Hosted Payment Pages - How They Work. @endlink
 *
 * @CommercePaymentGateway(
 *   id = "helcim_hosted_pages",
 *   label = "Helcim (Hosted Page)",
 *   display_label = "Helcim",
 *   forms = {
 *     "offsite-payment" = "Drupal\commerce_helcim\PluginForm\HelcimRedirectCheckoutForm",
 *   },
 *   payment_method_types = {"credit_card"},
 *   credit_card_types = {
 *     "amex", "dinersclub", "discover", "jcb", "maestro", "mastercard", "visa",
 *   },
 * )
 */
class HelcimHostedPage extends OffsitePaymentGatewayBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {

    return [
      'redirect_method' => 'post',
      'display_label' => 'Helcim',
      'secret_hash_key' => '',
      'hosted_page_url' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    // A placeholder for Display label field.
    $form['display_label']['#default_value'] = $this->configuration['display_label'];

    $form['secret_hash_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Secret Hash Key'),
      '#default_value' => $this->configuration['secret_hash_key'],
      '#required' => TRUE,
    ];

    $form['hosted_page_url'] = [
      '#type' => 'url',
      '#title' => $this->t('URL of Hosted Page'),
      '#description' => '<a href="https://support.helcim.com/article/helcim-commerce-new-ui-payment-pages-an-overview-of-helcim-payment-pages" target="_blank">' . $this->t('An Overview of Helcim Payment Pages') . '</a>',
      '#default_value' => $this->configuration['hosted_page_url'],
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    // @todo check url validity
    parent::submitConfigurationForm($form, $form_state);
    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);
      $this->configuration['secret_hash_key'] = $values['secret_hash_key'];
      $this->configuration['hosted_page_url'] = $values['hosted_page_url'];
    }
  }

  /**
   * Handles a successful payment request.
   *
   * {@inheritdoc}
   */
  public function onReturn(OrderInterface $order, Request $request) {
    $transaction = $request->request->all();
    $logger = \Drupal::logger('commerce_helcim');
    $logger->info('Helcim returned: ' . print_r($transaction, TRUE));

    if (!$this->isTrusted($order, $request)) {
      throw new PaymentGatewayException('Payment origin has not been verified.');
    }

    $this->messenger()->addMessage($this->t('You have returned from @gateway.', ['@gateway' => $this->getDisplayLabel()]));

    $payment_storage = $this->entityTypeManager->getStorage('commerce_payment');

    $payment = $payment_storage->create([
      'state' => 'authorized',
      // Helcim returns date and time in separate fields, Drupal stores as UNIX.
      'authorized' => strtotime($transaction['date'] . ' ' . $transaction['time']),
      // Helcim doesn't return amount with currency but Drupal wants
      // special formatting for amount, so amount from order is recorded.
      'amount' => $order->getTotalPrice(),
      'payment_gateway' => $this->parentEntity->id(),
      'remote_id' => $transaction['approvalCode'],
      'remote_state' => $transaction['response'],
      'responseMessage' => $transaction['responseMessage'],
      'order_id' => $transaction['orderId'],
      'payment_gateway_mode' => $this->getMode(),
    ]);

    $payment->save();

  }

  /**
   * Handles canceled payment request.
   *
   * Because Helcim's API doesn't support dynamically generated URLs for
   * canceled payments at the time of making this module, a cookie with order
   * ID has been set. And when a user returns back to the web-site, according
   * to that cookie's order ID zie will be redirected to the correct URL.
   *
   * {@inheritdoc}
   */
  public function onCancel(OrderInterface $order, Request $request) {
    user_cookie_delete('commerce_helcim_order_id');

    parent::onCancel($order, $request);
  }

  /**
   * Verifies transaction by comparing of amount in Payment and Order.
   *
   * Helcim returns amountHash parameter in a successful POST response which is
   * made by sha265 hash function of concatenated amount in xxxxx.xx format and
   * secret hash key that is displayed at Hosted Page settings on Helcim.com and
   * is saved in payment method settings.
   *
   * @link https://support.helcim.com/article/helcim-commerce-new-ui-integrations-helcimjs-ssl-security-and-hashing SSL, Security and Hashing . @endlink
   */
  protected function isTrusted(OrderInterface $order, Request $request) {
    $secret_hash_key = $this->configuration['secret_hash_key'];

    $original_amount = number_format($order->getTotalPrice()->getNumber(), 2, '.', '');

    $calculated_hash = hash('sha256', $secret_hash_key . $original_amount);
    $reported_hash = $request->get('amountHash');

    return $calculated_hash === $reported_hash;
  }

}
