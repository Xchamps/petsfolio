<?php

namespace App\Models;

use CodeIgniter\Model;

class Provider extends Model
{
  protected $db;
  protected $provider;
  public function __construct()
  {
    parent::__construct();
    $this->db       = \Config\Database::connect();
    $this->provider = 'service_providers';
  }

  public function createProvider($data)
  {
    $this->db->table($this->provider)->insert($data);
    return $this->db->insertID();
  }

  public function getProviders()
  {
    return $this->db->table($this->provider)
      ->get()->getResult();
  }

  public function getProvider($provider_id)
  {
    return $this->db->table($this->provider)
      ->select([
        'id',
        "name",
        "email",
        "phone",
        "gender",
        "dob",
        "profile",
        "permanent_address",
        "area",
        "service_address",
        "service_latitude",
        "service_longitude",
        "city",
        "state",
        "zip_code",
        "status",
        "device_token",
        "auth_token",
        "aadhar_name",
        "aadhar_number",

      ])
      ->where('id', $provider_id)
      ->get()
      ->getRow();
  }

  public function checkPhone($phone)
  {
    return $this->db->table($this->provider)
      ->select('id')
      ->where('phone', $phone)
      ->get()
      ->getRow();
  }

  public function checkEmail($email)
  {
    return $this->db->table($this->provider)
      ->select('id')
      ->where('email', $email)
      ->get()
      ->getRow();
  }

  public function checkExists($email, $phone)
  {
    return $this->db->table($this->provider)
      ->select('id')
      ->where('phone', $phone)
      ->where('email', $email)
      ->get()
      ->getRow();
  }

  public function removeOtp($email, $phone)
  {
    return $this->db->table($this->provider)->update(
      [
        'mobile_otp' => null,
      ],
      [
        'email' => $email,
        'phone' => $phone,
      ]
    );
  }

  public function updateToken($email, $phone, $token)
  {
    return $this->db->table($this->provider)
      ->set('auth_token', $token)
      ->where('phone', $phone)
      ->where('email', $email)
      ->update();
  }

  public function getToken($token)
  {
    return $this->db->table($this->provider)
      ->select('id')
      ->where('auth_token', $token)
      ->get()
      ->getRow();
  }

  public function check($email, $phone, $otp)
  {
    return $this->db->table($this->provider)
      ->select('id,email,phone,name')
      ->where('phone', $phone)
      ->where('email', $email)
      ->where('mobile_otp', $otp)
      ->get()
      ->getRow();
  }

  public function updateOTP($data)
  {
    return $this->db->table($this->provider)
      ->set('mobile_otp', $data['mobile_otp'])
      ->set('ip_address', $data['ip_address'])
      ->set('device_token', $data['device_token'])
      ->where('phone', $data['phone'])
      ->where('email', $data['email'])
      ->update();
  }

  public function checkPersonalDetails($email, $phone)
  {
    return $this->db->table($this->provider)
      ->select('name,gender,dob,profile,permanent_address,service_address')
      ->where('phone', $phone)
      ->where('email', $email)
      ->get()
      ->getRow();
  }

  public function checkServiceDetails($id)
  {
    return $this->db->table('sp_services')
      ->where('provider_id', $id)
      ->get()
      ->getResult();
  }

  public function checkVerificationDetails($id)
  {
    return $this->db->table('sp_verifications')
      ->select('email_verified,mobile_verified,address_proof_verified,identity_proof_verified,police_verification_verified,post_card_verified,certificate_verified,')
      ->where('provider_id', $id)
      ->get()
      ->getRow();
  }

  public function updateProfile($data)
  {
    return $this->db->table($this->provider)
      ->set('name', $data['name'])
      ->set('phone', $data['phone'])
      ->set('email', $data['email'])
      ->set('gender', $data['gender'])
      ->set('dob', $data['dob'])
      // ->set('permanent_address', $data['permanent_address'])
      // ->set('permanent_latitude', $data['permanent_latitude'])
      ->set('area', $data['area'])
      ->set('service_address', $data['service_address'])
      ->set('service_latitude', $data['service_latitude'])
      ->set('service_longitude', $data['service_longitude'])
      ->set('city', $data['city'])
      ->set('state', $data['state'])
      ->set('zip_code', $data['zip_code'])
      ->set('profile', $data['profile'])
      ->set('ip_address', $data['ip_address'])
      ->set('updated_at', gmdate('Y-m-d H:i:s'))

      ->where('id', $data['provider_id'])

      ->update();
  }

  public function addBank($data)
  {
    return $this->db->table('sp_bank_details')
      ->insert($data);
  }

  public function getRatings($provider_id)
  {
    // Fetch the provider details, ratings
    $data = $this->db->table($this->provider)
      ->select('users.name as user_name, users.profile as profile')
      ->select('sp_reviews.rating, sp_reviews.comment, sp_reviews.created_at as rating_at')
      ->join('sp_reviews', 'sp_reviews.provider_id = service_providers.id', 'inner')
      ->join('users', 'users.id = sp_reviews.user_id', 'inner')
      ->where('service_providers.id', $provider_id)
      ->orderBy('sp_reviews.id')
      ->groupBy('sp_reviews.id')
      ->get()
      ->getResult();

    return $data;
  }


  public function getPhotos($provider_id)
  {
    // Fetch the provider photos
    $data = $this->db->table($this->provider)
      ->select('sp_service_images.file_name,sp_service_images.file_type, sp_service_images.created_at as uploaded_at')
      ->join('sp_service_images', 'sp_service_images.provider_id = service_providers.id', 'left')
      ->where('sp_service_images.provider_id', $provider_id)
      ->get()
      ->getResult();

    return $data;
  }

  public function getVerfications($provider_id)
  {
    $data = $this->db->table($this->provider)
      ->select('service_providers.name as sp_name,profile')
      ->select('sp_verifications.email_verified, sp_verifications.mobile_verified, sp_verifications.identity_proof_verified,sp_verifications.address_proof_verified,sp_verifications.identity_proof_verified,sp_verifications.police_verification_verified,sp_verifications.post_card_verified,sp_verifications.certificate_verified,sp_verifications.profile_picture_verified')
      ->join('sp_verifications', 'sp_verifications.provider_id = service_providers.id', 'left')
      ->where('service_providers.id', $provider_id)
      ->get()
      ->getRow();

    return $data;
  }
  public function getAllVerifications($provider_id)
  {
    $data = $this->db->table($this->provider)
      ->select('sp_verifications.email_verified, sp_verifications.mobile_verified, sp_verifications.identity_proof_verified,sp_verifications.address_proof_verified,sp_verifications.identity_proof_verified,sp_verifications.police_verification_verified,sp_verifications.post_card_verified,sp_verifications.certificate_verified,sp_verifications.profile_picture_verified, sp_verifications.police_verification, sp_verifications.certificate')
      ->join('sp_verifications', 'sp_verifications.provider_id = service_providers.id', 'left')
      ->where('service_providers.id', $provider_id)
      ->get()
      ->getRow();

    return $data;
  }
  public function getAverageRating($provider_id)
  {
    // Calculate the average rating and total number of reviews
    $ratingData = $this->db->table('sp_reviews')
      ->select('AVG(rating) as avg_rating, COUNT(id) as total_reviews')
      ->where('provider_id', $provider_id)
      ->get()
      ->getRow();

    if ($ratingData) {
      $avgRating    = number_format((float) $ratingData->avg_rating, 1);
      $totalReviews = $ratingData->total_reviews;
      return "$avgRating ($totalReviews)";
    } else {
      return "0.0 (0)";
    }
  }

  public function getTotalCompletedBookings($provider_id)
  {

    // Walking Bookings
    $walkingBookings = $this->db->table('walking_service_bookings')
      ->join('quotations', 'walking_service_bookings.id = quotations.booking_id')
      ->where('quotations.provider_id', $provider_id)
      ->where('walking_service_bookings.status', 'Completed')
      ->countAllResults();

    // Training Bookings
    $trainingBookings = $this->db->table('training_service_bookings')
      ->join('quotations', 'training_service_bookings.id = quotations.booking_id')
      ->where('quotations.provider_id', $provider_id)
      ->where('training_service_bookings.status', 'Completed')
      ->countAllResults();

    // Boarding Bookings
    $boardingBookings = $this->db->table('boarding_service_bookings')
      ->join('quotations', 'boarding_service_bookings.id = quotations.booking_id')
      ->where('quotations.provider_id', $provider_id)
      ->where('boarding_service_bookings.status', 'Completed')
      ->countAllResults();

    // Grooming Bookings
    $groomingBookings = $this->db->table('grooming_service_bookings')
      ->join('quotations', 'grooming_service_bookings.id = quotations.booking_id')
      ->where('quotations.provider_id', $provider_id)
      ->where('grooming_service_bookings.status', 'Completed')
      ->countAllResults();

    // Total completed bookings
    return $walkingBookings + $trainingBookings + $boardingBookings + $groomingBookings;
  }

  public function getWalkingBookingAddress($user_id, $booking_id)
  {
    $walkingBookingAddress = $this->db->table('walking_service_bookings')

      ->select('user_addresses.city,user_addresses.latitude,user_addresses.longitude')

      ->join('users', 'walking_service_bookings.user_id = users.id')
      ->join('user_addresses', 'user_addresses.id = walking_service_bookings.address_id')

      ->where('walking_service_bookings.id', $booking_id)
      ->where('users.id', $user_id)
      ->get()->getRow();
    return $walkingBookingAddress;
  }

  public function getGroomingBookingAddress($user_id, $booking_id)
  {
    return $this->db->table('grooming_service_bookings')
      ->select('user_addresses.city,user_addresses.latitude,user_addresses.longitude')
      ->join('users', 'grooming_service_bookings.user_id = users.id')
      ->join('user_addresses', 'user_addresses.id = grooming_service_bookings.address_id')
      ->where('grooming_service_bookings.id', $booking_id)
      ->where('users.id', $user_id)
      ->get()->getRow();
  }

  public function getTrainingBookingAddress($user_id, $booking_id)
  {
    return $this->db->table('training_service_bookings')
      ->select('user_addresses.city,user_addresses.latitude,user_addresses.longitude')
      ->join('users', 'training_service_bookings.user_id = users.id')
      ->join('user_addresses', 'user_addresses.id = training_service_bookings.address_id')
      ->where('training_service_bookings.id', $booking_id)
      ->where('users.id', $user_id)
      ->get()->getRow();
  }

  public function getBoardingBookingAddress($user_id, $booking_id)
  {
    return $this->db->table('boarding_service_bookings')
      ->select('user_addresses.city,user_addresses.latitude,user_addresses.longitude')
      ->join('users', 'boarding_service_bookings.user_id = users.id')
      ->join('user_addresses', 'user_addresses.id = boarding_service_bookings.address_id')
      ->where('boarding_service_bookings.id', $booking_id)
      ->where('users.id', $user_id)
      ->get()->getRow();
  }

  public function addWalkingDetails($data)
  {
    return $this->db->table('sp_walking_details')
      // ->set('service_id', $data['service_id'])
      ->set('provider_id', $data['provider_id'])
      ->set('service_radius_km', $data['service_radius_km'])
      ->set('can_handle_aggressive_pets', $data['can_handle_aggressive_pets'])
      ->set('is_certified_walker', $data['is_certified'])
      ->set('certificate', $data['certificate'])
      ->set('is_accidentally_insured', $data['is_accidentally_insured'])
      ->set('emergency_contact_name', $data['emergency_contact_name'])
      ->set('emergency_contact_phone', $data['emergency_contact_phone'])
      ->set('created_at', gmdate('Y-m-d H:i:s'))
      ->insert();
  }
  public function addGroomingDetails($data)
  {
    return $this->db->table('sp_grooming_details')
      ->set('provider_id', $data['provider_id'])
      ->set('service_radius_km', $data['service_radius_km'])
      ->set('can_handle_aggressive_pets', $data['can_handle_aggressive_pets'])
      ->set('grooming_type', $data['type'])
      ->set('accepted_pets', $data['accepted_pets'])
      ->set('is_certified_groomer', $data['is_certified'])
      ->set('certificate', $data['certificate'])
      ->set('service_location', $data['service_location'])
      ->set('is_accidentally_insured', $data['is_accidentally_insured'])
      ->set('emergency_contact_name', $data['emergency_contact_name'])
      ->set('emergency_contact_phone', $data['emergency_contact_phone'])
      ->set('created_at', gmdate('Y-m-d H:i:s'))
      ->insert();
  }
  public function addTrainingDetails($data)
  {
    return $this->db->table('sp_training_details')
      ->set('provider_id', $data['provider_id'])
      ->set('accepted_pets', $data['accepted_pets'])
      ->set('can_handle_aggressive_pets', $data['can_handle_aggressive_pets'])
      ->set('training_type', $data['type'])
      ->set('training_method', $data['training_method'])
      ->set('is_certified_trainer', $data['is_certified'])
      ->set('certificate', $data['certificate'])
      ->set('is_accidentally_insured', $data['is_accidentally_insured'])
      ->set('emergency_contact_name', $data['emergency_contact_name'])
      ->set('emergency_contact_phone', $data['emergency_contact_phone'])
      ->set('created_at', gmdate('Y-m-d H:i:s'))
      ->insert();
  }
  public function addBoardingDetails($data)
  {
    return $this->db->table('sp_boarding_details')
      ->set('provider_id', $data['provider_id'])
      ->set('accepted_pets', $data['accepted_pets'])
      ->set('can_handle_aggressive_pets', $data['can_handle_aggressive_pets'])
      ->set('boarding_type', $data['type'])
      ->set('max_pets_capacity', $data['max_pets_capacity'])
      ->set('food_type', $data['food_type'])
      ->set('is_certified_boarder', $data['is_certified'])
      ->set('certificate', $data['certificate'])
      ->set('is_accidentally_insured', $data['is_accidentally_insured'])
      ->set('emergency_contact_name', $data['emergency_contact_name'])
      ->set('emergency_contact_phone', $data['emergency_contact_phone'])
      ->set('created_at', gmdate('Y-m-d H:i:s'))
      ->insert();
  }

  public function checkWalk($data)
  {
    return $this->db->table('walking_tracking')
      ->where('booking_id', $data['booking_id'])
      ->where('provider_id', $data['provider_id'])
      ->where('pet_id', $data['pet_id'])
      ->where('service_time', $data['service_time'])
      ->where('tracking_date', gmdate('Y-m-d'))
      ->whereIn('status', ['started', 'in_progress'])
      ->get()->getRow();
  }
  public function startWalk($data)
  {
    return $this->db->table('walking_tracking')
      ->set('booking_id', $data['booking_id'])
      ->set('provider_id', $data['provider_id'])
      ->set('pet_id', $data['pet_id'])
      ->set('service_time', $data['service_time'])
      ->set('tracking_date', gmdate('Y-m-d'))
      ->set('start_time', gmdate('Y-m-d H:i:s'))
      ->set('created_at', gmdate('Y-m-d H:i:s'))
      ->set('status', 'in_progress')
      ->insert();
  }

  public function endWalk($data)
  {
    return $this->db->table('walking_tracking')

      ->set('end_time', gmdate('Y-m-d H:i:s'))
      ->set('updated_at', gmdate('Y-m-d H:i:s'))
      ->set('status', 'completed')
      ->where('booking_id', $data['booking_id'])
      ->where('provider_id', $data['provider_id'])
      ->where('pet_id', $data['pet_id'])
      ->where('DATE(created_at)', gmdate('Y-m-d'))

      ->update();
  }

  public function completeWalk($data)
  {
    return $this->db->table('walking_tracking')

      ->set('completed_by', 'user')
      ->set('updated_at', gmdate('Y-m-d H:i:s'))
      ->set('status', 'completed')
      ->where('booking_id', $data['booking_id'])
      ->where('provider_id', $data['provider_id'])
      ->where('pet_id', $data['pet_id'])
      ->where('DATE(created_at)', gmdate('Y-m-d'))

      ->update();
  }

  public function getTracking($booking_id, $provider_id, $pet_id)
  {
    $today = date('Y-m-d');
    // $tomorrow = date("Y-m-d", strtotime("+1 day"));

    return $this->db->table('walking_tracking')

      ->select('walking_tracking.service_time, walking_tracking.status')
      ->where('booking_id', $booking_id)
      ->where('provider_id', $provider_id)
      ->where('tracking_date', $today)
      ->where('pet_id', $pet_id)
      ->get()->getResult();
  }

  public function checkWalkInProgress($booking_id, $provider_id, $pet_id, $service_time)
  {
    return $this->db->table('walking_tracking')

      ->where('booking_id', $booking_id)
      ->where('provider_id', $provider_id)
      ->where('pet_id', $pet_id)
      ->where('service_time', $service_time)
      ->where('status', 'in_progress')
      ->where('DATE(created_at)', gmdate('Y-m-d'))
      ->get()->getRow();
  }

  public function myWalkingQuotes($provider_id)
  {
    $today = date('Y-m-d');

    // Walking Bookings
    return $this->db->table('walking_service_bookings')
      ->select('walking_service_bookings.service_frequency,walking_service_bookings.walk_duration,walking_service_bookings.service_days,
      walking_service_bookings.service_start_date,walking_service_bookings.service_end_date,walking_service_bookings.preferable_time,
      walking_service_bookings.created_at,walking_service_bookings.id as booking_id,walking_service_bookings.total_price as total_price')
      ->select('user_addresses.latitude,user_addresses.longitude')
      ->select('quotations.id as quotation_id,quotations.bid_amount as bid_amount, quotations.sp_timings as sp_timings,quotations.discount as discount,quotations.receivable_amount')
      ->select('packages.package_name as package_name ,packages.price as package_price,packages.duration_days as days')
      ->select('service_providers.name as provider_name,service_providers.profile as provider_profile')

      ->join('quotations', 'walking_service_bookings.id = quotations.booking_id', 'left')
      ->join('packages', 'packages.id = walking_service_bookings.package_id', 'left')
      ->join('user_addresses', 'user_addresses.id = walking_service_bookings.address_id', 'left')
      ->join('service_providers', 'service_providers.id = quotations.provider_id', 'left')

      ->where('quotations.provider_id', $provider_id)
      ->where('walking_service_bookings.status', 'New')

      ->where('walking_service_bookings.service_start_date >=', $today)

      ->orderBy('walking_service_bookings.id', 'DESC')

      ->groupBy('walking_service_bookings.id')

      ->get()->getResult();
  }

  public function myTrainingQuotes($provider_id)
  {
    // Training Bookings
    return $this->db->table('training_service_bookings')
      ->join('quotations', 'training_service_bookings.id = quotations.booking_id', 'left')
      ->where('quotations.provider_id', $provider_id)
      ->where('training_service_bookings.status', 'New')
      ->orderBy('training_service_bookings.id', 'DESC')
      ->get()->getResult();
  }

  public function myBoardingQuotes($provider_id)
  {
    // Boarding Bookings
    return $this->db->table('boarding_service_bookings')

      ->select('boarding_service_bookings.service_start_date,boarding_service_bookings.service_end_date,boarding_service_bookings.preferable_time,boarding_service_bookings.created_at,boarding_service_bookings.id as booking_id')
      ->select('user_addresses.latitude,user_addresses.longitude')
      ->select('quotations.id as quotation_id,quotations.bid_amount as bid_amount')
      ->select('packages.package_name as package_name ,packages.price as package_price,packages.duration_days as days')
      ->select('service_providers.name as provider_name,service_providers.profile as provider_profile')

      ->join('quotations', 'boarding_service_bookings.id = quotations.booking_id', 'left')
      ->join('packages', 'packages.id = boarding_service_bookings.package_id', 'left')
      ->join('user_addresses', 'user_addresses.id = boarding_service_bookings.address_id', 'left')
      ->join('service_providers', 'service_providers.id = quotations.provider_id', 'left')
      ->where('quotations.provider_id', $provider_id)
      ->where('boarding_service_bookings.status', 'New')
      ->orderBy('boarding_service_bookings.id', 'DESC')
      ->get()->getResult();
  }

  public function myGroomingQuotes($provider_id)
  {
    // Grooming Bookings
    $groomingBookings = $this->db->table('grooming_service_bookings')
      ->select('grooming_service_bookings.service_start_date,grooming_service_bookings.preferable_time,
      grooming_service_bookings.created_at,grooming_service_bookings.id as booking_id,grooming_service_bookings.total_price as total_price')
      ->select('user_addresses.latitude,user_addresses.longitude')
      ->select('quotations.id as quotation_id,quotations.bid_amount as bid_amount,quotations.sp_timings as sp_timings,quotations.discount as discount,quotations.service_mode,quotations.receivable_amount')
      ->select('service_providers.name as provider_name,service_providers.profile as provider_profile')

      ->join('user_addresses', 'user_addresses.id = grooming_service_bookings.address_id', 'left')
      ->join('quotations', 'grooming_service_bookings.id = quotations.booking_id', 'left')
      ->join('service_providers', 'service_providers.id = quotations.provider_id', 'left')

      ->where('quotations.provider_id', $provider_id)
      ->where('grooming_service_bookings.status', 'New')
      ->orderBy('grooming_service_bookings.id', 'DESC')
      ->get()->getResult();

    $bookingIds = array_column($groomingBookings, 'booking_id');
    $packages   = [];
    $addons     = [];

    if (!empty($bookingIds)) {
      $packageResults = $this->db->table('grooming_booking_packages')
        ->select('grooming_booking_packages.booking_id, packages.id as package_id, packages.package_name as package_name,packages.price,packages.included_addons')
        ->join('packages', 'packages.id = grooming_booking_packages.package_id', 'left')
        ->whereIn('grooming_booking_packages.booking_id', $bookingIds)
        ->get()
        ->getResult();

      foreach ($packageResults as $package) {
        $packages[$package->booking_id][] = $package;
        $package->included_addons         = !empty($package->included_addons) ? explode(',', (string) $package->included_addons) : [];
      }
    }
    foreach ($groomingBookings as $booking) {
      $booking->packages = $packages[$booking->booking_id] ?? [];
    }
    return $groomingBookings;
  }

  public function getPetDetails($booking_id)
  {
    return $this->db->table('booking_pets')
      ->select('user_pets.name,user_pets.image')
      ->join('user_pets', 'user_pets.id = booking_pets.pet_id')
      ->where('booking_pets.booking_id', $booking_id)
      ->get()->getResult();
  }

  public function getTotalQuotes($booking_id, $service_id)
  {
    return $this->db->table('quotations')
      ->where('quotations.booking_id', $booking_id)
      ->where('quotations.service_id', $service_id)
      ->countAllResults();
  }

  public function getAddress($address_id)
  {
    return $this->db->table('user_addresses')
      ->select('latitude,longitude')
      ->get()->getRow();
  }
  public function getTemporaryBooking($original_booking_id)
  {
    return $this->db->table('walking_service_bookings')
      ->select('users.name,users.phone,users.gender,users.profile')
      ->select('walking_service_bookings.id,walking_service_bookings.service_frequency,walking_service_bookings.walk_duration,walking_service_bookings.service_days,
      walking_service_bookings.service_start_date,walking_service_bookings.service_end_date,walking_service_bookings.preferable_time,walking_service_bookings.created_at,
      walking_service_bookings.id as booking_id,walking_service_bookings.original_booking_id as original_booking_id')
      ->select('user_addresses.latitude,user_addresses.longitude,user_addresses.city,user_addresses.address')
      ->select('quotations.provider_id as provider_id,quotations.id as quotation_id,quotations.bid_amount as bid_amount,quotations.sp_timings')
      ->select('packages.package_name as package_name ,packages.price as package_price,packages.duration_days as days')
      ->select('services.name as service_name')

      ->join('services', 'services.id = walking_service_bookings.service_id', 'left')
      ->join('users', 'users.id = walking_service_bookings.user_id', 'left')
      ->join('quotations', 'walking_service_bookings.id = quotations.booking_id', 'left')
      ->join('packages', 'packages.id = walking_service_bookings.package_id', 'left')
      ->join('user_addresses', 'user_addresses.id = walking_service_bookings.address_id', 'left')

      ->where('walking_service_bookings.status', 'Confirmed')
      ->where('quotations.status', 'Accepted')
      ->where('original_booking_id', $original_booking_id)

      ->orderBy('walking_service_bookings.id', 'DESC')

      ->groupBy('walking_service_bookings.id')

      ->get()->getRow();
  }
  public function activeWalkingJobs($provider_id)
  {
    // Walking Bookings
    $today           = date('Y-m-d');
    $walkingBookings = $this->db->table('walking_service_bookings')
      ->select('users.name,users.phone,users.gender,users.profile')
      ->select('walking_service_bookings.id,walking_service_bookings.service_frequency,walking_service_bookings.walk_duration,walking_service_bookings.service_days,
      walking_service_bookings.service_start_date,walking_service_bookings.service_end_date,walking_service_bookings.preferable_time,walking_service_bookings.created_at,
      walking_service_bookings.id as booking_id,walking_service_bookings.original_booking_id as original_booking_id')
      ->select('user_addresses.latitude,user_addresses.longitude,user_addresses.city,user_addresses.address')
      ->select('quotations.id as quotation_id,quotations.bid_amount as bid_amount,quotations.sp_timings')
      ->select('packages.package_name as package_name ,packages.price as package_price,packages.duration_days as days')
      ->select('services.name as service_name')

      ->join('services', 'services.id = walking_service_bookings.service_id', 'left')
      ->join('users', 'users.id = walking_service_bookings.user_id', 'left')
      ->join('quotations', 'walking_service_bookings.id = quotations.booking_id', 'left')
      ->join('packages', 'packages.id = walking_service_bookings.package_id', 'left')
      ->join('user_addresses', 'user_addresses.id = walking_service_bookings.address_id', 'left')

      ->where('quotations.provider_id', $provider_id)
      ->where('walking_service_bookings.status', 'Confirmed')
      ->where('quotations.status', 'Accepted')

      // ->where('walking_service_bookings.service_start_date <=', $today)
      // ->where('walking_service_bookings.service_end_date >=', $today)

      ->orderBy('walking_service_bookings.id', 'DESC')

      ->groupBy('walking_service_bookings.id')

      ->get()->getResult();

    return $walkingBookings;
  }

  public function activeTrainingJobs($provider_id)
  {
    // Training Bookings
    return $this->db->table('training_service_bookings')
      ->join('quotations', 'training_service_bookings.id = quotations.booking_id', 'left')
      ->where('quotations.provider_id', $provider_id)
      ->where('training_service_bookings.status', 'Confirmed')
      ->get()->getResult();
  }

  public function activeBoardingJobs($provider_id)
  {
    // Boarding Bookings
    return $this->db->table('boarding_service_bookings')
      ->join('quotations', 'boarding_service_bookings.id = quotations.booking_id', 'left')
      ->where('quotations.provider_id', $provider_id)
      ->where('boarding_service_bookings.status', 'Confirmed')
      ->get()->getResult();
  }

  public function activeGroomingJobs($provider_id)
  {
    $groomingBookings = $this->db->table('grooming_service_bookings')
      ->select('users.name, users.phone,users.gender,users.profile')
      ->select('grooming_service_bookings.service_start_date, grooming_service_bookings.preferable_time, 
      grooming_service_bookings.created_at, grooming_service_bookings.id as booking_id, grooming_service_bookings.track_status')
      ->select('user_addresses.latitude, user_addresses.longitude,user_addresses.city,user_addresses.address')
      ->select('quotations.id as quotation_id, quotations.bid_amount, quotations.sp_timings')
      ->select('services.name as service_name')

      ->join('services', 'services.id = grooming_service_bookings.service_id', 'left')
      ->join('users', 'users.id = grooming_service_bookings.user_id', 'left')
      ->join('quotations', 'grooming_service_bookings.id = quotations.booking_id', 'left')
      ->join('user_addresses', 'user_addresses.id = grooming_service_bookings.address_id', 'left')
      ->where('quotations.provider_id', $provider_id)
      ->where('grooming_service_bookings.status', 'Confirmed')
      ->where('quotations.status', 'Accepted')

      ->get()->getResult();

    if (empty($groomingBookings)) {
      return [];
    }

    $bookingIds = array_column($groomingBookings, 'booking_id');

    $packageResults = $this->db->table('grooming_booking_packages')
      ->select('grooming_booking_packages.booking_id, packages.id as package_id, packages.package_name')
      ->join('packages', 'packages.id = grooming_booking_packages.package_id', 'left')
      ->whereIn('grooming_booking_packages.booking_id', $bookingIds)
      ->get()->getResult();

    $packages = [];
    foreach ($packageResults as $package) {
      $packages[$package->booking_id][] = $package;
    }

    $addonResults = $this->db->table('booking_addons')
      ->select('booking_addons.booking_id, booking_addons.addon, addons.price')
      ->join('addons', 'addons.name = booking_addons.addon')
      ->whereIn('booking_addons.booking_id', $bookingIds)
      ->get()->getResult();

    $addons = [];
    foreach ($addonResults as $addon) {
      $addons[$addon->booking_id][] = ["name" => $addon->addon, 'price' => $addon->price];
    }

    foreach ($groomingBookings as $booking) {
      $booking->packages = $packages[$booking->booking_id] ?? [];
      $booking->addons   = $addons[$booking->booking_id] ?? [];
    }

    return $groomingBookings;
  }


  public function newWalkingJobs()
  {
    // Walking Bookings
    return $this->db->table('walking_service_bookings')
      ->select('walking_service_bookings.service_frequency, walking_service_bookings.walk_duration, walking_service_bookings.service_days, 
          walking_service_bookings.service_start_date, walking_service_bookings.service_end_date, walking_service_bookings.preferable_time, 
          walking_service_bookings.created_at, walking_service_bookings.id as booking_id, walking_service_bookings.total_price, 
          walking_service_bookings.address_id, walking_service_bookings.type, walking_service_bookings.original_booking_id, walking_service_bookings.repost')
      ->select('user_addresses.latitude, user_addresses.longitude, user_addresses.city')
      ->select('packages.package_name as package_name, packages.price as package_price, packages.duration_days as days')
      ->select('(SELECT GROUP_CONCAT(booking_addons.addon) FROM booking_addons WHERE booking_addons.booking_id = walking_service_bookings.id) as addons') // Subquery for multiple addons
      ->join('quotations', 'walking_service_bookings.id = quotations.booking_id', 'left')
      ->join('packages', 'packages.id = walking_service_bookings.package_id', 'left')
      ->join('user_addresses', 'user_addresses.id = walking_service_bookings.address_id', 'left')
      ->where('walking_service_bookings.status', 'New')
      ->whereIn('walking_service_bookings.type', ['normal', 'extend', 'temporary', 'permanent'])
      ->orderBy('walking_service_bookings.id', 'DESC')
      ->groupBy('walking_service_bookings.id')
      ->get()->getResult();
  }


  public function newTrainingJobs()
  {
    // Training Bookings
    return $trainingBookings = $this->db->table('training_service_bookings')
      ->join('quotations', 'training_service_bookings.id = quotations.booking_id', 'left')
      ->where('training_service_bookings.status', 'New')
      ->orderBy('training_service_bookings.id', 'DESC')

      ->get()->getResult();
  }
  public function newBoardingJobs()
  {
    // Boarding Bookings
    return $boardingBookings = $this->db->table('boarding_service_bookings')
      ->select('boarding_service_bookings.service_start_date,boarding_service_bookings.service_end_date,boarding_service_bookings.preferable_time,boarding_service_bookings.created_at,boarding_service_bookings.id as booking_id')
      ->select('user_addresses.latitude,user_addresses.longitude,user_addresses.city')
      ->select('packages.package_name as package_name ,packages.price as package_price,packages.duration_days as days')

      ->join('packages', 'packages.id = boarding_service_bookings.package_id', 'left')
      ->join('user_addresses', 'user_addresses.id = boarding_service_bookings.address_id', 'left')
      ->join('quotations', 'boarding_service_bookings.id = quotations.booking_id', 'left')
      ->where('boarding_service_bookings.status', 'New')
      ->orderBy('boarding_service_bookings.id', 'DESC')

      ->get()->getResult();
  }

  public function newGroomingJobs()
  {
    // Grooming Bookings
    $groomingBookings = $this->db->table('grooming_service_bookings')
      ->select('grooming_service_bookings.service_start_date, grooming_service_bookings.preferable_time,
        grooming_service_bookings.created_at, grooming_service_bookings.id as booking_id,grooming_service_bookings.total_price as total_price')
      ->select('user_addresses.latitude, user_addresses.longitude, user_addresses.city')

      ->join('packages', 'packages.id = grooming_service_bookings.package_id', 'left')
      ->join('user_addresses', 'user_addresses.id = grooming_service_bookings.address_id', 'left')
      ->join('quotations', 'grooming_service_bookings.id = quotations.booking_id', 'left')

      ->where('grooming_service_bookings.status', 'New')
      ->orderBy('grooming_service_bookings.id', 'DESC')
      ->groupBy('grooming_service_bookings.id')
      ->get()->getResult();

    $bookingIds = array_column($groomingBookings, 'booking_id');
    $packages   = [];
    $addons     = [];

    if (!empty($bookingIds)) {
      $packageResults = $this->db->table('grooming_booking_packages')
        ->select('grooming_booking_packages.booking_id, packages.id as package_id, packages.package_name as package_name,packages.price,packages.included_addons')
        ->join('packages', 'packages.id = grooming_booking_packages.package_id', 'left')
        ->whereIn('grooming_booking_packages.booking_id', $bookingIds)
        ->get()
        ->getResult();

      foreach ($packageResults as $package) {
        $packages[$package->booking_id][] = $package;
        $package->included_addons         = !empty($package->included_addons) ? explode(',', (string) $package->included_addons) : [];
      }

      $addonResults = $this->db->table('booking_addons')
        ->select('booking_addons.booking_id, booking_addons.addon')
        ->select('addons.price')
        ->join('addons', 'addons.name = booking_addons.addon')
        ->whereIn('booking_addons.booking_id', $bookingIds)
        ->get()
        ->getResult();

      foreach ($addonResults as $addon) {
        $addons[$addon->booking_id][] = ["name" => $addon->addon, 'price' => $addon->price];
      }
    }
    foreach ($groomingBookings as $booking) {
      $booking->packages = $packages[$booking->booking_id] ?? [];
      $booking->addons   = $addons[$booking->booking_id] ?? [];
    }

    return $groomingBookings;
  }

  public function fetchBookingAddons($booking_id)
  {
    $addons = $this->db->table('booking_addons')
      ->select('booking_addons.addon')
      ->select('addons.price')
      ->join('addons', 'addons.name = booking_addons.addon')
      ->where('booking_addons.booking_id', $booking_id)
      ->get()
      ->getResult();

    return array_map(function ($addon) {
      return [
        "name" => $addon->addon,
      ];
    }, $addons);
  }
  public function checkProviderServices($provider_id, $service_id)
  {
    return $this->db->table('sp_services')
      ->where('provider_id', $provider_id)
      ->where('service_id', $service_id)
      ->get()->getResult();
  }

  public function addProviderServices($provider_id, $service_id)
  {
    return $this->db->table('sp_services')
      ->set('provider_id', $provider_id)
      ->set('service_id', $service_id)
      ->insert();
  }

  public function getBookingPets($booking_id)
  {
    return $this->db->table('booking_pets')
      ->select('booking_pets.booking_id,user_pets.id as pet_id,user_pets.name,user_pets.type,user_pets.breed,
      user_pets.image,user_pets.gender,user_pets.aggressiveness_level,user_pets.vaccinated,user_pets.dob')
      ->join('user_pets', 'booking_pets.pet_id = user_pets.id', 'left')
      ->where('booking_pets.booking_id', $booking_id)
      ->get()->getResult();
  }

  public function getBookingAddons($booking_id)
  {
    return $this->db->table('booking_addons')
      ->select('booking_addons.addon as name')
      ->select('addons.price')
      ->join('addons', 'addons.name = booking_addons.addon')
      ->where('booking_addons.booking_id', $booking_id)
      ->get()->getResult();
  }
  public function getBookingAddons2($booking_id)
  {
    $addons = $this->db->table('booking_addons')
      ->select('booking_addons.addon')
      // ->select('addons.price')
      ->join('addons', 'addons.name = booking_addons.addon')
      ->where('booking_addons.booking_id', $booking_id)
      ->get()->getResult();
    return array_column($addons, 'addon');
  }
  public function getQuoteAddons($id, $provider_id)
  {
    return $this->db->table('quotation_addons')
      ->select('quotation_addons.addon as name,quotation_addons.price')
      ->where('quotation_addons.quotation_id', $id)
      ->where('quotation_addons.provider_id', $provider_id)

      ->get()->getResult();
  }

  public function hasQuoteForJob($job_id)
  {
    return $this->db->table('quotations')
      ->where('booking_id', $job_id)
      ->where('status', 'New')
      ->get()->getResult();
  }

  public function uploadServicePhoto($provider_id, $fileName, $file_type)
  {
    return $this->db->table('sp_service_images')
      ->set('provider_id', $provider_id)
      ->set('file_name', $fileName)
      ->set('file_type', $file_type)
      ->set('created_at', gmdate('Y-m-d H:i:s'))
      ->insert();
  }

  public function getWalkingAddress($booking_id)
  {
    return $this->db->table('walking_service_bookings')
      ->select('user_addresses.latitude,user_addresses.longitude')
      ->join('user_addresses', 'user_addresses.id=walking_service_bookings.address_id', 'left')
      ->where('walking_service_bookings.id', $booking_id)
      ->get()->getRow();
  }

  public function getBoardingAddress($booking_id)
  {
    return $this->db->table('boarding_service_bookings')
      ->select('user_addresses.latitude,user_addresses.longitude')
      ->join('user_addresses', 'user_addresses.id=boarding_service_bookings.address_id', 'left')
      ->where('boarding_service_bookings.id', $booking_id)
      ->get()->getRow();
  }

  public function getTrainingAddress($booking_id)
  {
    return $this->db->table('training_service_bookings')
      ->select('user_addresses.latitude,user_addresses.longitude')
      ->join('user_addresses', 'user_addresses.id=training_service_bookings.address_id', 'left')
      ->where('training_service_bookings.id', $booking_id)
      ->get()->getRow();
  }

  public function getGroomingAddress($booking_id)
  {
    return $this->db->table('grooming_service_bookings')
      ->select('user_addresses.latitude,user_addresses.longitude')
      ->join('user_addresses', 'user_addresses.id=grooming_service_bookings.address_id', 'left')
      ->where('grooming_service_bookings.id', $booking_id)
      ->get()->getRow();
  }

  public function getAddressByBooking($type, $booking_id)
  {
    $table = $type . '_service_bookings';
    return $this->db->table($table)
      ->select('user_addresses.latitude,user_addresses.longitude')
      ->join('user_addresses', 'user_addresses.id=' . $table . '.address_id', 'left')
      ->where($table . '.id', $booking_id)
      ->get()->getRow();
  }

  public function getWalkingBookingProviders($booking_id)
  {
    return $this->db->table('quotations')
      ->select('walking_service_bookings.service_frequency')
      ->select('quotations.bid_amount')
      ->select('service_providers.id as provider_id,service_providers.name ,service_providers.profile')
      ->join('service_providers', 'service_providers.id=quotations.provider_id', 'left')
      ->join('walking_service_bookings', 'walking_service_bookings.id=quotations.booking_id', 'left')
      ->where('quotations.booking_id', $booking_id)
      ->where('quotations.status', 'New')
      ->get()->getResult();
  }

  public function getGroomingBookingProviders($booking_id)
  {
    return $this->db->table('quotations')
      ->select('services.name as service_name')
      ->select('quotations.bid_amount')
      ->select('service_providers.id as provider_id,service_providers.name ,service_providers.profile')
      ->join('service_providers', 'service_providers.id=quotations.provider_id', 'left')
      ->join('grooming_service_bookings', 'grooming_service_bookings.id=quotations.booking_id', 'left')
      ->join('services', 'services.id=grooming_service_bookings.service_id', 'left')

      ->where('quotations.booking_id', $booking_id)
      ->where('quotations.status', 'New')

      ->get()->getResult();
  }

  public function getTrainingBookingProviders($booking_id)
  {
    return $this->db->table('quotations')
      ->select('services.name as service_name')
      ->select('quotations.bid_amount')
      ->select('service_providers.id as provider_id,service_providers.name ,service_providers.profile')
      ->join('service_providers', 'service_providers.id=quotations.provider_id', 'left')
      ->join('training_service_bookings', 'training_service_bookings.id=quotations.booking_id', 'left')
      ->join('services', 'services.id=training_service_bookings.service_id', 'left')
      ->where('quotations.booking_id', $booking_id)
      ->where('quotations.status', 'New')
      ->get()->getResult();
  }

  public function getBoardingBookingProviders($booking_id)
  {
    return $this->db->table('quotations')
      ->select('services.name as service_name')
      ->select('quotations.bid_amount')
      ->select('service_providers.id as provider_id,service_providers.name ,service_providers.profile')
      ->join('service_providers', 'service_providers.id=quotations.provider_id', 'left')
      ->join('boarding_service_bookings', 'boarding_service_bookings.id=quotations.booking_id', 'left')
      ->join('services', 'services.id=boarding_service_bookings.service_id', 'left')
      ->where('quotations.booking_id', $booking_id)
      ->where('quotations.status', 'New')
      ->get()->getResult();
  }

  public function getExtendRequest($provider_id)
  {
    return $this->db->table('walking_service_bookings')
      ->select('walking_service_bookings.original_booking_id,walking_service_bookings.id as booking_id,walking_service_bookings.type,walking_service_bookings.total_price')
      ->select('services.name as service_name')
      ->select('packages.package_name as package_name, packages.price as package_price,packages.duration_days as days')
      ->select('service_frequency, walk_duration, service_days, service_start_date,service_end_date, preferable_time')
      ->select('walking_service_bookings.created_at')
      ->select('user_addresses.city as city')

      ->join('services', 'services.id = walking_service_bookings.service_id', 'left')
      ->join('packages', 'packages.id = walking_service_bookings.package_id', 'left')
      ->join('quotations', 'quotations.booking_id=walking_service_bookings.original_booking_id', 'left')
      ->join('user_addresses', 'user_addresses.id=walking_service_bookings.address_id', 'left')

      ->groupBy('walking_service_bookings.id')
      ->orderBy('walking_service_bookings.id', 'DESC')

      ->where('quotations.provider_id', $provider_id)
      ->where('walking_service_bookings.type', 'extend')
      ->whereNotIn('walking_service_bookings.approval', ['rejected', 'accepted'])

      ->get()->getResult();
  }

  public function updateRequest($booking_id, $action)
  {
    return $this->db->table('walking_service_bookings')
      ->set('approval', $action)
      ->set('payment_status', 'pending')
      ->where('id', $booking_id)
      ->update();
  }

  public function getGroomingTracking($booking_id, $provider_id)
  {
    return $this->db->table('grooming_tracking')

      ->select('grooming_tracking.id,grooming_tracking.addon, grooming_tracking.status')
      ->where('booking_id', $booking_id)
      ->where('provider_id', $provider_id)
      ->get()->getResult();
  }

  public function getGroomingTrackingByAddon($booking_id, $addon)
  {
    return $this->db->table('grooming_tracking')

      ->select('grooming_tracking.id,grooming_tracking.addon, grooming_tracking.status')
      ->where('booking_id', $booking_id)
      ->where('addon', $addon)
      ->get()->getRow();
  }

  public function createGroomingTracking($data)
  {
    return $this->db->table('grooming_tracking')
      ->insert($data);
  }

  public function updateGroomingTracking($data, $id)
  {
    return $this->db->table('grooming_tracking')
      ->set('status', $data['status'])
      ->set('addon', $data['addon'])
      ->where('id', $id)
      ->update($data);
  }

  public function approveGroomingTracking($data, $id)
  {
    return $this->db->table('grooming_tracking')
      ->set('is_approved', true)
      ->set('status', $data['status'])
      ->set('payment_status', true)
      ->set('approved_at', gmdate('Y-m-d H:i:s'))
      ->where('id', $id)
      ->update($data);
  }

  public function rejectGroomingTracking($data, $id)
  {
    return $this->db->table('grooming_tracking')
      ->set('is_approved', false)
      ->set('status', 'rejected')
      ->where('id', $id)
      ->update();
  }

  public function getBookingDetails($original_booking_id)
  {
    return $this->db->table('walking_service_bookings')
      ->select('quotations.provider_id')
      ->join('quotations', 'quotations.booking_id=walking_service_bookings.id')
      ->where('quotations.status', 'Accepted')
      ->where('walking_service_bookings.id', $original_booking_id)
      ->get()->getRow();
  }

  public function getQuotedJobIds($provider_id)
  {
    $quotedJobs = $this->db->table('quotations')
      ->select('booking_id')
      ->where('provider_id', $provider_id)
      ->get()
      ->getResultArray();
    return array_column($quotedJobs, 'booking_id');
  }

  public function getUserBankAccount($provider_id, $bankId)
  {
    return $this->db->table('sp_bank_details')
      ->select('bank_name, account_number, account_holder_name,upi_number,upi_id,ifsc_code,type')
      ->where('provider_id', $provider_id)
      ->where('id', $bankId)
      ->get()->getRow();
  }
  public function getSpServices($provider_id)
  {
    return $this->db->table('sp_services')
      ->select('services.id as service_id,services.name as service_name')
      ->join('services', 'services.id=sp_services.service_id')
      ->where('provider_id', $provider_id)
      ->get()->getResult();
  }
  public function getWithdrawalAmount($provider_id)
  {
    $result = $this->db->table('sp_withdrawal_wallet')
      ->select('amount')
      ->where('provider_id', $provider_id)
      ->get()->getRow();
    return $result->amount ?? 0;
  }
  public function getserviceImages($provider_id)
  {
    return $this->db->table('sp_service_images')
      ->select('id as imageId,file_name as image')
      ->where('provider_id', $provider_id)

      ->orderBy('sp_service_images.id', 'DESC')
      ->get()->getResult();
  }
  public function saveTracking($data)
  {
    return $this->db->table('boarding_tracking')
      ->insert($data);
  }
  public function updateTracking($id, $data)
  {
    return $this->db->table('boarding_tracking')
      ->where('id', $id)
      ->update($data);
  }
  public function getExistingTrack($pet_id, $date)
  {
    return $this->db->table('boarding_tracking')
      ->select('id')
      ->where('pet_id', $pet_id)
      ->where('date', $date)
      ->get()->getRow();
  }
  public function getEntryById($booking_id, $provider_id)
  {
    $ride_date = gmdate('Y-m-d');
    return $this->db->table('ride_tracking')
      ->select('id')
      ->where('provider_id', $provider_id)
      ->where('ride_date', $ride_date)
      ->where('booking_id', $booking_id)
      ->get()->getRow();
  }
  public function getEntryBy($booking_id, $provider_id)
  {
    $ride_date = gmdate('Y-m-d');
    return $this->db->table('ride_tracking')
      ->select('id')
      ->where('provider_id', $provider_id)
      ->where('ride_date', $ride_date)
      ->where('booking_id', $booking_id)
      ->where('status', 'started')
      ->get()->getRow();
  }
  public function saveEntry($data)
  {
    return $this->db->table('ride_tracking')
      ->insert($data);
  }
  public function getUserByBookingId($booking_id, $table)
  {
    return $this->db->table($table)
      ->select($table . '.id')
      ->select('users.id as user_id,users.device_token')
      ->join('users', 'users.id=' . $table . '.user_id', 'left')
      ->where($table . '.id', $booking_id)
      ->get()->getRow();
  }

  public function endRide($data)
  {
    $ride_date = gmdate('Y-m-d');

    return $this->db->table('ride_tracking')
      ->set('end_time', gmdate('Y-m-d h:i:s'))
      ->set('status', 'completed')
      ->where('provider_id', $data['provider_id'])
      ->where('ride_date', $ride_date)
      ->where('booking_id', $data['booking_id'])
      ->update();
  }
  public function updateEntry($data)
  {
    return $this->db->table('ride_tracking')

      ->set('status', 'started')
      ->where('id', $data['entry_id'])
      ->update();
  }
  public function getRideTracking($user_id)
  {
    return $this->db->table('walking_service_bookings')
      ->select('ride_tracking.status')
      ->select('service_providers.name as provider_name')

      ->join('ride_tracking', 'ride_tracking.booking_id=walking_service_bookings.id', 'inner')
      ->join('service_providers', 'service_providers.id=ride_tracking.provider_id', 'inner')

      ->where('walking_service_bookings.user_id', $user_id)
      ->where('ride_tracking.status', 'started')
      ->where('ride_tracking.ride_date', gmdate('Y-m-d'))
      ->get()->getResult();
  }
  public function updateProvider($data, $permanent_address)
  {
    return $this->db->table('service_providers')
      ->set('aadhar_name', $data['aadhaar_name'])
      ->set('aadhar_number', $data['aadhaar_number'])
      ->set('aadhar_verified', true)
      ->set('permanent_address', $permanent_address)

      ->where('id', $data['provider_id'])
      ->update();
  }
  public function updateVerifications($id)
  {
    return $this->db->table('sp_verifications')
      ->set('mobile_verified', true)
      // ->set('email_verified', true)
      ->where('provider_id', $id)
      ->update();
  }
  public function checkVerification($id)
  {
    return $this->db->table('sp_verifications')
      ->where('id', $id)
      ->get()->getRow();
  }
  public function createVerifications($id)
  {
    return $this->db->table('sp_verifications')
      ->set('mobile_verified', true)
      // ->set('email_verified', true)
      ->set('provider_id', $id)
      ->insert();
  }
  public function updateOrCreateIdentityVerify($provider_id)
  {
    $builder = $this->db->table('sp_verifications');

    $record = $builder->where('provider_id', $provider_id)->get()->getRow();

    if ($record) {
      return $builder
        ->set('identity_proof_verified', true)
        ->where('provider_id', $provider_id)
        ->update();
    } else {
      return $builder->insert([
        'provider_id'             => $provider_id,
        'identity_proof_verified' => true,
      ]);
    }
  }

  public function addAadhar($id, $data)
  {
    $builder = $this->db->table('sp_aadhar');
    $record  = $builder->where('provider_id', $id)->get()->getRow();

    if ($record) {
      return $builder
        ->set('data', $data)
        ->where('provider_id', $id)
        ->update();
    } else {
      return $builder->insert([
        'provider_id' => $id,
        'data'        => $data
      ]);
    }
  }
  public function getBookingProvider($booking_id)
  {
    return $this->db->table('service_providers')
      ->select('service_providers.name')
      ->join('quotations', 'quotations.provider_id=service_providers.id', 'left')
      // ->join('bookings', 'bookings.id=quotations.booking_id', 'left')
      // ->where('bookings.id', $booking_id)
      ->where('quotations.booking_id', $booking_id)
      ->get()->getRow();
  }
  public function checkAadharExists($aadhaar_number)
  {
    return $this->db->table('service_providers')
      ->select('id,device_token')
      ->where('aadhar_number', $aadhaar_number)
      ->get()->getRow();
  }
  public function gettotalOrders($provider_id)
  {
    return $this->db->table('quotations')
      ->where('provider_id', $provider_id)
      ->whereIn('status', ['Accepted', 'Completed'])
      ->countAllResults();
  }
  public function updateWithdrawlRequest($id, $amount)
  {
    $builder = $this->db->table('withdrawal_receipts');
    return $builder
      ->set('requested_date', date('Y-m-d'))
      ->set('status', 'in_progress')
      ->set('amount', $amount)
      ->set('provider_id', $id)
      ->insert();
  }
  public function getNotifications($provider_id)
  {
    return $notifications = $this->db->table('notifications')
      ->select('message,created_at')
      ->where('user_id', $provider_id)
      ->where('user_type', 'provider')
      ->orderBy('id', 'DESC')
      ->get()
      ->getResult();
  }
  public function getBanks($provider_id)
  {
    return $this->db->table('sp_bank_details')
      ->select('bank_name,account_number,ifsc_code')
      ->where('provider_id', $provider_id)
      ->orderBy('id', 'DESC')
      ->get()->getResult();
  }
  public function getProviderBank($provider_id)
  {
    return $this->db->table('sp_bank_details')
      ->select('service_providers.name as beneficiaryName,service_providers.phone as beneficiaryMobile,service_providers.email')
      ->select('sp_bank_details.bank_name,sp_bank_details.account_number as beneficiaryAccountNumber,sp_bank_details.ifsc_code as beneficiaryIfscCode')

      ->join('service_providers', 'service_providers.id = sp_bank_details.provider_id')

      ->where('service_providers.id', $provider_id)

      ->get()->getRow();
  }

  public function getProviderByEmail($email)
  {
    return $this->db->table($this->provider)
      ->where('email', $email)
      ->get()->getRow();
  }

  public function updateMailVerification($id)
  {
    return $this->db->table('sp_verifications')
      ->set('email_verified', true)
      ->where('provider_id', $id)
      ->update();
  }

  public function deleteImage($imageId, $provider_id)
  {
    $this->db->table('sp_service_images')->delete(['id' => $imageId, 'provider_id' => $provider_id]);
    return true;
  }

  public function storeOtp($email, $otp)
  {
    return $this->db->table($this->provider)
      ->set('mail_otp', $otp)
      ->where('email', $email)
      ->update();
  }

  public function checkMailOtp($email, $otp)
  {
    return $this->db->table($this->provider)
      ->select('id,email,phone,name')
      ->where('email', $email)
      ->where('mail_otp', $otp)
      ->get()
      ->getRow();
  }

  public function removeMailOtp($email)
  {
    return $this->db->table($this->provider)->update(
      [
        'mail_otp' => null,
      ],
      [
        'email' => $email,
      ]
    );
  }

  public function updateCertificate($id, $fileName, $certificate_type)
  {
    return $this->db->table('sp_verifications')
      ->set($certificate_type, $fileName)
      ->where('provider_id', $id)
      ->update();
  }

  public function getProviderById($providerId)
  {
    return $this->db->table('service_providers')
      ->select('id')
      ->where('id', $providerId)
      ->get()->getRow();
  }
  public function checkProviderAddress($provider_id)
  {
    return $this->db->table('service_providers')
      ->select('quotations.id')
      ->join('quotations', 'quotations.provider_id = service_providers.id')
      ->where('quotations.provider_id', $provider_id)
      ->whereIn('quotations.status', ['New', 'Accepted'])
      ->get()->getRow();
  }
  public function newGroomingJobsCount()
  {
    // Grooming Bookings
    return $this->db->table('grooming_service_bookings')
      ->select('grooming_service_bookings.id as booking_id')
      ->where('grooming_service_bookings.status', 'New')
      ->get()->getResult();
  }
  public function getServiceDetails($provider_id)
  {
    return $this->db->table('sp_grooming_details')
      ->select('sp_grooming_details.id as provider_id,sp_grooming_details.service_location')
      ->join('service_providers', 'service_providers.id = sp_grooming_details.provider_id')
      ->where('sp_grooming_details.provider_id', $provider_id)
      ->get()->getRow();
  }
  public function startGrooming($booking_id, $action)
  {
    // Grooming Bookings
    return $this->db->table('grooming_service_bookings')
      ->set('track_status', $action)
      ->where('grooming_service_bookings.id', $booking_id)
      ->update();
  }
  public function getGroomingTrackingByPackage($booking_id, $package_id, $pet_id)
  {
    return $booking = $this->db->table('grooming_tracking')
      ->set('grooming_tracking.id')

      ->where('grooming_tracking.booking_id', $booking_id)
      ->where('grooming_tracking.pet_id', $pet_id)
      ->where('grooming_tracking.package_id', $package_id)

      ->get()
      ->getROw();
  }
  public function createGroomingTrackingPkg($data)
  {
    $this->db->table('grooming_tracking')->insert($data);
    return $this->db->insertID();
  }


  public function getGroomingTrackingByPet($booking_id, $provider_id, $pet_id)
  {
    return $this->db->table('grooming_tracking')

      ->select('grooming_tracking.id,grooming_tracking.addon, grooming_tracking.status')
      ->where('booking_id', $booking_id)
      ->where('provider_id', $provider_id)
      ->where('pet_id', $pet_id)

      ->get()->getResult();
  }

  public function getBookingPackages($booking_id)
  {

    return $this->db->table('grooming_booking_packages')
      ->select('packages.id as package_id, packages.package_name as package_name,packages.price,packages.included_addons')
      ->join('packages', 'packages.id = grooming_booking_packages.package_id', 'left')
      ->where('grooming_booking_packages.booking_id', $booking_id)
      ->get()
      ->getResult();
  }
  public function getPackageById($package_id)
  {
    return $this->db->table('packages')
      ->select('packages.package_name as package_name, packages.price')
      ->where('packages.id', $package_id)
      ->get()
      ->getRow();
  }
  public function getAddonByName($addon)
  {
    return $this->db->table('addons')
      ->select('price')
      ->where('addons.name', $addon)
      ->get()
      ->getRow();
  }
  public function rejectGrooming($booking_id)
  {
    return $this->db->table('grooming_service_bookings')
      ->set('track_status', 'rejected')
      ->where('grooming_service_bookings.id', $booking_id)
      ->update();
  }
  public function updateGroomingBooking($booking_id, $status)
  {
    return $this->db->table('grooming_service_bookings')
      ->set('track_status', 'completed')
      ->where('grooming_service_bookings.id', $booking_id)
      ->update();
  }
  public function updateBooking($booking_id)
  {
    return $this->db->table('grooming_service_bookings')
      ->set('status', 'Completed')
      ->where('grooming_service_bookings.id', $booking_id)
      ->update();
  }
}
