<?php

namespace App\Models;

use CodeIgniter\Model;

class Quotation extends Model
{
  protected $db;
  protected $quotations;
  protected $quotation_addons;
  public function __construct()
  {
    parent::__construct();
    $this->db = \Config\Database::connect();
    $this->quotations = 'quotations';
    $this->quotation_addons = 'quotation_addons';
  }

  public function calculateTotalPrice($packagePrice, $addonsPrice)
  {
    return $packagePrice + $addonsPrice;
  }

  public function calculateBidPrice($totalPrice, $discount)
  {
    return $totalPrice - $discount;
  }

  public function calculateReceivablePrice($bidPrice, $platformCharges)
  {
    return $bidPrice - $platformCharges;
  }

  public function calculatePayableAmount($totalPrice, $gst, $platformCharges, $discount)
  {
    return $totalPrice + ($totalPrice * $gst) + $platformCharges - $discount;
  }

  public function createQuotation($data)
  {
    $this->db->table($this->quotations)->insert($data);
    return $this->db->insertID();
  }

  public function createQuotationAddons($data)
  {
    return $this->db->table($this->quotation_addons)
      ->insert($data);
  }

  public function getQuotations($user_id, $booking_id, $service_id)
  {
    return $this->db->table('quotations')
      ->select('service_providers.id as provider_id, service_providers.name, service_providers.city, service_providers.service_longitude,
       service_providers.service_latitude, service_providers.profile')
      ->select('SUM(sp_reviews.rating) as rating_sum, COUNT(sp_reviews.id) as total_count')
      ->select('quotations.id as quotation_id, quotations.bid_amount, quotations.actual_amount,quotations.sp_timings,quotations.service_mode')

      ->join('service_providers', 'service_providers.id = quotations.provider_id', 'left')
      ->join('sp_reviews', 'sp_reviews.provider_id = service_providers.id', 'left')
      ->join('quotation_addons', 'quotation_addons.quotation_id = quotations.id', 'left')

      ->where('quotations.booking_id', $booking_id)
      ->where('quotations.service_id', $service_id)
      ->where('quotations.status', 'New')
      ->groupBy('quotations.id', 'DESC')
      ->get()
      ->getResult();
  }

  public function quotationAddons($quotation_id)
  {
    return $this->db->table('quotations')
      ->select('quotation_addons.id,quotation_addons.addon')
      ->join('quotation_addons', 'quotation_addons.quotation_id = quotations.id', 'left')
      ->where('quotation_addons.quotation_id', $quotation_id)
      ->get()
      ->getResult();
  }

  public function getQuotationsByProvider($booking_id, $service_id, $provider_id, $quotation_id)
  {
    $quotation = $this->db->table($this->quotations)
      ->select('service_providers.id as provider_id,service_providers.name,service_providers.city,service_providers.service_longitude,
      service_providers.service_latitude,service_providers.profile')
      ->select('SUM(sp_reviews.rating) as rating_sum,COUNT(sp_reviews.id) as total_count')
      ->select('quotations.id as quotation_id,quotations.bid_amount,quotations.sp_timings,quotations.discount,quotations.service_mode')

      ->join('service_providers', 'service_providers.id=quotations.provider_id', 'left')
      ->join('sp_reviews', 'sp_reviews.provider_id=service_providers.id', 'left')

      ->where('quotations.booking_id', $booking_id)
      ->where('quotations.service_id', $service_id)
      ->where('quotations.provider_id', $provider_id)
      ->where('quotations.id', $quotation_id)
      ->where('quotations.status', 'New')
      ->get()
      ->getRow();
    $addons = $this->db->table('quotation_addons')
      ->select('addon, price')
      ->where('quotation_addons.quotation_id', $quotation_id)
      ->get()
      ->getResult();


    $quotation->addons = array_map(function ($addon) {
      return [
        "name" => $addon->addon,
        "price" => $addon->price,
      ];
    }, $addons);

    return $quotation;
  }

  public function getQuotationsByProviderId($booking_id, $service_id, $provider_id, $quotation_id)
  {
    $quotation = $this->db->table($this->quotations)
      ->select('service_providers.id as provider_id,
                  service_providers.name as provider_name,
                  service_providers.city,
                  service_providers.service_longitude,
                  service_providers.service_latitude,
                  service_providers.profile,
                  service_providers.phone,
                  service_providers.service_address,
                  service_providers.gender')
      ->select('SUM(sp_reviews.rating) as rating_sum,
                  COUNT(sp_reviews.id) as total_count')
      ->select('quotations.id as quotation_id,
                  quotations.bid_amount,quotations.sp_timings')
      ->join('service_providers', 'service_providers.id = quotations.provider_id', 'left')
      ->join('sp_reviews', 'sp_reviews.provider_id = service_providers.id', 'left')
      ->where('quotations.booking_id', $booking_id)
      ->where('quotations.service_id', $service_id)
      ->where('quotations.provider_id', $provider_id)
      ->where('quotations.id', $quotation_id)
      ->where('quotations.status', 'Accepted')
      ->get()
      ->getRow();
    $quotation->addons = $this->fetchAddons($quotation_id);

    return $quotation;
  }

  private function fetchAddons($quotation_id)
  {
    $addons = $this->db->table('quotation_addons')
      ->select('addon, price')
      ->where('quotation_addons.quotation_id', $quotation_id)
      ->get()
      ->getResult();

    return array_map(function ($addon) {
      return [
        "name" => $addon->addon,
        "price" => $addon->price,
      ];
    }, $addons);
  }

  public function acceptQuotation($data)
  {
    return $this->db->table($this->quotations)
      ->set('status', 'Accepted')
      ->where(['id' => $data['quotation_id'], 'booking_id' => $data['booking_id'], 'provider_id' => $data['provider_id']])
      ->update();
  }

  public function deleteQuotations($booking_id, $user_id, $service_id)
  {
    return $this->db->table($this->quotations)
      ->where('booking_id', $booking_id)
      // ->where('user_id', $user_id)
      ->where('service_id', $service_id)
      ->delete();
  }

  public function getOldProviders($user_id, $service_id)
  {
    return $providers = $this->db->table($this->quotations)
      ->select('service_providers.id as provider_id,service_providers.name')
      ->join('service_providers', 'service_providers.id=quotations.provider_id', 'left')
      ->where('quotations.user_id', $user_id)
      ->where('quotations.service_id', $service_id)
      ->orWhere('quotations.status', 'New')
      ->orWhere('quotations.status', 'Accepted')
      ->orWhere('quotations.status', 'Cancelled')
      ->get()
      ->getResult();
  }

  public function checkExists($booking_id, $provider_id, $service_id)
  {
    return $this->db->table($this->quotations)
      ->select('*')
      ->where('booking_id', $booking_id)
      ->where('provider_id', $provider_id)
      ->where('service_id', $service_id)
      ->where('status', 'New')
      ->get()
      ->getResult();
  }

  public function updateQuotation($data)
  {
    return $this->db->table($this->quotations)

      ->set('actual_amount', $data['actual_amount'])
      ->set('extra_amount', $data['extra_amount'])
      ->set('discount', $data['discount'])
      ->set('discount_amount', $data['discount_amount'])
      ->set('platform_charges', $data['platform_charges'])
      ->set('total_amount', $data['total_amount'])
      ->set('bid_amount', $data['bid_amount'])
      ->set('receivable_amount', $data['receivable_amount'])
      ->set('addons', $data['addons'])
      ->set('sp_timings', $data['sp_timings'])
      ->set('updated_at', gmdate('Y-m-d H:i:s'))
      ->set('service_mode', $data['service_mode'])


      ->where('provider_id', $data['provider_id'])
      ->where('id', $data['quotation_id'])
      ->where('service_id', $data['service_id'])

      ->update();
  }

  public function updateQuotationAddons($data)
  {
    $exists = $this->db->table($this->quotation_addons)
      ->where([
        'provider_id' => $data['provider_id'],
        'quotation_id' => $data['quotation_id'],
        'service_id' => $data['service_id'],
        'addon' => $data['addon'],
      ])
      ->get()
      ->getRowArray();

    if ($exists) {
      return $this->db->table($this->quotation_addons)
        ->set('price', $data['price'])
        ->set('updated_at', $data['updated_at'])
        ->where([
          'provider_id' => $data['provider_id'],
          'quotation_id' => $data['quotation_id'],
          'service_id' => $data['service_id'],
          'addon' => $data['addon'],
        ])
        ->update();
    } else {
      return $this->db->table($this->quotation_addons)
        ->insert([
          'provider_id' => $data['provider_id'],
          'quotation_id' => $data['quotation_id'],
          'service_id' => $data['service_id'],
          'addon' => $data['addon'],
          'price' => $data['price'],
          'updated_at' => $data['updated_at'],
        ]);
    }
  }

  public function deleteQuotationAddons($quotation_id)
  {
    return $this->db->table($this->quotation_addons)
      ->where('quotation_id', $quotation_id)
      ->delete();
  }
  public function getProviderRatings($provider_id)
  {
    $ratings = $this->db->table('sp_reviews')
      ->select('sp_reviews.id, sp_reviews.provider_id, sp_reviews.user_id, sp_reviews.rating, sp_reviews.comment, users.name')

      ->join('users', 'sp_reviews.user_id = users.id')
      ->where('sp_reviews.provider_id', $provider_id)
      ->get()->getResult();
    return $ratings;
  }

  public function getQuotationAddOns($quotationId)
  {
    $addons = $this->db->table('quotation_addons')
      ->select('addon')
      ->where('quotation_id', $quotationId)
      ->get()
      ->getResult();
    return $addons = array_column($addons, 'addon');
  }
  public function deleteQuotation($provider_id, $quotation_id)
  {
    return $this->db->table($this->quotations)
      ->where('provider_id', $provider_id)
      ->where('id', $quotation_id)
      ->delete();
  }
  public function cancelOtherQuotes($booking_id, $quotation_id)
  {
    return $this->db->table($this->quotations)
      ->where('booking_id', $booking_id)
      ->where('id !=', $quotation_id)
      ->update(['status' => 'Rejected']);
  }
}
