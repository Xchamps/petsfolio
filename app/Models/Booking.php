<?php

namespace App\Models;

use CodeIgniter\Model;
use DateInterval;
use DatePeriod;
use DateTime;

class Booking extends Model
{
    protected $walking;
    protected $boarding;
    protected $training;
    protected $grooming;
    protected $addOns;
    protected $bookingPets;

    public function __construct()
    {
        parent::__construct();
        $this->db          = \Config\Database::connect();
        $this->walking     = 'walking_service_bookings';
        $this->boarding    = 'boarding_service_bookings';
        $this->training    = 'training_service_bookings';
        $this->grooming    = 'grooming_service_bookings';
        $this->addOns      = 'booking_addons';
        $this->bookingPets = 'booking_pets';
    }

    // create Boarding booking
    public function createBoardingBooking($data)
    {
        return $this->db->table($this->boarding)
            ->insert($data);
    }

    // create Training booking
    public function createTrainingBooking($data)
    {
        return $this->db->table($this->training)
            ->insert($data);
    }
    // create Grooming booking

    public function createGroomingBooking($data)
    {
        return $this->db->table($this->grooming)
            ->insert($data);
    }
    // create Walking booking

    public function createWalkingBooking($data)
    {
        return $this->db->table($this->walking)->insert($data);
    }

    //get Walking booking
    public function getWalkingBooking($id, $user_id)
    {
        // booking details
        $booking = $this->db->table($this->walking)
            ->select(
                'walking_service_bookings.id as booking_id,
                walking_service_bookings.address_id as address_id,
                walking_service_bookings.package_id as package_id,
                walking_service_bookings.original_booking_id,
                walking_service_bookings.total_price,
                walking_service_bookings.service_frequency,
                walking_service_bookings.service_end_date,
                walking_service_bookings.type as service_type, walking_service_bookings.status'
            )
            ->select('services.name as service_name')
            ->select('packages.package_name as package_name, packages.price as package_price,packages.duration_days as days')
            ->select('walking_service_bookings.service_frequency, walking_service_bookings.walk_duration, walking_service_bookings.service_days, walking_service_bookings.service_start_date, walking_service_bookings.preferable_time')
            ->select('walking_service_bookings.created_at')
            ->select('quotations.receivable_amount')

            ->join('services', 'services.id = walking_service_bookings.service_id', 'left')
            ->join('packages', 'packages.id = walking_service_bookings.package_id', 'left')
            ->join('walking_tracking', 'walking_tracking.booking_id=walking_service_bookings.id', 'left')
            ->join('quotations', 'walking_service_bookings.id = quotations.booking_id', 'left')

            ->where('walking_service_bookings.id', $id)
            ->where('walking_service_bookings.user_id', $user_id)

            ->get()
            ->getRow();

        if (!$booking) {
            return null;
        }

        // associated pets
        $pets = $this->db->table('booking_pets')
            ->select('booking_pets.pet_id, user_pets.name as pet_name, booking_pets.walk_type,user_pets.image')
            ->select('user_pets.gender,user_pets.vaccinated,user_pets.aggressiveness_level,user_pets.dob')
            ->join('user_pets', 'user_pets.id = booking_pets.pet_id', 'left')
            ->where('booking_pets.booking_id', $id)
            ->orWhere('booking_pets.booking_id', $booking->original_booking_id)

            ->get()
            ->getResult();

        $booking->pets = $pets;

        // associated add-ons
        $addons = $this->db->table('booking_addons')
            ->select('addon')
            ->where('booking_addons.booking_id', $id)
            ->get()
            ->getResult();

        $booking->addons = array_column($addons, 'addon');

        return $booking;
    }

    //get Boarding booking

    public function getBoardingBooking($id, $user_id)
    {
        // booking details
        $booking = $this->db->table($this->boarding)
            ->select('boarding_service_bookings.id as booking_id,boarding_service_bookings.address_id as address_id')
            ->select('services.name as service_name')
            ->select('packages.package_name as package_name, packages.price as package_price,packages.duration_days as days')
            ->select('service_start_date,service_end_date, preferable_time')
            ->select('boarding_service_bookings.created_at')

            ->join('services', 'services.id = boarding_service_bookings.service_id', 'left')
            ->join('packages', 'packages.id = boarding_service_bookings.package_id', 'left')
            ->where('boarding_service_bookings.id', $id)
            ->where('boarding_service_bookings.user_id', $user_id)
            ->get()
            ->getRow();

        if (!$booking) {
            return null;
        }

        //  associated pets
        $pets = $this->db->table('booking_pets')
            ->select('booking_pets.pet_id, user_pets.name as pet_name, booking_pets.walk_type,user_pets.image')
            ->select('user_pets.gender,user_pets.vaccinated,user_pets.aggressiveness_level,user_pets.dob')
            ->join('user_pets', 'user_pets.id = booking_pets.pet_id', 'left')
            ->where('booking_pets.booking_id', $id)
            ->get()
            ->getResult();

        $booking->pets = $pets;

        //  associated add-ons
        $addons = $this->db->table('booking_addons')
            ->select('addon')
            ->where('booking_addons.booking_id', $id)
            ->get()
            ->getResult();

        $booking->addons = array_column($addons, 'addon');

        return $booking;
    }
    //get Grooming booking

    public function getGroomingBooking($id, $user_id)
    {
        // booking details
        $booking = $this->db->table($this->grooming)
            ->select('grooming_service_bookings.id as booking_id,grooming_service_bookings.address_id as address_id,
            grooming_service_bookings.total_price,grooming_service_bookings.service_start_date, grooming_service_bookings.preferable_time,
            grooming_service_bookings.created_at')
            ->select('services.name as service_name')

            ->join('services', 'services.id = grooming_service_bookings.service_id', 'left')
            ->where('grooming_service_bookings.id', $id)
            ->where('grooming_service_bookings.user_id', $user_id)
            ->get()
            ->getRow();

        if (!$booking) {
            return null;
        }

        //  associated pets
        $pets = $this->db->table('booking_pets')
            ->select('booking_pets.pet_id, user_pets.name as pet_name, booking_pets.walk_type,user_pets.image')
            ->select('user_pets.gender,user_pets.vaccinated,user_pets.aggressiveness_level,user_pets.dob')
            ->join('user_pets', 'user_pets.id = booking_pets.pet_id', 'left')
            ->where('booking_pets.booking_id', $id)
            ->get()
            ->getResult();

        $booking->pets = $pets;

        //  associated packages

        $packages = $this->db->table('grooming_booking_packages')
            ->select('packages.id as package_id, packages.package_name as package_name,packages.price,packages.included_addons')
            ->join('packages', 'packages.id = grooming_booking_packages.package_id', 'left')
            ->where('grooming_booking_packages.booking_id', $id)
            ->get()
            ->getResult();
        $booking->packages = $packages;

        //  associated add-ons
        $addons = $this->db->table('booking_addons')
            ->select('addon')
            ->where('booking_addons.booking_id', $id)
            ->get()
            ->getResult();

        $booking->addons = array_column($addons, 'addon');

        return $booking;
    }

    //get Training booking

    public function getTrainingBooking($id, $user_id)
    {
        // booking details
        $booking = $this->db->table($this->training)
            ->select('training_service_bookings.id as booking_id,training_service_bookings.address_id as address_id')
            ->select('services.name as service_name')

            ->select('packages.package_name as package_name, packages.price as package_price,packages.duration_days as days')
            ->select('service_start_date, preferable_time')
            ->select('training_service_bookings.created_at')

            ->join('services', 'services.id = training_service_bookings.service_id', 'left')
            ->join('packages', 'packages.id = training_service_bookings.package_id', 'left')
            ->where('training_service_bookings.id', $id)
            ->where('training_service_bookings.user_id', $user_id)
            ->get()
            ->getRow();

        if (!$booking) {
            return null;
        }

        //  associated pets
        $pets = $this->db->table('booking_pets')
            ->select('booking_pets.pet_id, user_pets.name as pet_name, booking_pets.walk_type,user_pets.image')
            ->select('user_pets.gender,user_pets.vaccinated,user_pets.aggressiveness_level,user_pets.dob')
            ->join('user_pets', 'user_pets.id = booking_pets.pet_id', 'left')
            ->where('booking_pets.booking_id', $id)
            ->get()
            ->getResult();

        $booking->pets = $pets;

        //  associated add-ons
        $addons = $this->db->table('booking_addons')
            ->select('addon')
            ->where('booking_addons.booking_id', $id)
            ->get()
            ->getResult();

        $booking->addons = array_column($addons, 'addon');

        return $booking;
    }
    public function getWalkingBookings($user_id)
    {

        $bookings = $this->db->table($this->walking)
            ->select('services.name as service_name')
            ->select('packages.package_name, packages.price as package_price')
            ->select($this->walking . '.service_frequency, walk_duration, service_days, service_start_date, preferable_time')
            ->select($this->walking . '.created_at')
            ->join('services', 'services.id = walking_service_bookings.service_id', 'left')
            ->join('packages', 'packages.id = walking_service_bookings.package_id', 'left')
            ->where('walking_service_bookings.user_id', $user_id)
            ->get()
            ->getResult();

        if (!$bookings) {
            return null;
        }

        foreach ($bookings as $booking) {
            $pet_ids = explode(',', string: $booking->pet_id);

            if (is_array($pet_ids)) {
                $pet_details = $this->db->table('user_pets')
                    ->select('name,id')
                    ->whereIn('id', $pet_ids)
                    ->get()
                    ->getResult();

                $booking->pets = $pet_details;
            } else {
                $pet_detail = $this->db->table('user_pets')
                    ->select('name')
                    ->where('id', $booking->pet_id)
                    ->get()
                    ->getRow();

                $booking->pets = [$pet_detail];
            }
        }

        return $bookings;
    }
    public function calculatePayableAmount($totalPrice, $gst, $platformCharges, $discount)
    {
        return $totalPrice + ($totalPrice * $gst) + $platformCharges - $discount;
    }
    public function checkWalkingBookingExists($user_id, $pet_id, $service_id)
    {
        return $this->db->table($this->bookingPets)
            ->select('booking_pets.*')
            ->join('walking_service_bookings', 'walking_service_bookings.id = booking_pets.booking_id')
            ->where('booking_pets.user_id', $user_id)
            ->where('booking_pets.pet_id', $pet_id)
            ->where('walking_service_bookings.service_id', $service_id)
            ->whereIn('walking_service_bookings.status', ['New', 'Confirmed'])
            ->get()
            ->getRow();
    }
    public function checkTrainingBookingExists($user_id, $pet_id, $service_id)
    {
        return $this->db->table($this->bookingPets)
            ->select('booking_pets.*')
            ->join('training_service_bookings', 'training_service_bookings.id = booking_pets.booking_id')
            ->where('booking_pets.user_id', $user_id)
            ->where('booking_pets.pet_id', $pet_id)
            ->where('training_service_bookings.service_id', $service_id)
            ->whereIn('training_service_bookings.status', ['New', 'Confirmed'])
            ->get()
            ->getRow();
    }
    public function checkGroomingBookingExists($user_id, $pet_id, $service_id)
    {
        return $this->db->table($this->bookingPets)
            ->select('booking_pets.*')
            ->join('grooming_service_bookings', 'grooming_service_bookings.id = booking_pets.booking_id')
            ->where('booking_pets.user_id', $user_id)
            ->where('booking_pets.pet_id', $pet_id)
            ->where('grooming_service_bookings.service_id', $service_id)
            ->whereIn('grooming_service_bookings.status', ['New', 'Confirmed'])
            ->get()
            ->getRow();
    }
    public function checkBoardingBookingExists($user_id, $pet_id, $service_id)
    {
        return $this->db->table($this->bookingPets)
            ->select('booking_pets.*')
            ->join('boarding_service_bookings', 'boarding_service_bookings.id = booking_pets.booking_id')
            ->where('booking_pets.user_id', $user_id)
            ->where('booking_pets.pet_id', $pet_id)
            ->where('boarding_service_bookings.service_id', $service_id)
            ->whereIn('boarding_service_bookings.status', ['New', 'Confirmed'])
            ->get()
            ->getRow();
    }
    public function deleteWalkingBooking($id, $user_id)
    {
        return $this->db->table($this->walking)
            ->set('is_deleted', true)
            ->set('deleted_at', gmdate('Y-m-d H:i:s'))
            ->set('status', 'Cancelled')
            ->where('id', $id)
            ->where('user_id', $user_id)
            ->update();
    }
    public function deleteGroomingBooking($id, $user_id)
    {
        return $this->db->table($this->grooming)
            ->delete(['id' => $id, 'user_id' => $user_id]);
    }
    public function deleteTrainingBooking($id, $user_id)
    {
        return $this->db->table($this->training)
            ->delete(['id' => $id, 'user_id' => $user_id]);
    }
    public function deleteBoardingBooking($id, $user_id)
    {
        return $this->db->table($this->boarding)
            ->delete(['id' => $id, 'user_id' => $user_id]);
    }
    public function confirmWalkingBooking($data, $status, $payment_status)
    {
        return $this->db->table($this->walking)
            ->set('status', $status)
            ->set('payment_status ', $payment_status)
            ->where(['id' => $data['booking_id'], 'user_id' => $data['user_id'], 'service_id' => $data['service_id']])
            ->update();
    }
    public function confirmGroomingBooking($data)
    {
        return $this->db->table($this->grooming)
            ->set('status', 'Confirmed')
            ->set('track_status', 'not_started')
            ->where(['id' => $data['booking_id'], 'user_id' => $data['user_id'], 'service_id' => $data['service_id']])
            ->update();
    }
    public function confirmBoardingBooking($data)
    {
        return $this->db->table($this->boarding)
            ->set('status', 'Confirmed')
            ->where(['id' => $data['booking_id'], 'user_id' => $data['user_id'], 'boarding_service_id' => $data['service_id']])
            ->update();
    }
    public function confirmTrainingBooking($data)
    {
        return $this->db->table($this->training)
            ->set('status', 'Confirmed')
            ->where(['id' => $data['booking_id'], 'user_id' => $data['user_id'], 'service_id' => $data['service_id']])
            ->update();
    }
    public function createWalkingBookingPets($data)
    {
        return $this->db->table($this->bookingPets)
            ->insert($data);
    }
    public function createWalkingBookingAddons($data)
    {
        return $this->db->table($this->addOns)
            ->insert($data);
    }
    public function getWalkingAddress($user_id, $booking_id)
    {
        return $this->db->table($this->walking)
            ->select('walking_service_bookings.address_id')
            ->where('walking_service_bookings.user_id', $user_id)
            ->where('walking_service_bookings.id', $booking_id)
            ->get()
            ->getRow();
    }
    public function getTrainingAddress($user_id, $booking_id)
    {
        return $this->db->table($this->training)
            ->select('training_service_bookings.address_id')
            ->where('training_service_bookings.user_id', $user_id)
            ->where('training_service_bookings.id', $booking_id)
            ->get()
            ->getRow();
    }
    public function getGroomingAddress($user_id, $booking_id)
    {
        return $this->db->table($this->grooming)
            ->select('grooming_service_bookings.address_id')
            ->select('user_addresses.city,user_addresses.latitude,user_addresses.longitude')
            ->select('quotations.provider_id')

            ->join('quotations', 'grooming_service_bookings.id = quotations.booking_id')
            ->join('users', 'grooming_service_bookings.user_id = users.id')
            ->join('user_addresses', 'user_addresses.id = grooming_service_bookings.address_id')
            ->where('grooming_service_bookings.user_id', $user_id)
            ->where('grooming_service_bookings.id', $booking_id)
            ->get()
            ->getRow();
    }
    public function getBoardingAddress($user_id, $booking_id)
    {
        return $this->db->table($this->boarding)
            ->select('boarding_service_bookings.address_id')
            ->where('boarding_service_bookings.user_id', $user_id)
            ->where('boarding_service_bookings.id', $booking_id)
            ->get()
            ->getRow();
    }
    public function getWalkingBookingAddress($user_id, $booking_id)
    {
        $walkingBookingAddress = $this->db->table('walking_service_bookings')
            ->select('user_addresses.city,user_addresses.latitude,user_addresses.longitude')
            ->select('quotations.provider_id')

            ->join('quotations', 'walking_service_bookings.id = quotations.booking_id')
            ->join('users', 'walking_service_bookings.user_id = users.id')
            ->join('user_addresses', 'user_addresses.id = walking_service_bookings.address_id')
            ->where('walking_service_bookings.id', $booking_id)
            ->where('walking_service_bookings.user_id', $user_id)
            ->get()->getRow();
        return $walkingBookingAddress;
    }
    public function getBookingAddress($user_id, $booking_id)
    {
        $walkingBookingAddress = $this->db->table('walking_service_bookings')
            ->select('user_addresses.city,user_addresses.latitude,user_addresses.longitude')

            ->join('user_addresses', 'user_addresses.id = walking_service_bookings.address_id')
            ->where('walking_service_bookings.id', $booking_id)
            ->where('walking_service_bookings.user_id', $user_id)
            ->get()->getRow();
        return $walkingBookingAddress;
    }
    public function getTracking($booking_id, $provider_id, $pet_id)
    {
        $today = date('Y-m-d');
        return $this->db->table('walking_tracking')
            ->select('walking_tracking.service_time, walking_tracking.status')
            ->where('booking_id', $booking_id)
            ->where('provider_id', $provider_id)
            ->where('tracking_date', $today)
            ->where('pet_id', $pet_id)
            ->get()->getResult();
    }
    public function createTrainingBookingPets($data)
    {
        return $this->db->table($this->bookingPets)
            ->insert($data);
    }
    // Training unquoted bookings
    public function getUnquotedBookingsForTraining($timeLimit)
    {
        $oneHourAgo = gmdate('Y-m-d H:i:s', time() - $timeLimit);

        return $this->db->table('training_service_bookings')
            ->select('training_service_bookings.id')
            ->join('quotations', 'quotations.booking_id = training_service_bookings.id', 'left')
            ->where('training_service_bookings.created_at <', $oneHourAgo)
            ->where('quotations.id IS NULL')
            ->where('training_service_bookings.status', 'New')
            ->get()->getResult();
    }

    // Walking unquoted bookings
    public function getUnquotedBookingsForWalking($timeLimit)
    {
        $oneHourAgo = gmdate('Y-m-d H:i:s', time() - $timeLimit);

        return $this->db->table('walking_service_bookings')
            ->select('walking_service_bookings.id')
            ->join('quotations', 'quotations.booking_id = walking_service_bookings.id', 'left')
            ->where('walking_service_bookings.created_at <', $oneHourAgo)
            ->where('quotations.id IS NULL')
            ->where('walking_service_bookings.status', 'New')
            ->get()->getResult();
    }

    // Boarding unquoted bookings
    public function getUnquotedBookingsForBoarding($timeLimit)
    {
        $oneHourAgo = gmdate('Y-m-d H:i:s', time() - $timeLimit);

        return $this->db->table('boarding_service_bookings')
            ->select('boarding_service_bookings.id')
            ->join('quotations', 'quotations.booking_id = boarding_service_bookings.id', 'left')
            ->where('boarding_service_bookings.created_at <', $oneHourAgo)
            ->where('quotations.id IS NULL')
            ->where('boarding_service_bookings.status', 'New')
            ->get()->getResult();
    }

    // Grooming unquoted bookings
    public function getUnquotedBookingsForGrooming($timeLimit)
    {
        $oneHourAgo = gmdate('Y-m-d H:i:s', time() - $timeLimit);

        return $this->db->table('grooming_service_bookings')
            ->select('grooming_service_bookings.id')
            ->join('quotations', 'quotations.booking_id = grooming_service_bookings.id', 'left')
            ->where('grooming_service_bookings.created_at <', $oneHourAgo)
            ->where('quotations.id IS NULL')
            ->where('grooming_service_bookings.status', 'New')
            ->get()->getResult();
    }
    public function createSPBooking($data)
    {
        return $this->db->table('sp_bookings')
            ->set('provider_id', $data['provider_id'])
            ->set('quotation_id', $data['quotation_id'])
            ->set('booking_id', $data['booking_id'])
            ->set('service_id', $data['service_id'])
            ->set('start_date', $data['service_start_date'])
            ->set('status', 'active')
            ->set('type', 'permanent')
            ->set('created_at', gmdate('Y-m-d H:i:s'))
            ->insert();
    }
    public function createBookingPets($data)
    {
        return $this->db->table($this->bookingPets)
            ->insert($data);
    }
    public function createBookingAddons($data)
    {
        return $this->db->table($this->addOns)
            ->insert($data);
    }
    public function updateWalkingBookingTimings($booking_id, $user_id, $preferable_time)
    {
        return $this->db->table('quotations')
            ->set('sp_timings', $preferable_time)
            ->set('updated_at', gmdate('Y-m-d H:i:s'))
            ->where('booking_id', $booking_id)
            ->where('status', 'Accepted')
            ->update();
    }
    public function updateGroomingBookingTimings($booking_id, $user_id, $preferable_time)
    {
        return $this->db->table('walking_service_bookings')
            ->set('preferable_time', $preferable_time)
            ->set('updated_at', gmdate('Y-m-d H:i:s'))

            ->where('id', $booking_id)
            ->where('user_id', $user_id)
            // ->where('status', 'Confirmed')
            ->update();
    }
    public function updateTrainingBookingTimings($booking_id, $user_id, $preferable_time)
    {
        return $this->db->table('walking_service_bookings')
            ->set('preferable_time', $preferable_time)
            ->set('updated_at', gmdate('Y-m-d H:i:s'))
            ->where('id', $booking_id)
            ->where('user_id', $user_id)
            // ->where('status', 'Confirmed')
            ->update();
    }
    public function updateBoardingBookingTimings($booking_id, $user_id, $preferable_time)
    {
        return $this->db->table('walking_service_bookings')
            ->set('preferable_time', $preferable_time)
            ->set('updated_at', gmdate('Y-m-d H:i:s'))
            ->where('id', $booking_id)
            ->where('user_id', $user_id)
            // ->where('status', 'Confirmed')
            ->update();
    }
    public function updateWalkingBookingAddress($booking_id, $user_id, $address_id)
    {
        return $this->db->table('walking_service_bookings')
            ->set('address_id', $address_id)
            ->set('updated_at', gmdate('Y-m-d H:i:s'))
            ->where('id', $booking_id)
            ->where('user_id', $user_id)
            // ->where('status', 'Confirmed')
            ->update();
    }
    public function updateGroomingBookingAddress($booking_id, $user_id, $address_id)
    {
        return $this->db->table('walking_service_bookings')
            ->set('address_id', $address_id)
            ->set('updated_at', gmdate('Y-m-d H:i:s'))

            ->where('id', $booking_id)
            ->where('user_id', $user_id)
            // ->where('status', 'Confirmed')
            ->update();
    }
    public function updateTrainingBookingAddress($booking_id, $user_id, $address_id)
    {
        return $this->db->table('walking_service_bookings')
            ->set('address_id', $address_id)
            ->set('updated_at', gmdate('Y-m-d H:i:s'))
            ->where('id', $booking_id)
            ->where('user_id', $user_id)
            // ->where('status', 'Confirmed')
            ->update();
    }
    public function updateBoardingBookingAddress($booking_id, $user_id, $address_id)
    {
        return $this->db->table('walking_service_bookings')
            ->set('address_id', $address_id)
            ->set('updated_at', gmdate('Y-m-d H:i:s'))
            ->where('id', $booking_id)
            ->where('user_id', $user_id)
            // ->where('status', 'Confirmed')
            ->update();
    }
    public function getWalkingHistory($user_id, $service_id, $pet_id)
    {
        $bookings = $this->db->table('walking_service_bookings')
            ->select('walking_tracking.service_time,walking_tracking.status,walking_tracking.end_time as completed_at,walking_tracking.tracking_date')
            ->select('user_pets.id as pet_id, user_pets.name as pet_name, user_pets.image')

            ->join('walking_tracking', 'walking_service_bookings.id = walking_tracking.booking_id', 'inner')
            ->join('user_pets', 'user_pets.id = walking_tracking.pet_id', 'left')

            ->where('walking_service_bookings.user_id', $user_id)
            ->where('walking_service_bookings.service_id', $service_id)
            ->where('walking_tracking.pet_id', $pet_id)
            ->where('walking_tracking.status', 'completed')
            ->where('walking_tracking.is_approved', 'true')
            ->where('walking_service_bookings.status', 'Confirmed')
            ->orderBy('walking_tracking.id', 'DESC')
            ->get()
            ->getResult();

        return $bookings;
    }

    public function getBoardingHistory($user_id, $service_id, $pet_id)
    {
        $bookings = $this->db->table('boarding_service_bookings')
            ->select('user_pets.id as pet_id,user_pets.name as pet_name,user_pets.image')
            ->select('boarding_tracking.morning_image,boarding_tracking.morning_video,boarding_tracking.afternoon_image, boarding_tracking.afternoon_video,boarding_tracking.evening_image,boarding_tracking.evening_video,boarding_tracking.created_at as completed_at')

            ->join('boarding_tracking', 'boarding_service_bookings.id = boarding_tracking.booking_id')
            ->join('user_pets', 'user_pets.id = boarding_tracking.pet_id', 'left')

            ->where('boarding_service_bookings.user_id', $user_id)
            ->where('boarding_service_bookings.service_id', $service_id)
            ->where('boarding_tracking.pet_id', $pet_id)
            ->where('boarding_tracking.status', 'completed')
            ->where('boarding_service_bookings.is_approved', true)
            ->where('boarding_service_bookings.status', 'Confirmed')
            ->get()
            ->getResult();
        return $bookings;
    }
    public function getGroomingHistory($user_id, $service_id, $pet_id)
    {
        $bookings = $this->db->table('grooming_service_bookings')
            ->select('user_pets.id as pet_id,user_pets.name as pet_name,user_pets.image')
            ->select('grooming_tracking.service_time, grooming_tracking.created_at as completed_at')
            ->join('grooming_tracking', 'grooming_service_bookings.id = grooming_tracking.booking_id')
            ->join('user_pets', 'user_pets.id = grooming_tracking.pet_id', 'left')
            ->where('grooming_service_bookings.user_id', $user_id)
            ->where('grooming_service_bookings.service_id', $service_id)
            ->where('grooming_tracking.pet_id', $pet_id)
            ->where('grooming_tracking.status', 'completed')
            ->where('grooming_service_bookings.is_approved', true)
            ->where('grooming_service_bookings.status', 'Confirmed')
            ->get()
            ->getResult();
        return $bookings;
    }
    public function getTrainingHistory($user_id, $service_id, $pet_id)
    {
        $bookings = $this->db->table('training_service_bookings')
            ->select('user_pets.id as pet_id,user_pets.name as pet_name,user_pets.image')
            ->select('training_tracking.service_time, training_tracking.created_at as completed_at')
            ->join('training_tracking', 'training_service_bookings.id = training_tracking.booking_id')
            ->join('user_pets', 'user_pets.id = training_tracking.pet_id', 'left')
            ->where('training_service_bookings.user_id', $user_id)
            ->where('training_service_bookings.service_id', $service_id)
            ->where('training_tracking.pet_id', $pet_id)
            ->where('training_tracking.status', 'completed')
            ->where('training_service_bookings.is_approved', true)

            ->where('training_service_bookings.status', 'Confirmed')
            ->get()
            ->getResult();
        return $bookings;
    }
    public function extendWalkingService($booking_id, $user_id, $end_date)
    {
        return $this->db->table('walking_service_bookings')->where(
            'id',
            $booking_id
        )->update(['service_end_date' => $end_date]);
    }
    public function getPackageDetails($package_id)
    {
        return $this->db->table('packages')
            ->select('packages.duration_days as totalDays')
            ->where('id', $package_id)
            ->get()->getRow();
    }
    public function cancelBoardingService($booking_id, $user_id)
    {
        return $this->db->table('boarding_service_bookings')
            ->set('status', 'Cancelled')
            ->where('id', $booking_id)
            ->where('user_id', $user_id)
            ->update();
    }
    public function cancelGroomingService($booking_id, $user_id, $reason)
    {
        return $this->db->table('grooming_service_bookings')
            ->set('status', 'Cancelled')
            ->set('cancel_reason', $reason)
            ->where('id', $booking_id)
            ->where('user_id', $user_id)
            ->update();
    }
    public function cancelTrainingService($booking_id, $user_id)
    {
        return $this->db->table('training_service_bookings')
            ->set('status', 'Cancelled')
            ->where('id', $booking_id)
            ->where('user_id', $user_id)
            ->update();
    }
    public function cancelWalkingService($booking_id, $user_id, $reason)
    {
        $this->db->table('walking_service_bookings')
            ->set('status', 'Cancelled')
            ->set('cancel_reason', $reason)
            ->where('id', $booking_id)
            ->where('user_id', $user_id)
            ->update();

        return $this->db->table('walking_service_bookings')
            ->set('status', 'Cancelled')
            ->set('cancel_reason', $reason)
            ->where('original_booking_id', $booking_id)
            ->update();
    }


    public function cancelGroomingServiceForPet($booking_id, $user_id, $pet_id)
    {
        return $this->db->table('booking_pets')
            ->set('status', 'Cancelled')
            ->where('booking_id', $booking_id)
            ->where('pet_id', $pet_id)
            ->where('user_id', $user_id)
            ->update();
    }
    public function cancelWalkingServiceForPet($booking_id, $user_id, $pet_id)
    {
        return $this->db->table('booking_pets')
            ->set('status', 'Cancelled')
            ->where('booking_id', $booking_id)
            ->where('pet_id', $pet_id)
            ->where('user_id', $user_id)
            ->update();
    }
    public function cancelProviderService($booking_id, $provider_id)
    {
        return $this->db->table('quotations')
            ->set('status', 'Cancelled')
            ->where('booking_id', $booking_id)
            ->where('provider_id', $provider_id)
            ->update();
    }
    public function reportProvider($data)
    {
        return $this->db->table('sp_bookings')
            ->set('report_reason', $data['report_reason'])
            ->set('report_comment', $data['report_comment'])
            ->where('booking_id', $data['booking_id'])
            ->where('provider_id', $data['provider_id'])
            ->update();
    }

    public function getRemainingWalksForPet($booking_id, $pet_id)
    {
        $booking = $this->db->table('walking_service_bookings')
            ->select('service_start_date, service_end_date, service_frequency,service_days')
            ->where('id', $booking_id)
            ->get()
            ->getRow();

        if (!$booking) {
            return 0;
        }

        $totalPlannedWalks = $this->calculateTotalWalks($booking->service_start_date, $booking->service_end_date, $booking->service_frequency, $booking->service_days);

        $completedWalks = $this->db->table('walking_tracking')
            ->where('booking_id', $booking_id)
            ->where('pet_id', $pet_id)
            ->where('status', 'completed')
            ->where('is_approved', 'true')
            ->countAllResults();

        $remainingWalks = $totalPlannedWalks - $completedWalks;

        return max(0, $remainingWalks);
    }
    public function calculateTotalWalks($start_date, $end_date, $frequency_per_day, $service_days)
    {
        // Calculate the number of days between the start and end date
        $startDate = new DateTime($start_date);
        $endDate   = new DateTime($end_date);
        $endDate->modify('+1 day');
        $totalDays = 0;

        // Calculate total walks by multiplying days with frequency per day
        if ($frequency_per_day == 'once a day') {
            $frequency_per_day = 1;
        } elseif ($frequency_per_day == 'twice a day') {
            $frequency_per_day = 2;
        } elseif ($frequency_per_day == 'thrice a day') {
            $frequency_per_day = 3;
        }
        if ($service_days == 'weekdays') {
            $period = new DatePeriod($startDate, new DateInterval('P1D'), $endDate);

            foreach ($period as $date) {
                // Check if the day is not Sunday (0 is Sunday in PHP's w format)
                if ($date->format('w') != 0) {
                    $totalDays++;
                }
            }
        } else {
            // If not restricted to weekdays, calculate total days including both start and end dates
            $totalDays = $startDate->diff($endDate)->days;
        }
        $totalWalks = $totalDays * $frequency_per_day;

        return $totalWalks;
    }
    public function getPricePerWalkForPet($booking_id, $pet_id)
    {

        $booking = $this->db->table('walking_service_bookings')
            ->select('walk_duration, service_frequency')
            ->where('id', $booking_id)
            ->get()
            ->getRow();

        if (!$booking) {
            return 0;
        }

        $baseCostPerWalk = ($booking->walk_duration == '30 min walk') ? 1 : 2;
        //     $multiplier = $booking->walk_duration== '30 min walk' ? 2 : 1;

        // $totalCost = $actualCost * $multiplier * $numWalksPerDay * $numDays;
        // $addOnPrice      = $booking->addon_prices ?? 0;
        return $baseCostPerWalk;
    }
    public function getAddonsPrice($booking_id, $provider_id)
    {
        return $booking = $this->db->table('quotations')
            ->select('extra_amount')
            ->where('booking_id', $booking_id)
            ->where('provider_id', $provider_id)
            ->get()
            ->getRow();
    }
    public function repostBooking($booking_id)
    {
        return $this->db->table('walking_service_bookings')
            ->set('status', 'New')
            ->set('repost', true)
            ->where('id', $booking_id)
            ->update();
    }
    public function repostGroomingBooking($data)
    {
        return $this->db->table('walking_service_bookings')
            ->set('status', 'New')
            ->set('service_start_date', $data['service_start_date'])
            ->set('preferable_time', $data['preferable_time'])
            ->set('repost', true)
            ->where('id', $data['booking_id'])
            ->update();
    }
    public function getUserByBooking($booking_id, $table)
    {
        return $this->db->table('users')
            ->select('users.id,users.name,users.email,users.phone,users.gender,users.profile,users.device_token,users.city')
            ->select('service_providers.id as provider_id')
            ->select('user_addresses.city,user_addresses.latitude,user_addresses.longitude')

            ->join($table, $table . '.user_id=users.id', 'left')
            ->join('quotations', 'quotations.booking_id=' . $table . '.id', 'left')
            ->join('user_addresses', 'user_addresses.id = ' . $table . '.address_id')

            ->join('service_providers', 'service_providers.id=quotations.provider_id', 'left')
            ->where($table . '.id', $booking_id)
            // ->where('quotations.status', 'Accepted')
            ->get()
            ->getRow();
    }
    public function checkWalk($data)
    {
        return $this->db->table('walking_tracking')
            ->select('walking_tracking.id')

            ->where('booking_id', $data['booking_id'])
            ->where('pet_id', $data['pet_id'])
            ->where('service_time', $data['service_time'])
            ->where('provider_id', $data['provider_id'])
            ->where('tracking_date', date('Y-m-d'))

            ->get()->getRow();
    }
    public function walkApproval($data)
    {
        $updateData = [
            'is_approved'   => $data['approval'],
            'status'        => 'completed',
            'booking_id'    => $data['booking_id'],
            'pet_id'        => $data['pet_id'],
            'service_time'  => $data['service_time'],
            'provider_id'   => $data['provider_id'],
            'tracking_date' => date('Y-m-d'),
        ];

        if ($data['approval'] == 'true') {
            $updateData['approved_at']    = date('Y-m-d H:i:s');
            $updateData['payment_status'] = 'paid';
        }

        return $this->db->table('walking_tracking')
            ->where('booking_id', $data['booking_id'])
            ->where('pet_id', $data['pet_id'])
            ->where('service_time', $data['service_time'])
            ->where('provider_id', $data['provider_id'])
            ->where('tracking_date', date('Y-m-d'))
            ->update($updateData);
    }

    public function addWalkApproval($data)
    {
        $insertData = [
            'is_approved'   => $data['approval'],
            'status'        => 'completed',
            'booking_id'    => $data['booking_id'],
            'pet_id'        => $data['pet_id'],
            'service_time'  => $data['service_time'],
            'provider_id'   => $data['provider_id'],
            'tracking_date' => date('Y-m-d'),
        ];

        if ($data['approval'] == 'true') {
            $insertData['approved_at']    = date('Y-m-d H:i:s');
            $insertData['payment_status'] = 'paid';
        }

        return $this->db->table('walking_tracking')->insert($insertData);
    }

    public function getBooking($booking_id, $provider_id)
    {
        return $this->db->table('walking_service_bookings')
            ->select('walking_service_bookings.original_booking_id,walking_service_bookings.user_id,walking_service_bookings.service_frequency')
            ->select('quotations.bid_amount')
            ->select('quotations.platform_charges')
            ->select('packages.price as price_per_walk,packages.duration_days')
            ->select('user_pets.name as pet_name')

            ->join('booking_pets', 'booking_pets.booking_id=walking_service_bookings.id', 'left')
            ->join('user_pets', 'user_pets.id=booking_pets.pet_id', 'left')
            ->join('packages', 'packages.id=walking_service_bookings.package_id', 'left')
            ->join('quotations', 'quotations.booking_id=walking_service_bookings.id', 'left')

            ->where('quotations.booking_id', $booking_id)
            ->where('quotations.provider_id', $provider_id)
            ->where('quotations.status', 'Accepted')

            ->get()
            ->getRow();
    }


    // Update old booking status
    public function updateOldBooking($original_booking_id, $status)
    {
        $this->db->table('walking_service_bookings')
            ->set('status', $status)
            ->where('id', $original_booking_id)
            ->update();
    }

    // Update temporary booking status
    public function updateTempBookingStatus($temp_booking_id, $status)
    {
        $this->db->table('walking_service_bookings')
            ->set('status', $status)
            ->where('id', $temp_booking_id)
            ->update();
    }

    // Get bookings that are completed based on the end date
    public function getCompletedBookings()
    {
        return $this->db->table('walking_service_bookings')
            ->select('walking_service_bookings.*')
            ->where('status', 'Confirmed')
            ->where('service_end_date <=', date('Y-m-d'))
            ->get()
            ->getResult();
    }

    // Get temporary bookings for a user
    public function getTempBooking($user_id)
    {
        $currentDate = date('Y-m-d H:i:s');
        return $this->db->table('walking_service_bookings')
            ->select('walking_service_bookings.*')

            ->where('type', 'temporary')
            ->where('status', 'onHold')
            ->where('payment_status', 'completed')
            ->where('service_start_date <=', $currentDate)
            ->where('user_id', $user_id)

            ->get()
            ->getResult();
    }
    public function getPermanentBooking($user_id)
    {
        $currentDate = date('Y-m-d H:i:s');
        return $this->db->table('walking_service_bookings')
            ->select('walking_service_bookings.*')

            ->where('type', 'permanent')
            ->where('status', 'onHold')
            ->where('payment_status', 'completed')
            ->where('service_start_date <=', $currentDate)
            ->where('user_id', $user_id)

            ->get()
            ->getResult();
    }
    // Get extended bookings for a user
    public function getExtendBooking($user_id)
    {
        return $this->db->table('walking_service_bookings')
            ->select('walking_service_bookings.*')
            ->where('type', 'extend')

            ->groupStart()
            ->where('status', 'onHold')
            ->orWhere('status', 'New')
            ->groupEnd()

            ->where('user_id', $user_id)
            ->where('approval', 'accepted')
            ->groupStart()

            ->where('repost', true)
            ->where('payment_status', 'completed')

            ->groupEnd()
            ->get()
            ->getResult();
    }


    // Update temporary booking by user and booking id
    public function updateTemp($user_id, $booking_id)
    {
        return $this->db->table('walking_service_bookings')
            ->where('id', $booking_id)
            ->where('user_id', $user_id)
            ->update(['status' => 'Confirmed']);
    }

    // Update booking status
    public function updateBookingStatus($booking_id, $status)
    {
        $this->db->table('walking_service_bookings')
            ->set('status', $status)
            ->where('id', $booking_id)
            ->update();
    }
    public function updateQuoteStatus($booking_id, $status)
    {
        $this->db->table('quotations')
            ->set('status', $status)
            ->where('booking_id', $booking_id)
            ->where('status', 'Accepted')
            ->update();
    }
    public function updateGroomingStatus($booking_id)
    {
        $this->db->table('grooming_service_bookings')
            ->set('status', 'Completed')
            ->where('id', $booking_id)
            ->update();
    }
    public function getTemporaryBookingsToCancel($currentDate)
    {
        return $this->db->table('walking_service_bookings')
            ->where('service_start_date <=', $currentDate)
            ->where('type', 'temporary')
            ->where('status', 'onHold')
            ->groupStart()
            ->where('payment_status', 'pending')
            ->orWhere('payment_status IS NULL', null, false)
            ->groupEnd()
            ->get()
            ->getResult();
    }

    public function getPermanentBookingsToCancel($currentDate)
    {
        return $this->db->table('walking_service_bookings')
            ->where('service_start_date <=', $currentDate)
            ->where('type', 'permanent')
            ->where('status', 'onHold')
            ->groupStart()
            ->where('payment_status', 'pending')
            ->orWhere('payment_status IS NULL', null, false)
            ->groupEnd()
            ->get()
            ->getResult();
    }

    public function cancelBooking($id)
    {
        $currentDate = date('Y-m-d H:i:s');
        return $this->db->table('walking_service_bookings')
            ->where('id', $id)
            ->where('type', 'temporary')
            ->where('service_start_date <=', $currentDate)
            ->where('status', 'onHold')
            ->groupStart()
            ->where('payment_status', 'pending')
            ->orWhere('payment_status IS NULL', null, false)
            ->groupEnd()
            ->update(['status' => 'Cancelled']);
    }

    public function cancelPermanentBooking($id)
    {
        $currentDate = date('Y-m-d H:i:s');
        return $this->db->table('walking_service_bookings')
            ->where('id', $id)
            ->where('type', 'permanent')
            ->where('service_start_date <=', $currentDate)
            ->where('status', 'onHold')
            ->groupStart()
            ->where('payment_status', 'pending')
            ->orWhere('payment_status IS NULL', null, false)
            ->groupEnd()
            ->update(['status' => 'Cancelled']);
    }

    public function getServiceWallet($pet_id)
    {
        $result = $this->db->table('user_service_wallet')
            ->selectSum('walking_amount')
            ->where('pet_id', $pet_id)
            ->get()
            ->getRow();

        return isset($result->walking_amount) ? (string) $result->walking_amount : '0';
    }
    public function getRefundAmount($userId)
    {
        $result = $this->db->table('user_withdrawal_wallet')
            ->selectSum('amount')
            ->where('user_id', $userId)
            ->get()
            ->getRow();

        return isset($result->amount) ? (string) $result->amount : '0';
    }
    public function getWalkingBookingData($booking_id)
    {
        return $result = $this->db->table('walking_service_bookings')
            ->select('service_end_date,service_days')
            ->where('id', $booking_id)
            ->get()
            ->getRow();
    }
    public function updateBooking($booking_id, $endDate)
    {
        return $result = $this->db->table('walking_service_bookings')
            ->set('service_end_date', $endDate)
            ->where('id', $booking_id)
            ->update();
    }
    public function updateQuote($booking_id, $provider_id, $total_amount)
    {
        return $result = $this->db->table('quotations')

            ->set('bid_amount', 'bid_amount+' . $total_amount, false)

            ->where('booking_id', $booking_id)
            ->where('provider_id', $provider_id)
            ->where('status', 'Accepted')
            ->update();
    }
    public function getQuoteData($booking_id, $provider_id)
    {
        return $result = $this->db->table('quotations')
            ->select('extra_amount,discount,platform_charges')
            ->where('booking_id', $booking_id)
            ->where('provider_id', $provider_id)
            ->get()
            ->getRow();
    }
    public function createNotification($data)
    {
        $result = $this->db->table('notifications')
            ->insert($data);
    }
    public function getPetName($pet_id)
    {
        return $result = $this->db->table('user_pets')
            ->select('name,image')
            ->where('id', $pet_id)
            ->get()
            ->getRow();
    }
    public function getUserToken($booking_id, $table)
    {
        return $result = $this->db->table('users')
            ->select('users.device_token,users.id')
            ->join($table, $table . '.user_id=users.id', 'left')
            ->where($table . '.id', $booking_id)
            ->get()
            ->getRow();
    }
    public function getPet($booking_id)
    {
        return $result = $this->db->table('booking_pets')
            ->select('user_pets.name')
            ->join('user_pets', 'user_pets.id=booking_pets.pet_id', 'left')
            ->where('booking_pets.booking_id', $booking_id)
            ->get()
            ->getRow();
    }
    public function getProviderByBooking($booking_id)
    {
        return $this->db->table('walking_service_bookings')
            ->select('service_providers.id,service_providers.name,service_providers.device_token')

            ->join('quotations', 'quotations.booking_id = walking_service_bookings.id', 'left')
            ->join('service_providers', 'service_providers.id = quotations.provider_id', 'left')

            ->where('walking_service_bookings.id', $booking_id)
            ->where('quotations.booking_id', $booking_id)
            ->where('quotations.status', 'Accepted')
            ->get()->getRow();
    }
    public function getBookingData($booking_id, $user_id, $service_id, $provider_id, $quotation_id)
    {
        $bookingQuery = $this->db->table($this->walking)
            ->select([
                'walking_service_bookings.id as booking_id',
                'walking_service_bookings.address_id',
                'walking_service_bookings.package_id',
                'walking_service_bookings.original_booking_id',
                'walking_service_bookings.total_price',
                'walking_service_bookings.service_frequency',
                'walking_service_bookings.walk_duration',
                'walking_service_bookings.service_days',
                'walking_service_bookings.service_end_date',
                'walking_service_bookings.type as service_type',
                'walking_service_bookings.status',
                'service_providers.id as provider_id',
                'service_providers.name as provider_name',
                'service_providers.city',
                'service_providers.service_longitude',
                'service_providers.service_latitude',
                'service_providers.profile',
                'service_providers.phone',
                'service_providers.service_address',
                'service_providers.gender',
                'SUM(sp_reviews.rating) as rating_sum',
                'COUNT(sp_reviews.id) as total_count',
                'quotations.id as quotation_id',
                'quotations.bid_amount',
                'quotations.sp_timings',
                'services.name as service_name',
                'packages.package_name as package_name',
                'packages.price as package_price',
                'packages.duration_days as days',
                'walking_service_bookings.created_at',
                'walking_service_bookings.preferable_time',
                'walking_service_bookings.service_start_date'
            ])
            ->join('quotations', 'walking_service_bookings.id = quotations.booking_id', 'left')
            ->join('service_providers', 'service_providers.id = quotations.provider_id', 'left')
            ->join('sp_reviews', 'sp_reviews.provider_id = service_providers.id', 'left')
            ->join('services', 'services.id = walking_service_bookings.service_id', 'left')
            ->join('packages', 'packages.id = walking_service_bookings.package_id', 'left')
            ->where([
                'walking_service_bookings.id'      => $booking_id,
                'walking_service_bookings.user_id' => $user_id,
                'quotations.booking_id'            => $booking_id,
                'quotations.service_id'            => $service_id,
                'quotations.provider_id'           => $provider_id,
                'quotations.id'                    => $quotation_id,
                'quotations.status'                => 'Accepted',
                'walking_service_bookings.status'  => 'Confirmed'

            ])
            ->groupBy('walking_service_bookings.id')
            ->get()
            ->getRow();

        if (!$bookingQuery) {
            return null;
        }

        // Fetch associated pets in a single query
        $pets = $this->db->table('booking_pets')
            ->select([
                'booking_pets.pet_id',
                'user_pets.name as pet_name',
                // 'booking_pets.walk_type',
                'user_pets.image',
                'user_pets.gender',
                'user_pets.vaccinated',
                'user_pets.aggressiveness_level',
                'user_pets.dob'
            ])
            ->join('user_pets', 'user_pets.id = booking_pets.pet_id', 'left')
            ->whereIn('booking_pets.booking_id', [$booking_id, $bookingQuery->original_booking_id])
            // ->where('booking_pets.status!=', 'Cancelled')
            ->get()
            ->getResult();

        $bookingQuery->pets = $pets;

        $bookingQuery->addons = $this->fetchAddons($quotation_id);

        return $bookingQuery;
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
                "name"  => $addon->addon,
                "price" => $addon->price,
            ];
        }, $addons);
    }
    public function getBookingById($booking_id)
    {
        $bookingQuery = $this->db->table('walking_service_bookings')
            ->select('walking_service_bookings.original_booking_id')
            ->where('walking_service_bookings.id', $booking_id)
            ->get()
            ->getRow();
        return $bookingQuery;
    }
    public function checkBookingConfirmed($booking_id, $service_id, $user_id)
    {
        return $this->db->table('walking_service_bookings')
            ->select('id')
            ->where('id', $booking_id)
            ->where('service_id', $service_id)
            ->where('user_id', $user_id)
            ->where('status', 'Confirmed')
            ->get()
            ->getRow();
    }

    public function getPaymentPendingWalks()
    {
        $today = date('Y-m-d');
        return $this->db->table('walking_tracking')
            ->where('status', 'completed')
            ->where('tracking_date <', $today)
            ->groupStart() // Start grouping conditions
            ->whereIn('payment_status', ['pending', 'not_approved'])
            ->orWhere('payment_status IS NULL', null, false) // Explicitly check for NULL
            ->groupEnd() // End grouping
            ->get()
            ->getResult();
    }

    public function getPendingWalks()
    {
        $today = date('Y-m-d');
        return $this->db->table('walking_tracking')
            ->where('status', 'in_progress')
            // ->where('is_approved', false)
            ->where('tracking_date <', $today)
            // ->whereIn('payment_status', ['pending', 'rejected'])
            ->get()
            ->getResult();
    }
    public function getUntrackedWalksToday()
    {
        $yesterday = date('Y-m-d', strtotime('-1 day')); // Get yesterday's date

        return $this->db->table('walking_service_bookings')
            ->select('walking_service_bookings.*')
            ->select('quotations.provider_id')
            ->join('walking_tracking', 'walking_tracking.booking_id = walking_service_bookings.id AND DATE(walking_tracking.tracking_date) = ' . $this->db->escape($yesterday), 'left') // Match records for yesterday
            ->join('quotations', 'quotations.booking_id = walking_service_bookings.id', 'left')
            ->where('walking_service_bookings.service_start_date <=', $yesterday)
            ->where('walking_service_bookings.service_end_date >=', $yesterday)
            ->where('walking_tracking.id IS NULL') // Ensure no tracking record for yesterday
            ->where('quotations.status', 'Accepted')
            ->where('walking_service_bookings.status', 'Confirmed')
            ->where('(walking_tracking.is_untracked IS NULL OR walking_tracking.is_untracked = 0)') // Exclude flagged untracked records
            ->get()
            ->getResult();
    }
    public function updatePaymentStatus($walkId, $status)
    {
        return $this->db->table('walking_tracking')
            ->where('id', $walkId)
            ->update(['payment_status' => $status]);
    }
    public function updatePayment($id)
    {
        $this->db->table('walking_tracking')
            ->where('id', $id)
            ->update(['is_approved' => 'true', 'approved_at' => gmdate('Y-m-d')]);
    }
    public function updateStatus($id, $status)
    {
        $this->db->table('walking_tracking')
            ->where('id', $id)
            ->update(['status' => $status]);
    }
    public function getWalkingAmount($id, $user_id, $quotation_id)
    {
        $booking = $this->db->table($this->walking)
            ->select('quotations.receivable_amount')
            ->select('walking_service_bookings.type,walking_service_bookings.service_start_date')
            ->join('quotations', 'quotations.booking_id = walking_service_bookings.id', 'left')
            ->where('quotations.id', $quotation_id)
            ->where('walking_service_bookings.id', $id)
            ->where('walking_service_bookings.user_id', $user_id)
            // ->where('quotations.status', 'Accepted')
            ->get()
            ->getRow();

        return $booking;
    }
    public function getGroomingAmount($id, $user_id, $quotation_id)
    {
        $booking = $this->db->table('grooming_service_bookings')
            ->select('quotations.receivable_amount')
            ->select('grooming_service_bookings.service_start_date')
            ->join('quotations', 'quotations.booking_id = grooming_service_bookings.id', 'left')
            ->where('quotations.id', $quotation_id)
            ->where('grooming_service_bookings.id', $id)
            ->where('grooming_service_bookings.user_id', $user_id)
            // ->where('quotations.status', 'Accepted')
            ->get()
            ->getRow();

        return $booking;
    }
    public function deletePets($booking_id, $user_id, $service_id)
    {
        $this->db->table('booking_pets')
            ->where('id', $booking_id)
            ->where('user_id', $user_id)
            ->where('service_id', $service_id)
            ->delete();
    }
    public function checkExtend($booking_id)
    {
        return $this->db->table('walking_service_bookings')
            ->select('id')
            ->where('original_booking_id', $booking_id)
            ->where('approval!=', 'rejected')
            ->whereIn('status', ['New', 'Confirmed', 'onHold'])
            ->get()
            ->getRow();
    }
    public function getExpiringBookings()
    {
        // $tomorrow = gmdate('Y-m-d', strtotime('+1 day'));
        $tomorrowStart = date('Y-m-d 00:00:00');
        $tomorrowEnd = date('Y-m-d 23:59:59');

        return $this->db->table('walking_service_bookings')
            ->select('walking_service_bookings.id')
            ->select('users.id as user_id, users.device_token')
            ->join('users', 'users.id = walking_service_bookings.user_id', 'left')
            ->where('walking_service_bookings.service_start_date >=', $tomorrowStart)
            ->where('walking_service_bookings.service_start_date <=', $tomorrowEnd)
            ->where('walking_service_bookings.status', 'New')
            ->get()
            ->getResult();
    }

    public function getTotalQuotes($id)
    {
        return $this->db->table('quotations')
            ->select('quotations.id')
            ->join('walking_service_bookings', 'walking_service_bookings.id=quotations.booking_id', 'left')
            ->where('quotations.status', 'New')
            ->where('quotations.booking_id', $id)
            ->get()
            ->getResult();
    }


    public function calculateTotalPriceForRemainingWalks($booking_id, $pet_id, $provider_id)
    {
        // Fetch remaining walks
        $remainingWalks = $this->getRemainingWalksForPet($booking_id, $pet_id);
        $addon_prices   = $this->getAddonsPrice($booking_id, $provider_id);
        if ($remainingWalks > 0) {
            // Fetch booking details
            $booking = $this->db->table('walking_service_bookings')
                ->select('walk_duration, service_start_date, service_end_date')
                ->where('id', $booking_id)
                ->get()
                ->getRow();

            if (!$booking) {
                return 0; // No booking found
            }

            $pricePerWalk = $this->getPricePerWalkForPet($booking_id, $pet_id);
            $addOnPrice   = isset($addon_prices) ? $addon_prices : 0;

            $numDaysBetween = $this->calculateDaysBetween($booking->service_start_date, $booking->service_end_date);

            // 60-min logic applies if the walk duration is '60 min walk'
            $basePrice = $pricePerWalk * $numDaysBetween * ($booking->walk_duration === '60 min walk' ? 2 : 1);

            // Add-ons to total
            $totalPrice = ($basePrice + $addOnPrice) * $remainingWalks;

            return $totalPrice;
        }

        return 0; // No remaining walks or invalid data
    }


    public function calculateDaysBetween($startDate, $endDate)
    {
        $start    = new DateTime($startDate);
        $end      = new DateTime($endDate);
        $interval = $start->diff($end);

        return $interval->days + 1; // +1 to include both start and end date
    }
    public function getBookingforPriceCalculate($id, $provider_id)
    {
        return $booking = $this->db->table('walking_service_bookings')
            ->select('walking_service_bookings.user_id,walking_service_bookings.id,walking_service_bookings.service_frequency,
            walking_service_bookings.walk_duration, walking_service_bookings.service_start_date, walking_service_bookings.service_end_date,walking_service_bookings.service_days')
            ->select('quotations.platform_charges,quotations.discount_amount')
            ->select('packages.price as package_price,packages.duration_days')

            ->select('packages.price as package_price')
            ->join('packages', 'packages.id=walking_service_bookings.package_id', 'left')
            ->join('quotations', 'quotations.booking_id=walking_service_bookings.id', 'left')

            ->where('quotations.booking_id', $id)
            ->where('quotations.provider_id', $provider_id)
            ->where('quotations.status', 'Accepted')
            ->where('walking_service_bookings.id', $id)
            ->get()
            ->getRow();
    }
    public function getGroomingPriceCalculate($id, $provider_id)
    {
        return $booking = $this->db->table('grooming_service_bookings')
            ->select('grooming_service_bookings.user_id,grooming_service_bookings.id,grooming_service_bookings.service_start_date')
            ->select('quotations.platform_charges,quotations.discount_amount,quotations.discount,quotations.service_mode')

            ->join('quotations', 'quotations.booking_id=grooming_service_bookings.id', 'left')

            ->where('quotations.booking_id', $id)
            ->where('quotations.provider_id', $provider_id)
            ->where('quotations.status', 'Accepted')
            ->where('grooming_service_bookings.id', $id)
            ->get()
            ->getRow();
    }
    public function getLongTermCompletingBookings($user_id)
    {
        return $booking = $this->db->table('walking_service_bookings')
            ->select('service_end_date,id')
            ->where('package_id', '4')
            ->where('service_end_date<=', gmdate('Y-m-d', strtotime('+3 days')))
            ->where('user_id', $user_id)
            ->get()
            ->getResult();
    }
    public function getCompletedWalks($booking_id, $today)
    {
        return $this->db->table('walking_tracking')
            ->where('booking_id', $booking_id)
            ->where('tracking_date', $today)
            ->whereIn('status', ['completed', 'Untracked'])
            ->where('is_approved', 'true')
            ->orWhere('is_untracked', true)
            ->countAllResults();
    }
    public function getBookingPets($booking_id)
    {
        return $this->db->table('booking_pets')
            ->where('booking_id', $booking_id)
            ->countAllResults();
    }
    public function getPets($booking_id)
    {
        return $this->db->table('booking_pets')
            ->where('booking_id', $booking_id)
            ->get()
            ->getResult();
    }
    public function getExpiredBookings()
    {
        $today = gmdate('Y-m-d');

        return $this->db->table('walking_service_bookings')
            ->select('walking_service_bookings.id')
            ->select('users.id as user_id, users.device_token')
            ->join('users', 'users.id = walking_service_bookings.user_id', 'left')
            ->where('walking_service_bookings.service_end_date', $today)
            ->where('walking_service_bookings.status', 'New')
            ->get()
            ->getResult();
    }
    public function getRemainingWalksForToday($booking_id, $pet_id)
    {
        $today    = date('Y-m-d'); // Current date
        $todayDay = strtolower(date('l'));

        // Fetch booking details
        $booking = $this->db->table('walking_service_bookings')
            ->select('service_start_date, service_end_date, service_frequency, service_days')
            ->where('id', $booking_id)
            ->where('walking_service_bookings.status', 'Confirmed')
            ->get()
            ->getRow();

        if (!$booking) {
            return 0; // No booking found
        }

        if ($booking->service_days === 'weekdays') {
            if (!in_array($todayDay, ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'])) {
                return 0;
            }
        }

        $plannedWalksToday = (int) $this->getFrequency($booking->service_frequency); // Walks per day

        $completedWalksToday = $this->db->table('walking_tracking')
            ->where('booking_id', $booking_id)
            ->where('pet_id', $pet_id)
            ->where('tracking_date', $today)
            ->countAllResults();

        $remainingWalksForToday = max(0, $plannedWalksToday - $completedWalksToday);

        return $remainingWalksForToday;
    }

    private function getFrequency($serviceFrequency)
    {
        switch ($serviceFrequency) {
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

    public function markProcess($id, $provider_id, $pet_id)
    {
        $this->db->table('walking_tracking')
            ->insert([
                'booking_id' => $id,
                'provider_id' => $provider_id,
                'pet_id' => $pet_id,
                'tracking_date' => date('Y-m-d'),
                'status' => 'Untracked',
                'reason' => 'Walk not tracked',
                'is_untracked' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
    }

    public function addPackages($bookingId, $packageId)
    {
        $this->db->table('grooming_booking_packages')
            ->insert([
                'booking_id' => $bookingId,
                'package_id' => $packageId,
                'created_at' => date('Y-m-d H:i:s')
            ]);
    }
    public function getGroomingBookingData($id, $user_id)
    {
        // booking details
        $booking = $this->db->table($this->grooming)
            ->select('grooming_service_bookings.id as booking_id,grooming_service_bookings.address_id as address_id,
            grooming_service_bookings.total_price,grooming_service_bookings.service_start_date, grooming_service_bookings.preferable_time,
            grooming_service_bookings.created_at,grooming_service_bookings.status')
            ->select('services.name as service_name')
            ->select(
                [
                    'service_providers.id as provider_id',
                    'service_providers.name as provider_name',
                    'service_providers.city',
                    'service_providers.service_longitude',
                    'service_providers.service_latitude',
                    'service_providers.profile',
                    'service_providers.phone',
                    'service_providers.service_address',
                    'service_providers.gender',
                    'SUM(sp_reviews.rating) as rating_sum',
                    'COUNT(sp_reviews.id) as total_count',
                    'quotations.id as quotation_id',
                    'quotations.bid_amount',
                    'quotations.sp_timings',
                    'services.name as service_name'
                ]
            )
            ->join('quotations', 'grooming_service_bookings.id = quotations.booking_id', 'left')
            ->join('service_providers', 'service_providers.id = quotations.provider_id', 'left')
            ->join('sp_reviews', 'sp_reviews.provider_id = service_providers.id', 'left')

            ->join('services', 'services.id = grooming_service_bookings.service_id', 'left')
            ->where('grooming_service_bookings.id', $id)
            ->where('grooming_service_bookings.user_id', $user_id)
            ->where('quotations.status', 'Accepted')
            ->where('grooming_service_bookings.status', 'Confirmed')

            ->get()
            ->getRow();

        if (!$booking) {
            return null;
        }

        //  associated pets
        $pets = $this->db->table('booking_pets')
            ->select('booking_pets.pet_id, user_pets.name as pet_name, booking_pets.walk_type,user_pets.image')
            ->select('user_pets.gender,user_pets.vaccinated,user_pets.aggressiveness_level,user_pets.dob')
            ->join('user_pets', 'user_pets.id = booking_pets.pet_id', 'left')
            ->where('booking_pets.booking_id', $id)
            ->get()
            ->getResult();

        $booking->pets = $pets;

        //  associated packages

        $packages = $this->db->table('grooming_booking_packages')
            ->select('packages.id as package_id, packages.package_name as package_name,packages.price,packages.included_addons')
            ->join('packages', 'packages.id = grooming_booking_packages.package_id', 'left')
            ->where('grooming_booking_packages.booking_id', $id)
            ->get()
            ->getResult();
        $booking->packages = $packages;

        //  associated add-ons
        $addons = $this->db->table('booking_addons')
            ->select('addon')
            ->where('booking_addons.booking_id', $id)
            ->get()
            ->getResult();

        $booking->addons = array_column($addons, 'addon');

        return $booking;
    }
    public function getGroomingTracking($booking_id, $provider_id, $pet_id)
    {
        return $booking = $this->db->table('grooming_tracking')
            ->select('grooming_tracking.addon,grooming_tracking.status,grooming_tracking.is_approved')
            ->select('addons.price as addon_cost')
            ->join('addons', 'addons.name = grooming_tracking.addon', 'left')
            ->where('grooming_tracking.booking_id', $booking_id)
            ->where('grooming_tracking.provider_id', $provider_id)
            ->where('grooming_tracking.pet_id', $pet_id)
            ->where('grooming_tracking.package_id', 0)

            ->get()
            ->getResult();
    }
    public function getGroomingTrackingpackages($booking_id, $provider_id, $pet_id)
    {
        return $packages = $this->db->table('grooming_booking_packages')
            ->select('packages.id as package_id, packages.package_name as package_name,packages.price,packages.included_addons')
            ->select('grooming_tracking.status,grooming_tracking.is_approved')

            ->join('packages', 'packages.id = grooming_booking_packages.package_id', 'left')
            ->join('grooming_tracking', 'grooming_tracking.booking_id = grooming_booking_packages.booking_id', 'left')

            ->where('grooming_tracking.booking_id', $booking_id)
            ->where('grooming_tracking.provider_id', $provider_id)
            ->where('grooming_tracking.pet_id', $pet_id)
            ->get()
            ->getResult();
    }


    public function getGroomingTrackingpackages2($booking_id, $provider_id, $pet_id)
    {
        return $this->db->table('grooming_tracking')
            ->select('packages.id as package_id, packages.package_name as package_name, packages.price, packages.included_addons')
            ->select('grooming_tracking.status, grooming_tracking.is_approved')
            ->join('packages', 'packages.id = grooming_tracking.package_id', 'left')
            ->where('grooming_tracking.booking_id', $booking_id)
            ->where('grooming_tracking.provider_id', $provider_id)
            ->where('grooming_tracking.pet_id', $pet_id)
            ->get()
            ->getResult();
    }


    public function getGroomingPaymentPending()
    {
        $today = date('Y-m-d');
        return $this->db->table('grooming_tracking')
            ->where('status', 'completed')
            ->where('tracking_date <', $today)
            ->whereIn('payment_status', ['pending', 'not_approved', null])
            ->get()
            ->getResult();
    }
    public function getGroomingBookingAmount($booking_id)
    {
        return $booking = $this->db->table($this->grooming)
            ->select('grooming_service_bookings.id as booking_id,grooming_service_bookings.address_id as address_id,
        grooming_service_bookings.total_price,grooming_service_bookings.service_start_date')
            ->select('quotations.bid_amount,quotations.platform_charges,quotations.discount_amount')

            ->join('quotations', 'grooming_service_bookings.id = quotations.booking_id', 'left')
            ->where('grooming_service_bookings.id', $booking_id)
            ->get()
            ->getRow();
    }
}
