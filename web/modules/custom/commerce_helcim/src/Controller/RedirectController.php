<?php

namespace Drupal\commerce_helcim\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * The controller for handling of Helcim's redirects to a static callback URLs.
 *
 * At the time of making this module, Helcim accepts only static URL for
 * redirect in case of successful or canceled payment. This controller handles
 * data that Helcim provides and redirects user to the appropriate route.
 */
class RedirectController extends ControllerBase {

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $currentRequest;

  /**
   * The HTTP Kernel service.
   *
   * @var \Symfony\Component\HttpKernel\HttpKernelInterface
   */
  protected $httpKernel;

  /**
   * Constructs a new RedirectController object.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Symfony\Component\HttpKernel\HttpKernelInterface $http_kernel
   *   The request instance.
   */
  public function __construct(RequestStack $request_stack, HttpKernelInterface $http_kernel) {
    $this->currentRequest = $request_stack->getCurrentRequest();
    $this->httpKernel = $http_kernel;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack'),
      $container->get('http_kernel.basic')
    );
  }

  /**
   * Handles static route for successful response and POST params from Helcim.
   *
   * Makes a sub-request to the route that handles the actual order ID and
   * passes POST parameters from Heclim that are sent in case of authorized
   * payment.
   */
  public function onReturn() {
    $post_data = $this->currentRequest->request->all();
    $cookies = $this->currentRequest->cookies->all();
    $order_id = $post_data['orderId'];
    if (empty($order_id)) {
      $url = Url::fromRoute('<front>');
      return new RedirectResponse($url->toString());
    }

    $url = Url::fromRoute('commerce_payment.checkout.return', [
      'commerce_order' => $order_id,
      'step' => 'payment',
    ])->toString();
    $sub_request = Request::create($url, 'POST', $post_data, $cookies);

    $sub_response = $this->httpKernel->handle($sub_request, HttpKernelInterface::SUB_REQUEST);
    return $sub_response;
  }

  /**
   * Handles a static "on cancel" route.
   *
   * Clears cookie and redirects to the appropriate step.
   */
  public function onCancel() {
    if ($order_id = $this->currentRequest->cookies->get('Drupal_visitor_commerce_helcim_order_id')) {
      $url = Url::fromRoute('commerce_payment.checkout.cancel', [
        'commerce_order' => $order_id,
        'step' => 'payment',
      ]);
    }
    else {
      $url = Url::fromRoute('<front>');
    }
    return new RedirectResponse($url->toString());
  }

}
