<?php

namespace App\Controllers\api;

use App\Helpers\CommonHelper;
use App\Libraries\PayU;
use App\Libraries\PhonePeLibrary;
use App\Libraries\PushNotify;
use App\Models\Booking;
use App\Models\Provider;
use App\Models\Quotation;
use App\Models\User;
use App\Models\Wallet;
use CodeIgniter\Controller;
use DateTime;

class PaymentController extends Controller
{
  protected $user;
  protected $booking;
  protected $quotations;
  protected $wallet;
  protected $provider;
  protected $pushNotify;

  public function __construct()
  {
    $this->user       = new User();
    $this->booking    = new Booking();
    $this->quotations = new Quotation();
    $this->wallet     = new Wallet();
    $this->provider   = new Provider();
    $this->pushNotify = new PushNotify();
  }

  /**
   * payment initiate in phonepe
   *
   * @return void
   */

  public function initiatePayment()
  {
    $rules = [
      "user_id"    => 'required',
      'booking_id' => 'required',
      'amount'     => 'required',
    ];

    if (!$this->validate($rules)) {
      return $this->response->setJSON(['status' => false, 'errors' => $this->validator->getErrors()]);
    }

    $userId      = $this->request->getJsonVar('user_id');
    $booking_id  = $this->request->getJsonVar('booking_id');
    $amount      = $this->request->getJsonVar('amount');
    $user        = $this->user->getUser($userId);
    $orderId     = uniqid('ORD_');
    $callbackUrl = base_url('api/payment/callback');

    $paymentData = [
      'merchantId'     => getenv('PHONEPEMERCHANTID'),
      'merchantUserId' => 'PSF-UR-' . $userId,
      'mobileNumber'   => $user->phone ?? '',
      'transactionId'  => $orderId,
      'amount'         => $amount,
      'callbackUrl'    => $callbackUrl,
    ];

    // Store transaction with all required details
    $this->wallet->createTransaction([
      "user_id"        => $userId,
      'booking_id'     => $booking_id,
      'transaction_id' => $orderId,
      'amount'         => $amount,
      'created_at'     => gmdate('Y-m-d H:i:s'),
    ]);
    return $this->response->setStatusCode(200)
      ->setJSON(['status' => 200, 'data' => $paymentData]);
  }


  /**
   * payment initiation call back
   * @return mixed
   */
  public function callBack()
  {
    $response = file_get_contents('php://input');
    $xVerify  = $this->request->getHeaderLine('X-VERIFY');
    if (!empty($response) && !empty($xVerify)) {
      $responseData = json_decode($response);
      $response     = $responseData->response;

      $phonepeClient = PhonePeLibrary::phonePeClient();
      $isValid       = $phonepeClient->verifyCallback($response, $xVerify);

      if ($isValid) {
        $decodedResponse = base64_decode($response);
        $responseObject  = json_decode($decodedResponse);
        // error_log('JSON Decoded Response: ' . print_r($responseObject->data, true));

        if ($responseObject->code == 'PAYMENT_SUCCESS' && $responseObject->data->state == 'COMPLETED') {
          $transactionDetails = $this->wallet->getTransactionByTransactionId($responseObject->data->merchantTransactionId);
          // error_log('transactionDetails Response: ' . print_r($transactionDetails, true));

          if ($transactionDetails) {
            $this->wallet->updateTransaction([
              'transaction_id'   => $responseObject->data->merchantTransactionId,
              'amount'           => $responseObject->data->amount,
              'status'           => $responseObject->data->state,
              'payment_mode'     => $responseObject->data->paymentInstrument->type,
              'response_code'    => $responseObject->data->responseCode,
              'transaction_date' => gmdate('Y-m-d H:i:s'),
            ]);
            return $this->response->setJSON(['status' => true]);
          } else {
            return $this->response->setJSON(['status' => false, 'message' => 'Transaction not found']);
          }
        } else if ($responseObject->code == 'PAYMENT_ERROR') {
          return $this->response->setJSON(['status' => false, 'response' => $responseObject]);
        }
      }
    }

    return $this->response->setJSON(['status' => false, 'response' => $response]);
  }


  public function phonePecallBack()
  {
    // Get raw POST data
    $response = file_get_contents('php://input');
    $xVerify  = $this->request->getHeaderLine('X-VERIFY');

    // Log raw response and verification header
    // log_message('info', 'Raw Response: ' . print_r($response, true));
    // log_message('info', 'X-VERIFY Header: ' . $xVerify);

    // Check if the response and verification header are present
    if (empty($response) || empty($xVerify)) {
      // log_message('error', 'Missing response or X-VERIFY header');
      return $this->response->setJSON(['status' => false, 'message' => 'Invalid callback payload']);
    }

    // Decode the JSON response
    $decodedResponse = json_decode($response);
    if (!$decodedResponse) {
      // log_message('error', 'Failed to decode JSON response');
      return $this->response->setJSON(['status' => false, 'message' => 'Invalid JSON format']);
    }

    // Extract the response data from the decoded response
    $responseData = $decodedResponse->response;
    if (!$responseData) {
      // log_message('error', 'Missing response data in the callback');
      return $this->response->setJSON(['status' => false, 'message' => 'Missing response data']);
    }

    // Verify the callback signature
    $phonepeClient = PhonePeLibrary::phonePeClient();
    $isValid       = $phonepeClient->verifyCallback($responseData, $xVerify);
    // log_message('info', 'Callback signature verification result: ' . ($isValid ? 'valid' : 'invalid'));

    // If verification fails, log and return error response
    if (!$isValid) {
      // log_message('error', 'Callback signature verification failed');
      return $this->response->setJSON(['status' => false, 'message' => 'Invalid signature']);
    }

    // Decode the base64-encoded response data
    $decodedResponseData = base64_decode($responseData);
    $responseObject = json_decode($decodedResponseData);
    if (!$responseObject) {
      // log_message('error', 'Failed to decode base64 response data');
      return $this->response->setJSON(['status' => false, 'message' => 'Failed to decode base64 response']);
    }

    // Log decoded response object for debugging
    // log_message('info', 'Decoded Response Object: ' . print_r($responseObject->data, true));

    // Process the response based on the payment status
    if ($responseObject->code == 'PAYMENT_SUCCESS' && $responseObject->data->state == 'COMPLETED') {
      // Successful payment, update the transaction
      $transactionDetails = $this->wallet->getTransactionByTransactionId($responseObject->data->merchantTransactionId);
      // log_message('info', 'Transaction Details: ' . print_r($transactionDetails, true));

      if ($transactionDetails) {
        $this->wallet->updateTransaction([
          'transaction_id'   => $responseObject->data->merchantTransactionId,
          'amount'           => $responseObject->data->amount,
          'status'           => $responseObject->data->state,
          'payment_mode'     => $responseObject->data->paymentInstrument->type,
          'response_code'    => $responseObject->data->responseCode,
          'transaction_date' => gmdate('Y-m-d H:i:s'),
        ]);
        return $this->response->setJSON(['status' => true, 'message' => 'Payment successfully processed']);
      } else {
        // log_message('error', 'Transaction not found');
        return $this->response->setJSON(['status' => false, 'message' => 'Transaction not found']);
      }
    } elseif ($responseObject->code == 'PAYMENT_ERROR') {
      // Payment failed, update transaction status as failed
      $transactionDetails = $this->wallet->getTransactionByTransactionId($responseObject->data->merchantTransactionId);

      if ($transactionDetails) {
        $this->wallet->updateTransaction([
          'transaction_id'   => $responseObject->data->merchantTransactionId,
          'amount'           => $responseObject->data->amount,
          'status'           => $responseObject->data->state,
          'payment_mode'     => $responseObject->data->paymentInstrument->type ?? '',
          'response_code'    => $responseObject->data->responseCode,
          'transaction_date' => gmdate('Y-m-d H:i:s'),
        ]);
      }
      return $this->response->setJSON(['status' => false, 'message' => 'Payment failed']);
    } else {
      // Handle unknown payment code, log and return error
      // log_message('warning', 'Unhandled Payment Status: ' . $responseObject->code);
      return $this->response->setJSON(['status' => false, 'message' => 'Unhandled payment status: ' . $responseObject->code]);
    }
  }


  public function checkPaymentStatus($transactionId)
  {
    $status = $this->wallet->getPaymentStatus($transactionId);
    return $this->response->setJSON($status);
  }

  /**
   * payment withdrawal in PayU
   */
  public function initiateTransfer()
  {

    $rules = [
      "provider_id"    => 'required',
      'amount'     => 'required',
    ];

    if (!$this->validate($rules)) {
      return $this->response->setJSON(['status' => false, 'errors' => $this->validator->getErrors()]);
    }

    $provider_id      = $this->request->getJsonVar('provider_id');
    $amount      = $this->request->getJsonVar('amount');
    $provider_bank        = $this->provider->getProviderBank($provider_id);
    $provider_bank->amount = $amount;
    $payU = new PayU();
    $response = $payU->initiateTransfer((array)$provider_bank);
    // log_message('info', 'payu response', json_decode($response));
    // die;
    var_dump($response); die;
    if ($response->status == 0) {
      // $this->wallet->updateTransaction([
      //   'transaction_id'   => $response->data->merchantTransactionId,
      //   'amount'           => $response->data->amount,
      //   'status'           => $response->data->state,
      //   'payment_mode'     => $response->data->paymentInstrument->type ?? '',
      //   'response_code'    => $response->data->responseCode,
      //   'transaction_date' => gmdate('Y-m-d H:i:s'),
      // ]);
      return $this->response->setJSON(['status' => true, 'message' => '
        Transfer initiated successfully']);
    } else {
      // Handle failed transfer, log and return error
      return $this->response->setJSON(['status' => false, 'message' => '
          Transfer failed']);
    }
  }



  /**
   * payment withdrawl in razorpay
   * @return mixed
   */
  public function withdrawAmount()
  {
    $rules = [
      'amount'      => 'required|numeric|greater_than_equal_to[0]|less_than_equal_to[1000000]',
      // 'bank_id'     => 'required',
      'provider_id' => 'required',
    ];

    if (!$this->validate($rules)) {
      return $this->response->setJSON(['status' => false, 'errors' => $this->validator->getErrors()]);
    }

    $amount = $this->request->getJsonVar('amount');
    // $bankId     = $this->request->getJsonVar('bank_id');
    $providerId = $this->request->getJsonVar('provider_id');

    $total = $this->wallet->getTotalWithdrawalAmount($providerId);

    if (!empty($total)) {
      $spDetails = $this->provider->getProvider($providerId);
      // $spBank    = $this->provider->getUserBankAccount($providerId, $bankId);

      if (floatval($amount) > floatval($total->amount)) {
        return $this->response
          ->setJSON(['status' => false, 'message' => 'Invalid amount']);
      }
      $this->provider->updateWithdrawlRequest($providerId);
      // if (!empty($spDetails)) {
      //   if (!empty($spBank->upi_id)) {
      //     $fundAccountId = $this->createRazorpayFundAccount($spBank);
      //     $response      = $this->initiateRazorpayPayout($fundAccountId, $amount, "UPI");
      //   } elseif (!empty($spBank->account_number)) {
      //     $fundAccountId = $this->createRazorpayFundAccount($spBank);
      //     $response      = $this->initiateRazorpayPayout($fundAccountId, $amount, "IMPS");
      //   } else {
      //     return $this->response->setJSON(['status' => false, 'message' => 'Invalid withdrawal details.']);
      //   }
      // }

      // $statusCode = $response->getStatusCode();
      // if ($response && ($statusCode == 200 || $statusCode == 201)) {
      //   $this->wallet->debitProviderWallet($providerId, $amount);
      //   return $this->response->setJSON(['status' => true, 'message' => 'Withdrawal successful.']);
      // } else {
      //   return $this->response->setJSON(['status' => false, 'message' => 'Payout failed: ' . $response->error->description]);
      // }
      return $this->response->setJSON(['status' => true, 'message' => 'Withdrawal successful.']);
    } else {
      return $this->response->setJSON(['status' => false, 'message' => 'Failed']);
    }
  }

  private function createRazorpayFundAccount($userBankOrUpi)
  {
    $curl = \Config\Services::curlrequest();

    $razorpayKeyId     = getenv('RAZORPAY_KEY_ID');
    $razorpayKeySecret = getenv('RAZORPAY_KEY_SECRET');

    $fundAccountData = [
      "contact_id"   => "cont_PBguRi0kW7Tugv",
      "account_type" => isset($userBankOrUpi->upi_id) ? "vpa" : "bank_account", // Check if it's UPI or Bank
    ];

    if (isset($userBankOrUpi->upi_id)) {
      $fundAccountData["vpa"] = [
        "address" => $userBankOrUpi->upi_id,
      ];
    } else {
      $fundAccountData["bank_account"] = [
        "name"           => $userBankOrUpi->account_holder_name,
        "ifsc"           => $userBankOrUpi->ifsc_code,
        "account_number" => $userBankOrUpi->account_number,
      ];
    }

    try {
      $response = $curl->request('POST', 'https://api.razorpay.com/v1/fund_accounts', [
        'auth'    => [$razorpayKeyId, $razorpayKeySecret],
        'headers' => [
          'Content-Type' => 'application/json',
        ],
        'json'    => $fundAccountData,
      ]);

      if ($response->getStatusCode() === 200) {
        $result = json_decode($response->getBody());
        return $result->id;
      } else {
        return false;
      }
    } catch (\Exception $e) {
      return false;
    }
  }


  private function initiateRazorpayPayout($fundAccountId, $amount, $mode = "IMPS")
  {
    $curl              = \Config\Services::curlrequest();
    $razorpayKeyId     = getenv('RAZORPAY_KEY_ID');
    $razorpayKeySecret = getenv('RAZORPAY_KEY_SECRET');

    if (intval($amount) <= 0) {
      return false;
    }

    $payoutData = [
      "account_number"       => "2323230099936812",
      "fund_account_id"      => $fundAccountId,
      "amount"               => intval($amount * 100), // Amount in paise
      "currency"             => "INR",
      "mode"                 => $mode, // IMPS, UPI, etc.
      "purpose"              => "payout",
      "queue_if_low_balance" => true,
      "reference_id"         => uniqid(),
      "narration"            => "Withdrawal to provider",
      "notes"                => [],
    ];

    try {
      $response = $curl->request('POST', 'https://api.razorpay.com/v1/payouts', [
        'auth'    => [$razorpayKeyId, $razorpayKeySecret],
        'headers' => [
          'Content-Type' => 'application/json',
        ],
        'json'    => $payoutData,
      ]);

      if ($response->getStatusCode() === 200) {
        return $response;
      } else {
        return false;
      }
    } catch (\Exception $e) {
      return false;
    }
  }




  public function processWithdrawal()
  {
    $payU = new PayU();

    $params = [
      'txnid' => uniqid('withdraw_'),
      'amount' => $this->request->getPost('amount'),
      'productinfo' => 'Withdrawal',
      'firstname' => $this->request->getPost('firstname'),
      'lastname' => $this->request->getPost('lastname'),
      'zipcode' => $this->request->getPost('zipcode'),
      'email' => $this->request->getPost('email'),
      'phone' => $this->request->getPost('phone'),
      'address1' => $this->request->getPost('address1'),
      'city' => $this->request->getPost('city'),
      'state' => $this->request->getPost('state'),
      'country' => $this->request->getPost('country'),
      'hash' => $this->getHashKey($payU, $this->request->getPost()),
    ];

    $payload = json_encode($params);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $payU->baseUrl . '/payout-api-endpoint');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
      'Authorization: ' . $payU->authHeader,
      'Content-Type: application/json',
    ]);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
      return $this->respond(['error' => curl_error($ch)], 500);
    }

    curl_close($ch);

    return $this->respond(json_decode($response, true));
  }

  private function getHashKey($payU, $params)
  {
    return hash('sha512', $payU->key . '|' . $params['txnid'] . '|' . $params['amount'] . '|' . $params['productinfo'] . '|' . $params['firstname'] . '|' . $params['email'] . '|' . '||||||' . $payU->merchantSalt);
  }
}
