<?php

// Require libraries needed for gateway module functions.
require_once __DIR__ . '/../../../init.php';

use WHMCS\Database\Capsule;

if (isset($_GET['invoiceid'])) {
  $invoiceid = $_GET['invoiceid'];
  $merchantRequestId = $_GET['merchantRequestId'];
  $checkoutRequestId = $_GET['checkoutRequestId'];

  $response = localAPI('GetInvoice', array('invoiceid' => $invoiceid));

  if ($response['result'] == 'success') {
    if ($response['status'] == 'Paid') {
      echo json_encode(['status' => 'Paid']);
    } else {
      // Check mpesa transaction status
      $transaction = Capsule::table('mod_mpesa_transactions')
        ->where('invoice_id', $invoiceid)
        ->where('merchant_request_id', $merchantRequestId)
        ->where('checkout_request_id', $checkoutRequestId)
        ->first();

      if ($transaction && $transaction->status == 'Failed') {
        echo json_encode([
          'status' => 'Failed',
          'message' => $transaction->status_message,
        ]);
      }
    }
  }
}
