<?php

use WHMCS\Database\Capsule;

if (!defined("WHMCS")) {
  die("This file cannot be accessed directly");
}

/**
 * Define module related meta data.
 *
 * Values returned here are used to determine module related capabilities and
 * settings.
 *
 * @see https://developers.whmcs.com/payment-gateways/meta-data-params/
 *
 * @return array
 */
function mpesa_MetaData()
{
  return array(
    'DisplayName' => 'M-Pesa',
    'APIVersion' => '1.1',
    'DisableLocalCreditCardInput' => true,
    'TokenisedStorage' => false,
  );
}

/**
 * Define gateway configuration options.
 *
 * The fields you define here determine the configuration options that are
 * presented to administrator users when activating and configuring your
 * payment gateway module for use.
 *
 * Supported field types include:
 * * text
 * * password
 * * yesno
 * * dropdown
 * * radio
 * * textarea
 *
 * Examples of each field type and their possible configuration parameters are
 * provided in the sample function below.
 *
 * @return array
 */
function mpesa_config()
{
  // Create a table to store transactions if it doesn't exist
  try {
    if (!Capsule::schema()->hasTable('mod_mpesa_transactions')) {
      Capsule::schema()->create(
        'mod_mpesa_transactions',
        function ($table) {
          $table->increments('id');
          $table->integer('shortcode');
          $table->string('invoice_id');
          $table->string('transaction_id')->nullable();
          $table->string('phone_number');
          $table->string('amount');
          $table->string('transaction_timestamp')->nullable();
          $table->string('status');
          $table->string('status_code')->nullable();
          $table->string('status_message')->nullable();
          $table->string('merchant_request_id')->nullable();
          $table->string('checkout_request_id')->nullable();
        }
      );
    }
  } catch (\Exception $e) {
    logActivity('M-PESA Gateway: ' . $e->getMessage());
  }

  return array(
    // the friendly display name for a payment gateway should be
    // defined here for backwards compatibility
    'FriendlyName' => array(
      'Type' => 'System',
      'Value' => 'M-Pesa',
    ),
    'shortcode' => array(
      'FriendlyName' => 'Shortcode',
      'Type' => 'text',
      'Size' => '10',
      'Default' => '',
      'Description' => 'Enter your M-Pesa shortcode',
    ),
    'shortcode_type' => array(
      'FriendlyName' => 'Shortcode Type',
      'Type' => 'radio',
      'Options' => 'Paybill,Till Number',
      'Description' => 'Select the type of shortcode',
    ),
    'consumer_key' => array(
      'FriendlyName' => 'Consumer Key',
      'Type' => 'password',
      'Size' => '50',
      'Default' => '',
      'Description' => 'Enter your M-Pesa consumer key',
    ),
    'consumer_secret' => array(
      'FriendlyName' => 'Consumer Secret',
      'Type' => 'password',
      'Size' => '50',
      'Default' => '',
      'Description' => 'Enter your M-Pesa consumer secret',
    ),
    'passkey' => array(
      'FriendlyName' => 'Passkey',
      'Type' => 'password',
      'Size' => '50',
      'Default' => '',
      'Description' => 'Enter your M-Pesa passkey',
    ),
    'sandbox' => array(
      'FriendlyName' => 'Sandbox Mode',
      'Type' => 'yesno',
      'Description' => 'Tick to enable sandbox mode',
    ),
    'sandbox_shortcode' => array(
      'FriendlyName' => 'Sandbox Shortcode',
      'Type' => 'text',
      'Size' => '10',
      'Default' => '174379',
      'Description' => 'Enter your M-Pesa sandbox shortcode',
    ),
    'sandbox_consumer_key' => array(
      'FriendlyName' => 'Sandbox Consumer Key',
      'Type' => 'password',
      'Size' => '50',
      'Default' => '',
      'Description' => 'Enter your M-Pesa sandbox consumer key',
    ),
    'sandbox_consumer_secret' => array(
      'FriendlyName' => 'Sandbox Consumer Secret',
      'Type' => 'password',
      'Size' => '50',
      'Default' => '',
      'Description' => 'Enter your M-Pesa sandbox consumer secret',
    ),
    'sandbox_passkey' => array(
      'FriendlyName' => 'Sandbox Passkey',
      'Type' => 'password',
      'Size' => '50',
      'Default' => '',
      'Description' => 'Enter your M-Pesa sandbox passkey',
    ),
  );
}

/**
 * Payment link.
 *
 * Required by third party payment gateway modules only.
 *
 * Defines the HTML output displayed on an invoice. Typically consists of an
 * HTML form that will take the user to the payment gateway endpoint.
 *
 * @param array $params Payment Gateway Module Parameters
 *
 * @see https://developers.whmcs.com/payment-gateways/third-party-gateway/
 *
 * @return string
 */
function mpesa_link($params)
{
  # Invoice Variables
  $invoiceid = $params['invoiceid'];
  $amount = ceil($params['amount']);
  $currency = $params['currency'];

  # Client Variables
  $phone = $params['clientdetails']['phonenumber'];

  # System Variables
  $systemurl = $params['systemurl'];
  $paymentmethod = $params['paymentmethod'];
  
  # Gateway Configuration Parameters
  $gatewayParams = array(
    'shortcode' => $params['shortcode'],
    'shortcodeType' => $params['shortcode_type'],
    'consumerKey' => $params['consumer_key'],
    'consumerSecret' => $params['consumer_secret'],
    'passkey' => $params['passkey'],
    'sandbox' => $params['sandbox'],
    'sandboxShortcode' => $params['sandbox_shortcode'],
    'sandboxConsumerKey' => $params['sandbox_consumer_key'],
    'sandboxConsumerSecret' => $params['sandbox_consumer_secret'],
    'sandboxPasskey' => $params['sandbox_passkey'],
  );

  $alert = '';
  $script = '';

  if (isset($_POST['stkpush'])) {
    $phone = formatPhoneNumber($_POST['phone']);
    $callbackurl = $systemurl . '/modules/gateways/callback/' . $paymentmethod . '.php';
    $gateway = new WHMCS\Module\Gateway\Mpesa\MpesaGateway($gatewayParams);
    $response = $gateway->stkPush($phone, $amount, $currency, $invoiceid, $callbackurl);

    if ($response['ResponseCode'] == '0') {
      $loadingurl = $systemurl . '/modules/gateways/' . $paymentmethod . '/loading.gif';

      $alert = <<<HTML
        <div class="alert alert-success small" role="alert">
          Payment request sent successfully. Check your phone for PIN prompt
        </div>

        <div class="text-center">
          <img src="$loadingurl" alt="Loading..." />
        </div>
      HTML;

      $merchantRequestId = $response['MerchantRequestID'];
      $checkoutRequestId = $response['CheckoutRequestID'];

      Capsule::table('mod_mpesa_transactions')->insert([
        'shortcode' => $gateway->shortcode,
        'invoice_id' => $invoiceid,
        'phone_number' => $phone,
        'amount' => $amount,
        'status' => 'Pending',
        'merchant_request_id' => $merchantRequestId,
        'checkout_request_id' => $checkoutRequestId,
      ]);

      $statuslink = $systemurl . '/modules/gateways/' . $paymentmethod . '/status.php?invoiceid=' . $invoiceid . '&merchantRequestId=' . $merchantRequestId . '&checkoutRequestId=' . $checkoutRequestId;

      $script = <<<JS
        <script type="text/javascript">
          const polling = setInterval(function() {            
            const xhr = new XMLHttpRequest();
            xhr.open('GET', '$statuslink', true);
            xhr.send();

            xhr.onreadystatechange = function() {
              if (xhr.readyState === 4 && xhr.status === 200) {
                const response = JSON.parse(xhr.responseText);

                if (response.status === 'Paid') {
                  // Reload page to update invoice status
                  location.reload();
                } else if (response.status === 'Failed') {
                  // Remove any existing alerts
                  document.querySelector('#alertcontainer').innerHTML = '';

                  // Add error message alert
                  const alert = document.createElement('div');
                  alert.className = 'alert alert-danger small';
                  alert.role = 'alert';
                  alert.textContent = response.message;

                  document.querySelector('#alertcontainer').appendChild(alert);

                  // Stop polling
                  clearInterval(polling);
                }
              }
            }
          }, 5000);
        </script>
      JS;

    } else {
      $alert = <<<HTML
        <div class="alert alert-danger small" role="alert">
          Failed to send payment request
        </div>
      HTML;

      logActivity('M-PESA Gateway: ' . $response['ResponseDescription'] . ' (' . $response['ResponseCode']) . ')';
    }
  }

  $htmlOutput = <<<HTML
    <div>
      $script
      
      <form id="mpesaform" class="form-inline mb-3" method="POST">
        <div class="input-group mr-2">
          <div class="input-group-prepend">
            <span id="phone-prefix" class="input-group-text">+254</span>
          </div>

          <input type="tel" name="phone" class="form-control" aria-label="Phone Number" aria-describedby="phone-prefix" value="$phone" required />
        </div>

        <button type="submit" name="stkpush" value="true" class="btn btn-primary px-3">
          Pay
        </button>
      </form>

      <div id="alertcontainer">
        $alert
      </div>
    </div>
  HTML;

  return $htmlOutput;
}

/**
 * Format the phone number to the format 2547XXXXXXXX or 2541XXXXXXXX.
 * 
 * @param string $phone
 * 
 * @return string
 */
function formatPhoneNumber($phone)
{
  // if the phone number starts with 07, 7, 01 or 1, replace it with 2547
  if (preg_match('/^(07|7|01|1)/', $phone)) {
    $phone = '254' . substr($phone, -9);
  }

  return $phone;    
}
