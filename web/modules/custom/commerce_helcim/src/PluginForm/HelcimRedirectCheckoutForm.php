<?php

namespace Drupal\commerce_helcim\PluginForm;

use Drupal\commerce_payment\PluginForm\PaymentOffsiteForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Helcim configuration form.
 */
class HelcimRedirectCheckoutForm extends PaymentOffsiteForm {

  /**
   * {@inheritdoc}
   *
   * The full list of available parameters that can be passed to are
   * Hecim are listed here.
   *
   * @link https://support.helcim.com/article/sending-data-to-a-hosted-payment-page Sending Data to a Hosted Payment Page  @endlink
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {

    $form = parent::buildConfigurationForm($form, $form_state);

    /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
    $payment = $this->entity;
    $payment_gateway_plugin = $payment->getPaymentGateway()->getPlugin();
    $configuration = $payment_gateway_plugin->getConfiguration();

    $order = $payment->getOrder();
    $billing_info = $order->getBillingProfile()->get('address')->first();

    $url = $configuration['hosted_page_url'];

    $data = [
      'currency' => $payment->getAmount()->getCurrencyCode(),
      'transactionType' => 'purchase',
      'amount' => $payment->getAmount()->getNumber(),
      'orderId' => $payment->getOrderId(),
      'customerId' => $order->getCustomerId(),
      'billingcontactName' => $billing_info->getGivenName() . ' ' . $billing_info->getFamilyName(),
      'billingstreet1' => $billing_info->getAddressLine1(),
      'billingstreet2' => $billing_info->getAddressLine2(),
      'billingpostalCode' => $billing_info->getPostalCode(),
      'billingemail' => $order->getEmail(),
    ];

    // @todo to use in the future when the API will get improved.
    $data['return_url'] = $form['#return_url'];
    $data['cancel_url'] = $form['#cancel_url'];

    // This cookie has been set because at this time Helcim doesn't support
    // dynamic payment URLs for successful and canceled payments, so to redirect
    // a user to a proper page we use order_id cookie.
    user_cookie_save(['commerce_helcim_order_id' => $payment->getOrderId()]);

    if ($configuration['mode'] == 'test') {
      $data['test'] = 1;
    }

    $form = $this->buildRedirectForm($form, $form_state, $url, $data, PaymentOffsiteForm::REDIRECT_POST);

    return $form;
  }

}
