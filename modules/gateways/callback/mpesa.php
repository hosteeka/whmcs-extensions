<?php

/**
 * WHMCS M-PESA Payment Callback File
 *
 * For more information, please refer to the online documentation.
 *
 * @see https://developers.whmcs.com/payment-gateways/callbacks/
 */

// Require libraries needed for gateway module functions.
require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';

use WHMCS\Database\Capsule;

// Detect module name from filename.
$gatewayModuleName = basename(__FILE__, '.php');

// Fetch gateway configuration parameters.
$gatewayParams = getGatewayVariables($gatewayModuleName);

// Die if module is not active.
if (!$gatewayParams['type']) {
  die("Module Not Activated");
}

// Retrieve data returned in payment gateway callback
$response = file_get_contents('php://input');
$data = json_decode($response);

if (isset($data->Body->stkCallback)) {
  // stk push callback
  $merchantRequestId = $data->Body->stkCallback->MerchantRequestID;
  $checkoutRequestId = $data->Body->stkCallback->CheckoutRequestID;

  if ($data->Body->stkCallback->ResultCode == 0) {
    $transaction = Capsule::table('mod_mpesa_transactions')
      ->where('merchant_request_id', $merchantRequestId)
      ->where('checkout_request_id', $checkoutRequestId)
      ->where('status', 'Pending')
      ->first();

    if ($transaction) {
      $transactionId = $transaction->id;
      $invoiceId = $transaction->invoice_id;
      $phone = $transaction->phone_number;
      $amount = $transaction->amount;
      $timestamp = $transaction->transaction_timestamp;

      foreach ($data->Body->stkCallback->CallbackMetadata->Item as $item) {
        if ($item->Name == 'Amount') {
          $amount = $item->Value;
        } elseif ($item->Name == 'MpesaReceiptNumber') {
          $transactionId = $item->Value;
        } elseif ($item->Name == 'PhoneNumber') {
          $phone = $item->Value;
        } elseif ($item->Name == 'TransactionDate') {
          $timestamp = $item->Value;
        }
      }

      // peform a check for any existing transactions
      checkCbTransID($transactionId);

      // add invoice payment
      $success = addInvoicePayment(
        $invoiceId,
        $transactionId,
        $amount,
        0,
        $gatewayModuleName
      );

      // update transaction status
      if ($success) {
        Capsule::table('mod_mpesa_transactions')
          ->where('merchant_request_id', $merchantRequestId)
          ->where('checkout_request_id', $checkoutRequestId)
          ->update([
            'transaction_id' => $transactionId,
            'phone_number' => $phone,
            'amount' => $amount,
            'transaction_timestamp' => $timestamp,
            'status' => 'Completed',
          ]);
      }
    }
  } else {
    // update transaction status to failed
    Capsule::table('mod_mpesa_transactions')
      ->where('merchant_request_id', $merchantRequestId)
      ->where('checkout_request_id', $checkoutRequestId)
      ->update([
        'status' => 'Failed',
        'status_code' => $data->Body->stkCallback->ResultCode,
        'status_message' => $data->Body->stkCallback->ResultDesc,
      ]);
  }
} else {
  // c2b callback
}
