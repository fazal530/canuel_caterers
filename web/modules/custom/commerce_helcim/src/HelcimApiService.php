<?php

namespace Drupal\commerce_helcim;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Service for handling Helcim API requests.
 */
class HelcimApiService {

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a new HelcimApiService object.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(ClientInterface $http_client, LoggerChannelFactoryInterface $logger_factory, ConfigFactoryInterface $config_factory) {
    $this->httpClient = $http_client;
    $this->loggerFactory = $logger_factory;
    $this->configFactory = $config_factory;
  }



  /**
   * Process a payment transaction.
   *
   * @param array $payment_data
   *   The payment data.
   * @param array $gateway_config
   *   The gateway configuration.
   *
   * @return array
   *   The API response.
   *
   * @throws \Exception
   */
  public function processPayment(array $payment_data, array $gateway_config) {
    $endpoint = '/payment/process';
    return $this->makeRequest('POST', $endpoint, $payment_data, $gateway_config);
  }

  /**
   * Capture a pre-authorized payment.
   *
   * @param array $capture_data
   *   The capture data.
   * @param array $gateway_config
   *   The gateway configuration.
   *
   * @return array
   *   The API response.
   *
   * @throws \Exception
   */
  public function capturePayment(array $capture_data, array $gateway_config) {
    $endpoint = '/payment/capture';
    return $this->makeRequest('POST', $endpoint, $capture_data, $gateway_config);
  }

  /**
   * Void a pre-authorized payment.
   *
   * @param array $void_data
   *   The void data.
   * @param array $gateway_config
   *   The gateway configuration.
   *
   * @return array
   *   The API response.
   *
   * @throws \Exception
   */
  public function voidPayment(array $void_data, array $gateway_config) {
    $endpoint = '/payment/void';
    return $this->makeRequest('POST', $endpoint, $void_data, $gateway_config);
  }

  /**
   * Refund a completed payment.
   *
   * @param array $refund_data
   *   The refund data.
   * @param array $gateway_config
   *   The gateway configuration.
   *
   * @return array
   *   The API response.
   *
   * @throws \Exception
   */
  public function refundPayment(array $refund_data, array $gateway_config) {
    $endpoint = '/payment/refund';
    return $this->makeRequest('POST', $endpoint, $refund_data, $gateway_config);
  }

  /**
   * Get transaction details.
   *
   * @param string $transaction_id
   *   The transaction ID.
   * @param array $gateway_config
   *   The gateway configuration.
   *
   * @return array
   *   The API response.
   *
   * @throws \Exception
   */
  public function getTransaction($transaction_id, array $gateway_config) {
    $endpoint = '/payment/' . $transaction_id;
    return $this->makeRequest('GET', $endpoint, [], $gateway_config);
  }

  /**
   * Make a request to the Helcim API.
   *
   * @param string $method
   *   The HTTP method.
   * @param string $endpoint
   *   The API endpoint.
   * @param array $data
   *   The request data.
   * @param array $gateway_config
   *   The gateway configuration.
   *
   * @return array
   *   The API response.
   *
   * @throws \Exception
   */
  protected function makeRequest($method, $endpoint, array $data, array $gateway_config) {
    $base_url = $this->getApiBaseUrl($gateway_config['mode'] ?? 'live');
    $url = $base_url . $endpoint;

    $options = [
      'headers' => [
        'Content-Type' => 'application/json',
        'api-token' => $gateway_config['api_token'],
      ],
      'timeout' => 30,
    ];

    if (!empty($data)) {
      $options['json'] = $data;
    }

    try {
      $response = $this->httpClient->request($method, $url, $options);
      $body = $response->getBody()->getContents();
      $decoded_response = json_decode($body, TRUE);

      if (json_last_error() !== JSON_ERROR_NONE) {
        throw new \Exception('Invalid JSON response from Helcim API');
      }

      // Log successful requests in test mode
      if ($gateway_config['mode'] === 'test') {
        $this->loggerFactory->get('commerce_helcim')->info('Helcim API request successful: @method @endpoint', [
          '@method' => $method,
          '@endpoint' => $endpoint,
        ]);
      }

      return $decoded_response;
    }
    catch (RequestException $e) {
      $error_message = 'Helcim API request failed: ' . $e->getMessage();
      
      if ($e->hasResponse()) {
        $response_body = $e->getResponse()->getBody()->getContents();
        $error_data = json_decode($response_body, TRUE);
        
        if (isset($error_data['message'])) {
          $error_message = $error_data['message'];
        }
      }

      $this->loggerFactory->get('commerce_helcim')->error('Helcim API error: @error', [
        '@error' => $error_message,
      ]);

      throw new \Exception($error_message);
    }
  }

  /**
   * Get the API base URL based on mode.
   *
   * @param string $mode
   *   The gateway mode (test or live).
   *
   * @return string
   *   The API base URL.
   */
  protected function getApiBaseUrl($mode) {
    // Helcim uses the same URL for both test and live, 
    // the mode is determined by the API token
    return 'https://api.helcim.com/v2';
  }

  /**
   * Validate API credentials.
   *
   * @param array $gateway_config
   *   The gateway configuration.
   *
   * @return bool
   *   TRUE if credentials are valid, FALSE otherwise.
   */
  public function validateCredentials(array $gateway_config) {
    try {
      // Make a simple request to validate credentials
      $this->makeRequest('GET', '/payment/transactions', [], $gateway_config);
      return TRUE;
    }
    catch (\Exception $e) {
      return FALSE;
    }
  }

}
