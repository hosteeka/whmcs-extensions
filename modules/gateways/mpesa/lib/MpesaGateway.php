<?php

namespace WHMCS\Module\Gateway\Mpesa;

class MpesaGateway
{
  # Define the host URLs
  private const SANDBOX_HOST = 'https://sandbox.safaricom.co.ke';
  private const PRODUCTION_HOST = 'https://api.safaricom.co.ke';

  # Define the API endpoints
  private const AUTH_URL = '/oauth/v1/generate?grant_type=client_credentials';
  private const STK_PUSH_URL = '/mpesa/stkpush/v1/processrequest';

  # Define private class properties
  private $host;
  private $shortcodeType;
  private $consumerKey;
  private $consumerSecret;
  private $passkey;

  # Define public class properties
  public $shortcode;

  public function __construct($params)
  {
    if ($params['sandbox'] == 'on') {
      $this->host = self::SANDBOX_HOST;
      $this->shortcode = $params['sandboxShortcode'];
      $this->consumerKey = $params['sandboxConsumerKey'];
      $this->consumerSecret = $params['sandboxConsumerSecret'];
      $this->passkey = $params['sandboxPasskey'];
    } else {
      $this->host = self::PRODUCTION_HOST;
      $this->shortcode = $params['shortcode'];
      $this->consumerKey = $params['consumerKey'];
      $this->consumerSecret = $params['consumerSecret'];
      $this->passkey = $params['passkey'];
    }

    $this->shortcodeType = $params['shortcodeType'];
  }

  /**
   * Make a cURL request to the M-PESA API.
   * 
   * @param string $url
   * @param string $method
   * @param array $params
   * 
   * @return array
   */
  private function curlRequest($url, $method, $params = [])
  {
    $curl = curl_init();
    $headers = isset($params['headers']) ? $params['headers'] : [];
    $data = isset($params['data']) ? $params['data'] : [];

    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));

    $response = curl_exec($curl);
    $response = json_decode($response, true);
    $error = curl_error($curl);

    curl_close($curl);

    if ($error) {
      logActivity('M-PESA Gateway: Curl error ' . $error);
      die('M-PESA Gateway: Curl error ' . $error);
    }

    if (isset($response['errorCode'])) {
      logActivity('M-PESA Gateway: ' . $response['errorMessage']) . ' (' . $response['errorCode'] . ')';
      die('M-PESA Gateway: ' . $response['errorMessage']) . ' (' . $response['errorCode'] . ')';
    }

    return $response;
  }

  /**
   * Generate an access token.
   * 
   * @return string
   */
  private function generateToken()
  {
    $url = $this->host . self::AUTH_URL;
    $credentials = base64_encode($this->consumerKey . ':' . $this->consumerSecret);

    $response = $this->curlRequest($url, 'GET', [
      'headers' => [
        'Authorization: Basic ' . $credentials,
      ],
    ]);

    return $response['access_token'];
  }

  /**
   * Format the phone number to the format 2547XXXXXXXX or 2541XXXXXXXX.
   * 
   * @param string $phone
   * 
   * @return string
   */
  private function formatPhoneNumber($phone)
  {
    // if the phone number starts with 07, 7, 01 or 1, replace it with 2547
    if (preg_match('/^(07|7|01|1)/', $phone)) {
      $phone = '254' . substr($phone, -9);
    }

    return $phone;    
  }

  /**
   * Initiate an STK push.
   * 
   * @param string $phone
   * @param float $amount
   * @param string $currency
   * @param string $invoiceid
   * @param string $callbackurl
   * 
   * @return array
   */
  public function stkPush($phone, $amount, $currency, $invoiceid, $callbackurl)
  {
    $access_token = $this->generateToken();
    $url = $this->host . self::STK_PUSH_URL;
    $timestamp = date('YmdHis');
    $password = base64_encode($this->shortcode . $this->passkey . $timestamp);
    $phone = $this->formatPhoneNumber($phone);
    $transactionType = $this->shortcodeType == 'Paybill' ? 'CustomerPayBillOnline' : 'CustomerBuyGoodsOnline';

    $response = $this->curlRequest($url, 'POST', [
      'headers' => [
        'Authorization: Bearer ' . $access_token,
        'Content-Type: application/json',
      ],
      'data' => [
        'BusinessShortCode' => $this->shortcode,
        'Password' => $password,
        'Timestamp' => $timestamp,
        'TransactionType' => $transactionType,
        'Amount' => floatval($amount),
        'PartyA' => $phone,
        'PartyB' => $this->shortcode,
        'PhoneNumber' => $phone,
        'CallBackURL' => $callbackurl,
        'AccountReference' => $invoiceid,
        'TransactionDesc' => 'Invoice',
      ],
    ]);

    return $response;
  }
}
