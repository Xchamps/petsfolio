<?php

namespace App\Controllers\api;

use App\Helpers\CommonHelper;
use App\Libraries\PhonePeLibrary;
use App\Libraries\PushNotify;
use App\Models\Booking;
use App\Models\Provider;
use App\Models\Quotation;
use App\Models\User;
use App\Models\Wallet;
use CodeIgniter\Controller;
use DateInterval;
use DatePeriod;
use DateTime;

class BookingController extends Controller
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

  public function createBooking($serviceType)
  {
    $rules = [
      'user_id'            => 'required|numeric',
      'address_id'         => 'required|numeric',
      'pet_id'             => 'required',
      'package_id'         => 'required',
      'service_start_date' => 'required|valid_date[Y-m-d]',
      'preferable_time'    => 'required',
    ];

    // Add service-specific rules
    if ($serviceType === 'walking') {
      $rules = array_merge($rules, [
        'service_frequency' => 'required|in_list[once a day, twice a day, thrice a day]',
        'walk_duration'     => 'required|in_list[30 min walk, 60 min walk]',
        'service_days'      => 'required|in_list[weekdays, all days]',
        'service_id'        => 'required|in_list[4]',
      ]);
    } elseif ($serviceType === 'boarding') {
      $rules['service_id'] = 'required|in_list[1]';
    } elseif ($serviceType === 'grooming') {
      $rules['service_id'] = 'required|in_list[2]';
    } elseif ($serviceType === 'training') {
      $rules['service_id'] = 'required|in_list[3]';
    }

    if (!$this->validate($rules)) {
      return $this->response->setJSON(['status' => false, 'errors' => $this->validator->getErrors()]);
    }

    $json = $this->request->getJSON();
    if (!is_array($json->pet_id) || count($json->pet_id) == 0) {
      return $this->response->setJSON(['status' => false, 'message' => 'Invalid or no pet IDs selected']);
    }

    $uniqueId  = substr(bin2hex(random_bytes(3)), 0, 5);
    $bookingId = CommonHelper::generateID($uniqueId, $serviceType);

    $bookingData = [
      'id'                 => $bookingId,
      'user_id'            => $json->user_id,
      'address_id'         => $json->address_id,
      'preferable_time'    => implode(',', $json->preferable_time),
      'service_start_date' => $json->service_start_date,
      'status'             => 'New',
      'created_at'         => gmdate('Y-m-d H:i:s'),
    ];

    if ($serviceType === 'walking') {

      $totalDays   = $this->booking->getPackageDetails($json->package_id)->totalDays;
      $endDate     = $this->calculateEndDate($json->service_start_date, $totalDays, $json->service_days);
      $bookingData = array_merge($bookingData, [
        'package_id'        => $json->package_id,
        'service_id'        => $json->service_id,
        'service_frequency' => $json->service_frequency,
        'walk_duration'     => $json->walk_duration,
        'service_days'      => $json->service_days,
        'service_end_date'  => $endDate,
        'total_price'       => $json->total_price,
        'type'              => 'normal',
        'addons'            => count($json->addons) ?? 0,
      ]);
    } elseif ($serviceType === 'training') {
      $bookingData['service_id'] = $json->service_id;
      $bookingData['package_id'] = $json->package_id;
    } elseif ($serviceType === 'grooming') {

      $bookingData = array_merge($bookingData, [
        'service_id'  => $json->service_id,
        'total_price' => $json->total_price,
      ]);
    } else if ($serviceType === 'boarding') {
      $bookingData['service_id']       = $json->service_id;
      $bookingData['service_end_date'] = $json->service_end_date;
      $bookingData['package_id']       = $json->package_id;
    }

    // Check for existing bookings for each pet
    $existingPetIds = [];
    foreach ($json->pet_id as $pet_id) {
      $bookingMethod = 'check' . ucfirst($serviceType) . 'BookingExists';
      if ($this->booking->{$bookingMethod}($json->user_id, $pet_id, $json->service_id)) {
        $existingBookings = $this->booking->{$bookingMethod}($json->user_id, $pet_id, $json->service_id);
        if ($existingBookings) {
          $existingPetIds[] = $pet_id;
        }
      }
    }

    if (!empty($existingPetIds)) {
      return $this->response->setJSON(['status' => false, 'message' => 'Booking already exists for pet IDs: ' . implode(', ', $existingPetIds)]);
    }

    // Create the booking
    $bookingMethod = 'create' . ucfirst($serviceType) . 'Booking';
    if (!$this->booking->{$bookingMethod}((object) $bookingData)) {
      return $this->response->setJSON(['status' => false, 'message' => 'Booking failed']);
    }

    // Save pet and addon details
    foreach ($json->pet_id as $pet_id) {
      $this->booking->createBookingPets([
        'user_id'    => $json->user_id,
        'booking_id' => $bookingId,
        'pet_id'     => $pet_id,
        'service_id' => $json->service_id,
        'created_at' => gmdate('Y-m-d H:i:s'),
      ]);
    }
    if (isset($json->addons) && is_array($json->addons)) {
      foreach ($json->addons as $addon) {
        $this->booking->createBookingAddons([
          'user_id'    => $json->user_id,
          'booking_id' => $bookingId,
          'service_id' => $json->service_id,
          'addon'      => $addon,
          'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
      }
    }
    if ($serviceType === 'grooming') {
      $bookingData['service_id'] = $json->service_id;
      foreach ($json->package_id as $packageId) {
        $this->booking->addPackages($bookingId, $packageId);
      }
    }
    // Notify providers
    $providers         = $this->provider->getProviders();
    $notifiedProviders = [];

    foreach ($providers as $provider) {

      if ($provider->device_token && !in_array($provider->id, $notifiedProviders)) {
        $user     = $this->user->getUserAddress($json->user_id, $json->address_id);
        $distance = CommonHelper::distanceCalculator($user->latitude, $user->longitude, $provider->service_latitude, $provider->service_longitude);

        if ($distance <= 30) {
          $token   = $provider->device_token;
          $title   = 'New job alert - ' . ucfirst($serviceType);
          $message = "Don’t miss out—submit your quote now!";

          $this->booking->createNotification([
            'user_id'   => $provider->id,
            'user_type' => 'provider',
            'type'      => 'booking_requested',
            'message'   => $message,
          ]);

          $PushNotify = new PushNotify();
          $PushNotify->notify($token, $title, $message);
          $notifiedProviders[] = $provider->id;
        }
      }

      if (!empty($notifiedProviders)) {
        break;
      }
    }

    return $this->response->setJSON(['status' => true, 'message' => 'Service booked successfully for all selected pets']);
  }

  //create booking view
  public function show()
  {
    $rules = [
      'user_id'    => 'required|numeric',
      'booking_id' => 'required',
      'service_id' => 'required|numeric',

    ];
    if (!$this->validate($rules)) {
      return $this->response->setJSON(['status' => false, 'errors' => $this->validator->getErrors()]);
    }
    $json       = $this->request->getJSON();
    $user_id    = $json->user_id;
    $booking_id = $json->booking_id;
    $service_id = $json->service_id;
    if ($service_id == '1') {
      // Get the Boarding booking details
    } else if ($service_id == '2') {
      // Get the Grooming booking details

    } else if ($service_id == '3') {
      // Get the Training booking details

    } else if ($service_id == '4') {
      // Get the walking booking details
      $data = $this->booking->getWalkingBooking($booking_id, $user_id);
    } else {
      return $this->response->setJSON(['status' => false, 'message' => 'Invalid Service']);
    }
    if (!$data) {
      return $this->response->setJSON(['status' => false, 'message' => 'Data not found']);
    }
    return $this->response->setJSON(['status' => true, 'data' => $data]);
  }

  //update walking booking timings
  public function updateTimings($serviceType)
  {
    $rules = [
      'user_id'         => 'required|numeric',
      'booking_id'      => 'required',
      'preferable_time' => 'required',
    ];
    if (!$this->validate($rules)) {
      return $this->response->setJSON(['status' => false, 'errors' => $this->validator->getErrors()]);
    }
    $json            = $this->request->getJSON();
    $user_id         = $json->user_id;
    $booking_id      = $json->booking_id;
    $preferable_time = implode(',', array: $json->preferable_time);
    $updateMethod    = 'update' . ucfirst(string: $serviceType) . 'BookingTimings';
    $result          = $this->booking->{$updateMethod}($booking_id, $user_id, $preferable_time);
    if ($result) {
      // $user = $this->user->getUser($user_id);
      $pet      = $this->booking->getPet($booking_id);
      $provider = $this->booking->getProviderByBooking($booking_id);

      $token   = $provider->device_token;
      $title   = 'Service Update for ' . $pet->name;
      $message = "The client has changed the service time for " . $pet->name;

      $this->booking->createNotification([
        'user_id'   => $provider->id,
        'user_type' => 'provider',
        'type'      => 'booking_updated',
        'message'   => $message,
      ]);

      $PushNotify = new PushNotify();
      $PushNotify->notify($token, $title, $message);
      return $this->response->setStatusCode(200)
        ->setJSON(['status' => true, 'message' => 'Updated Successfully...']);
    } else {
      return $this->response->setStatusCode(500)
        ->setJSON(['status' => false, 'message' => 'Failed to update timings']);
    }
  }

  //update walking booking address
  public function updateAddress($serviceType)
  {
    $rules = [
      'user_id'    => 'required|numeric',
      'booking_id' => 'required',
      'address_id' => 'required|numeric',
    ];
    if (!$this->validate($rules)) {
      return $this->response->setJSON(['status' => false, 'errors' => $this->validator->getErrors()]);
    }
    $json       = $this->request->getJSON();
    $user_id    = $json->user_id;
    $booking_id = $json->booking_id;
    $address_id = $json->address_id;

    $updateMethod = 'update' . ucfirst(string: $serviceType) . 'BookingAddress';
    $result       = $this->booking->{$updateMethod}($booking_id, $user_id, $address_id);
    if ($result) {

      $pet      = $this->booking->getPet($booking_id);
      $provider = $this->booking->getProviderByBooking($booking_id);

      $token   = $provider->device_token;
      $title   = 'Service Update for ' . $pet->name;
      $message = "The client has changed the service address for " . $pet->name;

      $this->booking->createNotification([
        'user_id'   => $provider->id,
        'user_type' => 'provider',
        'type'      => 'booking_updated',
        'message'   => $message,
      ]);

      $PushNotify = new PushNotify();
      $PushNotify->notify($token, $title, $message);

      return $this->response->setStatusCode(200)
        ->setJSON(['status' => true, 'message' => 'Updated Successfully...']);
    } else {
      return $this->response->setStatusCode(500)
        ->setJSON(['status' => false, 'message' => 'Failed to update timings']);
    }
  }

  //delete booking
  public function delete($serviceType)
  {
    $rules = [
      'user_id'    => 'required|numeric',
      'booking_id' => 'required',
      'service_id' => 'required|numeric',
    ];
    if (!$this->validate($rules)) {
      return $this->response->setJSON(['status' => false, 'errors' => $this->validator->getErrors()]);
    }
    $json       = $this->request->getJSON();
    $user_id    = $json->user_id;
    $booking_id = $json->booking_id;
    $service_id = $json->service_id;
    $check      = $this->booking->checkBookingConfirmed($json->booking_id, $json->service_id, $json->user_id);
    if ($check) {
      return $this->response->setJSON(['status' => false, 'message' => 'Not allowed to delete confirmed booking']);
    }
    $deleteMethod = 'delete' . ucfirst($serviceType) . 'Booking';
    $this->booking->{$deleteMethod}($booking_id, $user_id);
    $this->booking->deletePets($booking_id, $user_id, $service_id);

    $this->quotations->deleteQuotations($booking_id, $user_id, $service_id);

    return $this->response->setJSON(['status' => true, 'message' => 'Data deleted successfully']);
  }

  //booking Summary
  public function bookingSummary()
  {
    $rules = [
      'user_id'      => 'required|numeric',
      'booking_id'   => 'required',
      'service_id'   => 'required',
      'provider_id'  => 'required',
      'quotation_id' => 'required',
    ];

    if (!$this->validate($rules)) {
      return $this->response->setJSON(['status' => false, 'errors' => $this->validator->getErrors()]);
    }

    $json        = $this->request->getJSON();
    $serviceId   = $json->service_id;
    $userId      = $json->user_id;
    $bookingId   = $json->booking_id;
    $providerId  = $json->provider_id;
    $quotationId = $json->quotation_id;

    $bookingData = $this->getBookingData($serviceId, $bookingId, $userId);

    $bookingData->refundAmount = $this->booking->getRefundAmount($userId);
    if (!$bookingData) {
      return $this->response->setJSON(['status' => false, 'message' => 'Invalid Service']);
    }

    $this->formatPetImages($bookingData);

    $quotationData = $this->quotations->getQuotationsByProvider($bookingId, $serviceId, $providerId, $quotationId);

    if ($serviceId == '4') {
      $this->calculatePackagePrice($bookingData);
    }
    $user                    = $this->getUserAddress($userId, $bookingData->address_id);
    $distance                = $this->calculateDistance($user, $quotationData);
    $quotationData->distance = number_format($distance, 1);

    $this->formatRating($quotationData);
    $this->formatProfile($quotationData);

    $combinedData = array_merge((array) $bookingData, (array) $quotationData);

    if ($combinedData) {
      return $this->response->setJSON(['status' => true, 'data' => $combinedData]);
    } else {
      return $this->response->setJSON(['status' => false, 'message' => 'Not Found']);
    }
  }

  private function getBookingData($serviceId, $bookingId, $userId)
  {
    switch ($serviceId) {
      case '1':
        return $this->booking->getBoardingBooking($bookingId, $userId);
      case '2':
        return $this->booking->getGroomingBooking($bookingId, $userId);
      case '3':
        return $this->booking->getTrainingBooking($bookingId, $userId);
      case '4':
        return $this->booking->getWalkingBooking($bookingId, $userId);
      default:
        return null;
    }
  }

  private function formatPetImages(&$bookingData)
  {
    foreach ($bookingData->pets as &$pet) {
      $bookingData->serviceAmount = $this->booking->getServiceWallet($pet->pet_id);
      if ($pet->image) {
        $pet->image = base_url() . 'public/uploads/pets/' . $pet->image;
      }
    }
  }

  private function getQuotationData($bookingId, $serviceId, $providerId, $quotationId)
  {
    return $this->quotations->getQuotationsByProviderId($bookingId, $serviceId, $providerId, $quotationId);
  }

  private function calculatePackagePrice(&$bookingData)
  {
    if ($bookingData->days) {
      $bookingData->package_price = $bookingData->package_price * $bookingData->days;
    } else {
      $bookingData->package_price = $bookingData->package_price;
    }
  }

  private function getUserAddress($userId, $addressId)
  {
    return $this->user->getUserAddress($userId, $addressId);
  }

  private function calculateDistance($user, $quotationData)
  {
    $latFrom = $user->latitude;
    $lonFrom = $user->longitude;
    $latTo   = $quotationData->service_latitude;
    $lonTo   = $quotationData->service_longitude;

    return CommonHelper::distanceCalculator($latFrom, $lonFrom, $latTo, $lonTo);
  }

  private function formatRating(&$quotationData)
  {
    if ($quotationData->total_count > 0) {
      $rating                = CommonHelper::ratingCalculator($quotationData->rating_sum, $quotationData->total_count);
      $quotationData->rating = number_format($rating, 1) . ' (' . $quotationData->total_count . ')';
    }
  }

  private function formatProfile(&$quotationData)
  {
    if ($quotationData->profile) {
      $quotationData->profile = base_url() . 'public/uploads/providers/' . $quotationData->profile;
    }
    unset($quotationData->service_longitude);
    unset($quotationData->service_latitude);
    unset($quotationData->rating_sum);
    unset($quotationData->total_count);
  }

  // payment initiation
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

  // payment initiation call back
  public function callBack()
  {
    $response = file_get_contents('php://input');
    $xVerify  = $this->request->getHeaderLine('X-VERIFY');

    error_log('JSON Decoded Response: ' . print_r($response, true));
    error_log('xVerify Response: ' . print_r($xVerify, true));

    if (!empty($response) && !empty($xVerify)) {
      $responseData = json_decode($response);
      error_log('JSON Decoded Response: ' . print_r($responseData, true));

      $response = $responseData->response;
      if ($response) {
        $phonepeClient = PhonePeLibrary::phonePeClient();
        $isValid       = $phonepeClient->verifyCallback($response, $xVerify);
        error_log('xVerify Response: ' . print_r($xVerify, true));
        error_log('isValid Response: ' . print_r($isValid, true));

        if ($isValid) {
          $decodedResponse = base64_decode($response);
          $responseObject  = json_decode($decodedResponse);
          error_log('JSON Decoded Response: ' . print_r($responseObject->data, true));

          if ($responseObject->code == 'PAYMENT_SUCCESS' && $responseObject->data->state == 'COMPLETED') {
            $transactionDetails = $this->wallet->getTransactionByTransactionId($responseObject->data->merchantTransactionId);
            error_log('transactionDetails Response: ' . print_r($transactionDetails, true));

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
            }
            return $this->response->setJSON(['status' => false, 'response' => $responseObject]);
          }
        } else {
          $decodedResponse    = base64_decode($response);
          $responseObject     = json_decode($decodedResponse);
          $transactionDetails = $this->wallet->getTransactionByTransactionId($responseObject->data->merchantTransactionId);
          error_log('transactionDetails Response: ' . print_r($transactionDetails, true));

          if ($transactionDetails) {
            $this->wallet->updateTransaction([
              'transaction_id'   => $responseObject->data->merchantTransactionId,
              'amount'           => $responseObject->data->amount,
              'status'           => $responseObject->data->state,
              'payment_mode'     => $responseObject->data->paymentInstrument->type,
              'response_code'    => $responseObject->data->responseCode,
              'transaction_date' => gmdate('Y-m-d H:i:s'),
            ]);
          }
        }
      }
    }

    return $this->response->setJSON(['status' => false, 'response' => $response]);
  }

  // booking confirmation
  public function confirmBooking()
  {
    $rules = [
      'user_id'      => 'required|numeric',
      'booking_id'   => 'required',
      'service_id'   => 'required',
      'provider_id'  => 'required',
      'quotation_id' => 'required',
    ];
    if (!$this->validate($rules)) {
      return $this->response->setJSON(['status' => false, 'errors' => $this->validator->getErrors()]);
    }

    $json = $this->request->getJSON();

    $service_id = $json->service_id;

    // $totalPrice = $json->total_amount;

    $gst          = $json->gst;
    $platform_fee = $json->platform_fee;
    $discount     = $json->discount;

    $totalPrice = ($json->total_amount - $discount);

    $payableAmount = $this->booking->calculatePayableAmount($totalPrice, $gst, $platform_fee, $discount);

    $data = [
      'user_id'      => $json->user_id,
      'booking_id'   => $json->booking_id,
      'service_id'   => $service_id,
      'provider_id'  => $json->provider_id,
      'quotation_id' => $json->quotation_id,
    ];

    $provider = $this->provider->getProvider($json->provider_id);
    $token    = $provider->device_token;

    // Confirm booking based on service type
    if ($service_id == '1') {
      // confirm the Boarding booking details
      $b                          = $this->booking->confirmBoardingBooking($data);
      $booking                    = $this->booking->getBoardingBooking($json->booking_id, $json->user_id);
      $data['service_start_date'] = $booking->service_start_date;
      $user                       = $this->booking->getUserByBooking($json->booking_id, 'boarding_service_bookings');
    } else if ($service_id == '2') {
      log_message('info', 'Confirming Grooming booking for user_id: ' . $json->user_id);

      $b = $this->booking->confirmGroomingBooking($data);
      $booking = $this->booking->getGroomingAmount($json->booking_id, $json->user_id, $json->quotation_id);
      $data['service_start_date'] = $booking->service_start_date;

      log_message('info', 'Booking details fetched: ' . json_encode($booking));

      $user = $this->booking->getUserByBooking($json->booking_id, 'grooming_service_bookings');

      foreach ($json->pet_id as $pet_id) {
        log_message('info', 'Processing pet_id: ' . $pet_id);

        $clientWallet = $this->wallet->getWithdrawlWallet($json->user_id, $pet_id);
        $balance = $clientWallet ? (float) $clientWallet->balance : 0;
        $withdrawal_amount = $json->withdrawal_amount ?? 0;

        log_message('info', "User Wallet Balance: $balance, Withdrawal Request: $withdrawal_amount");

        if (!empty($json->withdrawl_amount) && $json->withdrawl_amount >= 0) {
          $totalWithdrawlAmount = $this->wallet->getWithdrawlAmount($json->user_id);
          $totalPrice += $totalWithdrawlAmount - $json->withdrawl_amount;

          log_message('info', "Total Withdrawal Amount: $totalWithdrawlAmount, Adjusted Total Price: $totalPrice");

          $this->wallet->updateWithdrawalWallet($json->user_id, $pet_id, $json->withdrawl_amount);
          $this->wallet->logTransaction($json->user_id, 'debit', $json->withdrawl_amount, 'Refund amount used', 'user_wallet_histories');
        }

        $wallet = [
          'user_id'         => $json->user_id,
          'pet_id'          => $pet_id,
          'grooming_amount' => $totalPrice / count($json->pet_id),
        ];
        log_message('info', 'User Wallet Update: ' . json_encode($wallet));

        $this->wallet->logTransaction($json->user_id, 'credit', $wallet['grooming_amount'], 'Grooming service amount', 'user_wallet_histories');

        $pwallet = [
          'provider_id'     => $json->provider_id,
          'pet_id'          => $pet_id,
          'grooming_amount' => $booking->receivable_amount / count($json->pet_id),
        ];
        log_message('info', 'Provider Wallet Update: ' . json_encode($pwallet));

        $this->wallet->logTransaction($json->provider_id, 'credit', $pwallet['grooming_amount'], 'Grooming service amount', 'sp_wallet_histories');

        $userWallet = $this->wallet->checkPetService($json->user_id, $pet_id);
        if ($userWallet) {
          $this->wallet->updateGroomingWalletAmount($wallet);
        } else {
          $this->wallet->addGroomingWalletAmount($wallet);
        }

        $providerWallet = $this->wallet->checkProviderPetService($json->provider_id, $pet_id);
        if ($providerWallet) {
          $this->wallet->updateProviderGroomingWalletAmount($pwallet);
        } else {
          $this->wallet->addProviderGroomingWalletAmount($pwallet);
        }

        log_message('info', 'Wallets updated for pet_id: ' . $pet_id);

        $data1 = [
          'user_id'   => $json->provider_id,
          'user_type' => 'provider',
          'type'      => 'quotation_accepted',
          'message'   => 'Booking confirmed for Grooming with ' . ($user->name ?? 'User'),
        ];

        $this->booking->createNotification($data1);
        log_message('info', 'Notification sent: ' . json_encode($data1));
      }

      $quotationAccepted = $this->quotations->acceptQuotation($data);
      $this->quotations->cancelOtherQuotes($json->booking_id, $json->quotation_id);

      log_message('info', 'Quotation accepted and other quotes canceled.');
    } else if ($service_id == '3') {
      // confirm the Training booking details
      $b       = $this->booking->confirmTrainingBooking($data);
      $booking = $this->booking->getTrainingBooking($json->booking_id, $json->user_id);
      $user    = $this->booking->getUserByBooking($json->booking_id, 'training_service_bookings');

      $data['service_start_date'] = $booking->service_start_date;
    } else if ($service_id == '4') {
      // confirm the Walking booking details
      $booking = $this->booking->getWalkingAmount($json->booking_id, $json->user_id, $json->quotation_id);
      $user    = $this->booking->getUserByBooking($json->booking_id, 'walking_service_bookings');

      foreach ($json->pet_id as $pet_id) {

        $clientWallet      = $this->wallet->getWithdrawlWallet($json->user_id, $pet_id);
        $balance           = $clientWallet ? (float) $clientWallet->balance : 0; // Convert to float
        $withdrawal_amount = $json->withdrawal_amount ?? 0;

        // if (!$clientWallet) {
        //   return $this->response->setJSON(['status' => false, 'message' => 'Insufficient funds in the wallet.']);
        // }

        if (!empty($json->withdrawl_amount) && $json->withdrawl_amount >= 0) {
          $totalWithdrawlAmount = $this->wallet->getWithdrawlAmount($json->user_id);
          $totalPrice += $totalWithdrawlAmount - $json->withdrawl_amount;

          $this->wallet->updateWithdrawalWallet($json->user_id, $pet_id, $json->withdrawl_amount);
          $this->wallet->logTransaction($json->user_id, 'debit', $json->withdrawl_amount, 'Refund amount used', 'user_wallet_histories');
        }

        $wallet = [
          'user_id'        => $json->user_id,
          'pet_id'         => $pet_id,
          'walking_amount' => $totalPrice / count($json->pet_id),
        ];
        $this->wallet->logTransaction($json->user_id, 'credit', $totalPrice / count($json->pet_id), 'Walking service amount', 'user_wallet_histories');

        $pwallet = [
          'provider_id'    => $json->provider_id,
          'pet_id'         => $pet_id,
          'walking_amount' => $booking->receivable_amount / count($json->pet_id),
        ];
        $this->wallet->logTransaction($json->provider_id, 'credit', $booking->receivable_amount / count($json->pet_id), 'Walking service amount', 'sp_wallet_histories');

        $userWallet = $this->wallet->checkPetService($json->user_id, $pet_id);

        if ($userWallet) {
          $this->wallet->updateWalletAmount($wallet);
        } else {
          $this->wallet->addWalletAmount($wallet);
        }

        $Wallet = $this->wallet->checkProviderPetService($json->provider_id, $pet_id);

        if ($Wallet) {
          $this->wallet->updateProviderWalletAmount($pwallet);
        } else {
          $this->wallet->addProviderWalletAmount($pwallet);
        }

        // Update wallet and create a transaction
        $pet = $this->booking->getPet($json->booking_id);

        $data1 = [
          'user_id'   => $json->provider_id,
          'user_type' => 'provider',
          'type'      => 'quotation_accepted',
          'message'   => 'Booking confirmed for Walking with ' . $user->name ?? 'User',
        ];

        $this->booking->createNotification($data1);
      }

      if ($booking->type == 'extend') {
        $b = $this->booking->confirmWalkingBooking($data, 'onHold', 'completed');
      } else {
        $b = $this->booking->confirmWalkingBooking($data, 'Confirmed', 'completed');
      }
      $quotationAccepted = $this->quotations->acceptQuotation($data);
      $this->quotations->cancelOtherQuotes($json->booking_id, $json->quotation_id);
      $data['service_start_date'] = $booking->service_start_date;
      // $walletData = array_merge((array) $json, (array) $booking);
      // $this->processWalletUpdates($walletData);

    } else {
      return $this->response->setJSON(['status' => false, 'message' => 'Invalid Service']);
    }

    $title   = 'Booking Confirmation';
    $message = 'Booking confirmed for Walking with ' . $user->name ?? 'User';

    $this->booking->createNotification([
      'user_id'   => $json->provider_id,
      'user_type' => 'provider',
      'type'      => 'quotation_accepted',
      'message'   => $message,
    ]);

    $this->pushNotify->notify($token, $title, $message);
    // Confirm both quotation and booking
    if ($quotationAccepted && $b) {
      $this->booking->createSPBooking($data);
      return $this->response->setJSON(['status' => true, 'message' => 'Booking Confirmed']);
    } else {
      return $this->response->setJSON(['status' => false, 'message' => 'Something went wrong, try again!']);
    }
  }

  public function activeService()
  {
    $rules = [
      'user_id'      => 'required|numeric',
      'booking_id'   => 'required',
      'service_id'   => 'required|numeric',
      'provider_id'  => 'required|numeric',
      'quotation_id' => 'required|numeric',
    ];

    if (!$this->validate($rules)) {
      return $this->response->setJSON(['status' => false, 'errors' => $this->validator->getErrors()]);
    }

    $json         = $this->request->getJSON();
    $service_id   = $json->service_id;
    $user_id      = $json->user_id;
    $booking_id   = $json->booking_id;
    $provider_id  = $json->provider_id;
    $quotation_id = $json->quotation_id;

    // Get booking details based on service_id

    if ($service_id == '4') {
      $bookingData = $this->booking->getBookingData($json->booking_id, $json->user_id, $service_id, $json->provider_id, $quotation_id);
      $bookingData->package_price *= $bookingData->days ?? 1;
      $bookingData                = $this->processPetsAndTracking($bookingData, $booking_id, $provider_id);
    } else if ($service_id == '2') {
      $bookingData = $this->booking->getGroomingBookingData($booking_id, $user_id);
      $bookingData = $this->processPetsAndAddons($bookingData, $booking_id, $provider_id);
    }

    $bookingData = $this->appendProviderAndDistance($bookingData, $user_id);

    $nearbyProviders = $this->getNearbyProviders($user_id, $booking_id, $service_id, $provider_id, $bookingData->address_id);

    return $bookingData
      ? $this->response->setJSON(['status' => true, 'bookingData' => $bookingData, 'nearbyProviders' => $nearbyProviders])
      : $this->response->setJSON(['status' => false, 'message' => 'Not Found']);
  }

  //  pets and tracking data
  private function processPetsAndTracking($bookingData, $booking_id, $provider_id)
  {
    $preferable_times             = explode(',', $bookingData->preferable_time);
    $bookingData->preferable_time = $bookingData->sp_timings ?? $bookingData->preferable_time;
    $currentDate                  = date('Y-m-d');

    $startDate      = date('Y-m-d', strtotime($bookingData->service_start_date));
    $daysSinceStart = (strtotime($currentDate) - strtotime($startDate)) / (60 * 60 * 24); // Difference in days
    foreach ($bookingData->pets as &$pet) {
      $bookingData->completedDays = max(0, floor($daysSinceStart));

      $pet->completedDays = max(0, floor($daysSinceStart));

      if ($pet->image) {
        $pet->image = base_url() . 'public/uploads/pets/' . $pet->image;
      }
      $pet->age = CommonHelper::ageCalculator($pet->dob);

      // Set default walkstatus as 'pending' for each pet
      $pet->walkstatus      = 'pending';
      $pet->preferable_time = $bookingData->preferable_time;

      //  tracking data for this pet
      $trackings             = $this->booking->getTracking($booking_id, $provider_id, $pet->pet_id);
      $completed_timings_set = [];
      $all_timings_completed = true;
      if ($trackings) {
        foreach ($trackings as $track) {
          if ($track->service_time && $track->status) {

            $preferable_times = $bookingData->sp_timings ? explode(',', $bookingData->sp_timings) : explode(',', $bookingData->preferable_time);
            foreach ($preferable_times as $time) {
              $time = trim(string: $time);
              if ($track->service_time == $time && $track->status == 'completed') {
                $completed_timings_set[] = $time;
                $pet->service_time       = '';
                $pet->walkstatus         = '';
              } elseif ($track->status == 'in_progress') {
                $pet->service_time     = $track->service_time;
                $pet->walkstatus       = 'in_progress';
                $all_timings_completed = false;
              } else {
                $pet->service_time = '';
                $pet->walkstatus   = 'not_started';
              }
            }
          } else {
            $pet->service_time = '';
            $pet->walkstatus   = 'not_started';
          }
        }
      } else {
        $pet->service_time = '';
        $pet->walkstatus   = 'not_started';
      }
      $pet->completedTime = implode(',', $completed_timings_set);
      if ($all_timings_completed && count($completed_timings_set) == count($preferable_times)) {
        $pet->walkstatus = 'completed';
      } elseif (!$all_timings_completed) {
        $pet->walkstatus = 'in_progress';
      } else {
        $pet->walkstatus = 'not_started';
      }
      // Check if all preferable times are completed for the pet
      // if (count($completed_timings_set) === count($preferable_times)) {
      //   $pet->completedDays++;
      // }
    }

    return $bookingData;
  }

  private function processPetsAndAddons($bookingData, $booking_id, $provider_id)
  {
    foreach ($bookingData->pets as &$pet) {
      // Get tracking data for addons and packages
      $trackingAddons   = $this->booking->getGroomingTracking($booking_id, $provider_id, $pet->pet_id);
      $trackingPackages = $this->booking->getGroomingTrackingpackages2($booking_id, $provider_id, $pet->pet_id);
      // Initialize pet-specific addons and packages from booking data
      $petAddons        = $bookingData->addons;
      $petPackages      = $bookingData->packages;
      $pet->booking_id  = $booking_id;
      $pet->provider_id = $provider_id;

      // Create associative arrays of tracking statuses and approvals for easy lookup
      $trackingStatusMapAddons     = [];
      $trackingApprovalMapAddons   = [];
      $trackingStatusMapPackages   = [];
      $trackingApprovalMapPackages = [];

      foreach ($trackingAddons as $tracking) {
        $trackingStatusMapAddons[$tracking->addon]   = $tracking->status;
        $trackingApprovalMapAddons[$tracking->addon] = $tracking->is_approved ?? false;
      }

      foreach ($trackingPackages as $tracking) {
        $trackingStatusMapPackages[$tracking->package_name]   = $tracking->status;
        $trackingApprovalMapPackages[$tracking->package_name] = $tracking->is_approved ?? false;
      }

      // Iterate through pet addons and set status accordingly
      $petAddonsWithStatus = [];
      $allAddonsCompleted  = true;
      $anyAddonRejected    = false;
      foreach ($petAddons as $addon) {
        $status                = $trackingStatusMapAddons[$addon] ?? 'pending';
        $is_approved           = $trackingApprovalMapAddons[$addon] ?? false;
        $petAddonsWithStatus[] = (object) [
          'name'        => $addon,
          'status'      => $status,
          'is_approved' => $is_approved,
        ];
        if ($status === 'rejected' && !$is_approved) {
          $anyAddonRejected = true;
        }
        if ($status !== 'completed' || !$is_approved) {
          $allAddonsCompleted = false;
        }
      }

      // Iterate through pet packages and set status accordingly
      $petPackagesWithStatus = [];
      $allPackagesCompleted  = true;
      $anyPackageRejected    = false;
      foreach ($petPackages as $package) {
        $status                  = $trackingStatusMapPackages[$package->package_name] ?? 'pending';
        $is_approved             = $trackingApprovalMapPackages[$package->package_name] ?? false;
        $petPackagesWithStatus[] = (object) [
          'package_id'      => $package->package_id,
          'package_name'    => $package->package_name,
          'price'           => $package->price,
          'included_addons' => $package->included_addons,
          'status'          => $status,
          'is_approved'     => $is_approved,
        ];
        if ($status === 'rejected' && !$is_approved) {
          $anyPackageRejected = true;
        }
        if ($status !== 'completed' || !$is_approved) {
          $allPackagesCompleted = false;
        }
      }

      // Attach updated addons and packages to the pet
      $pet->addons   = $petAddonsWithStatus;
      $pet->packages = $petPackagesWithStatus;

      // Set track status based on completion, approval, and rejection
      if ($anyAddonRejected || $anyPackageRejected) {
        $pet->track_status = 'rejected';
      } elseif ($allAddonsCompleted && $allPackagesCompleted) {
        $pet->track_status = 'approved';
      } else {
        $pet->track_status = ($trackingAddons || $trackingPackages ? 'completed' : 'not_started');
      }

      // Process pet image and age
      if ($pet->image) {
        $pet->image = base_url() . 'public/uploads/pets/' . $pet->image;
      }
      $pet->age = CommonHelper::ageCalculator($pet->dob);
    }
    unset($bookingData->addons);
    unset($bookingData->packages);
    return $bookingData;
  }

  //  provider and distance details
  private function appendProviderAndDistance($bookingData, $user_id)
  {

    $user = $this->user->getUserAddress($user_id, $bookingData->address_id);

    $distance = CommonHelper::distanceCalculator($user->latitude, $user->longitude, $bookingData->service_latitude, $bookingData->service_longitude);

    if ($bookingData->total_count > 0) {
      $bookingData->rating = number_format(CommonHelper::ratingCalculator($bookingData->rating_sum, $bookingData->total_count), 1) . ' (' . $bookingData->total_count . ')';
    }

    $bookingData->distance = number_format($distance, 1);
    if ($bookingData->profile) {
      $bookingData->profile = base_url() . 'public/uploads/providers/' . $bookingData->profile;
    }
    unset($bookingData->service_latitude);
    unset($bookingData->service_longitude);
    unset($bookingData->rating_sum);
    unset($bookingData->total_count);

    return $bookingData;
  }

  // nearby providers
  private function getNearbyProviders($user_id, $booking_id, $service_id, $provider_id, $address_id)
  {
    $user            = $this->user->getUserAddress($user_id, $address_id);
    $nearbyProviders = [];
    $providers       = $this->provider->getProviders();

    foreach ($providers as &$provider) {
      if ($provider->id !== $provider_id) {
        $providerDetails = $this->provider->getProvider($provider->id);

        $providerDetails->averageRating = $this->provider->getAverageRating($provider->id);
        $providerDetails->distance      = CommonHelper::distanceCalculator(
          $user->latitude,
          $user->longitude,
          $providerDetails->service_latitude,
          $providerDetails->service_longitude
        );
        if ($provider->profile) {
          $providerDetails->profile = base_url() . 'public/uploads/providers/' . $provider->profile;
        }
        // Filter providers within 30km
        if ($providerDetails->distance <= 30) {
          $quotations = $this->quotations->checkExists($user_id, $booking_id, $providerDetails->id, $service_id);

          // Merge provider details with quotations
          $nearbyProviders[] = array_merge((array) $providerDetails, (array) $quotations);
        }
      }
    }

    return $nearbyProviders;
  }

  //distance label
  private function getDistanceLabel($distance)
  {
    if ($distance <= 7) {
      return 'Within 7 km';
    } elseif ($distance <= 15) {
      return 'Within 15 km';
    } elseif ($distance <= 30) {
      return 'Within 30 km';
    }

    return '';
  }

  public function serviceHistory()
  {
    $rules = [
      'user_id'    => 'required',
      'pet_id'     => 'required',
      'service_id' => 'required',
    ];
    if (!$this->validate($rules)) {
      return $this->response->setJSON(['status' => false, 'error' => $this->validator->getErrors()]);
    }
    $json       = $this->request->getJSON();
    $user_id    = $json->user_id;
    $service_id = $json->service_id;
    $pet_id     = $json->pet_id;
    $data       = [];
    if ($service_id == '1') {
      // confirm the Boarding booking details
      $data = $this->booking->getBoardingHistory($user_id, $service_id, $pet_id);
    } else if ($service_id == '2') {
      // confirm the Grooming booking details
      $data = $this->booking->getGroomingHistory($user_id, $service_id, $pet_id);
    } else if ($service_id == '3') {
      // confirm the Training booking details
      $data = $this->booking->getTrainingHistory($user_id, $service_id, $pet_id);
    } else if ($service_id == '4') {
      $data = $this->booking->getWalkingHistory($user_id, $service_id, $pet_id);
    }
    foreach ($data as &$dat) {
      if ($dat->image) {
        $dat->image = base_url() . 'public/uploads/pets/' . $dat->image;
      }
    }
    return $this->response->setJSON(['status' => true, 'data' => $data]);
  }

  public function hireProvider()
  {
    $json = $this->request->getJSON();

    $rules = [
      'user_id'            => 'required|numeric',
      'booking_id'         => 'required',
      'service_id'         => 'required|numeric',
      'provider_id'        => 'required|numeric',
      'service_start_date' => 'required',
      'type'               => 'required|in_list[permanent,temporary]',
    ];

    if ($json->type === 'permanent') {
      $rules['package_id']        = 'required';
      $rules['service_frequency'] = 'required';
      $rules['walk_duration']     = 'required';
      $rules['service_days']      = 'required';
      $rules['preferable_time']   = 'required';
    }

    if (!$this->validate($rules)) {
      return $this->response->setJSON(['status' => false, 'errors' => $this->validator->getErrors()]);
    }

    $bookingData = $this->getBookingData($json->service_id, $json->booking_id, $json->user_id);

    if (!$bookingData) {
      return $this->response->setJSON(['status' => false, 'message' => 'Invalid booking data.']);
    }

    if ($json->service_id == '4') {
      $uniqueId    = substr(bin2hex(random_bytes(3)), 0, 5);
      $serviceType = ($json->type == 'temporary') ? 'walkingTemporary' : 'walkingPermanent';
      $bookingId   = CommonHelper::generateID($uniqueId, $serviceType);
    }

    if ($json->type === 'permanent') {
      $totalDays = $this->booking->getPackageDetails($json->package_id)->totalDays;

      $endDate = $this->calculateEndDate($json->service_start_date, $totalDays, $json->service_days);

      $data = [
        'service_start_date' => $json->service_start_date,
        'service_end_date'   => $endDate,
        'package_id'         => $json->package_id,
        'address_id'         => $json->address_id,
        'service_frequency'  => $json->service_frequency,
        'walk_duration'      => $json->walk_duration,
        'service_days'       => $json->service_days,
        'preferable_time'    => implode(',', $json->preferable_time),
        'addons'             => $json->addons ?? [],
        'total_price'        => $json->total_price,
      ];
    } else {
      $booking = $this->booking->getBooking($json->booking_id, $json->provider_id);

      if ($booking->service_frequency == 'once a day') {
        $frequency = 1;
      } elseif ($booking->service_frequency == 'twice a day') {
        $frequency = 2;
      } elseif ($booking->service_frequency == 'thrice a day') {
        $frequency = 3;
      } else {
        $frequency = 0;
      }
      if ($bookingData->walk_duration == '60 min walk') {
        $walk_duration = 2;
      } else {
        $walk_duration = 1;
      }
      $pricePerWalk = $booking->price_per_walk;

      $service_start_date = $json->service_start_date;
      $service_end_date   = $json->service_end_date;

      // Create DateTime objects
      $start_date = new DateTime($service_start_date);
      $end_date   = new DateTime($service_end_date);

      // Calculate the difference
      $interval = $start_date->diff($end_date);

      // Get the number of days
      $duration_days = $interval->days + 1;
      $Amount        = $pricePerWalk * $frequency * $duration_days * $walk_duration;

      $data = [
        'service_start_date' => $json->service_start_date,
        'service_end_date'   => $json->service_end_date,
        'package_id'         => $bookingData->package_id,
        'address_id'         => $bookingData->address_id,
        'service_frequency'  => $bookingData->service_frequency,
        'walk_duration'      => $bookingData->walk_duration,
        'service_days'       => $bookingData->service_days,
        'preferable_time'    => $bookingData->preferable_time,
        'addons'             => $bookingData->addons ?? [],
        'total_price'        => $Amount,
      ];
    }

    $data = array_merge($data, [
      'user_id'             => $json->user_id,
      'id'                  => $bookingId ?? null,
      'original_booking_id' => $json->booking_id,
      'service_id'          => $json->service_id,
      'type'                => $json->type,
      'status'              => 'New',
      'created_at'          => date('Y-m-d H:i:s'),
    ]);

    // Create the booking
    $booking = $this->booking->createWalkingBooking((object) $data);

    if ($booking) {
      $addOns = $json->addons ?? $bookingData->addons;
      if (isset($addOns) && is_array($addOns)) {
        foreach ($addOns as $addon) {
          $this->booking->createBookingAddons([
            'user_id'    => $json->user_id,
            'booking_id' => $data['id'],
            'service_id' => $json->service_id,
            'addon'      => $addon,
            'created_at' => gmdate('Y-m-d H:i:s'),
          ]);
        }
      }

      // Notify providers if the booking is created successfully
      $providers         = $this->provider->getProviders();
      $notifiedProviders = [];

      foreach ($providers as $provider) {
        if ($provider->device_token && !in_array($provider->id, $notifiedProviders)) {
          // Fetch user address
          $user = $this->user->getUserAddress($json->user_id, $json->address_id ?? $bookingData->address_id);

          $distance = CommonHelper::distanceCalculator($user->latitude, $user->longitude, $provider->service_latitude, $provider->service_longitude);
          $pet      = $this->booking->getPet($json->booking_id);

          if ($distance <= 30) {
            $token      = $provider->device_token;
            $title      = 'Service request for ' . $pet->name ?? 'Pet';
            $message    = "You got a new job posting from " . $user->name ?? 'User' . " Want to offer your quotation";
            $PushNotify = new PushNotify();
            $PushNotify->notify($token, 'New Booking', $title, $message);
            $notifiedProviders[] = $provider->id;
          }
        }

        if (!empty($notifiedProviders)) {
          break;
        }
      }

      return $this->response->setJSON(['status' => true, 'message' => 'Service booked successfully.']);
    }

    return $this->response->setJSON(['status' => false, 'message' => 'Service not booked.']);
  }

  /**
   * Calculate the end date based on the service days (Mon-Sat for weekdays).
   */
  private function calculateEndDate($startDateStr, $totalDays, $serviceDays)
  {
    $startDate = new DateTime($startDateStr);
    $endDate   = clone $startDate;
    $daysAdded = 1; // Start with 1 because the start date is included.

    while ($daysAdded < $totalDays) {
      $endDate->modify('+1 day');

      if ($serviceDays === 'weekdays') {
        // Skip Sunday (N=7)
        if ($endDate->format('N') < 7) {
          $daysAdded++;
        }
      } else {
        // Include all days
        $daysAdded++;
      }
    }

    return $endDate->format('Y-m-d');
  }

  public function extendService()
  {
    $rules = [
      'user_id'            => 'required|numeric',
      'booking_id'         => 'required',
      'service_id'         => 'required|numeric',
      'provider_id'        => 'required|numeric',
      'service_start_date' => 'required',
      'package_id'         => 'required|numeric',
      'service_frequency'  => 'required',
      'walk_duration'      => 'required',
      'service_days'       => 'required',
      "preferable_time"    => 'required',
    ];

    if (!$this->validate($rules)) {
      return $this->response->setJSON(['status' => false, 'errors' => $this->validator->getErrors()]);
    }

    $json        = $this->request->getJSON();
    $bookingData = $this->getBookingData($json->service_id, $json->booking_id, $json->user_id);

    if (!$bookingData) {
      return $this->response->setJSON(['status' => false, 'message' => 'Invalid booking data']);
    }
    $check = $this->booking->checkExtend($json->booking_id);

    if ($check) {
      return $this->response->setJSON(['status' => false, 'message' => 'Already Extend Exists']);
    }

    $totalDays = $this->booking->getPackageDetails($json->package_id)->totalDays;
    $endDate   = $this->calculateEndDate($json->service_start_date, $totalDays, $json->service_days);

    $data    = [
      'user_id'             => $json->user_id,
      'id'                  => $this->generateBookingId('walkingExtend'),
      'original_booking_id' => $json->booking_id,
      'service_id'          => $json->service_id,
      'package_id'          => $json->package_id,
      'total_price'         => $json->total_price,
      'address_id'          => $json->address_id,
      'service_frequency'   => $json->service_frequency,
      'walk_duration'       => $json->walk_duration,
      'service_days'        => $json->service_days,
      'service_start_date'  => $json->service_start_date,
      'service_end_date'    => $endDate,
      'preferable_time'     => implode(',', $json->preferable_time),
      'type'                => 'extend',
      'status'              => 'onHold',
      'approval'            => 'pending',
      'addons'              => count($json->addons) ?? 0,
      'created_at'          => date('Y-m-d H:i:s'),
    ];
    $booking = $this->booking->createWalkingBooking((object) $data);
    if (isset($json->addons) && is_array($json->addons)) {
      foreach ($json->addons as $addon) {
        $this->booking->createBookingAddons([
          'user_id'    => $json->user_id,
          'booking_id' => $data['id'],
          'service_id' => $json->service_id,
          'addon'      => $addon,
          'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
      }
    }
    $provider = $this->provider->getProvider($json->provider_id);
    $user     = $this->booking->getUserByBooking($json->booking_id, 'walking_service_bookings');
    $pet      = $this->booking->getPet($json->booking_id);

    $token   = $provider->device_token;
    $title   = 'Extend Dog walking';
    $message = 'Client requests to extend service for ' . $pet->name ?? '' . '. Take action!';

    $this->booking->createNotification([
      'user_id'   => $json->provider_id,
      'user_type' => 'provider',
      'type'      => 'extend_requested',
      'message'   => $message,
    ]);

    $this->pushNotify->notify($token, $title, $message);

    return $booking
      ? $this->response->setJSON(['status' => true, 'message' => 'Service Extended Successfully..'])
      : $this->response->setJSON(['status' => false, 'message' => 'Service Not Extended']);
  }

  private function generateBookingId($serviceType)
  {
    $uniqueId = substr(bin2hex(random_bytes(3)), 0, 5);
    return CommonHelper::generateID($uniqueId, $serviceType);
  }

  public function cancelService()
  {
    $rules = [
      'user_id'     => 'required|numeric',
      'booking_id'  => 'required',
      'service_id'  => 'required|numeric',
      'provider_id' => 'required|numeric',
    ];

    if (!$this->validate($rules)) {
      return $this->response->setJSON(['status' => false, 'errors' => $this->validator->getErrors()]);
    }

    $json         = $this->request->getJSON();
    $refundAmount = 0;

    // Cancel service based on service_id and pet_id
    switch ($json->service_id) {
      case '1':
        $result = $this->booking->cancelBoardingServiceForPet($json->booking_id, $json->user_id, $json->pet_id);
        break;
      case '2':
        $booking = $this->booking->getGroomingPriceCalculate($json->booking_id, $json->provider_id);
        foreach ($json->pet_id as $pet_id) {
          $clientRefundAmount    = ($booking->bid_amount) / count($json->pet_id);
          $providerRemovalAmount = ($booking->receivable_amount) / count($json->pet_id);

          // Handle wallet refunds and transactions
          $this->wallet->addRefundAmount($json->user_id, $clientRefundAmount, $pet_id, $json->service_id);
          $this->wallet->debitGroomingRefundAmount($json->user_id, $clientRefundAmount, $pet_id);
          $this->wallet->debitSpGroomingRefundAmount($json->provider_id, $providerRemovalAmount, $pet_id);

          $this->wallet->logTransaction($json->user_id, 'credit', $clientRefundAmount, 'Refund Grooming service amount', 'user_wallet_histories');
          $this->wallet->logTransaction($json->provider_id, 'debit', $providerRemovalAmount, 'Cancelled Grooming service amount', 'sp_wallet_histories');

          // Cancel the grooming service for this pet
          $result = $this->booking->cancelGroomingService($json->booking_id, $json->user_id, $json->reason);

          $this->booking->cancelGroomingServiceForPet($json->booking_id, $json->user_id, $pet_id);
        }

        return $this->response->setJSON(['status' => true, 'message' => 'Service cancelled for the pet and refund processed successfully.']);
        break;
      case '3':
        $result = $this->booking->cancelTrainingServiceForPet($json->booking_id, $json->user_id, $json->pet_id);
        break;
      case '4':
        $booking = $this->booking->getBookingforPriceCalculate($json->booking_id, $json->provider_id);

        $pricePerWalk = $this->calculatePricePerWalk($booking, $json->provider_id, count($json->pet_id));
        $frequency = $this->getServiceFrequency($booking->service_frequency);

        $startDate = new DateTime($booking->service_start_date);
        $endDate = new DateTime($booking->service_end_date);
        $interval = $startDate->diff($endDate);
        $totalDays = $interval->days + 1;

        if ($booking->service_days == 'weekdays') {
          $period    = new DatePeriod($startDate, new DateInterval('P1D'), $endDate);
          $totalDays = 0;

          foreach ($period as $date) {
            // Check if the day is not Sunday (6 is Sunday in PHP's w format)
            if ($date->format('w') != 0) {
              $totalDays++;
            }
          }
        } else {
          $totalDays = $startDate->diff($endDate)->days;
        }
        $walks = $frequency * $totalDays;
        foreach ($json->pet_id as $pet_id) {

          $remainingWalks = $this->booking->getRemainingWalksForPet($json->booking_id, $pet_id);
          // no.of remaining days * price per walk
          if ($remainingWalks > 0) {
            $discount         = $booking->discount_amount / $walks;
            $platform_charges = ($booking->platform_charges / $walks) * $remainingWalks;

            $refundAmount          = $this->calculateRefundForWalking($remainingWalks, $pricePerWalk);
            $clientRefundAmount    = $refundAmount - $discount;
            $providerRemovalAmount = $refundAmount - $platform_charges - $discount;
          }
          $result = $this->booking->cancelWalkingService($json->booking_id, $json->user_id, $json->reason);

          $this->booking->cancelWalkingServiceForPet($json->booking_id, $json->user_id, $pet_id);

          if ($result) {

            if ($refundAmount > 0) {

              $this->wallet->addRefundAmount($json->user_id, $clientRefundAmount, $json->pet_id, $json->service_id);
              $this->wallet->debitRefundAmount($json->user_id, $clientRefundAmount, $json->pet_id);
              $this->wallet->debitSpRefundAmount($json->provider_id, $providerRemovalAmount, $json->pet_id);

              $this->wallet->logTransaction($json->user_id, 'credit', $clientRefundAmount, 'Refund Walking service amount', 'user_wallet_histories');
              $this->wallet->logTransaction($json->provider_id, 'debit', $providerRemovalAmount, 'Cancelled Walking service amount', 'sp_wallet_histories');
            }
            $pet = $this->booking->getPetName($pet_id);

            $user = $this->user->getUser($json->user_id);

            $provider = $this->provider->getProvider($json->provider_id);

            $message = 'The client ' . ($user->name ?? 'Pet Parent') . ' , has cancelled the service for ' . ($pet->name ?? 'Pet');

            $this->booking->createNotification([
              'user_id'   => $json->provider_id,
              'user_type' => 'provider',
              'type'      => 'booking_cancelled',
              'message'   => $message,
            ]);

            $pushNotify = new PushNotify();
            $pushNotify->notify($provider->device_token, 'Service cancelled', $message);

            return $this->response->setJSON(['status' => true, 'message' => 'Service cancelled for the pet and refund processed successfully.']);
          } else {
            return $this->response->setJSON(['status' => false, 'message' => 'Service not cancelled']);
          }
        }

        break;
      default:
        return $this->response->setJSON(['status' => false, 'message' => 'Invalid service ID']);
    }

    // Cancel provider service for the specific pet
    // $this->booking->cancelProviderServiceForPet($json->booking_id, $json->provider_id, $json->pet_id);

  }

  public function calculateRefundForWalking($remainingWalks, $pricePerWalk)
  {
    $refundAmount = 0;

    if ($remainingWalks > 0) {
      $refundAmount = $remainingWalks * $pricePerWalk;
    }

    return $refundAmount;
  }

  public function reportWalker()
  {
    $rules = [
      'user_id'       => 'required|numeric',
      'booking_id'    => 'required',
      'provider_id'   => 'required|numeric',
      'report_reason' => 'required',
    ];

    if (!$this->validate($rules)) {
      return $this->response->setJSON(['status' => false, 'errors' => $this->validator->getErrors()]);
    }
    $json   = $this->request->getJSON();
    $data   = [
      'booking_id'     => $json->booking_id,
      'provider_id'    => $json->provider_id,
      "report_comment" => $json->report_comment,
      'report_reason'  => $json->report_reason,
    ];
    $result = $this->booking->reportProvider($data);
    return $result
      ?
      $this->response->setJSON(['status' => true, 'message' => 'Reported Successfully..'])
      :
      $this->response->setJSON(['status' => false, 'message' => 'Not Reported']);
  }

  public function repostBooking()
  {
    $rules = [
      'user_id'    => 'required',
      'booking_id' => 'required',
      'service_id' => 'required'
    ];

    if (!$this->validate($rules)) {
      return $this->response->setJSON(['status' => false, 'errors' => $this->validator->getErrors()]);
    }

    $service_id = $this->request->getJsonVar('service_id');
    $user_id    = $this->request->getJsonVar('user_id');
    $booking_id = $this->request->getJsonVar('booking_id');

    // Check if it's a grooming service (service_id = 2)
    if ($service_id == '2') {
      $extraRules = [
        'service_start_date' => 'required',
        'preferable_time'    => 'required'
      ];

      if (!$this->validate($extraRules)) {
        return $this->response->setJSON(['status' => false, 'errors' => $this->validator->getErrors()]);
      }

      $data = [
        'booking_id'         => $booking_id,
        'user_id'            => $user_id,
        'service_start_date' => $this->request->getJsonVar('service_start_date'),
        'preferable_time'    => $this->request->getJsonVar('preferable_time')
      ];
      $result            = $this->booking->repostGroomingBooking($data);
      $booking           = $this->booking->getGroomingAddress($user_id, $booking_id);
      $notificationTitle = 'New job alert - Grooming';
    } else {
      // Default to regular booking repost
      $result            = $this->booking->repostBooking($booking_id);
      $booking           = $this->booking->getBookingAddress($user_id, $booking_id);
      $notificationTitle = 'New job alert - Walking';
    }

    $providers         = $this->provider->getProviders();
    $notifiedProviders = [];

    foreach ($providers as &$provider) {
      $provider = $this->provider->getProvider($provider->id);
      $distance = CommonHelper::distanceCalculator($booking->latitude, $booking->longitude, $provider->service_latitude, $provider->service_longitude);

      if ($distance <= 30) {
        $PushNotify = new PushNotify();
        $PushNotify->notify($provider->device_token, $notificationTitle, 'Don’t miss out—submit your quote now!');
        $notifiedProviders[] = $provider->id;
      }

      if (!empty($notifiedProviders)) {
        break;
      }
    }

    return $result
      ? $this->response->setJSON(['status' => true, 'message' => 'Repost completed successfully'])
      : $this->response->setJSON(['status' => false, 'message' => 'Repost not completed']);
  }

  public function walkApproval()
  {
    $rules = [
      'provider_id'  => 'required',
      'booking_id'   => 'required',
      'pet_id'       => 'required',
      'service_time' => 'required',
      'approval'     => 'required',
    ];

    if (!$this->validate($rules)) {
      return $this->response->setJSON([
        'status' => false,
        'errors' => $this->validator->getErrors(),
      ]);
    }

    $data = [
      'booking_id'   => $this->request->getJsonVar('booking_id'),
      'provider_id'  => $this->request->getJsonVar('provider_id'),
      'pet_id'       => $this->request->getJsonVar('pet_id'),
      'service_time' => $this->request->getJsonVar('service_time'),
      'approval'     => $this->request->getJsonVar('approval'),
    ];

    $provider = $this->provider->getProvider($data['provider_id']);
    $pet      = $this->booking->getPetName($data['pet_id']);
    $booking  = $this->booking->getBookingforPriceCalculate($data['booking_id'], $data['provider_id']);
    $pets[]   = $data['pet_id'];

    $pricePerWalk = $this->calculatePricePerWalk($booking, $data['provider_id'], count($pets));
    $frequency    = $this->getServiceFrequency($booking->service_frequency);
    $startDate    = new DateTime($booking->service_start_date);
    $endDate      = new DateTime($booking->service_end_date);
    $endDate->modify('+1 day'); // Include the end date in the range

    if ($booking->service_days == 'weekdays') {
      $period    = new DatePeriod($startDate, new DateInterval('P1D'), $endDate);
      $totalDays = 0;

      foreach ($period as $date) {
        // Check if the day is not Sunday (6 is Sunday in PHP's w format)
        if ($date->format('w') != 0) {
          $totalDays++;
        }
      }
    } else {
      $totalDays = $startDate->diff($endDate)->days;
    }

    $walks           = $frequency * $totalDays;
    $platformCharges = $booking->platform_charges / $walks;
    $discount        = $booking->discount_amount / $walks;
    $netAmount       = $pricePerWalk - $platformCharges - $discount;
    $clnetAmount     = $pricePerWalk - $discount;

    $currentDate = new DateTime();

    if ($data['approval'] == 'true') {
      $clientWallet = $this->wallet->getServiceWallet($booking->user_id, $data['pet_id']);
      $balance      = (float) $clientWallet->balance;

      if (!$clientWallet || $balance <= 0 || $balance < $clnetAmount) {
        return $this->response->setJSON([
          'status'  => false,
          'message' => 'Client has insufficient funds.',
        ]);
      }

      //debit from client,sp service wallet and credit sp withdrawl wallet

      $this->wallet->debitWallet($booking->user_id, $data['pet_id'], $clnetAmount);
      $this->wallet->creditWallet($data['provider_id'], $netAmount);
      $this->wallet->debitProviderServiceWallet($data['provider_id'], $data['pet_id'], $netAmount);

      $this->wallet->logTransaction($booking->user_id, 'debit', $clnetAmount, 'Walking service amount', 'user_wallet_histories');
      $this->wallet->logTransaction($data['provider_id'], 'credit', $netAmount, 'Walking service amount', 'sp_wallet_histories');

      $title   = 'Walking Service';
      $message = "Payment for today's walk has been successfully released.";
      $type    = 'walk_approved';

      if ($endDate->format('Y-m-d') === $currentDate->format('Y-m-d')) {
        $todayWalks = $this->booking->getCompletedWalks($data['booking_id'], $currentDate->format('Y-m-d'));
        if ($todayWalks >= $frequency) {
          $this->booking->updateBookingStatus($data['booking_id'], 'completed');
          $title   = 'Walk Completed';
          $message = "All walks have been successfully completed for booking." . ($pet->name ?? '');
        }
      }
    } else {
      //debit from client,sp service wallet and credit client withdrawl wallet
      $this->wallet->debitWallet($booking->user_id, $data['pet_id'], $clnetAmount);
      $this->wallet->creditRefundWallet($booking->user_id, $netAmount);
      $this->wallet->debitProviderServiceWallet($data['provider_id'], $data['pet_id'], $netAmount);

      $this->wallet->logTransaction($booking->user_id, 'credit', $clnetAmount, 'Walking service amount refunded', 'user_wallet_histories');
      $this->wallet->logTransaction($data['provider_id'], 'debit', $netAmount, 'Cancelled Walking service amount', 'sp_wallet_histories');

      $type    = 'walk_rejected';
      $title   = 'Walking Rejected';
      $message = 'The client has rejected your walk for ' . ($pet->name ?? '') . '. Payment will not proceed.';
    }

    $this->booking->createNotification([
      'user_id'   => $data['provider_id'],
      'user_type' => 'provider',
      'type'      => $type,
      'message'   => $message,
    ]);

    $this->pushNotify->notify($provider->device_token, $title, $message);

    $check  = $this->booking->checkWalk($data);
    $result = $check ? $this->booking->walkApproval($data) : $this->booking->addWalkApproval($data);

    return $result
      ? $this->response->setJSON(['status' => true, 'message' => 'Walk approval status updated successfully'])
      : $this->response->setJSON(['status' => false, 'message' => 'Failed to update walk approval status']);
  }

  private function getServiceFrequency($frequencyString)
  {
    switch ($frequencyString) {
      case 'once a day':
        return 1;
      case 'twice a day':
        return 2;
      case 'thrice a day':
        return 3;
      default:
        return 0;
    }
  }
  private function calculateTotalDays($startDate, $endDate)
  {
    $start = new DateTime($startDate);
    $end   = new DateTime($endDate);
    return $end->diff($start)->days + 1;
  }
  public function directExtend()
  {
    $rules = [
      'provider_id'  => 'required',
      'booking_id'   => 'required',
      'total_amount' => 'required',
      'totalDays'    => 'required',
    ];

    if (!$this->validate($rules)) {
      return $this->response->setJSON([
        'status' => false,
        'errors' => $this->validator->getErrors(),
      ]);
    }

    $json = $this->request->getJSON();

    $booking = $this->booking->getWalkingBookingData($json->booking_id);
    if (!$booking) {
      return $this->response->setJSON([
        'status'  => false,
        'message' => 'Booking not found.',
      ]);
    }

    $endDate = $this->calculateEndDate($booking->service_end_date, $json->totalDays, $booking->service_days);

    $bookingUpdated = $this->booking->updateBooking($json->booking_id, $endDate);
    $quoteUpdated   = $this->booking->updateQuote($json->booking_id, $json->provider_id, $json->total_amount);

    if ($bookingUpdated && $quoteUpdated) {
      $this->processWalletUpdates($json);

      return $this->response->setJSON([
        'status'  => true,
        'message' => 'Updated successfully.',
      ]);
    }

    return $this->response->setJSON([
      'status'  => false,
      'message' => 'Update failed.',
    ]);
  }

  private function processWalletUpdates($data)
  {
    $totalPets    = count($data->pet_id);
    $amountPerPet = $data->total_amount / $totalPets;

    foreach ($data->pet_id as $petId) {
      $wallet = [
        'user_id'        => $data->user_id,
        'pet_id'         => $petId,
        'walking_amount' => $amountPerPet,
      ];

      $pwallet = [
        'provider_id'    => $data->provider_id,
        'pet_id'         => $petId,
        'walking_amount' => $amountPerPet,
      ];

      $this->updateWallet($data->user_id, $data->provider_id, $petId, $wallet, $pwallet);
    }
  }

  private function updateWallet($userId, $providerId, $petId, $userWalletData, $providerWalletData)
  {
    if ($this->wallet->checkPetService($userId, $petId)) {
      $this->wallet->updateWalletAmount($userWalletData);
    } else {
      $this->wallet->addWalletAmount($userWalletData);
    }

    if ($this->wallet->checkProviderPetService($providerId, $petId)) {
      $this->wallet->updateProviderWalletAmount($providerWalletData);
    } else {
      $this->wallet->addProviderWalletAmount($providerWalletData);
    }
  }

  public function calculatePricePerWalk($booking, $provider_id, $pets)
  {
    $frequency_per_day = $booking->service_frequency;
    $package_price     = $booking->package_price;
    $startDate         = new DateTime($booking->service_start_date);
    $endDate           = new DateTime($booking->service_end_date);
    $endDate->modify('+1 day');
    $interval   = $startDate->diff($endDate);
    $addons     = $this->booking->getAddonsPrice($booking->id, $provider_id);
    $addonPrice = $addons->extra_amount;

    // Ensure $addonPrice is numeric
    if (is_object($addonPrice)) {
      $addonPrice = property_exists($addonPrice, 'price') ? (float) $addonPrice->price : 0.0;
    } elseif (!is_numeric($addonPrice)) {
      $addonPrice = 0.0;
    }
    if ($booking->service_days == 'weekdays') {
      $period    = new DatePeriod($startDate, new DateInterval('P1D'), $endDate);
      $totalDays = 0;

      foreach ($period as $date) {
        // Check if the day is not Sunday (6 is Sunday in PHP's w format)
        if ($date->format('w') != 0) {
          $totalDays++;
        }
      }
    } else {
      $totalDays = $startDate->diff($endDate)->days;
    }

    // $totalDays     = $interval->days + 1; // +1 to include the start date
    $walk_duration = ($booking->walk_duration == '30 min walk') ? 1 : 2;

    if ($frequency_per_day == 'once a day') {
      $frequency_per_day = 1;
    } elseif ($frequency_per_day == 'twice a day') {
      $frequency_per_day = 2;
    } elseif ($frequency_per_day == 'thrice a day') {
      $frequency_per_day = 3;
    }

    $pricePerWalk = (($package_price * $frequency_per_day * $walk_duration * $totalDays) + $addonPrice)
      / ($frequency_per_day * $totalDays * $pets);

    return $pricePerWalk;
  }
}
