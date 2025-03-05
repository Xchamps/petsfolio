<?php

namespace App\Controllers\api;

use App\Helpers\CommonHelper;
use App\Libraries\PushNotify;
use App\Models\Booking;
use App\Models\Provider;
use App\Models\Quotation;
use App\Models\User;
use CodeIgniter\Controller;

class QuotationController extends Controller
{

  protected $quotations;
  protected $user;
  protected $booking;
  protected $provider;
  protected $pushNotify;

  public function __construct()
  {
    $this->quotations = new Quotation();
    $this->user       = new User();
    $this->booking    = new Booking();
    $this->provider   = new Provider();
    $this->pushNotify = new PushNotify();
  }

  public function create()
  {

    $rules = [
      'booking_id'    => 'required',
      'provider_id'   => 'required|numeric',
      'service_id'    => 'required|numeric',
      'actual_amount' => 'required|decimal',
      'bid_amount'    => 'required|decimal',
    ];

    if (! $this->validate($rules)) {
      return $this->response
        ->setJSON(['status' => false, 'errors' => $this->validator->getErrors()]);
    }
    $this->quotations->db->transStart();
    $json        = $this->request->getJSON();
    $checkExists = $this->quotations->checkExists($json->booking_id, $json->provider_id, $json->service_id);

    if ($checkExists) {
      return $this->response
        ->setJSON(['status' => false, 'message' => 'Quotation already exists']);
    }

    $packagePrice    = (float) $json->actual_amount;
    $discount        = (float) $json->discount;
    $platformCharges = (float) $json->platform_charges;

    $addonsPrice = 0;
    foreach ($json->addons as $addon) {
      if (isset($addon->price) && $addon->price > 0) {
        $addonsPrice += $addon->price;
      }
    }

    $totalPrice      = $this->quotations->calculateTotalPrice($packagePrice, $addonsPrice);
    $receivablePrice = $this->quotations->calculateReceivablePrice($json->bid_amount, $platformCharges);

    if (count($json->addons) > 0) {
      $addons = true;
    } else {
      $addons = false;
    }

    if ($json->service_id == '2' && $json->service_mode == 'van_service') {
      $addonsPrice += getenv('VAN_SERVICE');
    }
    $quotationData = [
      "booking_id"        => $json->booking_id,
      "provider_id"       => $json->provider_id,
      "service_id"        => $json->service_id,
      'actual_amount'     => $packagePrice,
      'extra_amount'      => $addonsPrice,
      'discount'          => $discount,
      'discount_amount'   => $json->discount_amount,
      'platform_charges'  => $platformCharges,
      'total_amount'      => $totalPrice,
      'bid_amount'        => $json->bid_amount,
      'receivable_amount' => $receivablePrice,
      'addons'            => $addons,
      'status'            => 'New',
      'sp_timings'        => implode(',', $json->sp_timings) ?? '',
      'created_at'        => gmdate('Y-m-d H:i:s'),
      'service_mode'      => $json->service_mode ?? '',
    ];

    $quotation_id = $this->quotations->createQuotation((object) $quotationData);
    foreach ($json->addons as $addon) {
      if (isset($addon->price) && $addon->price > 0) {
        $data = [
          'addon'        => $addon->name,
          'price'        => (float) $addon->price,
          'provider_id'  => $json->provider_id,
          'quotation_id' => $quotation_id,
          'service_id'   => $json->service_id,
          'created_at'   => gmdate('Y-m-d H:i:s'),
        ];
        $this->quotations->createQuotationAddons($data);
      }
    }
    if ($json->type) {
      if ($json->type == 'extend') {
        $action = $json->action;
        $this->provider->updateRequest($json->booking_id, $action);
      }
    }
    if ($json->service_id == '4') {
      $table   = 'walking_service_bookings';
      $service = 'walking';
    } else if ($json->service_id == '2') {
      $table   = 'grooming_service_bookings';
      $service = 'grooming';
    }
    $original_booking = $this->booking->getBookingById($json->booking_id);
    $user             = $this->booking->getUserToken($json->booking_id, $table);
    $provider         = $this->provider->getProvider($json->provider_id);
    if ($original_booking) {
      $pet = $this->booking->getPet($original_booking->original_booking_id ?? $json->booking_id);
    } else {
      $pet = $this->booking->getPet($json->booking_id);
    }
    $title   = 'New quotation received';
    $message = $provider->name . ' has sent a ' . $service . ' quote for ' . $pet->name;

    $notifiData = [
      'user_id'   => $user->id,
      'user_type' => 'user',
      'type'      => 'quotation_received',
      'message'   => $message,
    ];

    $this->booking->createNotification($notifiData);
    $response = $this->pushNotify->notify($user->device_token, $title, $message);

    $this->quotations->db->transComplete();

    if ($this->quotations->db->transStatus() === false) {
      return $this->response
        ->setJSON(['status' => false, 'message' => 'Failed to create Quotation']);
    } else {
      return $this->response
        ->setJSON(['status' => true, 'message' => 'Quotation created successfully.']);
    }
  }

  public function update()
  {
    $rules = [
      'quotation_id'  => 'required',
      'booking_id'    => 'required',
      'provider_id'   => 'required|numeric',
      'service_id'    => 'required|numeric',
      'actual_amount' => 'required|decimal',
      'bid_amount'    => 'required|decimal',
    ];

    if (! $this->validate($rules)) {
      return $this->response
        ->setJSON(['status' => false, 'errors' => $this->validator->getErrors()]);
    }
    $this->quotations->db->transStart();
    $json        = $this->request->getJSON();
    $checkExists = $this->quotations->checkExists($json->booking_id, $json->provider_id, $json->service_id);

    if (! $checkExists) {
      return $this->response
        ->setJSON(['status' => false, 'message' => 'Quotation Not exists']);
    }

    $packagePrice    = (float) $json->actual_amount;
    $discount        = (float) $json->discount;
    $platformCharges = (float) $json->platform_charges;

    $addonsPrice = 0;
    foreach ($json->addons as $addon) {
      if (isset($addon->price) && $addon->price > 0) {
        $addonsPrice += $addon->price;
      }
    }

    $totalPrice = $this->quotations->calculateTotalPrice($packagePrice, $addonsPrice);
    // $bidPrice        = $this->quotations->calculateBidPrice($totalPrice, $discount);
    $receivablePrice = $this->quotations->calculateReceivablePrice($json->bid_amount, $platformCharges);
    if (count($json->addons) > 0) {
      $addons = true;
    } else {
      $addons = false;
    }
    if ($json->service_id == '2') {
      if ($json->service_mode == 'van_service') {
        $addonsPrice += getenv('VAN_SERVICE');
      }
    }

    $quotationData = [
      "quotation_id"      => $json->quotation_id,
      "booking_id"        => $json->booking_id,
      "provider_id"       => $json->provider_id,
      "service_id"        => $json->service_id,
      'actual_amount'     => $packagePrice,
      'extra_amount'      => $addonsPrice,
      'discount'          => $discount,
      'discount_amount'   => $json->discount_amount,
      'platform_charges'  => $platformCharges,
      'total_amount'      => $totalPrice,
      'bid_amount'        => $json->bid_amount,
      'receivable_amount' => $receivablePrice,
      'sp_timings'        => implode(',', $json->sp_timings),
      'addons'            => $addons,
      'updated_at'        => gmdate('Y-m-d H:i:s'),
      'service_mode'      => $json->service_mode,

    ];

    $this->quotations->updateQuotation($quotationData);
    if (empty($json->addons)) {
      $this->quotations->deleteQuotationAddons($json->quotation_id);
    } else {
      $this->quotations->deleteQuotationAddons($json->quotation_id);
      foreach ($json->addons as $addon) {
        if (isset($addon->price) && $addon->price > 0) {
          $data = [
            'addon'        => trim($addon->name),
            'price'        => (float) $addon->price,
            'provider_id'  => $json->provider_id,
            'quotation_id' => $json->quotation_id,
            'service_id'   => $json->service_id,
            'updated_at'   => gmdate('Y-m-d H:i:s'),
          ];
          $this->quotations->createQuotationAddons($data);
        } else {
          $this->quotations->deleteQuotationAddons($json->quotation_id);
        }
      }
    }
    if ($json->service_id == '4') {
      $table   = 'walking_service_bookings';
      $service = 'walking';
    } else if ($json->service_id == '2') {
      $table   = 'grooming_service_bookings';
      $service = 'grooming';
    }
    $user     = $this->booking->getUserToken($json->booking_id, $table);
    $provider = $this->provider->getProvider($json->provider_id);
    $pet      = $this->booking->getPet($json->booking_id);
    $title    = 'Quotation updated for ' . $pet->name ?? '';
    $message  = $provider->name . ' has updated there Quoatation for the ' . $pet->name;

    $notifiData = [
      'user_id'   => $user->id,
      'user_type' => 'user',
      'type'      => 'quotation_updated',
      'message'   => $message,
    ];

    $this->booking->createNotification($notifiData);

    $response = $this->pushNotify->notify($user->device_token, $title, $message);
    $this->quotations->db->transComplete();

    if ($this->quotations->db->transStatus() === false) {
      return $this->response
        ->setJSON(['status' => false, 'message' => 'Failed to update Quotation']);
    } else {
      return $this->response
        ->setJSON(['status' => true, 'message' => 'Quotation updated successfully.']);
    }
  }

  public function getQuotations()
  {
    $rules = [
      'user_id'    => 'required|numeric',
      'service_id' => 'required|numeric',
      'booking_id' => 'required',
    ];
    if (! $this->validate($rules)) {
      return $this->response
        ->setJSON(['status' => false, 'errors' => $this->validator->getErrors()]);
    }

    $json       = $this->request->getJSON();
    $user_id    = $json->user_id;
    $booking_id = $json->booking_id;
    $service_id = $json->service_id;
    $response   = $this->quotations->getQuotations($user_id, $booking_id, $service_id);

    if ($response) {
      if ($service_id == '1') {
        $address = $this->booking->getBoardingAddress($user_id, $booking_id);
      } elseif ($service_id == '2') {
        $address = $this->booking->getGroomingAddress($user_id, $booking_id);
      } elseif ($service_id == '3') {
        $address = $this->booking->getTrainingAddress($user_id, $booking_id);
      } elseif ($service_id == '4') {
        $address = $this->booking->getWalkingAddress($user_id, $booking_id);
      }
    } else {
      return $this->response
        ->setJSON(['status' => false, 'message' => 'No quotations found']);
    }
    foreach ($response as &$res) {
      // if ($res->service_mode && $res->service_mode == 'van_service') {
      //   $service = [
      //     'service_mode' => $res->service_mode,
      //     // 'price'        => getenv('VAN_SERVICE'),
      //   ];
      // }
      $addOns       = $this->quotations->quotationAddons($res->quotation_id);
      $res->addons  = count($addOns);
      $user         = $this->user->getUserAddress($user_id, $address->address_id);
      $latFrom      = $user->latitude;
      $lonFrom      = $user->longitude;
      $latTo        = $res->service_latitude;
      $lonTo        = $res->service_longitude;
      $distance     = CommonHelper::distanceCalculator($latFrom, $lonFrom, $latTo, $lonTo);
      if ($res->total_count > 0) {
        $rating      = CommonHelper::ratingCalculator($res->rating_sum, $res->total_count);
        $res->rating = number_format($rating, 1) . ' (' . $res->total_count . ')';
      } else {
        $res->rating = 0;
      }

      $res->distance = number_format((float) $distance, 1);
      if ($res->profile) {
        $res->profile = base_url() . 'public/uploads/providers/' . $res->profile;
      }

      unset($res->service_longitude);
      unset($res->service_latitude);
      unset($res->rating_sum);
      unset($res->total_count);
    }
    usort($response, function ($a, $b) {
      return $a->distance <=> $b->distance;
    });

    if ($response) {
      return $this->response
        ->setJSON(['status' => true, 'data' => $response]);
    } else {
      return $this->response
        ->setJSON(['status' => false, 'message' => 'No quotations found']);
    }
  }

  public function filter()
  {
    $rules = [
      'user_id'           => 'required|numeric',
      'service_id'        => 'required|numeric',
      'booking_id'        => 'required',
      'my_preferred_time' => 'permit_empty|in_list[1,0]',
      'repeated_walker'   => 'permit_empty|in_list[1,0]',
      'top_rated'         => 'permit_empty|in_list[1,0]',
      'low_to_high'       => 'permit_empty|in_list[1,0]',
      'near_by_me'        => 'permit_empty|in_list[1,0]',
      'add_ons_first'     => 'permit_empty|in_list[1,0]',
    ];

    if (! $this->validate($rules)) {
      return $this->response
        ->setJSON(['status' => false, 'errors' => $this->validator->getErrors()]);
    }

    $json       = $this->request->getJSON();
    $user_id    = $json->user_id;
    $booking_id = $json->booking_id;
    $service_id = $json->service_id;

    // Fetch Quotations
    $quotations = $this->quotations->getQuotations($user_id, $booking_id, $service_id);
    if ($service_id == '4') {
      $user       = $this->booking->getWalkingBookingAddress($user_id, $booking_id);

      $booking    = $this->booking->getWalkingBooking($booking_id, $user_id);
    } else if ($service_id == '2') {
      $user       = $this->booking->getGroomingAddress($user_id, $booking_id);

      $booking    = $this->booking->getGroomingBooking($booking_id, $user_id);
    }
    if (empty($quotations)) {
      return $this->response
        ->setJSON(['status' => false, 'message' => 'No quotations found']);
    }

    // Apply Filters
    if (! empty($json->my_preferred_time) && $json->my_preferred_time == 1) {
      $quotations = $this->filterByPreferredTime($quotations, $booking);
    }

    if (! empty($json->repeated_walker) && $json->repeated_walker == 1) {
      $quotations = $this->filterByRepeatedWalker($quotations, $user_id, $service_id);
    }

    if (! empty($json->top_rated) && $json->top_rated == 1) {
      $quotations = $this->filterByTopRated($quotations);
    }

    if (! empty($json->low_to_high) && $json->low_to_high == 1) {
      $quotations = $this->sortByBidPrice($quotations);
    }

    if (! empty($json->near_by_me) && $json->near_by_me == 1) {
      $quotations = $this->filterByNearby($quotations, $user_id, $booking_id);
    }

    if (! empty($json->add_ons_first) && $json->add_ons_first == 1) {
      $quotations = $this->filterByAddOns($quotations, $booking);
    }
    foreach ($quotations as $quot) {
      $addOns       = $this->quotations->quotationAddons($quot->quotation_id);
      $quot->addons = count($addOns);
      $distance     = CommonHelper::distanceCalculator($user->latitude, $user->longitude, $quot->service_latitude, $quot->service_longitude);
      if ($quot->total_count > 0) {
        $rating       = CommonHelper::ratingCalculator($quot->rating_sum, $quot->total_count);
        $quot->rating = number_format($rating, 1) . ' (' . $quot->total_count . ')';
      }
      $quot->distance = number_format((float) $distance, 1);

      $quot->profile = base_url() . 'public/uploads/providers/' . $quot->profile;

      unset($quot->service_longitude);
      unset($quot->service_latitude);
      unset($quot->rating_sum);
      unset($quot->total_count);
    }
    return $this->response
      ->setJSON(['status' => true, 'quotations' => $quotations]);
  }

  private function filterByPreferredTime($quotations, $booking)
  {

    $booking_times = explode(',', $booking->preferable_time);
    return array_filter($quotations, function ($quotation) use ($booking_times) {

      $sp_times = explode(',', $quotation->sp_timings);

      $matched_times = array_intersect($sp_times, $booking_times);

      return ! empty($matched_times);
    });
  }

  private function filterByRepeatedWalker($quotations, $user_id, $service_id)
  {
    $providers = $this->quotations->getOldProviders($user_id, $service_id);

    $providerIds = array_map(function ($provider) {
      return $provider->provider_id;
    }, $providers);

    return array_filter($quotations, function ($quotation) use ($providerIds) {
      return in_array($quotation->provider_id, $providerIds);
    });
  }

  private function filterByTopRated($quotations)
  {
    foreach ($quotations as $quotation) {
      $ratings = $this->quotations->getProviderRatings($quotation->provider_id);

      if (! empty($ratings)) {
        $totalRatings  = count($ratings);
        $sumRatings    = array_sum(array_column($ratings, 'rating'));
        $averageRating = $totalRatings > 0 ? $sumRatings / $totalRatings : 0;

        $quotation->rating = number_format($averageRating, 1);
      } else {
        $quotation->rating = 0;
      }
    }

    usort($quotations, function ($a, $b) {
      return $b->rating <=> $a->rating;
    });

    return $quotations;
  }

  private function sortByBidPrice($quotations)
  {
    usort($quotations, function ($a, $b) {
      return $b->bid_amount <=> $a->bid_amount;
    });
    return $quotations;
  }

  private function filterByNearby($quotations, $user_id, $booking_id, $maxDistance = 10)
  {
    $user = $this->booking->getWalkingBookingAddress($user_id, $booking_id);

    return array_filter($quotations, function ($quotation) use ($user, $maxDistance) {
      $distance = CommonHelper::distanceCalculator(
        $quotation->service_latitude,
        $quotation->service_longitude,
        $user->latitude,
        $user->longitude
      );

      return $distance < $maxDistance;
    });
  }

  private function filterByAddOns($quotations, $booking)
  {
    $bookingAddOns = $booking->addons;

    return array_filter($quotations, function ($quotation) use ($bookingAddOns) {

      $quotationAddOns = $this->quotations->getQuotationAddOns($quotation->quotation_id);
      $matchingAddOns  = array_intersect($bookingAddOns, $quotationAddOns);
      return ! empty($matchingAddOns);
    });
  }
  public function delete()
  {
    $rules = [
      'provider_id'  => 'required',
      'quotation_id' => 'required',
    ];

    if (! $this->validate($rules)) {
      return $this->response
        ->setJSON(['status' => false, 'errors' => $this->validator->getErrors()]);
    }
    $json = $this->request->getJSON();
    $this->quotations->deleteQuotation($json->provider_id, $json->quotation_id);
    return $this->response
      ->setJSON(['status' => true, 'message' => 'Quotation deleted successfully.']);
  }
}
